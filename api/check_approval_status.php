<?php
session_start();
require 'config.php';
require_once 'api_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    apiError('INVALID_JSON', 'Invalid JSON payload.', 400);
}

$role = strtolower(trim((string)($payload['role'] ?? '')));
$email = strtolower(trim((string)($payload['email'] ?? '')));

if (!in_array($role, ['vendor', 'customer'], true)) {
    apiError('VALIDATION_ERROR', 'Role must be vendor or customer.', 422, [
        ['field' => 'role', 'message' => 'Allowed values are vendor or customer.']
    ]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiError('VALIDATION_ERROR', 'Please provide a valid email address.', 422, [
        ['field' => 'email', 'message' => 'Invalid email format.']
    ]);
}

$stmt = $conn->prepare(
    'SELECT user_id, role, account_status, is_verified, created_at
     FROM users
     WHERE email = ? AND role = ? AND deleted_at IS NULL
     LIMIT 1'
);
$stmt->bind_param('ss', $email, $role);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    apiError('ACCOUNT_NOT_FOUND', 'No account found for this email and role.', 404);
}

$accountStatus = strtolower((string)($user['account_status'] ?? ''));
$approvalStatus = 'pending';
$loginAllowed = false;
$message = 'Your account is waiting for admin review.';

if ($accountStatus === 'active') {
    $approvalStatus = 'approved';
    $loginAllowed = true;
    $message = 'Your account is approved. You can sign in now.';
} elseif ($accountStatus === 'suspended') {
    $approvalStatus = 'rejected';
    $message = 'Your account application was rejected or suspended. Contact support for help.';
} elseif (in_array($accountStatus, ['inactive', 'pending_verification'], true)) {
    $approvalStatus = 'pending';
    $message = 'Your account is still pending admin approval.';
}

apiSuccess([
    'user_id' => (int)$user['user_id'],
    'role' => $role,
    'email' => $email,
    'account_status' => $accountStatus,
    'approval_status' => $approvalStatus,
    'is_verified' => (int)($user['is_verified'] ?? 0),
    'login_allowed' => $loginAllowed,
    'created_at' => (string)($user['created_at'] ?? ''),
    'status_message' => $message
], $message, 'APPROVAL_STATUS');

$conn->close();
?>