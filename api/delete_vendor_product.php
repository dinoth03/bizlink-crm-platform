<?php

require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';
require_once 'csrf_protection.php';
require_once 'rate_limiting.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST or DELETE.', 405);
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
requireRateLimit($conn, $clientIp, 'vendor_delete_product_by_ip', 50, 900);
requireRateLimit($conn, 'vendor:' . $userId, 'vendor_delete_product_by_user', 40, 900);

$payload = readJsonPayload();
$productId = (int)($payload['product_id'] ?? 0);

if ($productId <= 0) {
    apiError('VALIDATION_ERROR', 'product_id is required and must be positive.', 422, [
        ['field' => 'product_id', 'message' => 'product_id is required.']
    ]);
}

$vendorStmt = $conn->prepare('SELECT vendor_id FROM vendors WHERE user_id = ? LIMIT 1');
if (!$vendorStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare vendor query.', 500);
}
$vendorStmt->bind_param('i', $userId);
$vendorStmt->execute();
$vendor = $vendorStmt->get_result()->fetch_assoc();
$vendorStmt->close();

$vendorId = (int)($vendor['vendor_id'] ?? 0);
if ($vendorId <= 0) {
    apiError('VENDOR_NOT_FOUND', 'Vendor profile not found for this user.', 404);
}

$existsStmt = $conn->prepare('SELECT product_id, product_name FROM products WHERE product_id = ? AND vendor_id = ? LIMIT 1');
if (!$existsStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare product lookup query.', 500);
}
$existsStmt->bind_param('ii', $productId, $vendorId);
$existsStmt->execute();
$product = $existsStmt->get_result()->fetch_assoc();
$existsStmt->close();

if (!$product) {
    apiError('PRODUCT_NOT_FOUND', 'Product not found for this vendor.', 404);
}

$deleteStmt = $conn->prepare('DELETE FROM products WHERE product_id = ? AND vendor_id = ? LIMIT 1');
if (!$deleteStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare delete query.', 500);
}
$deleteStmt->bind_param('ii', $productId, $vendorId);

try {
    $deleteStmt->execute();
    $affected = $deleteStmt->affected_rows;
    $deleteStmt->close();

    if ($affected <= 0) {
        apiError('DELETE_FAILED', 'Product could not be deleted.', 500);
    }
} catch (Throwable $e) {
    $deleteStmt->close();
    apiError('DELETE_BLOCKED', 'Product cannot be deleted because it is linked to existing records.', 409, [
        ['field' => 'product_id', 'message' => $e->getMessage()]
    ]);
}

apiSuccess([
    'product_id' => $productId,
    'product_name' => (string)$product['product_name']
], 'Product deleted successfully.', 'PRODUCT_DELETED', 200);

$conn->close();
