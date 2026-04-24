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

$vendorId = (int)($payload['vendor_id'] ?? 0);
if ($vendorId <= 0) {
    apiError('VALIDATION_ERROR', 'vendor_id is required and must be positive.', 422, [
        ['field' => 'vendor_id', 'message' => 'vendor_id is required.']
    ]);
}

$conn->begin_transaction();

try {
    // Get vendor and user info
    $stmt = $conn->prepare(
        'SELECT v.user_id, v.business_name, u.email, u.full_name
         FROM vendors v
         INNER JOIN users u ON u.user_id = v.user_id
         WHERE v.vendor_id = ?
         LIMIT 1'
    );
    $stmt->bind_param('i', $vendorId);
    $stmt->execute();
    $vendor = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$vendor) {
        apiError('VENDOR_NOT_FOUND', 'Vendor not found.', 404);
    }

    $userId = (int)$vendor['user_id'];
    $vendorEmail = trim((string)($vendor['email'] ?? ''));
    $vendorName = trim((string)($vendor['full_name'] ?? $vendor['business_name'] ?? 'Vendor'));
    $businessName = trim((string)($vendor['business_name'] ?? 'your business'));
    $adminId = (int)($_SESSION['user_id'] ?? 0);

    // Update vendor verification status to 'verified'
    $updateVendor = $conn->prepare(
        'UPDATE vendors SET verification_status = "verified", verified_by = ?, verification_date = NOW() WHERE vendor_id = ?'
    );
    $updateVendor->bind_param('ii', $adminId, $vendorId);
    $updateVendor->execute();
    $updateVendor->close();

    // Update user account_status to 'active'
    $updateUser = $conn->prepare('UPDATE users SET account_status = "active", is_verified = 1 WHERE user_id = ?');
    $updateUser->bind_param('i', $userId);
    $updateUser->execute();
    $updateUser->close();

    // Notify vendor so the next login can show a one-time congratulations message.
    $approvalTitle = 'Congratulations! Vendor approved';
    $approvalMessage = 'Your vendor account has been approved by admin. You can now log in and access your vendor dashboard.';
    $entityType = 'vendor';
    $priority = 'high';
    $actionUrl = '/vendor/vendorpanel.html';
    $notifType = 'system';
    $insertNotif = $conn->prepare(
        'INSERT INTO notifications (user_id, notification_type, title, message, related_entity_type, related_entity_id, priority, action_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if ($insertNotif) {
        $insertNotif->bind_param('issssiss', $userId, $notifType, $approvalTitle, $approvalMessage, $entityType, $vendorId, $priority, $actionUrl);
        $insertNotif->execute();
        $insertNotif->close();
    }

    $conn->commit();

    $mailResult = ['success' => false];
    if ($vendorEmail !== '' && filter_var($vendorEmail, FILTER_VALIDATE_EMAIL)) {
        $emailBody = '<p>Hi ' . htmlspecialchars($vendorName, ENT_QUOTES) . ',</p>';
        $emailBody .= '<p>Great news! Your vendor account for <strong>' . htmlspecialchars($businessName, ENT_QUOTES) . '</strong> has been approved.</p>';
        $emailBody .= '<p>You can now log in and access your vendor dashboard.</p>';
        $mailResult = sendMail(
            $vendorEmail,
            'Your BizLink vendor account is approved',
            $emailBody,
            'Your BizLink vendor account has been approved. You can now log in.'
        );
    }

    apiSuccess([
        'vendor_id' => $vendorId,
        'status' => 'verified',
        'account_status' => 'active',
        'email_sent' => !empty($mailResult['success'])
    ], 'Vendor approved successfully.', 'VENDOR_APPROVED', 200);

} catch (Throwable $e) {
    $conn->rollback();
    apiError('INTERNAL_ERROR', 'Failed to approve vendor.', 500, [
        ['field' => 'server', 'message' => $e->getMessage()]
    ]);
}

$conn->close();
?>
