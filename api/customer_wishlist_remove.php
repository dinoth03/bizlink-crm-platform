<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';

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

$stmt = $conn->prepare("DELETE FROM wishlists WHERE customer_id = ? AND product_id = ?");
$stmt->bind_param("ii", $customerId, $productId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    apiSuccess(['product_id' => $productId], 'Product removed from wishlist.');
} else {
    apiError('NOT_FOUND', 'Item not found in wishlist.', 404);
}

$stmt->close();
$conn->close();
