<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';
require_once 'csrf_protection.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'], true)) {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST or PUT.', 405);
}

requireAuth(['admin', 'vendor']);

$currentUser = getCurrentUser();
$userId = (int)($currentUser['user_id'] ?? 0);
$role = strtolower((string)($currentUser['role'] ?? ''));
if ($userId <= 0) {
    apiError('UNAUTHORIZED', 'Unauthorized user.', 401);
}

if (CSRF_ENABLED) {
    requireCsrfToken($conn, $userId);
}

$payload = readJsonPayload();
$orderId = (int)($payload['order_id'] ?? 0);
$newStatus = strtolower(sanitizeString((string)($payload['order_status'] ?? ''), 30));
$trackingNumber = sanitizeString((string)($payload['tracking_number'] ?? ''), 255);
$carrierName = sanitizeString((string)($payload['carrier_name'] ?? ''), 100);
$carrierUrl = sanitizeString((string)($payload['carrier_url'] ?? ''), 500);
$shippingMethod = sanitizeString((string)($payload['shipping_method'] ?? ''), 100);
$notes = sanitizeString((string)($payload['notes'] ?? ''), 1000);
$expectedDeliveryDate = sanitizeString((string)($payload['expected_delivery_date'] ?? ''), 20);
$signatureRequired = !empty($payload['signature_required']) ? 1 : 0;

$allowedStatuses = ['pending', 'processing', 'shipped', 'out_for_delivery', 'delivered', 'completed', 'cancelled', 'returned'];
if ($orderId <= 0) {
    apiError('VALIDATION_ERROR', 'order_id is required.', 422, [
        ['field' => 'order_id', 'message' => 'order_id must be a positive integer.']
    ]);
}
if (!in_array($newStatus, $allowedStatuses, true)) {
    apiError('VALIDATION_ERROR', 'Invalid order status.', 422, [
        ['field' => 'order_status', 'message' => 'Unsupported order status.']
    ]);
}
if ($expectedDeliveryDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expectedDeliveryDate)) {
    apiError('VALIDATION_ERROR', 'Invalid expected_delivery_date format.', 422, [
        ['field' => 'expected_delivery_date', 'message' => 'Use YYYY-MM-DD format.']
    ]);
}

$orderStmt = $conn->prepare('SELECT o.order_id, o.vendor_id, o.customer_id, o.order_status, o.payment_status, v.user_id AS vendor_user_id FROM orders o INNER JOIN vendors v ON v.vendor_id = o.vendor_id WHERE o.order_id = ? LIMIT 1');
if (!$orderStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare order lookup.', 500);
}
$orderStmt->bind_param('i', $orderId);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

if (!$order) {
    apiError('ORDER_NOT_FOUND', 'Order not found.', 404);
}

if ($role === 'vendor' && (int)$order['vendor_user_id'] !== $userId) {
    apiError('FORBIDDEN', 'You can only update your own orders.', 403);
}

$currentStatus = strtolower((string)($order['order_status'] ?? ''));
$vendorTransitions = [
    'pending' => ['processing', 'cancelled'],
    'processing' => ['shipped', 'cancelled'],
    'shipped' => ['out_for_delivery', 'delivered', 'returned'],
    'out_for_delivery' => ['delivered', 'returned'],
    'delivered' => ['completed', 'returned'],
    'completed' => [],
    'cancelled' => [],
    'returned' => []
];

if ($role === 'vendor' && !in_array($newStatus, $vendorTransitions[$currentStatus] ?? [], true)) {
    apiError('INVALID_STATUS_TRANSITION', 'This status transition is not allowed for vendors.', 422);
}

$conn->begin_transaction();

