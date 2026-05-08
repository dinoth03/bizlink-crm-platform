<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';

requireAuth(['vendor']);

$currentUser = getCurrentUser();
$userId = (int)($currentUser['user_id'] ?? 0);
if ($userId <= 0) {
    apiError('UNAUTHORIZED', 'Unauthorized user.', 401);
}

$vendorStmt = $conn->prepare('SELECT vendor_id, business_name FROM vendors WHERE user_id = ? LIMIT 1');
if (!$vendorStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare vendor lookup.', 500);
}
$vendorStmt->bind_param('i', $userId);
$vendorStmt->execute();
$vendor = $vendorStmt->get_result()->fetch_assoc();
$vendorStmt->close();

$vendorId = (int)($vendor['vendor_id'] ?? 0);
if ($vendorId <= 0) {
    apiError('VENDOR_NOT_FOUND', 'Vendor profile not found.', 404);
}

$pagination = getPaginationParams($_GET, 20, 100);
$statusFilter = isset($_GET['status']) ? sanitizeString((string)$_GET['status'], 30) : '';
$paymentStatusFilter = isset($_GET['payment_status']) ? sanitizeString((string)$_GET['payment_status'], 30) : '';
$search = isset($_GET['search']) ? sanitizeString((string)$_GET['search'], 100) : '';

$validStatuses = ['pending', 'processing', 'shipped', 'out_for_delivery', 'delivered', 'completed', 'cancelled', 'returned'];
$validPaymentStatuses = ['unpaid', 'paid', 'partially_paid', 'failed', 'refunded'];
if ($statusFilter !== '' && !in_array($statusFilter, $validStatuses, true)) {
    apiError('VALIDATION_ERROR', 'Invalid order status filter.', 422);
}
if ($paymentStatusFilter !== '' && !in_array($paymentStatusFilter, $validPaymentStatuses, true)) {
    apiError('VALIDATION_ERROR', 'Invalid payment status filter.', 422);
}

