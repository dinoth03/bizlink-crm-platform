<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';
require_once 'csrf_protection.php';
require_once 'shipping_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

requireAuth(['vendor', 'admin']);
$currentUser = getCurrentUser();
$userId = (int)($currentUser['user_id'] ?? 0);
$role = strtolower((string)($currentUser['role'] ?? ''));
if ($userId <= 0) {
    apiError('UNAUTHORIZED', 'Unauthorized user.', 401);
}

if (CSRF_ENABLED) {
    requireCsrfToken($conn, $userId);
}

bizlinkEnsureLogisticsTables($conn);

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true) ?: $_POST ?: [];
$orderId = (int)($payload['order_id'] ?? 0);
$carrierName = sanitizeString((string)($payload['carrier_name'] ?? 'BizLink Courier'), 100);
$carrierUrl = sanitizeString((string)($payload['carrier_url'] ?? ''), 500);
$shippingMethod = sanitizeString((string)($payload['shipping_method'] ?? 'Standard Delivery'), 100);
$expectedDeliveryDate = sanitizeString((string)($payload['expected_delivery_date'] ?? ''), 20);
$notes = sanitizeString((string)($payload['notes'] ?? ''), 1000);
$signatureRequired = !empty($payload['signature_required']) ? 1 : 0;
$forceNewTracking = !empty($payload['force_new_tracking']);

if ($orderId <= 0) {
    apiError('VALIDATION_ERROR', 'order_id is required.', 422);
}

$orderStmt = $conn->prepare('SELECT o.order_id, o.order_number, o.customer_id, o.vendor_id, o.order_status, o.shipping_address, o.shipping_method, o.tracking_number, o.shipping_cost, o.expected_delivery_date, c.customer_id AS customer_lookup, u.city, u.province FROM orders o INNER JOIN customers c ON c.customer_id = o.customer_id INNER JOIN users u ON u.user_id = c.user_id WHERE o.order_id = ? LIMIT 1');
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

if ($role === 'vendor') {
    $vendorStmt = $conn->prepare('SELECT vendor_id FROM vendors WHERE user_id = ? LIMIT 1');
    if ($vendorStmt) {
        $vendorStmt->bind_param('i', $userId);
        $vendorStmt->execute();
        $vendor = $vendorStmt->get_result()->fetch_assoc();
        $vendorStmt->close();
        if ((int)($vendor['vendor_id'] ?? 0) !== (int)$order['vendor_id']) {
            apiError('FORBIDDEN', 'You can only create labels for your own orders.', 403);
        }
    }
}

$estimate = bizlinkEstimateShipping([
    'province' => $order['province'] ?? '',
    'city' => $order['city'] ?? '',
    'subtotal' => $order['shipping_cost'] ?? 0,
    'weight_kg' => $payload['weight_kg'] ?? 0,
    'item_count' => $payload['item_count'] ?? 1,
    'shipping_method' => $shippingMethod ?: 'standard',
    'is_service' => false
]);

if ($expectedDeliveryDate === '') {
    $expectedDeliveryDate = $estimate['estimated_delivery_end'];
}

$trackingNumber = trim((string)($order['tracking_number'] ?? ''));
if ($trackingNumber === '' || $forceNewTracking) {
    $trackingNumber = bizlinkGenerateTrackingNumber($orderId);
}

$conn->begin_transaction();

