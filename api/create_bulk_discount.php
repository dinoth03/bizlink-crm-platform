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

$required = ['min_quantity', 'discount_type', 'discount_value'];
$missing = [];
foreach ($required as $field) {
    if (!isset($data[$field])) {
        $missing[] = $field;
    }
}
if (!empty($missing)) {
    return apiError('VALIDATION_ERROR', 'Missing fields: ' . implode(', ', $missing), 400, ['missing_fields' => $missing]);
}

$productId = isset($data['product_id']) && (int)$data['product_id'] > 0 ? (int)$data['product_id'] : null;
$category = isset($data['category']) ? sanitizeString($data['category'], 100) : null;
$minQuantity = max(1, (int)$data['min_quantity']);
$maxQuantity = isset($data['max_quantity']) && (int)$data['max_quantity'] > 0 ? (int)$data['max_quantity'] : null;
$discountType = in_array($data['discount_type'], ['percentage', 'fixed']) ? $data['discount_type'] : 'percentage';
$discountValue = max(0.01, (float)$data['discount_value']);
$vendorId = $user['role'] === 'vendor' ? (int)($user['vendor_id'] ?? 0) : null;

if (!$productId && !$category) {
    return apiError('VALIDATION_ERROR', 'Either product_id or category is required', 400);
}

if ($discountType === 'percentage' && ($discountValue <= 0 || $discountValue > 100)) {
    return apiError('VALIDATION_ERROR', 'Percentage discount must be 0-100', 400);
}

if ($maxQuantity !== null && $maxQuantity <= $minQuantity) {
    return apiError('VALIDATION_ERROR', 'max_quantity must be greater than min_quantity', 400);
}

if ($productId && $user['role'] === 'vendor') {
    $stmt = $conn->prepare('SELECT vendor_id FROM products WHERE product_id = ? LIMIT 1');
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$product || (int)$product['vendor_id'] !== $vendorId) {
        return apiError('FORBIDDEN', 'Cannot set bulk discount for other vendor\'s products', 403);
    }
}

ensurePromotionalTables($conn);

$stmt = $conn->prepare('INSERT INTO bulk_discounts (vendor_id, product_id, category, min_quantity, max_quantity, discount_type, discount_value) VALUES (?, ?, ?, ?, ?, ?, ?)');
if (!$stmt) {
    return apiError('DB_ERROR', 'Failed to prepare statement', 500);
}

$stmt->bind_param(
    'isisisi',
    $vendorId,
    $productId,
    $category,
    $minQuantity,
    $maxQuantity,
    $discountType,
    $discountValue
);

if (!$stmt->execute()) {
    $stmt->close();
    return apiError('DB_ERROR', $stmt->error, 500);
}

$bulkDiscountId = $conn->insert_id;
$stmt->close();

apiSuccess([
    'bulk_discount_id' => $bulkDiscountId,
    'product_id' => $productId,
    'category' => $category,
    'min_quantity' => $minQuantity,
    'max_quantity' => $maxQuantity,
    'discount_type' => $discountType,
    'discount_value' => $discountValue,
    'is_active' => true
], 'Bulk discount created successfully');
?>
