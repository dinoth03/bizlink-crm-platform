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

$bulkDiscountId = (int)($data['bulk_discount_id'] ?? 0);
if ($bulkDiscountId <= 0) {
    return apiError('VALIDATION_ERROR', 'bulk_discount_id is required', 400);
}

ensurePromotionalTables($conn);

$stmt = $conn->prepare('SELECT bd.vendor_id FROM bulk_discounts bd WHERE bd.bulk_discount_id = ? LIMIT 1');
$stmt->bind_param('i', $bulkDiscountId);
$stmt->execute();
$discount = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$discount) {
    return apiError('NOT_FOUND', 'Bulk discount not found', 404);
}

if ($user['role'] === 'vendor' && (int)($user['vendor_id'] ?? 0) !== (int)$discount['vendor_id']) {
    return apiError('FORBIDDEN', 'Cannot delete other vendor\'s discounts', 403);
}

$stmt = $conn->prepare('DELETE FROM bulk_discounts WHERE bulk_discount_id = ?');
$stmt->bind_param('i', $bulkDiscountId);
if (!$stmt->execute()) {
    $stmt->close();
    return apiError('DB_ERROR', 'Failed to delete bulk discount', 500);
}
$stmt->close();

apiSuccess(['deleted_bulk_discount_id' => $bulkDiscountId], 'Bulk discount deleted successfully');
?>
