<?php
session_start();
require 'config.php';
require_once 'api_helpers.php';
require 'mail_service.php';

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
    $stmt = $conn->prepare(
        'SELECT c.user_id, u.email, u.full_name
         FROM customers c
         INNER JOIN users u ON u.user_id = c.user_id
         WHERE c.customer_id = ?
         LIMIT 1'
    );
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$customer) {
        apiError('CUSTOMER_NOT_FOUND', 'Customer not found.', 404);
    }

    $userId = (int)$customer['user_id'];
    $customerEmail = trim((string)($customer['email'] ?? ''));
    $customerName = trim((string)($customer['full_name'] ?? 'Customer'));

    // Update user account_status to 'active'
    $updateUser = $conn->prepare('UPDATE users SET account_status = "active", is_verified = 1 WHERE user_id = ? AND role = "customer"');
    $updateUser->bind_param('i', $userId);
    $updateUser->execute();
    $updateUser->close();

    // Notify customer in-app so they see approval after next login.
    $notifType = 'system';
    $title = 'Congratulations! Account approved';
    $message = 'Your customer account has been approved by admin. You can now log in and start using BizLink.';
    $entityType = 'customer';
    $priority = 'high';
    $actionUrl = '/customer/dashboard.html';
    $insertNotif = $conn->prepare(
        'INSERT INTO notifications (user_id, notification_type, title, message, related_entity_type, related_entity_id, priority, action_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if ($insertNotif) {
        $insertNotif->bind_param('issssiss', $userId, $notifType, $title, $message, $entityType, $customerId, $priority, $actionUrl);
        $insertNotif->execute();
        $insertNotif->close();
    }

    $conn->commit();

    $mailResult = ['success' => false];
    if ($customerEmail !== '' && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        $emailBody = '<p>Hi ' . htmlspecialchars($customerName, ENT_QUOTES) . ',</p>';
        $emailBody .= '<p>Your BizLink customer account has been approved by admin.</p>';
        $emailBody .= '<p>You can now sign in and access your dashboard.</p>';
        $mailResult = sendMail(
            $customerEmail,
            'Your BizLink account is now approved',
            $emailBody,
            'Your BizLink customer account has been approved. You can now sign in.'
        );
    }

    apiSuccess([
        'customer_id' => $customerId,
        'account_status' => 'active',
        'email_sent' => !empty($mailResult['success'])
    ], 'Customer approved successfully.', 'CUSTOMER_APPROVED', 200);

} catch (Throwable $e) {
    $conn->rollback();
    apiError('INTERNAL_ERROR', 'Failed to approve customer.', 500, [
        ['field' => 'server', 'message' => $e->getMessage()]
    ]);
}

$conn->close();
?>
