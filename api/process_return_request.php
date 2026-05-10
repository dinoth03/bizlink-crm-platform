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
$returnRequestId = (int)($payload['return_request_id'] ?? 0);
$rmaNumber = sanitizeString((string)($payload['rma_number'] ?? ''), 100);
$action = strtolower(sanitizeString((string)($payload['action'] ?? ''), 50));
$notes = sanitizeString((string)($payload['notes'] ?? ''), 2000);
$returnTrackingNumber = sanitizeString((string)($payload['return_tracking_number'] ?? ''), 255);
$carrierName = sanitizeString((string)($payload['carrier_name'] ?? 'BizLink Returns'), 100);
$returnShippingCost = isset($payload['return_shipping_cost']) ? (float)$payload['return_shipping_cost'] : null;

if (($returnRequestId <= 0 && $rmaNumber === '') || $action === '') {
    apiError('VALIDATION_ERROR', 'return_request_id or rma_number and action are required.', 422);
}

$sql = 'SELECT rr.*, o.order_status, o.return_status AS order_return_status, o.customer_id AS order_customer_id, o.vendor_id AS order_vendor_id, o.total_amount, o.shipping_cost, u.full_name AS customer_name, vu.full_name AS vendor_name FROM return_requests rr INNER JOIN orders o ON o.order_id = rr.order_id INNER JOIN customers c ON c.customer_id = rr.customer_id INNER JOIN users u ON u.user_id = c.user_id INNER JOIN vendors v ON v.vendor_id = rr.vendor_id INNER JOIN users vu ON vu.user_id = v.user_id WHERE ';
$params = [];
$types = '';
if ($returnRequestId > 0) {
    $sql .= 'rr.return_request_id = ? LIMIT 1';
    $params[] = $returnRequestId;
    $types .= 'i';
} else {
    $sql .= 'rr.rma_number = ? LIMIT 1';
    $params[] = $rmaNumber;
    $types .= 's';
}
$stmt = $conn->prepare($sql);
if (!$stmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare return request lookup.', 500);
}
$stmt->bind_param($types, ...$params);
$stmt->execute();
$returnRequest = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$returnRequest) {
    apiError('RETURN_NOT_FOUND', 'Return request not found.', 404);
}

if ($role === 'vendor') {
    $vendorStmt = $conn->prepare('SELECT vendor_id FROM vendors WHERE user_id = ? LIMIT 1');
    if ($vendorStmt) {
        $vendorStmt->bind_param('i', $userId);
        $vendorStmt->execute();
        $vendor = $vendorStmt->get_result()->fetch_assoc();
        $vendorStmt->close();
        if ((int)($vendor['vendor_id'] ?? 0) !== (int)$returnRequest['vendor_id']) {
            apiError('FORBIDDEN', 'You can only process your own return requests.', 403);
        }
    }
}

$newStatus = $returnRequest['return_status'];
$orderStatus = $returnRequest['order_return_status'] ?: 'requested';
if ($action === 'approve') {
    $newStatus = 'approved';
    $orderStatus = 'approved';
    if ($returnTrackingNumber === '') {
        $returnTrackingNumber = bizlinkGenerateTrackingNumber((int)$returnRequest['order_id']);
    }
} elseif ($action === 'reject') {
    $newStatus = 'rejected';
    $orderStatus = 'rejected';
} elseif ($action === 'in_transit') {
    $newStatus = 'in_transit';
    $orderStatus = 'in_transit';
} elseif ($action === 'receive' || $action === 'received') {
    $newStatus = 'received';
    $orderStatus = 'received';
} elseif ($action === 'refund') {
    $newStatus = 'refunded';
    $orderStatus = 'refunded';
} elseif ($action === 'close') {
    $newStatus = 'closed';
    $orderStatus = 'closed';
} else {
    apiError('VALIDATION_ERROR', 'Unsupported return action.', 422);
}

if ($returnShippingCost === null) {
    $returnShippingCost = (float)($returnRequest['return_shipping_cost'] ?? 0);
}

$update = $conn->prepare('UPDATE return_requests SET return_status = ?, return_tracking_number = ?, carrier_name = ?, return_shipping_cost = ?, notes = CONCAT(COALESCE(notes, ""), CASE WHEN notes IS NULL OR notes = "" THEN "" ELSE "\n" END, ?), updated_at = NOW() WHERE return_request_id = ?');
if (!$update) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare return update.', 500);
}
$returnRequestIdValue = (int)$returnRequest['return_request_id'];
$update->bind_param('sssdsi', $newStatus, $returnTrackingNumber, $carrierName, $returnShippingCost, $notes, $returnRequestIdValue);
$update->execute();
$update->close();

$orderUpdate = $conn->prepare('UPDATE orders SET return_status = ?, tracking_number = CASE WHEN ? <> "" THEN ? ELSE tracking_number END, carrier_name = CASE WHEN ? <> "" THEN ? ELSE carrier_name END, updated_at = NOW() WHERE order_id = ?');
if ($orderUpdate) {
    $orderUpdate->bind_param('sssssi', $orderStatus, $returnTrackingNumber, $returnTrackingNumber, $carrierName, $carrierName, $returnRequest['order_id']);
    $orderUpdate->execute();
    $orderUpdate->close();
}

apiSuccess([
    'return_request_id' => $returnRequestIdValue,
    'rma_number' => $returnRequest['rma_number'],
    'return_status' => $newStatus,
    'order_return_status' => $orderStatus,
    'return_tracking_number' => $returnTrackingNumber,
    'carrier_name' => $carrierName,
    'return_shipping_cost' => $returnShippingCost
], 'Return request updated.', 'RETURN_REQUEST_UPDATED');

$conn->close();
?>
