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

$saleId = (int)($data['seasonal_sale_id'] ?? 0);
if ($saleId <= 0) {
    return apiError('VALIDATION_ERROR', 'seasonal_sale_id is required', 400);
}

ensurePromotionalTables($conn);

$stmt = $conn->prepare('SELECT ss.vendor_id FROM seasonal_sales ss WHERE ss.seasonal_sale_id = ? LIMIT 1');
$stmt->bind_param('i', $saleId);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sale) {
    return apiError('NOT_FOUND', 'Seasonal sale not found', 404);
}

if ($user['role'] === 'vendor' && (int)($user['vendor_id'] ?? 0) !== (int)($sale['vendor_id'] ?? 0)) {
    return apiError('FORBIDDEN', 'Cannot delete other vendor\'s sales', 403);
}

$conn->begin_transaction();

$deleteItemsStmt = $conn->prepare('DELETE FROM seasonal_sale_items WHERE seasonal_sale_id = ?');
$deleteItemsStmt->bind_param('i', $saleId);
if (!$deleteItemsStmt->execute()) {
    $conn->rollback();
    return apiError('DB_ERROR', 'Failed to delete sale items', 500);
}
$deleteItemsStmt->close();

$deleteSaleStmt = $conn->prepare('DELETE FROM seasonal_sales WHERE seasonal_sale_id = ?');
$deleteSaleStmt->bind_param('i', $saleId);
if (!$deleteSaleStmt->execute()) {
    $conn->rollback();
    return apiError('DB_ERROR', 'Failed to delete seasonal sale', 500);
}
$deleteSaleStmt->close();

$conn->commit();

apiSuccess(['deleted_seasonal_sale_id' => $saleId], 'Seasonal sale deleted successfully');
?>
