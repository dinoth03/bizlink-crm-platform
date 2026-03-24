<?php
require 'config.php';
require 'auth_token_utils.php';
require 'rate_limiting.php';
require 'secure_logging.php';

$token = trim((string)($_GET['verify_token'] ?? $_POST['verify_token'] ?? ''));
if ($token === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Verification token is required.'
    ]);
    $conn->close();
    exit;
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
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid verification token.'
    ]);
    $conn->close();
    exit;
}

if (!empty($row['used_at'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'This verification link has already been used.'
    ]);
    $conn->close();
    exit;
}

if (strtotime((string)$row['expires_at']) < time()) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Verification link expired. Request a new one.'
    ]);
    $conn->close();
    exit;
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

    echo json_encode([
        'success' => true,
        'message' => 'Email verified successfully. You can now log in.'
    ]);
} catch (Throwable $error) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to verify email: ' . $error->getMessage()
    ]);
}

$conn->close();
?>
