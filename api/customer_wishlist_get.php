<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';
require_once 'wishlist_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('METHOD_NOT_ALLOWED', 'Use GET.', 405);
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

$wishlist = getCustomerWishlist($conn, $customerId);

apiSuccess($wishlist, 'Wishlist retrieved successfully.');

$conn->close();