try {
    $updateSql = 'UPDATE orders SET order_status = ?, tracking_number = ?, carrier_name = ?, carrier_url = ?, shipping_method = ?, expected_delivery_date = ?, actual_delivery_date = CASE WHEN ? IN ("delivered", "completed") THEN NOW() ELSE actual_delivery_date END, notes = CASE WHEN ? <> "" THEN ? ELSE notes END, updated_at = NOW() WHERE order_id = ?';
    $updateStmt = $conn->prepare($updateSql);
    if (!$updateStmt) {
        throw new Exception('Failed to prepare order update statement.');
    }
    $updateStmt->bind_param('sssssssssi', $newStatus, $trackingNumber, $carrierName, $carrierUrl, $shippingMethod, $expectedDeliveryDate, $newStatus, $notes, $notes, $orderId);
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update order status.');
    }
    $updateStmt->close();

    $shipmentStatus = null;
    if (in_array($newStatus, ['shipped', 'out_for_delivery', 'delivered', 'returned'], true)) {
        $shipmentStatus = [
            'shipped' => 'in_transit',
            'out_for_delivery' => 'out_for_delivery',
            'delivered' => 'delivered',
            'returned' => 'returned'
        ][$newStatus] ?? 'in_transit';

        $shipmentCheck = $conn->prepare('SELECT shipment_id FROM order_shipments WHERE order_id = ? ORDER BY created_at DESC, shipment_id DESC LIMIT 1');
        if (!$shipmentCheck) {
            throw new Exception('Failed to prepare shipment lookup.');
        }
        $shipmentCheck->bind_param('i', $orderId);
        $shipmentCheck->execute();
        $shipment = $shipmentCheck->get_result()->fetch_assoc();
        $shipmentCheck->close();

        if ($shipment) {
            $shipmentId = (int)$shipment['shipment_id'];
            $shipmentUpdate = $conn->prepare('UPDATE order_shipments SET shipment_date = COALESCE(shipment_date, NOW()), tracking_number = ?, carrier_name = ?, carrier_url = ?, estimated_delivery = CASE WHEN ? <> "" THEN ? ELSE estimated_delivery END, actual_delivery_date = CASE WHEN ? IN ("delivered", "returned") THEN NOW() ELSE actual_delivery_date END, shipment_status = ?, signature_required = ?, notes = ?, created_at = created_at WHERE shipment_id = ?');
            if ($shipmentUpdate) {
                $shipmentUpdate->bind_param('sssssssisi', $trackingNumber, $carrierName, $carrierUrl, $expectedDeliveryDate, $expectedDeliveryDate, $newStatus, $shipmentStatus, $signatureRequired, $notes, $shipmentId);
                $shipmentUpdate->execute();
                $shipmentUpdate->close();
            }
        } else {
            $shipmentInsert = $conn->prepare('INSERT INTO order_shipments (order_id, shipment_date, tracking_number, carrier_name, carrier_url, estimated_delivery, actual_delivery_date, shipment_status, signature_required, notes, created_at) VALUES (?, NOW(), ?, ?, ?, ?, CASE WHEN ? IN ("delivered", "returned") THEN NOW() ELSE NULL END, ?, ?, ?, NOW())');
            if ($shipmentInsert) {
                $shipmentInsert->bind_param('issssssis', $orderId, $trackingNumber, $carrierName, $carrierUrl, $expectedDeliveryDate, $newStatus, $shipmentStatus, $signatureRequired, $notes);
                $shipmentInsert->execute();
                $shipmentInsert->close();
            }
        }
    }

    if (in_array($newStatus, ['cancelled', 'returned'], true)) {
        $itemsStmt = $conn->prepare('SELECT product_id, variant_id, quantity FROM order_items WHERE order_id = ?');
        if ($itemsStmt) {
            $itemsStmt->bind_param('i', $orderId);
            $itemsStmt->execute();
            $itemsResult = $itemsStmt->get_result();
            $restoreProductStmt = $conn->prepare('UPDATE products SET quantity_in_stock = quantity_in_stock + ?, total_sold = GREATEST(total_sold - ?, 0) WHERE product_id = ?');
            $restoreVariantStmt = $conn->prepare('UPDATE product_variants SET quantity_in_stock = quantity_in_stock + ? WHERE variant_id = ?');
            while ($item = $itemsResult->fetch_assoc()) {
                $quantity = (int)$item['quantity'];
                $productId = (int)$item['product_id'];
                $variantId = $item['variant_id'] !== null ? (int)$item['variant_id'] : null;
                if ($restoreProductStmt) {
                    $restoreProductStmt->bind_param('iii', $quantity, $quantity, $productId);
                    $restoreProductStmt->execute();
                }
                if ($variantId !== null && $restoreVariantStmt) {
                    $restoreVariantStmt->bind_param('ii', $quantity, $variantId);
                    $restoreVariantStmt->execute();
                }
            }
            if ($restoreProductStmt) {
                $restoreProductStmt->close();
            }
            if ($restoreVariantStmt) {
                $restoreVariantStmt->close();
            }
            $itemsStmt->close();
        }
    }

    $refreshStmt = $conn->prepare('SELECT o.order_id, o.order_number, o.customer_id, o.vendor_id, o.order_status, o.payment_status, o.order_date, o.expected_delivery_date, o.actual_delivery_date, o.shipping_address, o.billing_address, o.subtotal, o.discount_amount, o.tax_amount, o.shipping_cost, o.commission_amount, o.total_amount, o.currency, o.shipping_method, o.tracking_number, o.carrier_name, o.notes, o.customer_notes, o.return_status, o.created_at, o.updated_at FROM orders o WHERE o.order_id = ? LIMIT 1');
    if (!$refreshStmt) {
        throw new Exception('Failed to prepare refreshed order query.');
    }
    $refreshStmt->bind_param('i', $orderId);
    $refreshStmt->execute();
    $updatedOrder = $refreshStmt->get_result()->fetch_assoc();
    $refreshStmt->close();

    $shipmentStmt = $conn->prepare('SELECT shipment_id, shipment_date, tracking_number, carrier_name, carrier_url, estimated_delivery, actual_delivery_date, shipment_status, signature_required, notes FROM order_shipments WHERE order_id = ? ORDER BY created_at DESC, shipment_id DESC LIMIT 1');
    $shipmentData = null;
    if ($shipmentStmt) {
        $shipmentStmt->bind_param('i', $orderId);
        $shipmentStmt->execute();
        $shipmentRow = $shipmentStmt->get_result()->fetch_assoc();
        if ($shipmentRow) {
            $shipmentData = $shipmentRow;
        }
        $shipmentStmt->close();
    }

    $conn->commit();

    apiSuccess([
        'order' => $updatedOrder,
        'shipment' => $shipmentData,
        'updated_by_role' => $role
    ], 'Order status updated successfully.', 'ORDER_STATUS_UPDATED');
} catch (Throwable $exception) {
    $conn->rollback();
    apiError('ORDER_STATUS_UPDATE_FAILED', $exception->getMessage(), 500);
}

$conn->close();
?>
