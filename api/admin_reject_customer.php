<?php
session_start();
require 'config.php';
require_once 'api_helpers.php';

// Only admins can access
if (($_SESSION['role'] ?? '') !== 'admin') {
    apiError('UNAUTHORIZED', 'Only admins can access this endpoint.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST or PUT.', 405);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    apiError('INVALID_JSON', 'Invalid JSON payload.', 400);
}

$customerId = (int)($payload['customer_id'] ?? 0);
if ($customerId <= 0) {
    apiError('VALIDATION_ERROR', 'customer_id is required and must be positive.', 422, [
        ['field' => 'customer_id', 'message' => 'customer_id is required.']
    ]);
}

$conn->begin_transaction();

try {
    // Get customer and user info
    $stmt = $conn->prepare('SELECT user_id FROM customers WHERE customer_id = ? LIMIT 1');
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$customer) {
        apiError('CUSTOMER_NOT_FOUND', 'Customer not found.', 404);
    }

    $userId = (int)$customer['user_id'];

    // Update user account_status to 'suspended'
    $updateUser = $conn->prepare('UPDATE users SET account_status = "suspended" WHERE user_id = ? AND role = "customer"');
    $updateUser->bind_param('i', $userId);
    $updateUser->execute();
    $updateUser->close();

    $conn->commit();

    apiSuccess([
        'customer_id' => $customerId,
        'account_status' => 'suspended'
    ], 'Customer rejected successfully.', 'CUSTOMER_REJECTED', 200);

} catch (Throwable $e) {
    $conn->rollback();
    apiError('INTERNAL_ERROR', 'Failed to reject customer.', 500, [
        ['field' => 'server', 'message' => $e->getMessage()]
    ]);
}

$conn->close();
?>
