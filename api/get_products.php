<?php
require 'config.php';

$query = "SELECT 
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
LEFT JOIN vendors v ON p.vendor_id = v.vendor_id
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
