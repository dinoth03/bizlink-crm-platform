<?php
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

// CSRF Protection
if (CSRF_ENABLED) {
    $csrfToken = getCsrfTokenFromRequest();
    if (!validateCsrfToken($conn, $csrfToken, null, session_id())) {
        logCsrfFailure('auth_reset_password');
        apiError('CSRF_VALIDATION_FAILED', 'Invalid or missing CSRF token.', 403);
    }
}

$token = trim((string)($payload['token'] ?? ''));
$newPassword = (string)($payload['password'] ?? '');

if ($token === '' || strlen($newPassword) < 8) {
    apiError('VALIDATION_ERROR', 'A valid token and password (min 8 chars) are required.', 422, [
        ['field' => 'token', 'message' => 'token is required.'],
        ['field' => 'password', 'message' => 'password must be at least 8 characters.']
    ]);
}

// Rate Limiting - per IP address for password reset
$clientIp = getClientIpAddress();
requireRateLimit($conn, $clientIp, 'reset_password_by_ip', 5, 900); // 5 per 15 min per IP

$tokenHash = hashAuthToken($token);

$tokenStmt = $conn->prepare(
    'SELECT reset_id, user_id, expires_at, used_at
     FROM password_reset_tokens
     WHERE token_hash = ?
     LIMIT 1'
);
$tokenStmt->bind_param('s', $tokenHash);
$tokenStmt->execute();
$record = $tokenStmt->get_result()->fetch_assoc();
$tokenStmt->close();

if (!$record) {
    apiError('INVALID_RESET_TOKEN', 'Invalid reset token.', 400);
}

if (!empty($record['used_at'])) {
    apiError('RESET_TOKEN_USED', 'Reset token already used.', 400);
}

if (strtotime((string)$record['expires_at']) < time()) {
    apiError('RESET_TOKEN_EXPIRED', 'Reset token expired. Request a new one.', 400);
}

$conn->begin_transaction();

try {
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $pwdStmt = $conn->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
    $pwdStmt->bind_param('si', $newPasswordHash, $record['user_id']);
    $pwdStmt->execute();
    $pwdStmt->close();

    $useStmt = $conn->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE reset_id = ?');
    $useStmt->bind_param('i', $record['reset_id']);
    $useStmt->execute();
    $useStmt->close();

    $conn->commit();

    apiSuccess(null, 'Password reset successful. Please log in.', 'PASSWORD_RESET_SUCCESS');
} catch (Throwable $error) {
    $conn->rollback();
    apiError('INTERNAL_ERROR', 'Password reset failed.', 500, [
        ['field' => 'server', 'message' => $error->getMessage()]
    ]);
}

$conn->close();
?>
