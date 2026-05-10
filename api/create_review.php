<?php
/**
 * Protected API: Create product review
 * POST /api/create_review.php
 * Body (application/json): { product_id, rating, review_title, review_content, order_id (optional), images (array), verified_purchase (bool) }
 */
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';

// Only customers may submit reviews
requireAuth(['customer']);

$payload = readJsonPayload();

$required = ['product_id', 'rating', 'review_content'];
$errors = validateRequired($payload, $required);
if (!empty($errors)) {
    apiError('VALIDATION_FAILED', 'Missing required fields.', 422, $errors);
}

$productId = (int)$payload['product_id'];
$rating = (int)$payload['rating'];
$title = isset($payload['review_title']) ? sanitizeString((string)$payload['review_title'], 255) : null;
$content = sanitizeString((string)$payload['review_content'], 2000);
$orderId = isset($payload['order_id']) ? (int)$payload['order_id'] : null;
$images = isset($payload['images']) && is_array($payload['images']) ? json_encode($payload['images']) : null;
$verified = isset($payload['verified_purchase']) ? (bool)$payload['verified_purchase'] : false;

if ($rating < 1 || $rating > 5) {
    apiError('VALIDATION_FAILED', 'rating must be between 1 and 5.', 422);
}

// Map session user -> customer_id
$user = getCurrentUser();
$userId = (int)$user['user_id'];
$custStmt = $conn->prepare('SELECT customer_id FROM customers WHERE user_id = ? LIMIT 1');
$custStmt->bind_param('i', $userId);
$custStmt->execute();
$custRow = $custStmt->get_result()->fetch_assoc();
$custStmt->close();
if (!$custRow) {
    apiError('NOT_A_CUSTOMER', 'User is not registered as a customer.', 403);
}
$customerId = (int)$custRow['customer_id'];

// Ensure product exists
$pstmt = $conn->prepare('SELECT product_id, vendor_id FROM products WHERE product_id = ? LIMIT 1');
$pstmt->bind_param('i', $productId);
$pstmt->execute();
$pinfo = $pstmt->get_result()->fetch_assoc();
$pstmt->close();
if (!$pinfo) {
    apiError('PRODUCT_NOT_FOUND', 'Product not found.', 404);
}
$vendorId = (int)$pinfo['vendor_id'];

// Insert review
$ins = $conn->prepare('INSERT INTO product_reviews (product_id, customer_id, order_id, rating, review_title, review_content, images, verified_purchase, is_approved, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())');
if (!$ins) apiError('DB_ERROR', 'Failed to prepare insert.', 500);
$ord = $orderId ?: null;
$ins->bind_param('iiiisssi', $productId, $customerId, $ord, $rating, $title, $content, $images, $verified);
$ok = $ins->execute();
if (!$ok) {
    apiError('DB_ERROR', 'Failed to save review.', 500);
}
$ins->close();

// Recompute product aggregates
$aggStmt = $conn->prepare('SELECT COUNT(*) as cnt, COALESCE(AVG(rating),0) as avg_rating FROM product_reviews WHERE product_id = ? AND is_approved = 1');
$aggStmt->bind_param('i', $productId);
$aggStmt->execute();
$agg = $aggStmt->get_result()->fetch_assoc();
$aggStmt->close();

$newCount = (int)($agg['cnt'] ?? 0);
$newAvg = round((float)($agg['avg_rating'] ?? 0), 2);

$up = $conn->prepare('UPDATE products SET avg_rating = ?, total_reviews = ? WHERE product_id = ?');
$up->bind_param('dii', $newAvg, $newCount, $productId);
$up->execute();
$up->close();

// Recompute vendor aggregates (vendor_reviews + product_reviews for vendor)
$vsumSql = 'SELECT COALESCE(SUM(rating),0) as sum_r, COUNT(*) as cnt FROM vendor_reviews WHERE vendor_id = ? AND is_approved = 1';
$vstmt = $conn->prepare($vsumSql);
$vstmt->bind_param('i', $vendorId);
$vstmt->execute();
$vmeta = $vstmt->get_result()->fetch_assoc();
$vstmt->close();

$psumSql = 'SELECT COALESCE(SUM(pr.rating),0) as sum_r, COUNT(*) as cnt FROM product_reviews pr JOIN products p ON pr.product_id = p.product_id WHERE p.vendor_id = ? AND pr.is_approved = 1';
$pstmt = $conn->prepare($psumSql);
$pstmt->bind_param('i', $vendorId);
$pstmt->execute();
$pmeta = $pstmt->get_result()->fetch_assoc();
$pstmt->close();

$sumTotal = (float)$vmeta['sum_r'] + (float)$pmeta['sum_r'];
$cntTotal = (int)$vmeta['cnt'] + (int)$pmeta['cnt'];
$vendorAvg = $cntTotal > 0 ? round($sumTotal / $cntTotal, 2) : 0.0;

$vup = $conn->prepare('UPDATE vendors SET avg_rating = ?, total_reviews = ? WHERE vendor_id = ?');
$vup->bind_param('dii', $vendorAvg, $cntTotal, $vendorId);
$vup->execute();
$vup->close();

apiSuccess(['product_id'=>$productId,'review_count'=>$newCount,'product_avg'=>$newAvg,'vendor_avg'=>$vendorAvg],'Review submitted.','REVIEW_CREATED',201);

?>
