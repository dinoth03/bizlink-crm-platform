<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';

// Require authentication
requireAuth();

$userId = getCurrentUser()['user_id'];
$pagination = getPaginationParams($_GET, 10, 50);

$unreadOnly = isset($_GET['unread_only']) ? strtolower(trim((string)$_GET['unread_only'])) : 'false';
if (!in_array($unreadOnly, ['true', 'false', '1', '0'], true)) {
    apiError('VALIDATION_ERROR', 'Invalid unread_only value.', 422, [
        ['field' => 'unread_only', 'message' => 'unread_only must be true or false.']
    ]);
}
$onlyUnread = in_array($unreadOnly, ['true', '1'], true);

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
    AND (? = 0 OR (nr.notification_read_id IS NULL AND n.is_read = 0))
ORDER BY is_read ASC, n.created_at DESC
LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$unreadFlag = $onlyUnread ? 1 : 0;
$stmt->bind_param('iiiii', $userId, $userId, $unreadFlag, $pagination['limit'], $pagination['offset']);
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

apiSuccess($notifications, 'Notifications fetched successfully.', 'NOTIFICATIONS_FETCHED', 200, [
    'user' => $user,
    'unread_count' => $unreadCount,
    'pagination' => [
        'page' => $pagination['page'],
        'per_page' => $pagination['per_page'],
        'returned' => count($notifications)
    ],
    'filters' => [
        'unread_only' => $onlyUnread
    ]
]);

$conn->close();
?>