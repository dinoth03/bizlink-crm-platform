<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';
require_once 'csrf_protection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Use POST.', 405);
}

requireAuth(['customer']);

$currentUser = getCurrentUser();
$userId = (int)$currentUser['user_id'];

// Get customer ID
$custStmt = $conn->prepare('SELECT customer_id FROM customers WHERE user_id = ? LIMIT 1');
$custStmt->bind_param('i', $userId);
$custStmt->execute();
$custRow = $custStmt->get_result()->fetch_assoc();
$custStmt->close();

if (!$custRow) {
    apiError('CUSTOMER_NOT_FOUND', 'Customer profile not found.', 404);
}

$customerId = (int)$custRow['customer_id'];

$payload = readJsonPayload();
$productId = (int)($payload['product_id'] ?? 0);

if ($productId <= 0) {
    apiError('VALIDATION_ERROR', 'Product ID is required.', 422);
}

// Check if product exists
$prodStmt = $conn->prepare('SELECT product_id FROM products WHERE product_id = ? LIMIT 1');
$prodStmt->bind_param('i', $productId);
$prodStmt->execute();
if (!$prodStmt->get_result()->fetch_assoc()) {
    apiError('NOT_FOUND', 'Product not found.', 404);
}
$prodStmt->close();

// Insert or ignore (unique constraint handles duplicates)
$stmt = $conn->prepare("INSERT IGNORE INTO wishlists (customer_id, product_id) VALUES (?, ?)");
$stmt->bind_param("ii", $customerId, $productId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    apiSuccess(['product_id' => $productId], 'Product added to wishlist.');
} else {
    // If it already exists, still return success but maybe with a different message
    apiSuccess(['product_id' => $productId], 'Product is already in your wishlist.');
}

$stmt->close();
$conn->close();
