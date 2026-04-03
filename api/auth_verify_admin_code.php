<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'config.php';
require_once 'api_helpers.php';
require 'auth_token_utils.php';
require 'csrf_protection.php';
require 'rate_limiting.php';

function ensureEmailVerificationTokensTable(mysqli $conn): void {
    $sql = 'CREATE TABLE IF NOT EXISTS email_verification_tokens (
        token_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_token_hash (token_hash),
        INDEX idx_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    $conn->query($sql);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

if (CSRF_ENABLED) {
    $csrfToken = getCsrfTokenFromRequest();
    if (!validateCsrfToken($conn, $csrfToken, null, session_id())) {
        apiError('CSRF_VALIDATION_FAILED', 'Invalid or missing CSRF token.', 403);
    }
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    apiError('INVALID_JSON', 'Invalid JSON payload.', 400);
}

$email = strtolower(trim((string)($payload['email'] ?? '')));
$code = trim((string)($payload['code'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiError('VALIDATION_ERROR', 'A valid admin email is required.', 422, [
        ['field' => 'email', 'message' => 'Invalid email format.']
    ]);
}

if (!preg_match('/^\d{6}$/', $code)) {
    apiError('VALIDATION_ERROR', 'Verification code must be a 6-digit number.', 422, [
        ['field' => 'code', 'message' => 'Use the 6-digit OTP sent to your email.']
    ]);
}

$clientIp = getClientIpAddress();
requireRateLimit($conn, $clientIp, 'verify_admin_code_by_ip', 20, 900);
requireRateLimit($conn, $email, 'verify_admin_code_by_email', 8, 900);

ensureEmailVerificationTokensTable($conn);

$userStmt = $conn->prepare('SELECT user_id, role, is_verified FROM users WHERE email = ? AND role = "admin" AND deleted_at IS NULL LIMIT 1');
$userStmt->bind_param('s', $email);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$user) {
    apiError('ADMIN_NOT_FOUND', 'Admin account not found for this email.', 404);
}

$userId = (int)$user['user_id'];
if ((int)($user['is_verified'] ?? 0) === 1) {
    apiSuccess([
        'user_id' => $userId,
        'already_verified' => true
    ], 'Admin already verified. You can log in now.', 'ADMIN_ALREADY_VERIFIED');
}

$tokenHash = hashAuthToken($code);
$tokenStmt = $conn->prepare(
    'SELECT token_id, expires_at
     FROM email_verification_tokens
     WHERE user_id = ? AND token_hash = ? AND used_at IS NULL
     ORDER BY created_at DESC
     LIMIT 1'
);
$tokenStmt->bind_param('is', $userId, $tokenHash);
$tokenStmt->execute();
$token = $tokenStmt->get_result()->fetch_assoc();
$tokenStmt->close();

if (!$token) {
    apiError('INVALID_VERIFICATION_CODE', 'Invalid verification code.', 400);
}

$expiresAt = strtotime((string)$token['expires_at']);
if ($expiresAt === false || $expiresAt < time()) {
    apiError('VERIFICATION_CODE_EXPIRED', 'Verification code has expired. Please request a new code.', 400);
}

$conn->begin_transaction();
try {
    $updateUser = $conn->prepare('UPDATE users SET is_verified = 1, account_status = "active" WHERE user_id = ?');
    $updateUser->bind_param('i', $userId);
    $updateUser->execute();
    $updateUser->close();

    $tokenId = (int)$token['token_id'];
    $updateToken = $conn->prepare('UPDATE email_verification_tokens SET used_at = NOW() WHERE token_id = ?');
    $updateToken->bind_param('i', $tokenId);
    $updateToken->execute();
    $updateToken->close();

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    apiError('INTERNAL_ERROR', 'Failed to verify admin code.', 500, [
        ['field' => 'server', 'message' => $e->getMessage()]
    ]);
}

apiSuccess([
    'user_id' => $userId,
    'verified' => true
], 'Admin verified successfully. Please log in again.', 'ADMIN_VERIFIED');

$conn->close();
?>