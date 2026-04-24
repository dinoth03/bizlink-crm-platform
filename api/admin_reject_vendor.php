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
$reason = trim((string)($payload['reason'] ?? ''));

if ($vendorId <= 0) {
    apiError('VALIDATION_ERROR', 'vendor_id is required and must be positive.', 422, [
        ['field' => 'vendor_id', 'message' => 'vendor_id is required.']
    ]);
}

$conn->begin_transaction();

try {
    // Get vendor and user info
    $stmt = $conn->prepare(
        'SELECT v.user_id, v.business_email, v.business_name, u.full_name
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
    $adminId = (int)($_SESSION['user_id'] ?? 0);
    $businessEmail = trim((string)($vendor['business_email'] ?? ''));
    $vendorName = trim((string)($vendor['full_name'] ?? $vendor['business_name'] ?? 'Vendor'));
    $reasonText = $reason ?: 'Business details do not meet our verification requirements.';

    // Update vendor verification status to 'rejected'
    $updateVendor = $conn->prepare(
        'UPDATE vendors SET verification_status = "rejected", verified_by = ?, verification_date = NOW() WHERE vendor_id = ?'
    );
    $updateVendor->bind_param('ii', $adminId, $vendorId);
    $updateVendor->execute();
    $updateVendor->close();

    // Update user account_status to 'suspended'
    $updateUser = $conn->prepare('UPDATE users SET account_status = "suspended" WHERE user_id = ?');
    $updateUser->bind_param('i', $userId);
    $updateUser->execute();
    $updateUser->close();

    // Notify vendor in-app about rejection details.
    $notifType = 'system';
    $title = 'Vendor application rejected';
    $message = 'Your vendor application was rejected by admin. Reason: ' . $reasonText;
    $entityType = 'vendor';
    $priority = 'high';
    $actionUrl = '/pages/index.html';
    $insertNotif = $conn->prepare(
        'INSERT INTO notifications (user_id, notification_type, title, message, related_entity_type, related_entity_id, priority, action_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if ($insertNotif) {
        $insertNotif->bind_param('issssiss', $userId, $notifType, $title, $message, $entityType, $vendorId, $priority, $actionUrl);
        $insertNotif->execute();
        $insertNotif->close();
    }

    $conn->commit();

    $mailResult = ['success' => false];
    if ($businessEmail && filter_var($businessEmail, FILTER_VALIDATE_EMAIL)) {
        $emailBody = '<p>Hi ' . htmlspecialchars($vendorName, ENT_QUOTES) . ',</p>';
        $emailBody .= '<p>Your vendor application has been reviewed and rejected.</p>';
        $emailBody .= '<p><strong>Reason:</strong> ' . htmlspecialchars($reasonText, ENT_QUOTES) . '</p>';
        $emailBody .= '<p>Please contact support for more information.</p>';
        $mailResult = sendMail(
            $businessEmail,
            'Your BizLink vendor application update',
            $emailBody,
            'Your vendor application was rejected. Reason: ' . $reasonText
        );
    }

    apiSuccess([
        'vendor_id' => $vendorId,
        'status' => 'rejected',
        'reason' => $reasonText,
        'email_sent' => !empty($mailResult['success'])
    ], 'Vendor rejected successfully.', 'VENDOR_REJECTED', 200);

} catch (Throwable $e) {
    $conn->rollback();
    apiError('INTERNAL_ERROR', 'Failed to reject vendor.', 500, [
        ['field' => 'server', 'message' => $e->getMessage()]
    ]);
}

$conn->close();
?>
