<?php
// Simple diagnostic for AI chat setup
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

$results = [];

// Check env
$apiKey = getenv('GOOGLE_GENERATIVE_AI_API_KEY');
$results['google_api_key_present'] = !empty($apiKey);
$results['google_api_key_value'] = $apiKey ? substr($apiKey, 0, 8) . '...' : null;

// DB connection check
$results['db_connected'] = ($conn instanceof mysqli) && $conn->connect_errno === 0;

function checkTableExists($conn, $dbName, $table) {
    $stmt = $conn->prepare('SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ? AND table_name = ?');
    $stmt->bind_param('ss', $dbName, $table);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)$res['c'] > 0;
}

function getColumnType($conn, $dbName, $table, $column) {
    $stmt = $conn->prepare('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->bind_param('sss', $dbName, $table, $column);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $res['COLUMN_TYPE'] ?? null;
}

$db = DB_NAME;
$tables = ['users', 'messages', 'conversations', 'conversation_participants', 'products', 'vendors', 'orders'];
$results['tables'] = [];
foreach ($tables as $t) {
    $results['tables'][$t] = checkTableExists($conn, $db, $t);
}

// Check specific columns that logs mentioned
$columnsToCheck = [
    ['table' => 'products', 'column' => 'base_price'],
    ['table' => 'orders', 'column' => 'shipping_status'],
    ['table' => 'users', 'column' => 'email_verified'],
];
$results['columns'] = [];
foreach ($columnsToCheck as $c) {
    $results['columns']["{$c['table']}.{$c['column']}"] = (getColumnType($conn, $db, $c['table'], $c['column']) !== null);
}

// Check enum values for role and message_type
$roleType = getColumnType($conn, $db, 'users', 'role');
$results['users.role_type'] = $roleType;
$results['users.role_has_bot'] = (strpos($roleType ?? '', "'bot'") !== false);

$msgType = getColumnType($conn, $db, 'messages', 'message_type');
$results['messages.message_type'] = $msgType;
$results['messages.has_ai'] = (strpos($msgType ?? '', "'ai'") !== false);

// Summary
$results['ready_for_live_test'] = $results['google_api_key_present'] && $results['db_connected'] && $results['tables']['messages'] && $results['tables']['users'] && $results['users.role_has_bot'] && $results['messages.has_ai'];

echo json_encode($results, JSON_PRETTY_PRINT);

// Close connection
$conn->close();
