<?php
require 'config.php';

function resolveNotificationUser(mysqli $conn): ?array {
    $email = isset($_GET['email']) ? trim($_GET['email']) : '';
    $userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

    if ($userId > 0) {
        $stmt = $conn->prepare("SELECT user_id, email, full_name, role FROM users WHERE user_id = ? LIMIT 1");
        $stmt->bind_param('i', $userId);
    } else {
        if ($email === '') {
            $email = 'kasun@bizlink.lk';
        }
        $stmt = $conn->prepare("SELECT user_id, email, full_name, role FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $user ?: null;
}

$limit = isset($_GET['limit']) ? max(1, min(20, (int) $_GET['limit'])) : 6;
$user = resolveNotificationUser($conn);

if (!$user) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Notification user not found.'
    ]);
    $conn->close();
    exit();
}

$query = "SELECT
    n.notification_id,
    n.notification_type,
    n.title,
    n.message,
    n.priority,
    n.action_url,
    n.related_entity_type,
    n.related_entity_id,
    n.created_at,
    CASE
        WHEN nr.notification_read_id IS NOT NULL OR n.is_read = 1 THEN 1
        ELSE 0
    END AS is_read,
    nr.read_at
FROM notifications n
LEFT JOIN notification_reads nr
    ON nr.notification_id = n.notification_id
   AND nr.user_id = ?
WHERE n.user_id = ?
  AND (n.expiry_date IS NULL OR n.expiry_date > NOW())
ORDER BY is_read ASC, n.created_at DESC
LIMIT ?";

$stmt = $conn->prepare($query);
$stmt->bind_param('iii', $user['user_id'], $user['user_id'], $limit);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
$unreadCount = 0;

while ($row = $result->fetch_assoc()) {
    $row['is_read'] = (bool) $row['is_read'];
    if (!$row['is_read']) {
        $unreadCount++;
    }
    $notifications[] = $row;
}

$stmt->close();

echo json_encode([
    'success' => true,
    'user' => $user,
    'data' => $notifications,
    'count' => count($notifications),
    'unread_count' => $unreadCount
]);

$conn->close();
?>