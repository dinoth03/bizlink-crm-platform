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
    $stmt = $conn->prepare('SELECT user_id, business_email FROM vendors WHERE vendor_id = ? LIMIT 1');
    $stmt->bind_param('i', $vendorId);
    $stmt->execute();
    $vendor = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$vendor) {
        apiError('VENDOR_NOT_FOUND', 'Vendor not found.', 404);
    }

    $userId = (int)$vendor['user_id'];
    $adminId = (int)($_SESSION['user_id'] ?? 0);
    $businessEmail = $vendor['business_email'];

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

    $conn->commit();

    // Optional: Send rejection email to vendor
    if ($businessEmail && filter_var($businessEmail, FILTER_VALIDATE_EMAIL)) {
        require 'mail_service.php';
        $reasonText = $reason ?: 'Business details do not meet our verification requirements.';
        $emailBody = "<p>Your vendor application has been reviewed and rejected.</p>";
        $emailBody .= "<p><strong>Reason:</strong> " . htmlspecialchars($reasonText, ENT_QUOTES) . "</p>";
        $emailBody .= "<p>Please contact support for more information.</p>";
        // Note: sendMail() is optional here and failures won't break the API response
    }

    apiSuccess([
        'vendor_id' => $vendorId,
        'status' => 'rejected'
    ], 'Vendor rejected successfully.', 'VENDOR_REJECTED', 200);

} catch (Throwable $e) {
    $conn->rollback();
    apiError('INTERNAL_ERROR', 'Failed to reject vendor.', 500, [
        ['field' => 'server', 'message' => $e->getMessage()]
    ]);
}

$conn->close();
?>
