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
$custStmt = $conn->prepare('SELECT customer_id, customer_name FROM customers WHERE user_id = ? LIMIT 1');
$custStmt->bind_param('i', $userId);
$custStmt->execute();
$custRow = $custStmt->get_result()->fetch_assoc();
$custStmt->close();

if (!$custRow) {
    apiError('CUSTOMER_NOT_FOUND', 'Customer profile not found.', 404);
}

$customerId = (int)$custRow['customer_id'];
$customerName = $custRow['customer_name'] ?: 'A BizLink Customer';

// Generate a simple share URL (In a real app, this might use a token)
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
// For development, we'll point to a public wishlist view page
$shareUrl = $baseUrl . "/customer/public-wishlist.html?cid=" . $customerId;

apiSuccess([
    'share_url' => $shareUrl,
    'customer_name' => $customerName
], 'Share link generated successfully.');

$conn->close();
