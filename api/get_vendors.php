<?php
require 'auth_middleware.php';
require 'config.php';

// Require authentication
requireAuth();

$userRole = getUserRole();
$userId = getCurrentUser()['user_id'];

$query = "SELECT 
    v.vendor_id,
    v.business_name as vendor_name,
    v.business_name as shop_name,
    u.email,
    v.business_category,
    v.avg_rating,
    v.commission_rate,
    v.verification_status as vendor_status,
    v.created_at,
    COUNT(p.product_id) as total_products
FROM vendors v
LEFT JOIN users u ON v.user_id = u.user_id
LEFT JOIN products p ON v.vendor_id = p.vendor_id";

// Add role-based filtering
if ($userRole === 'admin') {
    // Admins see all vendors
    $whereClause = "WHERE 1=1";
} elseif ($userRole === 'vendor') {
    // Vendors only see their own info
    $whereClause = "WHERE v.user_id = $userId";
} else {
    // Customers shouldn't access vendor API endpoint
    http_response_code(403);
    echo json_encode(['error' => 'Customers do not have access to vendor information']);
    exit;
}

$query = $query . " " . $whereClause . " 
GROUP BY v.vendor_id
ORDER BY v.created_at DESC
LIMIT 100";

$result = $conn->query($query);

if ($result) {
    $vendors = array();
    while($row = $result->fetch_assoc()) {
        $vendors[] = $row;
    }
    echo json_encode([
        'success' => true,
        'data' => $vendors,
        'count' => count($vendors)
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching vendors: ' . $conn->error
    ]);
}

$conn->close();
?>
