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

$required = ['coupon_code', 'discount_type', 'discount_value', 'valid_from', 'valid_until'];
$missing = [];
foreach ($required as $field) {
    if (empty($data[$field])) {
        $missing[] = $field;
    }
}
if (!empty($missing)) {
    return apiError('VALIDATION_ERROR', 'Missing fields: ' . implode(', ', $missing), 400, ['missing_fields' => $missing]);
}

$couponCode = strtoupper(sanitizeString($data['coupon_code'], 50));
$discountType = in_array($data['discount_type'], ['percentage', 'fixed']) ? $data['discount_type'] : 'percentage';
$discountValue = max(0.01, (float)$data['discount_value']);
$maxUses = isset($data['max_uses']) && (int)$data['max_uses'] > 0 ? (int)$data['max_uses'] : null;
$minOrderAmount = max(0, (float)($data['min_order_amount'] ?? 0));
$maxDiscountAmount = isset($data['max_discount_amount']) && (float)$data['max_discount_amount'] > 0 ? (float)$data['max_discount_amount'] : null;
$validFrom = sanitizeString($data['valid_from'], 20);
$validUntil = sanitizeString($data['valid_until'], 20);
$description = sanitizeString($data['description'] ?? '', 500);
$vendorId = $user['role'] === 'vendor' ? (int)($user['vendor_id'] ?? 0) : null;

if (!strtotime($validFrom) || !strtotime($validUntil)) {
    return apiError('VALIDATION_ERROR', 'Invalid date format', 400);
}

if (new DateTime($validFrom) >= new DateTime($validUntil)) {
    return apiError('VALIDATION_ERROR', 'valid_from must be before valid_until', 400);
}

if ($discountType === 'percentage' && ($discountValue <= 0 || $discountValue > 100)) {
    return apiError('VALIDATION_ERROR', 'Percentage discount must be 0-100', 400);
}

$conn->begin_transaction();

$stmt = $conn->prepare('INSERT INTO coupons (coupon_code, vendor_id, discount_type, discount_value, max_uses, min_order_amount, max_discount_amount, valid_from, valid_until, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
if (!$stmt) {
    $conn->rollback();
    return apiError('DB_ERROR', 'Failed to prepare statement', 500);
}

$stmt->bind_param(
    'sisdiddssssi',
    $couponCode,
    $vendorId,
    $discountType,
    $discountValue,
    $maxUses,
    $minOrderAmount,
    $maxDiscountAmount,
    $validFrom,
    $validUntil,
    $description,
    $user['user_id']
);

if (!$stmt->execute()) {
    $conn->rollback();
    if (str_contains($stmt->error, 'Duplicate')) {
        return apiError('DUPLICATE_COUPON', 'Coupon code already exists', 409);
    }
    return apiError('DB_ERROR', $stmt->error, 500);
}

$couponId = $conn->insert_id;
$stmt->close();

ensurePromotionalTables($conn);

$conn->commit();

apiSuccess(
    [
        'coupon_id' => $couponId,
        'coupon_code' => $couponCode,
        'discount_type' => $discountType,
        'discount_value' => $discountValue,
        'max_uses' => $maxUses,
        'current_uses' => 0,
        'min_order_amount' => $minOrderAmount,
        'max_discount_amount' => $maxDiscountAmount,
        'valid_from' => $validFrom,
        'valid_until' => $validUntil,
        'is_active' => true
    ],
    'Coupon created successfully'
);
?>
