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
$reason = trim((string)($payload['reason'] ?? ''));
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
    $reasonText = $reason !== '' ? $reason : 'Your account details did not meet our review requirements.';

    // Update user account_status to 'suspended'
    $updateUser = $conn->prepare('UPDATE users SET account_status = "suspended" WHERE user_id = ? AND role = "customer"');
    $updateUser->bind_param('i', $userId);
    $updateUser->execute();
    $updateUser->close();

    // Notify customer in-app about rejection outcome.
    $notifType = 'system';
    $title = 'Customer account application rejected';
    $message = 'Your customer account application was rejected by admin. Reason: ' . $reasonText;
    $entityType = 'customer';
    $priority = 'high';
    $actionUrl = '/pages/index.html';
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
        $emailBody .= '<p>Your BizLink customer account application has been reviewed and rejected.</p>';
        $emailBody .= '<p><strong>Reason:</strong> ' . htmlspecialchars($reasonText, ENT_QUOTES) . '</p>';
        $emailBody .= '<p>Please contact support if you need help.</p>';
        $mailResult = sendMail(
            $customerEmail,
            'Your BizLink customer application update',
            $emailBody,
            'Your BizLink customer account application was rejected. Reason: ' . $reasonText
        );
    }

    apiSuccess([
        'customer_id' => $customerId,
        'account_status' => 'suspended',
        'reason' => $reasonText,
        'email_sent' => !empty($mailResult['success'])
    ], 'Customer rejected successfully.', 'CUSTOMER_REJECTED', 200);

} catch (Throwable $e) {
    $conn->rollback();
    apiError('INTERNAL_ERROR', 'Failed to reject customer.', 500, [
        ['field' => 'server', 'message' => $e->getMessage()]
    ]);
}

$conn->close();
?>
