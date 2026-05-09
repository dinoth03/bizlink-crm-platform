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
requireRateLimit($conn, $clientIp, 'vendor_create_by_ip', 20, 900);

// Get vendor ID from user
$vendorStmt = $conn->prepare('SELECT vendor_id FROM vendors WHERE user_id = ? LIMIT 1');
if (!$vendorStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to get vendor ID.', 500);
}
$vendorStmt->bind_param('i', $userId);
$vendorStmt->execute();
$vendorRow = $vendorStmt->get_result()->fetch_assoc();
$vendorStmt->close();

if (!$vendorRow) {
    apiError('VENDOR_NOT_FOUND', 'Vendor profile not found for this user.', 404);
}

$vendorId = (int)$vendorRow['vendor_id'];

$payload = readJsonPayload();

$productName = trim((string)($payload['product_name'] ?? ''));
$description = trim((string)($payload['description'] ?? ''));
$price = (float)($payload['price'] ?? 0);
$stockQuantity = (int)($payload['stock_quantity'] ?? 0);
$category = trim((string)($payload['category'] ?? ''));
$imageUrl = trim((string)($payload['image_url'] ?? ''));

// Validation
if ($productName === '' || strlen($productName) > 255) {
    apiError('VALIDATION_ERROR', 'Product name is required and must be <= 255 characters.', 422);
}

if ($price <= 0) {
    apiError('VALIDATION_ERROR', 'Price must be greater than 0.', 422);
}

if ($stockQuantity < 0) {
    apiError('VALIDATION_ERROR', 'Stock quantity cannot be negative.', 422);
}

if ($category === '' || strlen($category) > 100) {
    apiError('VALIDATION_ERROR', 'Category is required and must be <= 100 characters.', 422);
}

if ($imageUrl !== '' && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
    apiError('VALIDATION_ERROR', 'Image URL must be a valid URL.', 422);
}

// Create product
$insertStmt = $conn->prepare(
    'INSERT INTO products (vendor_id, product_name, description, price, stock_quantity, category, primary_image_url, is_active, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())'
);

if (!$insertStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare insert statement.', 500);
}

$descriptionValue = $description !== '' ? $description : null;
$imageUrlValue = $imageUrl !== '' ? $imageUrl : null;

$insertStmt->bind_param('issdiss', $vendorId, $productName, $descriptionValue, $price, $stockQuantity, $category, $imageUrlValue);
$insertOk = $insertStmt->execute();
$productId = $insertStmt->insert_id;
$insertStmt->close();

if (!$insertOk) {
    apiError('DB_INSERT_ERROR', 'Failed to create product.', 500);
}

apiSuccess([
    'product_id' => $productId,
    'product_name' => $productName,
    'price' => $price,
    'stock_quantity' => $stockQuantity,
    'category' => $category
], 'Product created successfully.', 'PRODUCT_CREATED');

$conn->close();
?>
