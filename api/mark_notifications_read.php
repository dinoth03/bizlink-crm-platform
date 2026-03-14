<?php
require 'config.php';

function readPayload(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return $_POST ?: [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function resolveNotificationUserFromPayload(mysqli $conn, array $payload): ?array {
    $email = isset($payload['email']) ? trim((string) $payload['email']) : '';
    $userId = isset($payload['user_id']) ? (int) $payload['user_id'] : 0;

    if ($userId > 0) {
        $stmt = $conn->prepare("SELECT user_id, email, full_name FROM users WHERE user_id = ? LIMIT 1");
        $stmt->bind_param('i', $userId);
    } else {
        if ($email === '') {
            $email = 'kasun@bizlink.lk';
        }
        $stmt = $conn->prepare("SELECT user_id, email, full_name FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $user ?: null;
}

$payload = readPayload();
$user = resolveNotificationUserFromPayload($conn, $payload);

if (!$user) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Notification user not found.'
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
        $insertStmt->bind_param('i', $user['user_id']);
        $insertStmt->execute();
        $insertStmt->close();

        $updateSql = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param('i', $user['user_id']);
        $updateStmt->execute();
        $affectedRows = $updateStmt->affected_rows;
        $updateStmt->close();
    } else {
        $insertSql = "INSERT IGNORE INTO notification_reads (notification_id, user_id, read_at)
                      SELECT notification_id, user_id, NOW()
                      FROM notifications
                      WHERE notification_id = ? AND user_id = ?";
        $insertStmt = $conn->prepare($insertSql);
        $insertStmt->bind_param('ii', $notificationId, $user['user_id']);
        $insertStmt->execute();
        $insertStmt->close();

        $updateSql = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE notification_id = ? AND user_id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param('ii', $notificationId, $user['user_id']);
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