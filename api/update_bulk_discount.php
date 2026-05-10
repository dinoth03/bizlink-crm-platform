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

$bulkDiscountId = (int)($data['bulk_discount_id'] ?? 0);
if ($bulkDiscountId <= 0) {
    return apiError('VALIDATION_ERROR', 'bulk_discount_id is required', 400);
}

ensurePromotionalTables($conn);

$stmt = $conn->prepare('SELECT bd.* FROM bulk_discounts bd WHERE bd.bulk_discount_id = ? LIMIT 1');
$stmt->bind_param('i', $bulkDiscountId);
$stmt->execute();
$discount = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$discount) {
    return apiError('NOT_FOUND', 'Bulk discount not found', 404);
}

if ($user['role'] === 'vendor' && (int)($user['vendor_id'] ?? 0) !== (int)$discount['vendor_id']) {
    return apiError('FORBIDDEN', 'Cannot modify other vendor\'s discounts', 403);
}

$updates = [];
$types = '';
$params = [];

if (isset($data['min_quantity']) && (int)$data['min_quantity'] > 0) {
    $updates[] = 'min_quantity = ?';
    $types .= 'i';
    $params[] = (int)$data['min_quantity'];
}

if (isset($data['max_quantity'])) {
    $value = (int)$data['max_quantity'] > 0 ? (int)$data['max_quantity'] : null;
    $updates[] = 'max_quantity = ?';
    $types .= 'i';
    $params[] = $value;
}

if (isset($data['discount_type']) && in_array($data['discount_type'], ['percentage', 'fixed'])) {
    $updates[] = 'discount_type = ?';
    $types .= 's';
    $params[] = $data['discount_type'];
}

if (isset($data['discount_value'])) {
    $updates[] = 'discount_value = ?';
    $types .= 'd';
    $params[] = max(0.01, (float)$data['discount_value']);
}

if (isset($data['is_active'])) {
    $updates[] = 'is_active = ?';
    $types .= 'i';
    $params[] = (int)(bool)$data['is_active'];
}

if (empty($updates)) {
    return apiError('VALIDATION_ERROR', 'No valid fields to update', 400);
}

$updates[] = 'updated_at = NOW()';
$sql = 'UPDATE bulk_discounts SET ' . implode(', ', $updates) . ' WHERE bulk_discount_id = ?';
$types .= 'i';
$params[] = $bulkDiscountId;

$stmt = $conn->prepare($sql);
if (!$stmt) {
    return apiError('DB_ERROR', 'Failed to prepare statement', 500);
}

$stmt->bind_param($types, ...$params);
if (!$stmt->execute()) {
    $stmt->close();
    return apiError('DB_ERROR', $stmt->error, 500);
}
$stmt->close();

$stmt = $conn->prepare('SELECT bd.*, p.product_name FROM bulk_discounts bd LEFT JOIN products p ON bd.product_id = p.product_id WHERE bd.bulk_discount_id = ? LIMIT 1');
$stmt->bind_param('i', $bulkDiscountId);
$stmt->execute();
$updated = $stmt->get_result()->fetch_assoc();
$stmt->close();

apiSuccess([
    'bulk_discount_id' => (int)$updated['bulk_discount_id'],
    'product_id' => $updated['product_id'] ? (int)$updated['product_id'] : null,
    'product_name' => $updated['product_name'],
    'category' => $updated['category'],
    'min_quantity' => (int)$updated['min_quantity'],
    'max_quantity' => $updated['max_quantity'] ? (int)$updated['max_quantity'] : null,
    'discount_type' => $updated['discount_type'],
    'discount_value' => (float)$updated['discount_value'],
    'is_active' => (bool)$updated['is_active'],
    'updated_at' => $updated['updated_at']
], 'Bulk discount updated successfully');
?>
