<?php
require 'config.php';
require_once 'api_helpers.php';
require_once 'wishlist_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('METHOD_NOT_ALLOWED', 'Use GET.', 405);
}

$customerId = (int)($_GET['customer_id'] ?? 0);

if ($customerId <= 0) {
    apiError('VALIDATION_ERROR', 'Customer ID is required.', 422);
}

// Check if customer exists
$custStmt = $conn->prepare('SELECT customer_name FROM customers WHERE customer_id = ? LIMIT 1');
$custStmt->bind_param('i', $customerId);
$custStmt->execute();
$custRow = $custStmt->get_result()->fetch_assoc();
$custStmt->close();

if (!$custRow) {
    apiError('NOT_FOUND', 'Wishlist not found.', 404);
}

$wishlist = getCustomerWishlist($conn, $customerId);

apiSuccess([
    'customer_name' => $custRow['customer_name'] ?: 'Customer',
    'items' => $wishlist
], 'Public wishlist retrieved successfully.');

$conn->close();
