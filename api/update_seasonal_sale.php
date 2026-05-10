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

$saleId = (int)($data['seasonal_sale_id'] ?? 0);
if ($saleId <= 0) {
    return apiError('VALIDATION_ERROR', 'seasonal_sale_id is required', 400);
}

ensurePromotionalTables($conn);

$stmt = $conn->prepare('SELECT ss.* FROM seasonal_sales ss WHERE ss.seasonal_sale_id = ? LIMIT 1');
$stmt->bind_param('i', $saleId);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sale) {
    return apiError('NOT_FOUND', 'Seasonal sale not found', 404);
}

if ($user['role'] === 'vendor' && (int)($user['vendor_id'] ?? 0) !== (int)($sale['vendor_id'] ?? 0)) {
    return apiError('FORBIDDEN', 'Cannot modify other vendor\'s sales', 403);
}

$conn->begin_transaction();

$updates = [];
$types = '';
$params = [];

if (isset($data['sale_name'])) {
    $updates[] = 'sale_name = ?';
    $types .= 's';
    $params[] = sanitizeString($data['sale_name'], 255);
}

if (isset($data['sale_description'])) {
    $updates[] = 'sale_description = ?';
    $types .= 's';
    $params[] = sanitizeString($data['sale_description'], 1000);
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

if (isset($data['max_discount_per_item'])) {
    $value = (float)$data['max_discount_per_item'] > 0 ? (float)$data['max_discount_per_item'] : null;
    $updates[] = 'max_discount_per_item = ?';
    $types .= 'd';
    $params[] = $value;
}

if (isset($data['starts_at']) && strtotime($data['starts_at'])) {
    $updates[] = 'starts_at = ?';
    $types .= 's';
    $params[] = sanitizeString($data['starts_at'], 20);
}

if (isset($data['ends_at']) && strtotime($data['ends_at'])) {
    $updates[] = 'ends_at = ?';
    $types .= 's';
    $params[] = sanitizeString($data['ends_at'], 20);
}

if (isset($data['banner_image_url'])) {
    $updates[] = 'banner_image_url = ?';
    $types .= 's';
    $params[] = sanitizeString($data['banner_image_url'], 500);
}

if (isset($data['is_active'])) {
    $updates[] = 'is_active = ?';
    $types .= 'i';
    $params[] = (int)(bool)$data['is_active'];
}

if (!empty($updates)) {
    $updates[] = 'updated_at = NOW()';
    $sql = 'UPDATE seasonal_sales SET ' . implode(', ', $updates) . ' WHERE seasonal_sale_id = ?';
    $types .= 'i';
    $params[] = $saleId;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $conn->rollback();
        return apiError('DB_ERROR', 'Failed to prepare statement', 500);
    }

    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) {
        $conn->rollback();
        return apiError('DB_ERROR', $stmt->error, 500);
    }
    $stmt->close();
}

// Update items if provided
if (isset($data['items']) && is_array($data['items'])) {
    $deleteStmt = $conn->prepare('DELETE FROM seasonal_sale_items WHERE seasonal_sale_id = ?');
    $deleteStmt->bind_param('i', $saleId);
    if (!$deleteStmt->execute()) {
        $conn->rollback();
        return apiError('DB_ERROR', 'Failed to delete old items', 500);
    }
    $deleteStmt->close();

    $insertStmt = $conn->prepare('INSERT INTO seasonal_sale_items (seasonal_sale_id, product_id, category, applies_to_type) VALUES (?, ?, ?, ?)');
    if (!$insertStmt) {
        $conn->rollback();
        return apiError('DB_ERROR', 'Failed to prepare items insert', 500);
    }

    foreach ($data['items'] as $item) {
        $productId = isset($item['product_id']) && (int)$item['product_id'] > 0 ? (int)$item['product_id'] : null;
        $category = isset($item['category']) ? sanitizeString($item['category'], 100) : null;
        $appliesToType = $productId ? 'product' : ($category ? 'category' : 'vendor_all');

        $insertStmt->bind_param('iiss', $saleId, $productId, $category, $appliesToType);
        if (!$insertStmt->execute()) {
            $conn->rollback();
            return apiError('DB_ERROR', 'Failed to add item', 500);
        }
    }
    $insertStmt->close();
}

$conn->commit();

$stmt = $conn->prepare('SELECT ss.* FROM seasonal_sales ss WHERE ss.seasonal_sale_id = ? LIMIT 1');
$stmt->bind_param('i', $saleId);
$stmt->execute();
$updated = $stmt->get_result()->fetch_assoc();
$stmt->close();

apiSuccess([
    'seasonal_sale_id' => (int)$updated['seasonal_sale_id'],
    'sale_name' => $updated['sale_name'],
    'discount_type' => $updated['discount_type'],
    'discount_value' => (float)$updated['discount_value'],
    'starts_at' => $updated['starts_at'],
    'ends_at' => $updated['ends_at'],
    'is_active' => (bool)$updated['is_active'],
    'updated_at' => $updated['updated_at']
], 'Seasonal sale updated successfully');
?>
