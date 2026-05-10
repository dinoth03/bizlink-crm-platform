<?php
/**
 * Public Vendor Profile API
 * GET /api/get_vendor_profile.php?vendor_id={vendor_id}
 * Returns: Vendor info, avg rating, total reviews, products, reviews
 */

require 'config.php';
require_once 'api_helpers.php';

$vendorId = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : 0;

if ($vendorId <= 0) {
    apiError('VALIDATION_ERROR', 'vendor_id is required and must be > 0.', 422);
}

// Get vendor basic info
$vendorSql = "
SELECT
    v.vendor_id,
    v.user_id,
    v.business_name,
    v.business_category,
    v.business_description,
    v.business_logo_url,
    v.business_banner_url,
    v.business_website,
    v.business_phone,
    v.business_email,
    v.verification_status,
    v.avg_rating,
    v.total_reviews,
    v.total_products,
    v.is_premium,
    v.created_at,
    u.full_name,
    u.email,
    u.phone,
    u.province
FROM vendors v
LEFT JOIN users u ON v.user_id = u.user_id
WHERE v.vendor_id = ? AND v.verification_status = 'verified'
LIMIT 1
";

$stmt = $conn->prepare($vendorSql);
if (!$stmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare vendor query.', 500);
}

$stmt->bind_param('i', $vendorId);
$stmt->execute();
$vendor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$vendor) {
    apiError('VENDOR_NOT_FOUND', 'Vendor not found or not verified.', 404);
}

// Get vendor reviews
$reviewsSql = "
SELECT
    vr.vendor_review_id,
    vr.rating,
    vr.review_content,
    vr.delivery_speed_rating,
    vr.product_quality_rating,
    vr.communication_rating,
    vr.packaging_rating,
    vr.created_at,
    u.full_name as reviewer_name
FROM vendor_reviews vr
LEFT JOIN customers c ON vr.customer_id = c.customer_id
LEFT JOIN users u ON c.user_id = u.user_id
WHERE vr.vendor_id = ? AND vr.is_approved = 1
ORDER BY vr.created_at DESC
LIMIT 20
";

$stmt = $conn->prepare($reviewsSql);
if (!$stmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare reviews query.', 500);
}

$stmt->bind_param('i', $vendorId);
$stmt->execute();
$reviews = [];
while ($row = $stmt->get_result()->fetch_assoc()) {
    $reviews[] = $row;
}
$stmt->close();

// Get vendor products (active only)
$productsSql = "
SELECT
    p.product_id,
    p.product_name,
    p.product_description,
    p.category,
    p.price,
    p.discount_price,
    p.quantity_in_stock,
    p.primary_image_url,
    p.avg_rating,
    p.total_reviews,
    p.created_at
FROM products p
WHERE p.vendor_id = ? AND p.is_active = 1
ORDER BY p.created_at DESC
LIMIT 12
";

$stmt = $conn->prepare($productsSql);
if (!$stmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare products query.', 500);
}

$stmt->bind_param('i', $vendorId);
$stmt->execute();
$products = [];
while ($row = $stmt->get_result()->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();

// Build response
$response = [
    'vendor' => $vendor,
    'reviews' => $reviews,
    'products' => $products,
    'review_count' => count($reviews),
    'product_count' => count($products)
];

apiSuccess($response, 'Vendor profile fetched successfully.', 'VENDOR_PROFILE_FETCHED');

$conn->close();
?>
