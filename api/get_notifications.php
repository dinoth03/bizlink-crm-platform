<?php
require 'auth_middleware.php';
require 'config.php';

// Require authentication
requireAuth();

$userId = getCurrentUser()['user_id'];
$limit = isset($_GET['limit']) ? max(1, min(20, (int) $_GET['limit'])) : 6;

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
$stmt->bind_param('iii', $userId, $userId, $limit);
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

// Get current user info
$userStmt = $conn->prepare("SELECT user_id, email, full_name, role FROM users WHERE user_id = ? LIMIT 1");
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
$userStmt->close();

echo json_encode([
    'success' => true,
    'user' => $user,
    'data' => $notifications,
    'count' => count($notifications),
    'unread_count' => $unreadCount
]);

$conn->close();
?>