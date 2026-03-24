<?php
require 'config.php';
require 'auth_token_utils.php';
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
        logCsrfFailure('auth_reset_password');
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

$token = trim((string)($payload['token'] ?? ''));
$newPassword = (string)($payload['password'] ?? '');

if ($token === '' || strlen($newPassword) < 8) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'A valid token and password (min 8 chars) are required.'
    ]);
    $conn->close();
    exit;
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
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid reset token.'
    ]);
    $conn->close();
    exit;
}

if (!empty($record['used_at'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Reset token already used.'
    ]);
    $conn->close();
    exit;
}

if (strtotime((string)$record['expires_at']) < time()) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Reset token expired. Request a new one.'
    ]);
    $conn->close();
    exit;
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

    echo json_encode([
        'success' => true,
        'message' => 'Password reset successful. Please log in.'
    ]);
} catch (Throwable $error) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Password reset failed: ' . $error->getMessage()
    ]);
}

$conn->close();
?>
