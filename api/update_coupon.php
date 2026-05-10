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

$couponId = (int)($data['coupon_id'] ?? 0);
if ($couponId <= 0) {
    return apiError('VALIDATION_ERROR', 'coupon_id is required', 400);
}

ensurePromotionalTables($conn);

$stmt = $conn->prepare('SELECT c.*, v.vendor_id FROM coupons c LEFT JOIN vendors v ON c.vendor_id = v.vendor_id WHERE c.coupon_id = ? LIMIT 1');
$stmt->bind_param('i', $couponId);
$stmt->execute();
$coupon = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$coupon) {
    return apiError('NOT_FOUND', 'Coupon not found', 404);
}

if ($user['role'] === 'vendor' && (int)($user['vendor_id'] ?? 0) !== (int)($coupon['vendor_id'] ?? 0)) {
    return apiError('FORBIDDEN', 'Cannot modify other vendor\'s coupons', 403);
}

$allowedFields = ['discount_type', 'discount_value', 'max_uses', 'min_order_amount', 'max_discount_amount', 'valid_from', 'valid_until', 'description', 'is_active'];
$updates = [];
$types = '';
$params = [];

foreach ($allowedFields as $field) {
    if (!isset($data[$field])) continue;
    
    if ($field === 'discount_type') {
        if (!in_array($data[$field], ['percentage', 'fixed'])) continue;
        $updates[] = 'discount_type = ?';
        $types .= 's';
        $params[] = $data[$field];
    } elseif ($field === 'discount_value') {
        $value = max(0.01, (float)$data[$field]);
        $updates[] = 'discount_value = ?';
        $types .= 'd';
        $params[] = $value;
    } elseif ($field === 'max_uses') {
        $value = (int)$data[$field] > 0 ? (int)$data[$field] : null;
        $updates[] = 'max_uses = ?';
        $types .= 'i';
        $params[] = $value;
    } elseif ($field === 'min_order_amount') {
        $updates[] = 'min_order_amount = ?';
        $types .= 'd';
        $params[] = max(0, (float)$data[$field]);
    } elseif ($field === 'max_discount_amount') {
        $value = (float)$data[$field] > 0 ? (float)$data[$field] : null;
        $updates[] = 'max_discount_amount = ?';
        $types .= 'd';
        $params[] = $value;
    } elseif ($field === 'valid_from' || $field === 'valid_until') {
        if (!strtotime($data[$field])) continue;
        $updates[] = $field . ' = ?';
        $types .= 's';
        $params[] = sanitizeString($data[$field], 20);
    } elseif ($field === 'description') {
        $updates[] = 'description = ?';
        $types .= 's';
        $params[] = sanitizeString($data[$field], 1000);
    } elseif ($field === 'is_active') {
        $updates[] = 'is_active = ?';
        $types .= 'i';
        $params[] = (int)(bool)$data[$field];
    }
}

if (empty($updates)) {
    return apiError('VALIDATION_ERROR', 'No valid fields to update', 400);
}

$updates[] = 'updated_at = NOW()';
$sql = 'UPDATE coupons SET ' . implode(', ', $updates) . ' WHERE coupon_id = ?';
$types .= 'i';
$params[] = $couponId;

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

$stmt = $conn->prepare('SELECT * FROM coupons WHERE coupon_id = ? LIMIT 1');
$stmt->bind_param('i', $couponId);
$stmt->execute();
$updated = $stmt->get_result()->fetch_assoc();
$stmt->close();

apiSuccess([
    'coupon_id' => (int)$updated['coupon_id'],
    'coupon_code' => $updated['coupon_code'],
    'discount_type' => $updated['discount_type'],
    'discount_value' => (float)$updated['discount_value'],
    'max_uses' => $updated['max_uses'],
    'current_uses' => (int)$updated['current_uses'],
    'min_order_amount' => (float)$updated['min_order_amount'],
    'max_discount_amount' => $updated['max_discount_amount'] ? (float)$updated['max_discount_amount'] : null,
    'valid_from' => $updated['valid_from'],
    'valid_until' => $updated['valid_until'],
    'is_active' => (bool)$updated['is_active'],
    'updated_at' => $updated['updated_at']
], 'Coupon updated successfully');
?>
