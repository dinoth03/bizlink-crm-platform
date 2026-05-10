<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';

requireAuth();
$user = getCurrentUser();
$userId = (int)$user['user_id'];

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true) ?: $_POST ?: [];
$id = isset($payload['id']) ? (int)$payload['id'] : 0;
if ($id <= 0) apiError('VALIDATION_ERROR', 'Provide saved search id to delete.', 422);

$stmt = $conn->prepare('DELETE FROM saved_searches WHERE saved_search_id = ? AND user_id = ?');
if (!$stmt) apiError('DB_QUERY_ERROR', 'Failed to prepare delete query.', 500);
$stmt->bind_param('ii', $id, $userId);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

if ($affected <= 0) apiError('NOT_FOUND', 'Saved search not found or not owned by user.', 404);

apiSuccess(['deleted' => $id], 'Saved search deleted.', 'SAVED_SEARCH_DELETED');

$conn->close();
?>