try {
    $shipmentStmt = $conn->prepare('SELECT shipment_id FROM order_shipments WHERE order_id = ? ORDER BY created_at DESC, shipment_id DESC LIMIT 1');
    if (!$shipmentStmt) {
        throw new Exception('Failed to prepare shipment lookup.');
    }
    $shipmentStmt->bind_param('i', $orderId);
    $shipmentStmt->execute();
    $shipment = $shipmentStmt->get_result()->fetch_assoc();
    $shipmentStmt->close();

    $shipmentStatus = 'picked_up';
    if (in_array(strtolower((string)$order['order_status']), ['shipped', 'out_for_delivery', 'delivered'], true)) {
        $shipmentStatus = strtolower((string)$order['order_status']);
    }

    if ($shipment) {
        $shipmentId = (int)$shipment['shipment_id'];
        $stmt = $conn->prepare('UPDATE order_shipments SET shipment_date = COALESCE(shipment_date, NOW()), tracking_number = ?, carrier_name = ?, carrier_url = ?, estimated_delivery = ?, shipment_status = ?, signature_required = ?, notes = ?, created_at = created_at WHERE shipment_id = ?');
        if (!$stmt) {
            throw new Exception('Failed to prepare shipment update.');
        }
        $stmt->bind_param('sssssisi', $trackingNumber, $carrierName, $carrierUrl, $expectedDeliveryDate, $shipmentStatus, $signatureRequired, $notes, $shipmentId);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $conn->prepare('INSERT INTO order_shipments (order_id, shipment_date, tracking_number, carrier_name, carrier_url, estimated_delivery, shipment_status, signature_required, notes, created_at) VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, NOW())');
        if (!$stmt) {
            throw new Exception('Failed to prepare shipment insert.');
        }
        $stmt->bind_param('isssssis', $orderId, $trackingNumber, $carrierName, $carrierUrl, $expectedDeliveryDate, $shipmentStatus, $signatureRequired, $notes);
        $stmt->execute();
        $shipmentId = (int)$stmt->insert_id;
        $stmt->close();
    }

    $orderUpdate = $conn->prepare('UPDATE orders SET tracking_number = ?, carrier_name = ?, shipping_method = ?, expected_delivery_date = ?, shipping_cost = ?, updated_at = NOW() WHERE order_id = ?');
    if (!$orderUpdate) {
        throw new Exception('Failed to prepare order update.');
    }
    $shippingCost = (float)($estimate['shipping_cost'] ?? 0);
    $orderUpdate->bind_param('ssssdi', $trackingNumber, $carrierName, $shippingMethod, $expectedDeliveryDate, $shippingCost, $orderId);
    $orderUpdate->execute();
    $orderUpdate->close();

    $labelPayload = [
        'order_id' => $orderId,
        'order_number' => $order['order_number'],
        'tracking_number' => $trackingNumber,
        'carrier_name' => $carrierName,
        'carrier_url' => $carrierUrl,
        'shipping_method' => $shippingMethod,
        'expected_delivery_date' => $expectedDeliveryDate,
        'signature_required' => (bool)$signatureRequired,
        'estimate' => $estimate,
        'created_by' => $currentUser['full_name'] ?? $currentUser['email'] ?? 'BizLink User'
    ];

    $labelStmt = $conn->prepare('INSERT INTO shipping_labels (order_id, shipment_id, tracking_number, carrier_name, carrier_url, label_payload_json, print_status) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE shipment_id = VALUES(shipment_id), carrier_name = VALUES(carrier_name), carrier_url = VALUES(carrier_url), label_payload_json = VALUES(label_payload_json), print_status = VALUES(print_status), updated_at = NOW()');
    if ($labelStmt) {
        $printStatus = 'pending';
        $payloadJson = json_encode($labelPayload);
        $labelStmt->bind_param('iisssss', $orderId, $shipmentId, $trackingNumber, $carrierName, $carrierUrl, $payloadJson, $printStatus);
        $labelStmt->execute();
        $labelStmt->close();
    }

    $refreshStmt = $conn->prepare('SELECT o.order_id, o.order_number, o.customer_id, o.vendor_id, o.order_status, o.payment_status, o.order_date, o.expected_delivery_date, o.actual_delivery_date, o.shipping_address, o.billing_address, o.subtotal, o.discount_amount, o.tax_amount, o.shipping_cost, o.commission_amount, o.total_amount, o.currency, o.shipping_method, o.tracking_number, o.carrier_name, o.notes, o.customer_notes, o.return_status, o.created_at, o.updated_at FROM orders o WHERE o.order_id = ? LIMIT 1');
    if (!$refreshStmt) {
        throw new Exception('Failed to refresh order.');
    }
    $refreshStmt->bind_param('i', $orderId);
    $refreshStmt->execute();
    $updatedOrder = $refreshStmt->get_result()->fetch_assoc();
    $refreshStmt->close();

    $conn->commit();

    apiSuccess([
        'order' => $updatedOrder,
        'tracking_number' => $trackingNumber,
        'label' => $labelPayload,
        'printable_label_html' => bizlinkBuildPrintableLabel($order, [
            'tracking_number' => $trackingNumber,
            'carrier_name' => $carrierName
        ], $estimate)
    ], 'Shipping label created.', 'SHIPPING_LABEL_CREATED');
} catch (Throwable $error) {
    $conn->rollback();
    apiError('SHIPPING_LABEL_FAILED', $error->getMessage(), 500);
}

$conn->close();
?>