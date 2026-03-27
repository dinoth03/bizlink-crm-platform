<?php
session_start();
require 'config.php';
require_once 'api_helpers.php';

// Only admins can access
if (($_SESSION['role'] ?? '') !== 'admin') {
    apiError('UNAUTHORIZED', 'Only admins can access this endpoint.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use GET.', 405);
}

// Get pending customers (account_status = 'inactive')
$stmt = $conn->prepare(
    'SELECT c.customer_id, c.user_id, u.email, u.full_name, u.phone, u.created_at,
            c.preferred_language, c.preferred_currency, u.city, u.province, u.account_status
     FROM customers c
     JOIN users u ON c.user_id = u.user_id
     WHERE u.account_status = "inactive" AND u.role = "customer"
     ORDER BY c.created_at DESC
     LIMIT 100'
);
$stmt->execute();
$result = $stmt->get_result();
$customers = [];

while ($row = $result->fetch_assoc()) {
    $customers[] = [
        'customer_id' => (int)$row['customer_id'],
        'user_id' => (int)$row['user_id'],
        'email' => $row['email'],
        'full_name' => $row['full_name'],
        'phone' => $row['phone'],
        'preferred_language' => $row['preferred_language'],
        'preferred_currency' => $row['preferred_currency'],
        'city' => $row['city'],
        'province' => $row['province'],
        'created_at' => $row['created_at'],
        'account_status' => $row['account_status']
    ];
}

$stmt->close();
$conn->close();

apiSuccess($customers, 'Pending customers retrieved successfully.', 'PENDING_CUSTOMERS_RETRIEVED');
?>
