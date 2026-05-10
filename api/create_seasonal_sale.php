<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/promotional_helpers.php';

requireAuth(['admin', 'vendor'], $conn);
requireCsrfToken($conn, getCurrentUser()['user_id']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return apiError('METHOD_NOT_ALLOWED', 'POST only', 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$user = getCurrentUser();

if (!$user) {
    return apiError('AUTH_REQUIRED', 'Not authenticated', 401);
}

$required = ['sale_name', 'discount_type', 'discount_value', 'starts_at', 'ends_at'];
$missing = [];
foreach ($required as $field) {
    if (empty($data[$field])) {
        $missing[] = $field;
    }
}
if (!empty($missing)) {
    return apiError('VALIDATION_ERROR', 'Missing fields: ' . implode(', ', $missing), 400, ['missing_fields' => $missing]);
}

$saleName = sanitizeString($data['sale_name'], 255);
$saleDescription = sanitizeString($data['sale_description'] ?? '', 1000);
$discountType = in_array($data['discount_type'], ['percentage', 'fixed']) ? $data['discount_type'] : 'percentage';
$discountValue = max(0.01, (float)$data['discount_value']);
$maxDiscountPerItem = isset($data['max_discount_per_item']) && (float)$data['max_discount_per_item'] > 0 ? (float)$data['max_discount_per_item'] : null;
$startsAt = sanitizeString($data['starts_at'], 20);
$endsAt = sanitizeString($data['ends_at'], 20);
$bannerImageUrl = sanitizeString($data['banner_image_url'] ?? '', 500);
$vendorId = $user['role'] === 'vendor' ? (int)($user['vendor_id'] ?? 0) : null;

$items = $data['items'] ?? [];

if (!strtotime($startsAt) || !strtotime($endsAt)) {
    return apiError('VALIDATION_ERROR', 'Invalid date format', 400);
}

if (new DateTime($startsAt) >= new DateTime($endsAt)) {
    return apiError('VALIDATION_ERROR', 'starts_at must be before ends_at', 400);
}

if ($discountType === 'percentage' && ($discountValue <= 0 || $discountValue > 100)) {
    return apiError('VALIDATION_ERROR', 'Percentage discount must be 0-100', 400);
}

if (empty($items) || !is_array($items)) {
    return apiError('VALIDATION_ERROR', 'At least one product or category must be included', 400);
}

ensurePromotionalTables($conn);

$conn->begin_transaction();

$stmt = $conn->prepare('INSERT INTO seasonal_sales (sale_name, sale_description, vendor_id, discount_type, discount_value, max_discount_per_item, starts_at, ends_at, banner_image_url, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
if (!$stmt) {
    $conn->rollback();
    return apiError('DB_ERROR', 'Failed to prepare statement', 500);
}

$stmt->bind_param(
    'ssididssi',
    $saleName,
    $saleDescription,
    $vendorId,
    $discountType,
    $discountValue,
    $maxDiscountPerItem,
    $startsAt,
    $endsAt,
    $user['user_id']
);

if (!$stmt->execute()) {
    $conn->rollback();
    return apiError('DB_ERROR', $stmt->error, 500);
}

$saleId = $conn->insert_id;
$stmt->close();

$itemStmt = $conn->prepare('INSERT INTO seasonal_sale_items (seasonal_sale_id, product_id, category, applies_to_type) VALUES (?, ?, ?, ?)');
if (!$itemStmt) {
    $conn->rollback();
    return apiError('DB_ERROR', 'Failed to prepare items statement', 500);
}

foreach ($items as $item) {
    $productId = isset($item['product_id']) && (int)$item['product_id'] > 0 ? (int)$item['product_id'] : null;
    $category = isset($item['category']) ? sanitizeString($item['category'], 100) : null;
    $appliesToType = $productId ? 'product' : ($category ? 'category' : 'vendor_all');
    
    $itemStmt->bind_param(
        'iiss',
        $saleId,
        $productId,
        $category,
        $appliesToType
    );
    
    if (!$itemStmt->execute()) {
        $conn->rollback();
        return apiError('DB_ERROR', 'Failed to add item to sale', 500);
    }
}
$itemStmt->close();

$conn->commit();

apiSuccess([
    'seasonal_sale_id' => $saleId,
    'sale_name' => $saleName,
    'discount_type' => $discountType,
    'discount_value' => $discountValue,
    'starts_at' => $startsAt,
    'ends_at' => $endsAt,
    'item_count' => count($items),
    'is_active' => true
], 'Seasonal sale created successfully');
?>
