<?php
require 'auth_middleware.php';
require 'config.php';

// Require authentication
requireAuth();

function readPayload(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return $_POST ?: [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

$payload = readPayload();
$userId = getCurrentUser()['user_id'];

// Get user info
$userStmt = $conn->prepare("SELECT user_id, email, full_name FROM users WHERE user_id = ? LIMIT 1");
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
$userStmt->close();

if (!$user) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'User not found.'
    ]);
    $conn->close();
    exit();
}

$markAll = !empty($payload['mark_all']);
$notificationId = isset($payload['notification_id']) ? (int) $payload['notification_id'] : 0;

if (!$markAll && $notificationId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Provide notification_id or set mark_all=true.'
    ]);
    $conn->close();
    exit();
}

$conn->begin_transaction();

try {
    if ($markAll) {
        $insertSql = "INSERT IGNORE INTO notification_reads (notification_id, user_id, read_at)
                      SELECT notification_id, user_id, NOW()
                      FROM notifications
                      WHERE user_id = ?";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param('i', $userId);
        $insertStmt->execute();
        $insertStmt->close();

        $updateSql = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param('i', $userId);
        $updateStmt->execute();
        $affectedRows = $updateStmt->affected_rows;
        $updateStmt->close();
    } else {
        $insertSql = "INSERT IGNORE INTO notification_reads (notification_id, user_id, read_at)
                      SELECT notification_id, user_id, NOW()
                      FROM notifications
                      WHERE notification_id = ? AND user_id = ?";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param('ii', $notificationId, $userId);
        $insertStmt->execute();
        $insertStmt->close();

        $updateSql = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE notification_id = ? AND user_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param('ii', $notificationId, $userId);
        $updateStmt->execute();
        $affectedRows = $updateStmt->affected_rows;
        $updateStmt->close();
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'user' => $user,
        'affected_rows' => $affectedRows
    ]);
} catch (Throwable $error) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to mark notifications as read: ' . $error->getMessage()
    ]);
}

$conn->close();
?>