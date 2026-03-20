<?php
require 'auth_middleware.php';
require 'config.php';

// Require authentication
requireAuth();

$userRole = getUserRole();
$userId = getCurrentUser()['user_id'];

// Build base query
$baseQuery = "SELECT 
    c.customer_id,
    u.user_id,
    u.full_name,
    u.email,
    u.phone,
    u.address,
    u.city,
    u.country,
    u.account_status as customer_status,
    COUNT(o.order_id) as total_orders,
    SUM(o.total_amount) as total_spent,
    c.created_at
FROM customers c
LEFT JOIN users u ON c.user_id = u.user_id
LEFT JOIN orders o ON c.customer_id = o.customer_id";

// Add role-based filtering
if ($userRole === 'admin') {
    // Admins see all customers
    $whereClause = "WHERE 1=1";
} elseif ($userRole === 'vendor') {
    // Vendors see customers who have purchased from them
    $baseQuery = "SELECT DISTINCT
        c.customer_id,
        u.user_id,
        u.full_name,
        u.email,
        u.phone,
        u.address,
        u.city,
        u.country,
        u.account_status as customer_status,
        COUNT(o.order_id) as total_orders,
        SUM(o.total_amount) as total_spent,
        c.created_at
FROM customers c
LEFT JOIN users u ON c.user_id = u.user_id
LEFT JOIN orders o ON c.customer_id = o.customer_id
LEFT JOIN vendors v ON o.vendor_id = v.vendor_id";
    $whereClause = "WHERE v.user_id = $userId OR o.order_id IS NULL";
} elseif ($userRole === 'customer') {
    // Customers only see their own info
    $whereClause = "WHERE u.user_id = $userId";
} else {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid role']);
    exit;
}

$query = $baseQuery . " " . $whereClause . " 
GROUP BY c.customer_id
ORDER BY c.created_at DESC
LIMIT 100";

$result = $conn->query($query);

if ($result) {
    $customers = array();
    while($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    echo json_encode([
        'success' => true,
        'data' => $customers,
        'count' => count($customers)
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching customers: ' . $conn->error
    ]);
}

$conn->close();
?>
