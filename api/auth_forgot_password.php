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
        logCsrfFailure('auth_forgot_password');
        apiError('CSRF_VALIDATION_FAILED', 'Invalid or missing CSRF token.', 403);
    }
}

$email = strtolower(trim((string)($payload['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiError('VALIDATION_ERROR', 'Please provide a valid email.', 422, [
        ['field' => 'email', 'message' => 'Invalid email format.']
    ]);
}

// Rate Limiting - per IP address for forgot password
$clientIp = getClientIpAddress();
requireRateLimit($conn, $clientIp, 'forgot_password_by_ip', 10, 900); // 10 per 15 min per IP
requireRateLimit($conn, $email, 'forgot_password_by_email', 3, 900); // 3 per 15 min per email

$userStmt = $conn->prepare('SELECT user_id, email FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1');
$userStmt->bind_param('s', $email);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$user) {
    apiSuccess(null, 'If your account exists, a reset link has been generated.', 'PASSWORD_RESET_REQUEST_ACCEPTED');
}

$invalidateStmt = $conn->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
$invalidateStmt->bind_param('i', $user['user_id']);
$invalidateStmt->execute();
$invalidateStmt->close();

$resetToken = generateSecureToken();
$resetTokenHash = hashAuthToken($resetToken);
$resetExpiryMinutes = 30;

$insertStmt = $conn->prepare(
    'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW())'
);
$insertStmt->bind_param('isi', $user['user_id'], $resetTokenHash, $resetExpiryMinutes);
$insertStmt->execute();
$insertStmt->close();

$authPagePath = isLocalHostEnvironment() ? 'bizlink-crm-platform/pages/index.html' : 'pages/index.html';
$resetLink = buildPublicUrl($authPagePath, ['reset_token' => $resetToken]);

// Send password reset email
$userStmtForName = $conn->prepare('SELECT full_name FROM users WHERE user_id = ? LIMIT 1');
$userStmtForName->bind_param('i', $user['user_id']);
$userStmtForName->execute();
$userRecord = $userStmtForName->get_result()->fetch_assoc();
$userStmtForName->close();
$userName = ($userRecord && $userRecord['full_name']) ? $userRecord['full_name'] : 'User';

$emailHtmlBody = getPasswordResetEmailHtml($resetLink, $userName);
$mailResult = sendMail($user['email'], 'Reset Your BizLink CRM Password', $emailHtmlBody);

apiSuccess([
    'reset_link' => isLocalHostEnvironment() ? $resetLink : null,
    'email_sent' => $mailResult['success']
], 'If your account exists, a reset link has been generated.', 'PASSWORD_RESET_REQUEST_ACCEPTED');

$conn->close();
?>
