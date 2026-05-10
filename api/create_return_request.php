<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';
require_once 'csrf_protection.php';
require_once 'shipping_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

requireAuth(['customer']);
$currentUser = getCurrentUser();
$userId = (int)($currentUser['user_id'] ?? 0);
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
$reason = sanitizeString((string)($payload['reason'] ?? ''), 255);
$reasonDetails = sanitizeString((string)($payload['reason_details'] ?? ''), 2000);
$requestedAmount = isset($payload['requested_amount']) ? (float)$payload['requested_amount'] : 0.0;
$notes = sanitizeString((string)($payload['notes'] ?? ''), 2000);

if ($orderId <= 0 || $reason === '') {
    apiError('VALIDATION_ERROR', 'order_id and reason are required.', 422);
}

$orderStmt = $conn->prepare('SELECT o.order_id, o.order_number, o.customer_id, o.vendor_id, o.order_status, o.payment_status, o.total_amount, o.shipping_cost, o.return_status, o.created_at FROM orders o INNER JOIN customers c ON c.customer_id = o.customer_id WHERE o.order_id = ? AND c.user_id = ? LIMIT 1');
if (!$orderStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare order lookup.', 500);
}
$orderStmt->bind_param('ii', $orderId, $userId);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

if (!$order) {
    apiError('ORDER_NOT_FOUND', 'Order not found.', 404);
}

$existingStmt = $conn->prepare('SELECT return_request_id, return_status FROM return_requests WHERE order_id = ? AND customer_id = ? ORDER BY created_at DESC LIMIT 1');
if ($existingStmt) {
    $existingStmt->bind_param('ii', $orderId, $order['customer_id']);
    $existingStmt->execute();
    $existing = $existingStmt->get_result()->fetch_assoc();
    $existingStmt->close();
    if ($existing && in_array($existing['return_status'], ['requested', 'approved', 'in_transit', 'received'], true)) {
        apiError('RETURN_EXISTS', 'A return request already exists for this order.', 409);
    }
}

$rmaNumber = 'RMA-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
$returnShippingCost = round(max(0.0, (float)$order['shipping_cost']) * 0.5, 2);
$status = 'requested';

$stmt = $conn->prepare('INSERT INTO return_requests (order_id, customer_id, vendor_id, rma_number, reason, reason_details, requested_amount, return_status, return_shipping_cost, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
if (!$stmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare return request insert.', 500);
}
$stmt->bind_param('iiisssdsds', $orderId, $order['customer_id'], $order['vendor_id'], $rmaNumber, $reason, $reasonDetails, $requestedAmount, $status, $returnShippingCost, $notes);
$stmt->execute();
$returnRequestId = (int)$stmt->insert_id;
$stmt->close();

$orderUpdate = $conn->prepare('UPDATE orders SET return_status = ?, updated_at = NOW() WHERE order_id = ?');
if ($orderUpdate) {
    $orderUpdate->bind_param('si', $status, $orderId);
    $orderUpdate->execute();
    $orderUpdate->close();
}

apiSuccess([
    'return_request_id' => $returnRequestId,
    'rma_number' => $rmaNumber,
    'return_status' => $status,
    'return_shipping_cost' => $returnShippingCost,
    'order_id' => $orderId
], 'Return request created.', 'RETURN_REQUEST_CREATED');

$conn->close();
?>