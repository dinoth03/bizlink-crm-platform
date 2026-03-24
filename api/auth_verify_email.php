<?php
require 'config.php';
require_once 'api_helpers.php';
require 'auth_token_utils.php';
require 'rate_limiting.php';
require 'secure_logging.php';

$token = trim((string)($_GET['verify_token'] ?? $_POST['verify_token'] ?? ''));
if ($token === '') {
    apiError('VALIDATION_ERROR', 'Verification token is required.', 422, [
        ['field' => 'verify_token', 'message' => 'verify_token is required.']
    ]);
}

// Rate Limiting - per IP address for verification attempts
$clientIp = getClientIpAddress();
requireRateLimit($conn, $clientIp, 'verify_email_by_ip', 20, 900); // 20 attempts per 15 min per IP

$tokenHash = hashAuthToken($token);

$stmt = $conn->prepare(
    'SELECT evt.verification_id, evt.user_id, evt.expires_at, evt.used_at, u.role, u.account_status
     FROM email_verification_tokens evt
     JOIN users u ON u.user_id = evt.user_id
     WHERE evt.token_hash = ?
     LIMIT 1'
);
$stmt->bind_param('s', $tokenHash);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    apiError('INVALID_VERIFICATION_TOKEN', 'Invalid verification token.', 400);
}

if (!empty($row['used_at'])) {
    apiError('VERIFICATION_TOKEN_USED', 'This verification link has already been used.', 400);
}

if (strtotime((string)$row['expires_at']) < time()) {
    apiError('VERIFICATION_TOKEN_EXPIRED', 'Verification link expired. Request a new one.', 400);
}

$conn->begin_transaction();

try {
    $activateStmt = $conn->prepare(
        "UPDATE users
         SET is_verified = 1,
             account_status = CASE
                 WHEN role IN ('admin', 'customer') AND account_status = 'inactive' THEN 'active'
                 ELSE account_status
             END
         WHERE user_id = ?"
    );
    $activateStmt->bind_param('i', $row['user_id']);
    $activateStmt->execute();
    $activateStmt->close();

    $useStmt = $conn->prepare('UPDATE email_verification_tokens SET used_at = NOW() WHERE verification_id = ?');
    $useStmt->bind_param('i', $row['verification_id']);
    $useStmt->execute();
    $useStmt->close();

    $conn->commit();

    apiSuccess(null, 'Email verified successfully. You can now log in.', 'EMAIL_VERIFIED');
} catch (Throwable $error) {
    $conn->rollback();
    apiError('INTERNAL_ERROR', 'Failed to verify email.', 500, [
        ['field' => 'server', 'message' => $error->getMessage()]
    ]);
}

$conn->close();
?>
