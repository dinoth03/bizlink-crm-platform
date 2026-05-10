<?php
require 'config.php';
require_once 'api_helpers.php';

$term = isset($_GET['term']) ? trim((string)$_GET['term']) : '';
if ($term === '') {
    apiSuccess(['suggestions' => []], 'No term provided.', 'SEARCH_SUGGESTIONS_EMPTY');
}

$like = '%' . $term . '%';
$suggestions = [];

$sql = "SELECT suggestion_label, suggestion_type, sort_date FROM (
    SELECT DISTINCT p.product_name AS suggestion_label, 'product' AS suggestion_type, p.created_at AS sort_date
    FROM products p
    WHERE p.is_active = 1 AND (p.product_name LIKE ? OR p.product_description LIKE ?)
    UNION ALL
    SELECT DISTINCT v.business_name AS suggestion_label, 'vendor' AS suggestion_type, v.created_at AS sort_date
    FROM vendors v
    WHERE v.business_name LIKE ? OR v.business_description LIKE ?
    UNION ALL
    SELECT DISTINCT pc.category_name AS suggestion_label, 'category' AS suggestion_type, pc.created_at AS sort_date
    FROM product_categories pc
    WHERE pc.is_active = 1 AND pc.category_name LIKE ?
) AS x
ORDER BY sort_date DESC
LIMIT 8";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare search suggestions query.', 500);
}

$stmt->bind_param('sssss', $like, $like, $like, $like, $like);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $suggestions[] = $row;
}
$stmt->close();

apiSuccess(['suggestions' => $suggestions], 'Search suggestions fetched.', 'SEARCH_SUGGESTIONS_FETCHED');

$conn->close();
?>