$whereSql = ' WHERE o.vendor_id = ?';
$params = [$vendorId];
$types = 'i';
if ($statusFilter !== '') {
    $whereSql .= ' AND o.order_status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}
if ($paymentStatusFilter !== '') {
    $whereSql .= ' AND o.payment_status = ?';
    $params[] = $paymentStatusFilter;
    $types .= 's';
}
if ($search !== '') {
    $whereSql .= ' AND (o.order_number LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

$countStmt = $conn->prepare('SELECT COUNT(*) AS total FROM orders o INNER JOIN customers c ON c.customer_id = o.customer_id INNER JOIN users u ON u.user_id = c.user_id' . $whereSql);
if (!$countStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare vendor order count query.', 500);
}
bindDynamicParams($countStmt, $types, $params);
$countStmt->execute();
$total = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

$query = 'SELECT o.order_id, o.order_number, o.customer_id, u.full_name AS customer_name, u.email AS customer_email, u.phone AS customer_phone, u.city AS customer_city, u.province AS customer_province, o.order_status, o.payment_status, o.order_date, o.expected_delivery_date, o.actual_delivery_date, o.shipping_address, o.billing_address, o.subtotal, o.discount_amount, o.tax_amount, o.shipping_cost, o.commission_amount, o.total_amount, o.currency, o.shipping_method, o.tracking_number, o.carrier_name, o.notes, o.customer_notes, o.return_status, o.created_at, o.updated_at FROM orders o INNER JOIN customers c ON c.customer_id = o.customer_id INNER JOIN users u ON u.user_id = c.user_id' . $whereSql . ' ORDER BY o.created_at DESC LIMIT ? OFFSET ?';
$stmt = $conn->prepare($query);
if (!$stmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare vendor orders query.', 500);
}
$finalParams = $params;
$finalTypes = $types . 'ii';
$finalParams[] = $pagination['limit'];
$finalParams[] = $pagination['offset'];
bindDynamicParams($stmt, $finalTypes, $finalParams);
$stmt->execute();
$result = $stmt->get_result();

$itemStmt = $conn->prepare('SELECT oi.order_item_id, oi.product_id, oi.variant_id, oi.product_name, oi.price_at_purchase, oi.quantity, oi.discount_applied, oi.subtotal, oi.tax_amount, oi.total_amount, p.primary_image_url, p.category, pv.variant_name FROM order_items oi LEFT JOIN products p ON p.product_id = oi.product_id LEFT JOIN product_variants pv ON pv.variant_id = oi.variant_id WHERE oi.order_id = ? ORDER BY oi.order_item_id ASC');
$shipmentStmt = $conn->prepare('SELECT shipment_id, shipment_date, tracking_number, carrier_name, carrier_url, estimated_delivery, actual_delivery_date, shipment_status, notes FROM order_shipments WHERE order_id = ? ORDER BY created_at DESC, shipment_id DESC LIMIT 1');
$paymentStmt = $conn->prepare('SELECT payment_id, payment_method, payment_amount, payment_status, transaction_id, transaction_reference, gateway_name, receipt_url FROM payments WHERE order_id = ? ORDER BY created_at DESC, payment_id DESC LIMIT 1');

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orderId = (int)$row['order_id'];

    $items = [];
    if ($itemStmt) {
        $itemStmt->bind_param('i', $orderId);
        $itemStmt->execute();
        $itemResult = $itemStmt->get_result();
        while ($item = $itemResult->fetch_assoc()) {
            $items[] = [
                'order_item_id' => (int)$item['order_item_id'],
                'product_id' => (int)$item['product_id'],
                'variant_id' => $item['variant_id'] !== null ? (int)$item['variant_id'] : null,
                'product_name' => $item['product_name'],
                'variant_name' => $item['variant_name'],
                'price_at_purchase' => (float)$item['price_at_purchase'],
                'quantity' => (int)$item['quantity'],
                'discount_applied' => (float)$item['discount_applied'],
                'subtotal' => (float)$item['subtotal'],
                'tax_amount' => (float)$item['tax_amount'],
                'total_amount' => (float)$item['total_amount'],
                'primary_image_url' => $item['primary_image_url'],
                'category' => $item['category']
            ];
        }
    }

    $shipment = null;
    if ($shipmentStmt) {
        $shipmentStmt->bind_param('i', $orderId);
        $shipmentStmt->execute();
        $shipmentRow = $shipmentStmt->get_result()->fetch_assoc();
        if ($shipmentRow) {
            $shipment = [
                'shipment_id' => (int)$shipmentRow['shipment_id'],
                'shipment_date' => $shipmentRow['shipment_date'],
                'tracking_number' => $shipmentRow['tracking_number'],
                'carrier_name' => $shipmentRow['carrier_name'],
                'carrier_url' => $shipmentRow['carrier_url'],
                'estimated_delivery' => $shipmentRow['estimated_delivery'],
                'actual_delivery_date' => $shipmentRow['actual_delivery_date'],
                'shipment_status' => $shipmentRow['shipment_status'],
                'notes' => $shipmentRow['notes']
            ];
        }
    }

    $payment = null;
    if ($paymentStmt) {
        $paymentStmt->bind_param('i', $orderId);
        $paymentStmt->execute();
        $paymentRow = $paymentStmt->get_result()->fetch_assoc();
        if ($paymentRow) {
            $payment = [
                'payment_id' => (int)$paymentRow['payment_id'],
                'payment_method' => $paymentRow['payment_method'],
                'payment_amount' => (float)$paymentRow['payment_amount'],
                'payment_status' => $paymentRow['payment_status'],
                'transaction_id' => $paymentRow['transaction_id'],
                'transaction_reference' => $paymentRow['transaction_reference'],
                'gateway_name' => $paymentRow['gateway_name'],
                'receipt_url' => $paymentRow['receipt_url']
            ];
        }
    }

    $row['order_id'] = $orderId;
    $row['customer_id'] = (int)$row['customer_id'];
    $row['subtotal'] = (float)$row['subtotal'];
    $row['discount_amount'] = (float)$row['discount_amount'];
    $row['tax_amount'] = (float)$row['tax_amount'];
    $row['shipping_cost'] = (float)$row['shipping_cost'];
    $row['commission_amount'] = (float)$row['commission_amount'];
    $row['total_amount'] = (float)$row['total_amount'];
    $row['items'] = $items;
    $row['item_count'] = count($items);
    $row['total_quantity'] = array_sum(array_map(static fn($value) => (int)$value['quantity'], $items));
    $row['shipment'] = $shipment;
    $row['payment'] = $payment;
    $orders[] = $row;
}

if ($itemStmt) {
    $itemStmt->close();
}
if ($shipmentStmt) {
    $shipmentStmt->close();
}
if ($paymentStmt) {
    $paymentStmt->close();
}
$stmt->close();

apiSuccess([
    'orders' => $orders,
    'vendor' => [
        'vendor_id' => $vendorId,
        'business_name' => $vendor['business_name'] ?? ''
    ],
    'pagination' => [
        'page' => $pagination['page'],
        'per_page' => $pagination['per_page'],
        'total' => $total,
        'total_pages' => $pagination['per_page'] > 0 ? (int)ceil($total / $pagination['per_page']) : 0
    ]
], 'Vendor orders retrieved successfully.', 'VENDOR_ORDERS_RETRIEVED');

$conn->close();
?>
