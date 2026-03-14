<?php
require 'config.php';

$query = "SELECT 
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
