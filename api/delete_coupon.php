<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/promotional_helpers.php';

requireAuth(['admin', 'vendor'], $conn);
requireCsrfToken($conn, getCurrentUser()['user_id']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return apiError('METHOD_NOT_ALLOWED', 'POST only', 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$user = getCurrentUser();

if (!$user) {
    return apiError('AUTH_REQUIRED', 'Not authenticated', 401);
}

$couponId = (int)($data['coupon_id'] ?? 0);
if ($couponId <= 0) {
    return apiError('VALIDATION_ERROR', 'coupon_id is required', 400);
}

ensurePromotionalTables($conn);

$stmt = $conn->prepare('SELECT c.vendor_id FROM coupons c WHERE c.coupon_id = ? LIMIT 1');
$stmt->bind_param('i', $couponId);
$stmt->execute();
$coupon = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$coupon) {
    return apiError('NOT_FOUND', 'Coupon not found', 404);
}

if ($user['role'] === 'vendor' && (int)($user['vendor_id'] ?? 0) !== (int)($coupon['vendor_id'] ?? 0)) {
    return apiError('FORBIDDEN', 'Cannot delete other vendor\'s coupons', 403);
}

$conn->begin_transaction();

$deleteUsageStmt = $conn->prepare('DELETE FROM coupon_usage WHERE coupon_id = ?');
$deleteUsageStmt->bind_param('i', $couponId);
if (!$deleteUsageStmt->execute()) {
    $conn->rollback();
    return apiError('DB_ERROR', 'Failed to delete coupon usage records', 500);
}
$deleteUsageStmt->close();

$deleteCouponStmt = $conn->prepare('DELETE FROM coupons WHERE coupon_id = ?');
$deleteCouponStmt->bind_param('i', $couponId);
if (!$deleteCouponStmt->execute()) {
    $conn->rollback();
    return apiError('DB_ERROR', 'Failed to delete coupon', 500);
}
$deleteCouponStmt->close();

$conn->commit();

apiSuccess(['deleted_coupon_id' => $couponId], 'Coupon deleted successfully');
?>
