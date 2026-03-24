<?php
require 'config.php';
require_once 'api_helpers.php';
require 'auth_token_utils.php';
require 'mail_service.php';
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
        logCsrfFailure('auth_resend_verification');
        apiError('CSRF_VALIDATION_FAILED', 'Invalid or missing CSRF token.', 403);
    }
}

$email = strtolower(trim((string)($payload['email'] ?? '')));
$role = strtolower(trim((string)($payload['role'] ?? '')));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $role === '') {
    apiError('VALIDATION_ERROR', 'Valid email and role are required.', 422, [
        ['field' => 'email', 'message' => 'Valid email is required.'],
        ['field' => 'role', 'message' => 'role is required.']
    ]);
}

// Rate Limiting - per IP address and per email
$clientIp = getClientIpAddress();
requireRateLimit($conn, $clientIp, 'resend_verify_by_ip', 10, 900); // 10 per 15 min per IP
requireRateLimit($conn, $email, 'resend_verify_by_email', 5, 900); // 5 per 15 min per email

$userStmt = $conn->prepare('SELECT user_id, is_verified FROM users WHERE email = ? AND role = ? AND deleted_at IS NULL LIMIT 1');
$userStmt->bind_param('ss', $email, $role);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$user) {
    apiSuccess(null, 'If your account exists, a verification email has been sent.', 'VERIFICATION_RESEND_ACCEPTED');
}

if ((int)$user['is_verified'] === 1) {
    apiSuccess(null, 'Your email is already verified.', 'EMAIL_ALREADY_VERIFIED');
}

$invalidateStmt = $conn->prepare('UPDATE email_verification_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
$invalidateStmt->bind_param('i', $user['user_id']);
$invalidateStmt->execute();
$invalidateStmt->close();

$verifyToken = generateSecureToken();
$verifyTokenHash = hashAuthToken($verifyToken);
$verifyExpiryMinutes = 1440;

$insertStmt = $conn->prepare(
    'INSERT INTO email_verification_tokens (user_id, token_hash, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW())'
);
$insertStmt->bind_param('isi', $user['user_id'], $verifyTokenHash, $verifyExpiryMinutes);
$insertStmt->execute();
$insertStmt->close();

$authPagePath = isLocalHostEnvironment() ? 'bizlink-crm-platform/pages/index.html' : 'pages/index.html';
$verificationLink = buildPublicUrl($authPagePath, ['verify_token' => $verifyToken]);

// Send verification email
$userStmtForName = $conn->prepare('SELECT full_name FROM users WHERE user_id = ? LIMIT 1');
$userStmtForName->bind_param('i', $user['user_id']);
$userStmtForName->execute();
$userRecord = $userStmtForName->get_result()->fetch_assoc();
$userStmtForName->close();
$userName = ($userRecord && $userRecord['full_name']) ? $userRecord['full_name'] : 'User';

$emailHtmlBody = getVerificationEmailHtml($verificationLink, $userName);
$mailResult = sendMail($email, 'Verify Your BizLink CRM Email Address', $emailHtmlBody);

apiSuccess([
    'verification_link' => isLocalHostEnvironment() ? $verificationLink : null,
    'email_sent' => $mailResult['success']
], 'Verification link generated.', 'VERIFICATION_LINK_GENERATED');

$conn->close();
?>
