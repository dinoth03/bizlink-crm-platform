<?php
/**
 * Public API: Get product reviews
 * GET /api/get_product_reviews.php?product_id={id}&page=1&per_page=10
 */
require 'config.php';
require_once 'api_helpers.php';

$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if ($productId <= 0) {
    apiError('VALIDATION_ERROR', 'product_id is required.', 422);
}

$pagination = getPaginationParams($_GET, 10, 50);

$sql = "SELECT pr.review_id, pr.product_id, pr.customer_id, pr.rating, pr.review_title, pr.review_content, pr.verified_purchase, pr.images, pr.created_at,
               u.full_name as reviewer_name
        FROM product_reviews pr
        LEFT JOIN customers c ON pr.customer_id = c.customer_id
        LEFT JOIN users u ON c.user_id = u.user_id
        WHERE pr.product_id = ? AND pr.is_approved = 1
        ORDER BY pr.created_at DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare reviews query.', 500);
}

$limit = (int)$pagination['limit'];
$offset = (int)$pagination['offset'];
$stmt->bind_param('iii', $productId, $limit, $offset);
$stmt->execute();
$res = $stmt->get_result();
$reviews = [];
while ($row = $res->fetch_assoc()) {
    $row['images'] = $row['images'] ? json_decode($row['images'], true) : [];
    $reviews[] = $row;
}
$stmt->close();

$countSql = "SELECT COUNT(*) as cnt, COALESCE(AVG(rating),0) as avg_rating FROM product_reviews WHERE product_id = ? AND is_approved = 1";
$cstmt = $conn->prepare($countSql);
$cstmt->bind_param('i', $productId);
$cstmt->execute();
$meta = $cstmt->get_result()->fetch_assoc();
$cstmt->close();

$response = [
    'reviews' => $reviews,
    'meta' => [
        'count' => (int)($meta['cnt'] ?? 0),
        'avg_rating' => (float)round((float)($meta['avg_rating'] ?? 0), 2),
        'page' => $pagination['page'],
        'per_page' => $pagination['per_page']
    ]
];

apiSuccess($response, 'Product reviews fetched.', 'PRODUCT_REVIEWS_FETCHED');

?>
