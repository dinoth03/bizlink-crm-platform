<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'config.php';
require_once 'api_helpers.php';
require 'auth_token_utils.php';
require 'mail_service.php';
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

function generateVerificationCode(): string {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
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

$role = strtolower(trim((string)($payload['role'] ?? 'admin')));
$email = strtolower(trim((string)($payload['email'] ?? '')));

if ($role !== 'admin') {
    apiError('VALIDATION_ERROR', 'Resend verification is available only for admin accounts.', 422, [
        ['field' => 'role', 'message' => 'role must be admin.']
    ]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiError('VALIDATION_ERROR', 'A valid admin email is required.', 422, [
        ['field' => 'email', 'message' => 'Invalid email format.']
    ]);
}

$clientIp = getClientIpAddress();
requireRateLimit($conn, $clientIp, 'resend_admin_code_by_ip', 10, 900);
requireRateLimit($conn, $email, 'resend_admin_code_by_email', 5, 900);

$userStmt = $conn->prepare('SELECT user_id, is_verified FROM users WHERE email = ? AND role = "admin" AND deleted_at IS NULL LIMIT 1');
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
        'already_verified' => true,
        'email_sent' => false
    ], 'Admin is already verified. You can log in now.', 'ADMIN_ALREADY_VERIFIED');
}

ensureEmailVerificationTokensTable($conn);

$verifyCode = generateVerificationCode();
$verifyCodeHash = hashAuthToken($verifyCode);
$verifyExpiryMinutes = 15;

$insertToken = $conn->prepare(
    'INSERT INTO email_verification_tokens (user_id, token_hash, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW())'
);
$insertToken->bind_param('isi', $userId, $verifyCodeHash, $verifyExpiryMinutes);
$insertToken->execute();
$insertToken->close();

$emailHtmlBody = getAdminVerificationCodeEmailHtml($verifyCode, 'Admin');
$mailResult = sendMail($email, 'Your BizLink Admin Verification Code', $emailHtmlBody, 'Your verification code is: ' . $verifyCode);

apiSuccess([
    'email_sent' => !empty($mailResult['success']),
    'verification_method' => 'code',
    'verification_code' => isLocalHostEnvironment() ? $verifyCode : null
], !empty($mailResult['success'])
    ? 'A new verification code has been sent to your admin email.'
    : 'Verification code created, but email sending failed. Check SMTP settings.',
'ADMIN_VERIFICATION_CODE_RESENT');

$conn->close();
?>