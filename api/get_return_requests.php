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

bizlinkEnsureLogisticsTables($conn);

$status = sanitizeString((string)($_GET['status'] ?? ''), 50);
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

$sql = 'SELECT rr.return_request_id, rr.order_id, rr.customer_id, rr.vendor_id, rr.rma_number, rr.reason, rr.reason_details, rr.requested_amount, rr.return_status, rr.return_tracking_number, rr.carrier_name, rr.return_shipping_cost, rr.notes, rr.created_at, rr.updated_at, o.order_number, o.order_status, o.total_amount, u.full_name AS customer_name, vu.full_name AS vendor_name FROM return_requests rr INNER JOIN orders o ON o.order_id = rr.order_id INNER JOIN customers c ON c.customer_id = rr.customer_id INNER JOIN users u ON u.user_id = c.user_id INNER JOIN vendors v ON v.vendor_id = rr.vendor_id INNER JOIN users vu ON vu.user_id = v.user_id WHERE 1=1';
$params = [];
$types = '';

if ($role === 'customer') {
    $sql .= ' AND rr.customer_id = ?';
    $params[] = $userId;
    $types .= 'i';
} elseif ($role === 'vendor') {
    $vendorStmt = $conn->prepare('SELECT vendor_id FROM vendors WHERE user_id = ? LIMIT 1');
    if ($vendorStmt) {
        $vendorStmt->bind_param('i', $userId);
        $vendorStmt->execute();
        $vendor = $vendorStmt->get_result()->fetch_assoc();
        $vendorStmt->close();
        $sql .= ' AND rr.vendor_id = ?';
        $params[] = (int)($vendor['vendor_id'] ?? 0);
        $types .= 'i';
    }
}

if ($status !== '') {
    $sql .= ' AND rr.return_status = ?';
    $params[] = $status;
    $types .= 's';
}
if ($orderId > 0) {
    $sql .= ' AND rr.order_id = ?';
    $params[] = $orderId;
    $types .= 'i';
}

$sql .= ' ORDER BY rr.created_at DESC';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare return request query.', 500);
}
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$returnRequests = [];
while ($row = $result->fetch_assoc()) {
    $returnRequests[] = $row;
}
$stmt->close();

apiSuccess([
    'return_requests' => $returnRequests,
    'count' => count($returnRequests)
], 'Return requests fetched.', 'RETURN_REQUESTS_FETCHED');

$conn->close();
?>