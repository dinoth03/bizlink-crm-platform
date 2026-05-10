<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/promotional_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    return apiError('METHOD_NOT_ALLOWED', 'GET only', 405);
}

$couponCode = sanitizeString($_GET['code'] ?? '', 50);
$orderSubtotal = max(0, (float)($_GET['subtotal'] ?? 0));

if (empty($couponCode)) {
    return apiError('VALIDATION_ERROR', 'Coupon code is required', 400);
}

if ($orderSubtotal < 0) {
    return apiError('VALIDATION_ERROR', 'Invalid order subtotal', 400);
}

ensurePromotionalTables($conn);

$result = validateCoupon($conn, $couponCode, $orderSubtotal);

if (!$result['valid']) {
    $errorMsg = match($result['error']) {
        'COUPON_NOT_FOUND' => 'Coupon code not found or expired',
        'MINIMUM_ORDER_NOT_MET' => 'Order does not meet minimum amount',
        'COUPON_EXPIRED' => 'Coupon usage limit exceeded',
        default => 'Invalid coupon'
    };
    return apiError('COUPON_INVALID', $errorMsg, 400, $result);
}

apiSuccess([
    'coupon_id' => $result['coupon_id'],
    'coupon_code' => $result['coupon_code'],
    'discount_type' => $result['discount_type'],
    'discount_value' => $result['discount_value'],
    'discount_amount' => $result['discount_amount'],
    'min_order_amount' => $result['min_order_amount']
], 'Coupon is valid');
?>
