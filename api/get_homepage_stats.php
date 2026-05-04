<?php
// Public API for homepage statistics
header('Content-Type: application/json');

// Database connection
$db_host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'bizlink_crm';
$db_port = 3306;

$conn = new mysqli($db_host, $db_user, $db_password, $db_name, $db_port);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'data' => null
    ]);
    exit;
}

$stats = [
    'vendors' => 0,
    'customers' => 0,
    'industries' => 0
];

// Count active vendors
$vendorResult = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'vendor' AND account_status = 'active'");
if ($vendorResult) {
    $vendorRow = $vendorResult->fetch_assoc();
    $stats['vendors'] = (int)($vendorRow['count'] ?? 0);
}

// Count active customers
$customerResult = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer' AND account_status = 'active'");
if ($customerResult) {
    $customerRow = $customerResult->fetch_assoc();
    $stats['customers'] = (int)($customerRow['count'] ?? 0);
}

// Count distinct categories/industries
$industriesResult = $conn->query("SELECT COUNT(DISTINCT category) as count FROM products");
if ($industriesResult) {
    $industriesRow = $industriesResult->fetch_assoc();
    $count = (int)($industriesRow['count'] ?? 0);
    $stats['industries'] = max($count, 25); // Use at least 25 as a baseline
}

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Homepage statistics retrieved',
    'data' => $stats
]);

$conn->close();
?>
