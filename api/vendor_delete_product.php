<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';
require_once 'csrf_protection.php';
require_once 'rate_limiting.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Only POST is allowed.', 405);
}

requireAuth(['vendor']);

$currentUser = getCurrentUser();
$userId = (int)($currentUser['user_id'] ?? 0);

if ($userId <= 0) {
    apiError('UNAUTHORIZED', 'Unauthorized user.', 401);
}

if (CSRF_ENABLED) {
    requireCsrfToken($conn, $userId);
}

$clientIp = getClientIpAddress();
requireRateLimit($conn, $clientIp, 'vendor_delete_by_ip', 20, 900);

// Get vendor ID
$vendorStmt = $conn->prepare('SELECT vendor_id FROM vendors WHERE user_id = ? LIMIT 1');
$vendorStmt->bind_param('i', $userId);
$vendorStmt->execute();
$vendorRow = $vendorStmt->get_result()->fetch_assoc();
$vendorStmt->close();

if (!$vendorRow) {
    apiError('VENDOR_NOT_FOUND', 'Vendor profile not found.', 404);
}

$vendorId = (int)$vendorRow['vendor_id'];

$payload = readJsonPayload();
$productId = (int)($payload['product_id'] ?? 0);

if ($productId <= 0) {
    apiError('VALIDATION_ERROR', 'Product ID is required and must be positive.', 422);
}

// Verify product ownership
$ownerStmt = $conn->prepare('SELECT product_id FROM products WHERE product_id = ? AND vendor_id = ? LIMIT 1');
$ownerStmt->bind_param('ii', $productId, $vendorId);
$ownerStmt->execute();
$ownerRow = $ownerStmt->get_result()->fetch_assoc();
$ownerStmt->close();

if (!$ownerRow) {
    apiError('FORBIDDEN', 'You do not own this product.', 403);
}

// Soft-delete (set is_active = 0)
$deleteStmt = $conn->prepare('UPDATE products SET is_active = 0, updated_at = NOW() WHERE product_id = ? AND vendor_id = ? LIMIT 1');
$deleteStmt->bind_param('ii', $productId, $vendorId);
$ok = $deleteStmt->execute();
$deleteStmt->close();

if (!$ok) {
    apiError('DB_UPDATE_ERROR', 'Failed to delete product.', 500);
}

apiSuccess(['product_id' => $productId], 'Product deleted successfully.', 'PRODUCT_DELETED');

$conn->close();
?>
