<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';
require_once 'csrf_protection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST or DELETE.', 405);
}

requireAuth();

$userId = (int)(getCurrentUser()['user_id'] ?? 0);
if ($userId <= 0) {
    apiError('UNAUTHORIZED', 'Unauthorized user.', 401);
}

if (CSRF_ENABLED) {
    requireCsrfToken($conn, $userId);
}

$payload = readJsonPayload();
$onlyRead = (bool)($payload['only_read'] ?? false);

if ($onlyRead) {
    $stmt = $conn->prepare('DELETE FROM notifications WHERE user_id = ? AND (is_read = 1 OR read_at IS NOT NULL)');
} else {
    $stmt = $conn->prepare('DELETE FROM notifications WHERE user_id = ?');
}

if (!$stmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare clear notifications query.', 500);
}

$stmt->bind_param('i', $userId);
$stmt->execute();
$deleted = (int)$stmt->affected_rows;
$stmt->close();

apiSuccess([
    'deleted_count' => $deleted,
    'only_read' => $onlyRead
], 'Notifications cleared successfully.', 'NOTIFICATIONS_CLEARED');

$conn->close();
?>
