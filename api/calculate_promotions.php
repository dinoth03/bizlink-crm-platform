<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/promotional_helpers.php';

requireAuth(['customer'], $conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    return apiError('METHOD_NOT_ALLOWED', 'POST only', 405);
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$user = getCurrentUser();

if (!$user) {
    return apiError('AUTH_REQUIRED', 'Not authenticated', 401);
}

$couponCode = isset($data['coupon_code']) ? sanitizeString($data['coupon_code'], 50) : null;
$orderSubtotal = max(0, (float)($data['order_subtotal'] ?? 0));
$cartItems = $data['cart_items'] ?? [];

if (!is_array($cartItems) || empty($cartItems)) {
    return apiError('VALIDATION_ERROR', 'cart_items array is required', 400);
}

ensurePromotionalTables($conn);

$result = [
    'coupon_discount' => 0,
    'coupon_applied' => null,
    'bulk_discounts' => [],
    'seasonal_discounts' => [],
    'total_discount' => 0,
    'discounted_subtotal' => $orderSubtotal
];

if (!empty($couponCode)) {
    $couponValidation = validateCoupon($conn, $couponCode, $orderSubtotal);
    if ($couponValidation['valid']) {
        $result['coupon_discount'] = $couponValidation['discount_amount'];
        $result['coupon_applied'] = [
            'coupon_id' => $couponValidation['coupon_id'],
            'coupon_code' => $couponValidation['coupon_code'],
            'discount_amount' => $couponValidation['discount_amount']
        ];
    }
}

foreach ($cartItems as $item) {
    $vendorId = (int)($item['vendor_id'] ?? 0);
    $productId = (int)($item['product_id'] ?? 0);
    $quantity = (int)($item['quantity'] ?? 1);
    $itemSubtotal = (float)($item['subtotal'] ?? 0);
    
    if ($productId <= 0 || $vendorId <= 0) continue;
    
    $bulkDiscounts = calculateBulkDiscounts($conn, $vendorId, [$item]);
    if (!empty($bulkDiscounts)) {
        $result['bulk_discounts'][] = $bulkDiscounts[0];
    }
    
    $activeSales = getActiveSeasonalSales($conn, $vendorId, [$productId]);
    foreach ($activeSales as $sale) {
        if (canApplySeasonalSale($conn, (int)$sale['seasonal_sale_id'], $productId)) {
            $discountAmount = 0;
            if ($sale['discount_type'] === 'percentage') {
                $discountAmount = ($itemSubtotal * (float)$sale['discount_value']) / 100;
            } else {
                $discountAmount = (float)$sale['discount_value'] * $quantity;
            }
            
            if ($sale['max_discount_per_item'] !== null) {
                $discountAmount = min($discountAmount, (float)$sale['max_discount_per_item'] * $quantity);
            }
            
            $result['seasonal_discounts'][] = [
                'seasonal_sale_id' => (int)$sale['seasonal_sale_id'],
                'product_id' => $productId,
                'sale_name' => $sale['sale_name'],
                'discount_amount' => round(max(0, $discountAmount), 2)
            ];
        }
    }
}

$bulkTotal = array_sum(array_map(fn($d) => $d['discount_amount'], $result['bulk_discounts']));
$seasonalTotal = array_sum(array_map(fn($d) => $d['discount_amount'], $result['seasonal_discounts']));

$result['total_discount'] = round($result['coupon_discount'] + $bulkTotal + $seasonalTotal, 2);
$result['discounted_subtotal'] = round(max(0, $orderSubtotal - $result['total_discount']), 2);

apiSuccess($result, 'Promotions calculated successfully');
?>
