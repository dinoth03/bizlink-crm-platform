<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';

requireAuth();
$user = getCurrentUser();
$userId = (int)$user['user_id'];

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true) ?: $_POST ?: [];

$name = isset($payload['name']) ? trim((string)$payload['name']) : '';
$queryParams = isset($payload['query']) && is_array($payload['query']) ? $payload['query'] : [];

if ($name === '' || empty($queryParams)) {
    apiError('VALIDATION_ERROR', 'Provide name and query parameters to save.', 422, [
        ['field' => 'name', 'message' => 'Name is required.'],
        ['field' => 'query', 'message' => 'Query must be an object with filter params.']
    ]);
}

// Create table if missing
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

$insert = $conn->prepare('INSERT INTO saved_searches (user_id, name, query_json) VALUES (?, ?, ?)');
if (!$insert) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare save search query.', 500);
}
$json = json_encode($queryParams);
$insert->bind_param('iss', $userId, $name, $json);
$ok = $insert->execute();
if (!$ok) {
    apiError('DB_WRITE_ERROR', 'Unable to save search: ' . $insert->error, 500);
}
$id = (int)$insert->insert_id;
$insert->close();

apiSuccess(['id' => $id, 'name' => $name, 'query' => $queryParams], 'Search saved.', 'SEARCH_SAVED', 201);

$conn->close();
?>