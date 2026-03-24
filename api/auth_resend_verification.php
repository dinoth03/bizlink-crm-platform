<?php
require 'config.php';
require 'auth_token_utils.php';
require 'mail_service.php';
require 'csrf_protection.php';
require 'rate_limiting.php';
require 'secure_logging.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    $conn->close();
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON payload.'
    ]);
    $conn->close();
    exit;
}

// CSRF Protection
if (CSRF_ENABLED) {
    $csrfToken = getCsrfTokenFromRequest();
    if (!validateCsrfToken($conn, $csrfToken, null, session_id())) {
        logCsrfFailure('auth_resend_verification');
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'code' => 'csrf_validation_failed',
            'message' => 'Invalid or missing CSRF token.'
        ]);
        $conn->close();
        exit;
    }
}

$email = strtolower(trim((string)($payload['email'] ?? '')));
$role = strtolower(trim((string)($payload['role'] ?? '')));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $role === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Valid email and role are required.'
    ]);
    $conn->close();
    exit;
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
    echo json_encode([
        'success' => true,
        'message' => 'If your account exists, a verification email has been sent.'
    ]);
    $conn->close();
    exit;
}

if ((int)$user['is_verified'] === 1) {
    echo json_encode([
        'success' => true,
        'message' => 'Your email is already verified.'
    ]);
    $conn->close();
    exit;
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

echo json_encode([
    'success' => true,
    'message' => 'Verification link generated.',
    'verification_link' => isLocalHostEnvironment() ? $verificationLink : null,
    'email_sent' => $mailResult['success']
]);

$conn->close();
?>
