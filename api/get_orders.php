<?php
require 'config.php';

// Get orders with customer information
$query = "SELECT 
    o.order_id,
    o.order_number,
    o.customer_id,
    u.full_name as customer_name,
    u.email,
    o.total_amount,
    o.order_status,
    o.created_at
FROM orders o
LEFT JOIN customers c ON o.customer_id = c.customer_id
LEFT JOIN users u ON c.user_id = u.user_id
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
