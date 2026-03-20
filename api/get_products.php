<?php
require 'auth_middleware.php';
require 'config.php';

// Authentication required for sensitive operations
$isAuthenticated = isLoggedIn();
$userRole = isLoggedIn() ? getUserRole() : null;
$userId = isLoggedIn() ? getCurrentUser()['user_id'] : null;

$baseQuery = "SELECT 
    p.product_id,
    p.product_name,
    p.category,
    p.vendor_id,
    v.business_name as shop_name,
    p.price as base_price,
    p.quantity_in_stock as stock_quantity,
    p.is_active as product_status,
    p.created_at
FROM products p
LEFT JOIN vendors v ON p.vendor_id = v.vendor_id";

// Role-based filtering
if ($isAuthenticated && $userRole === 'vendor') {
    // Vendors see their own products + other active products for marketplace
    $whereClause = "WHERE (v.user_id = $userId OR p.is_active = 1)";
} else {
    // Public or customers see only active products
    $whereClause = "WHERE p.is_active = 1";
}

$query = $baseQuery . " " . $whereClause . " 
ORDER BY p.created_at DESC
LIMIT 100";

$result = $conn->query($query);

if ($result) {
    $products = array();
    while($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
    echo json_encode([
        'success' => true,
        'data' => $products,
        'count' => count($products)
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching products: ' . $conn->error
    ]);
}

$conn->close();
?>
