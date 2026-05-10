<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';
require_once 'shipping_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use GET.', 405);
}

requireAuth(['customer', 'vendor', 'admin']);
$currentUser = getCurrentUser();
$userId = (int)($currentUser['user_id'] ?? 0);
$role = strtolower((string)($currentUser['role'] ?? ''));
if ($userId <= 0) {
    apiError('UNAUTHORIZED', 'Unauthorized user.', 401);
}

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$trackingNumber = sanitizeString((string)($_GET['tracking_number'] ?? ''), 255);

$sql = 'SELECT o.order_id, o.order_number, o.customer_id, o.vendor_id, o.order_status, o.payment_status, o.expected_delivery_date, o.actual_delivery_date, o.shipping_address, o.shipping_method, o.tracking_number, o.carrier_name, o.shipping_cost, o.return_status, o.created_at, u.full_name AS customer_name, u.email AS customer_email FROM orders o INNER JOIN customers c ON c.customer_id = o.customer_id INNER JOIN users u ON u.user_id = c.user_id';
$params = [];
$types = '';

if ($orderId > 0) {
    $sql .= ' WHERE o.order_id = ? LIMIT 1';
    $params[] = $orderId;
    $types .= 'i';
} elseif ($trackingNumber !== '') {
    $sql .= ' WHERE o.tracking_number = ? OR EXISTS (SELECT 1 FROM order_shipments os WHERE os.order_id = o.order_id AND os.tracking_number = ? LIMIT 1) LIMIT 1';
    $params[] = $trackingNumber;
    $params[] = $trackingNumber;
    $types .= 'ss';
} else {
    apiError('VALIDATION_ERROR', 'order_id or tracking_number is required.', 422);
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare shipment lookup.', 500);
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    apiError('SHIPMENT_NOT_FOUND', 'Shipment not found.', 404);
}

if ($role === 'customer' && (int)$order['customer_id'] !== $userId) {
    apiError('FORBIDDEN', 'You can only view your own shipments.', 403);
}
if ($role === 'vendor') {
    $vendorStmt = $conn->prepare('SELECT vendor_id FROM vendors WHERE user_id = ? LIMIT 1');
    if ($vendorStmt) {
        $vendorStmt->bind_param('i', $userId);
        $vendorStmt->execute();
        $vendor = $vendorStmt->get_result()->fetch_assoc();
        $vendorStmt->close();
        if ((int)($vendor['vendor_id'] ?? 0) !== (int)$order['vendor_id']) {
            apiError('FORBIDDEN', 'You can only view your own shipments.', 403);
        }
    }
}

$shipmentStmt = $conn->prepare('SELECT shipment_id, order_id, shipment_date, tracking_number, carrier_name, carrier_url, estimated_delivery, actual_delivery, shipment_status, signature_required, notes, created_at, updated_at FROM order_shipments WHERE order_id = ? ORDER BY created_at DESC, shipment_id DESC LIMIT 1');
if (!$shipmentStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare shipment details query.', 500);
}
$shipmentStmt->bind_param('i', $order['order_id']);
$shipmentStmt->execute();
$shipment = $shipmentStmt->get_result()->fetch_assoc();
$shipmentStmt->close();

$estimate = bizlinkEstimateShipping([
    'province' => '',
    'city' => '',
    'subtotal' => $order['shipping_cost'] ?? 0,
    'item_count' => 1,
    'shipping_method' => $order['shipping_method'] ?? 'standard'
]);

$response = [
    'order' => $order,
    'shipment' => $shipment,
    'tracking_number' => $shipment['tracking_number'] ?? $order['tracking_number'] ?? $trackingNumber,
    'status' => $shipment['shipment_status'] ?? $order['order_status'],
    'estimated_delivery' => $shipment['estimated_delivery'] ?? $order['expected_delivery_date'],
    'delivery_window' => [
        'start' => $estimate['estimated_delivery_start'],
        'end' => $estimate['estimated_delivery_end']
    ]
];

apiSuccess($response, 'Shipment tracking fetched.', 'SHIPMENT_TRACKING_FETCHED');

$conn->close();
?>