<?php
require 'config.php';
require_once 'api_helpers.php';

$query = "SELECT 
    category_id,
    category_name,
    category_slug,
    category_description,
    sort_order
FROM product_categories
ORDER BY sort_order ASC";

$result = $conn->query($query);

if (!$result) {
    apiError('DB_QUERY_ERROR', 'Error fetching categories.', 500, [['field' => 'database', 'message' => $conn->error]]);
}

$categories = [];
while($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

apiSuccess($categories, 'Categories fetched successfully.', 'CATEGORIES_FETCHED');

$conn->close();
?>
