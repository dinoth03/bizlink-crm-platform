<?php
require 'auth_middleware.php';
require 'config.php';

// Require authentication
requireAuth();

$userRole = getUserRole();
$userId = getCurrentUser()['user_id'];

// Build base query
$baseQuery = "SELECT 
    o.order_id,
    o.order_number,
    o.customer_id,
    u.full_name as customer_name,
    u.email,
    u.phone,
    u.city,
    o.total_amount,
    o.order_status,
    o.payment_status,
    o.created_at,
    o.order_date,
    o.shipping_address,
    o.shipping_method,
    o.tracking_number,
    o.expected_delivery_date,
    v.vendor_id,
    v.business_name as vendor_name,
    v.business_category as vendor_category,
    v.business_email as vendor_email,
    v.avg_rating as vendor_rating,
    COALESCE(SUM(oi.quantity), 0) as quantity,
    COALESCE(MAX(oi.product_name), 'Order Item') as product_name
FROM orders o
LEFT JOIN customers c ON o.customer_id = c.customer_id
LEFT JOIN users u ON c.user_id = u.user_id
LEFT JOIN vendors v ON o.vendor_id = v.vendor_id
LEFT JOIN order_items oi ON o.order_id = oi.order_id";

// Add role-based filtering
if ($userRole === 'admin') {
    // Admins see all orders
    $whereClause = "WHERE 1=1";
} elseif ($userRole === 'vendor') {
    // Vendors see only their own orders
    $whereClause = "WHERE v.user_id = $userId";
} elseif ($userRole === 'customer') {
    // Customers see only their own orders
    $whereClause = "WHERE u.user_id = $userId";
} else {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid role']);
    exit;
}

$query = $baseQuery . " " . $whereClause . " 
GROUP BY 
    o.order_id,
    o.order_number,
    o.customer_id,
    u.full_name,
    u.email,
    u.phone,
    u.city,
    o.total_amount,
    o.order_status,
    o.payment_status,
    o.created_at,
    o.order_date,
    o.shipping_address,
    o.shipping_method,
    o.tracking_number,
    o.expected_delivery_date,
    v.vendor_id,
    v.business_name,
    v.business_category,
    v.business_email,
    v.avg_rating
ORDER BY o.created_at DESC
LIMIT 50";

$result = $conn->query($query);

if ($result) {
    $orders = array();
    while($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    echo json_encode([
        'success' => true,
        'data' => $orders,
        'count' => count($orders)
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching orders: ' . $conn->error
    ]);
}

$conn->close();
?>
