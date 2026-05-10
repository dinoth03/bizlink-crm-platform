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
requireRateLimit($conn, $clientIp, 'vendor_update_by_ip', 30, 900);

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

require_once 'wishlist_helpers.php';

// Get current product state for price drop check
$currentProductStmt = $conn->prepare('SELECT price FROM products WHERE product_id = ? LIMIT 1');
$currentProductStmt->bind_param('i', $productId);
$currentProductStmt->execute();
$currentProductRow = $currentProductStmt->get_result()->fetch_assoc();
$currentProductStmt->close();

$oldPrice = (float)($currentProductRow['price'] ?? 0);

// Parse update fields
$updates = [];
$bindings = '';
$values = [];

if (isset($payload['product_name'])) {
    $productName = trim((string)$payload['product_name']);
    if ($productName === '' || strlen($productName) > 255) {
        apiError('VALIDATION_ERROR', 'Product name must be 1-255 characters.', 422);
    }
    $updates[] = 'product_name = ?';
    $bindings .= 's';
    $values[] = $productName;
}

if (isset($payload['description'])) {
    $description = trim((string)$payload['description']);
    $updates[] = 'description = ?';
    $bindings .= 's';
    $values[] = $description !== '' ? $description : null;
}

if (isset($payload['price'])) {
    $price = (float)$payload['price'];
    if ($price <= 0) {
        apiError('VALIDATION_ERROR', 'Price must be > 0.', 422);
    }
    $updates[] = 'price = ?';
    $bindings .= 'd';
    $values[] = $price;
}

if (isset($payload['stock_quantity'])) {
    $stock = (int)$payload['stock_quantity'];
    if ($stock < 0) {
        apiError('VALIDATION_ERROR', 'Stock cannot be negative.', 422);
    }
    $updates[] = 'stock_quantity = ?';
    $bindings .= 'i';
    $values[] = $stock;
}

if (isset($payload['category'])) {
    $category = trim((string)$payload['category']);
    if ($category === '' || strlen($category) > 100) {
        apiError('VALIDATION_ERROR', 'Category must be 1-100 characters.', 422);
    }
    $updates[] = 'category = ?';
    $bindings .= 's';
    $values[] = $category;
}

if (isset($payload['image_url'])) {
    $imageUrl = trim((string)$payload['image_url']);
    if ($imageUrl !== '' && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        apiError('VALIDATION_ERROR', 'Image URL must be valid.', 422);
    }
    $updates[] = 'primary_image_url = ?';
    $bindings .= 's';
    $values[] = $imageUrl !== '' ? $imageUrl : null;
}

if (isset($payload['is_active'])) {
    $isActive = (int)(bool)$payload['is_active'];
    $updates[] = 'is_active = ?';
    $bindings .= 'i';
    $values[] = $isActive;
}

if (empty($updates)) {
    apiError('VALIDATION_ERROR', 'No fields to update.', 422);
}

$updates[] = 'updated_at = NOW()';
$updateSql = 'UPDATE products SET ' . implode(', ', $updates) . ' WHERE product_id = ? AND vendor_id = ? LIMIT 1';

$updateStmt = $conn->prepare($updateSql);
if (!$updateStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare update statement.', 500);
}

$bindings .= 'ii';
$values[] = $productId;
$values[] = $vendorId;

$updateStmt->bind_param($bindings, ...$values);
$ok = $updateStmt->execute();
$updateStmt->close();

if (!$ok) {
    apiError('DB_UPDATE_ERROR', 'Failed to update product.', 500);
}

// Check for price drop alerts
if (isset($payload['price'])) {
    $newPrice = (float)$payload['price'];
    checkPriceDropAlerts($conn, $productId, $oldPrice, $newPrice);
}

apiSuccess(['product_id' => $productId], 'Product updated successfully.', 'PRODUCT_UPDATED');

$conn->close();
?>
