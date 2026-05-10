<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';

requireAuth();
$user = getCurrentUser();
$userId = (int)$user['user_id'];

// Ensure table exists (safe)
$createSql = "CREATE TABLE IF NOT EXISTS saved_searches (
    saved_search_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    query_json JSON NOT NULL,
    created_at DATETIME DEFAULT NOW(),
    INDEX idx_saved_search_user (user_id),
    CONSTRAINT fk_saved_search_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
$conn->query($createSql);

$stmt = $conn->prepare('SELECT saved_search_id, name, query_json, created_at FROM saved_searches WHERE user_id = ? ORDER BY created_at DESC');
if (!$stmt) apiError('DB_QUERY_ERROR', 'Failed to prepare saved searches select.', 500);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($r = $result->fetch_assoc()) {
    $r['query'] = json_decode($r['query_json'], true) ?: [];
    unset($r['query_json']);
    $rows[] = $r;
}
$stmt->close();

apiSuccess($rows, 'Saved searches fetched.', 'SAVED_SEARCHES_FETCHED');

$conn->close();
?>