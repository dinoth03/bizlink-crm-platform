<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';

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
    apiError('USER_NOT_FOUND', 'User not found.', 404);
}

$markAll = !empty($payload['mark_all']);
$notificationId = isset($payload['notification_id']) ? (int) $payload['notification_id'] : 0;

if (!$markAll && $notificationId <= 0) {
    apiError('VALIDATION_ERROR', 'Provide notification_id or set mark_all=true.', 422, [
        ['field' => 'notification_id', 'message' => 'notification_id must be > 0 when mark_all is false.']
    ]);
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

    apiSuccess([
        'user' => $user,
        'affected_rows' => $affectedRows
    ], 'Notifications marked as read.', 'NOTIFICATIONS_MARKED');
} catch (Throwable $error) {
    $conn->rollback();
    apiError('INTERNAL_ERROR', 'Failed to mark notifications as read.', 500, [
        ['field' => 'server', 'message' => $error->getMessage()]
    ]);
}

$conn->close();
?>