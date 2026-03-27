<?php
session_start();
require 'config.php';
require_once 'api_helpers.php';
require 'auth_token_utils.php';
require 'csrf_protection.php';
require 'rate_limiting.php';
require 'secure_logging.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    apiError('INVALID_JSON', 'Invalid JSON payload.', 400);
}

if (CSRF_ENABLED) {
    $csrfToken = getCsrfTokenFromRequest();
    if (!validateCsrfToken($conn, $csrfToken, null, session_id())) {
        logCsrfFailure('auth_verify_admin_code');
        apiError('CSRF_VALIDATION_FAILED', 'Invalid or missing CSRF token.', 403);
    }
}

$email = strtolower(trim((string)($payload['email'] ?? '')));
$code = trim((string)($payload['code'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[0-9]{6}$/', $code)) {
    apiError('VALIDATION_ERROR', 'Valid email and 6-digit code are required.', 422, [
        ['field' => 'email', 'message' => 'Valid email is required.'],
        ['field' => 'code', 'message' => 'Code must be a 6-digit number.']
    ]);
}

$clientIp = getClientIpAddress();
requireRateLimit($conn, $clientIp, 'verify_admin_code_by_ip', 20, 900);
requireRateLimit($conn, $email, 'verify_admin_code_by_email', 8, 900);

$userStmt = $conn->prepare('SELECT user_id, role, is_verified, account_status FROM users WHERE email = ? AND role = "admin" AND deleted_at IS NULL LIMIT 1');
$userStmt->bind_param('s', $email);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$user) {
    apiError('ADMIN_NOT_FOUND', 'Admin account not found.', 404);
}

if ((int)$user['is_verified'] === 1 && strtolower((string)$user['account_status']) === 'active') {
    apiSuccess(null, 'Admin account is already verified.', 'ADMIN_ALREADY_VERIFIED');
}

$codeHash = hashAuthToken($code);
$verifyStmt = $conn->prepare(
    'SELECT verification_id, expires_at, used_at
     FROM email_verification_tokens
     WHERE user_id = ? AND token_hash = ?
     ORDER BY verification_id DESC
     LIMIT 1'
);
$verifyStmt->bind_param('is', $user['user_id'], $codeHash);
$verifyStmt->execute();
$verification = $verifyStmt->get_result()->fetch_assoc();
$verifyStmt->close();

if (!$verification) {
    apiError('INVALID_VERIFICATION_CODE', 'Invalid verification code.', 400);
}

if (!empty($verification['used_at'])) {
    apiError('VERIFICATION_CODE_USED', 'This verification code has already been used.', 400);
}

if (strtotime((string)$verification['expires_at']) < time()) {
    apiError('VERIFICATION_CODE_EXPIRED', 'Verification code expired. Request a new code.', 400);
}

$conn->begin_transaction();

try {
    $activateStmt = $conn->prepare('UPDATE users SET is_verified = 1, account_status = "active" WHERE user_id = ? AND role = "admin"');
    $activateStmt->bind_param('i', $user['user_id']);
    $activateStmt->execute();
    $activateStmt->close();

    $markUsedStmt = $conn->prepare('UPDATE email_verification_tokens SET used_at = NOW() WHERE verification_id = ?');
    $markUsedStmt->bind_param('i', $verification['verification_id']);
    $markUsedStmt->execute();
    $markUsedStmt->close();

    $conn->commit();

    apiSuccess(null, 'Admin email verified successfully. You can now log in.', 'ADMIN_EMAIL_VERIFIED');
} catch (Throwable $e) {
    $conn->rollback();
    apiError('INTERNAL_ERROR', 'Failed to verify admin code.', 500, [
        ['field' => 'server', 'message' => $e->getMessage()]
    ]);
}

$conn->close();
?>
