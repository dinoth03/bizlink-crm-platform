<?php
require 'config.php';

$query = "SELECT 
    category_id,
    category_name,
    category_slug,
    category_description,
    sort_order
FROM product_categories
ORDER BY sort_order ASC";

$result = $conn->query($query);

if ($result) {
    $categories = array();
    while($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    echo json_encode([
        'success' => true,
        'data' => $categories,
        'count' => count($categories)
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching categories: ' . $conn->error
    ]);
}

$conn->close();
?>
