<?php
require __DIR__ . '/api/config.php';

$result = $conn->query("SELECT inquiry_id, full_name, email, target_role, inquiry_status, created_at FROM contact_inquiries ORDER BY created_at DESC LIMIT 5");
$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
