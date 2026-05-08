<?php

require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';
require_once 'csrf_protection.php';
require_once 'rate_limiting.php';
require_once 'stripe_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

// Customers and vendors can buy premium plans
requireAuth(['customer', 'vendor']);

$currentUser = getCurrentUser();
$userId = (int)($currentUser['user_id'] ?? 0);
$userRole = strtolower(trim((string)($currentUser['role'] ?? '')));
if ($userId <= 0) {
    apiError('UNAUTHORIZED', 'Unauthorized user.', 401);
}

if (!in_array($userRole, ['customer', 'vendor'], true)) {
    apiError('FORBIDDEN', 'Only customer and vendor accounts can purchase premium plans.', 403);
}

if (CSRF_ENABLED) {
    requireCsrfToken($conn, $userId);
}

$clientIp = getClientIpAddress();
requireRateLimit($conn, $clientIp, 'stripe_premium_by_ip', 10, 900);

$payload = readJsonPayload();
$requestRole = strtolower(trim((string)($payload['role'] ?? $userRole)));
$plan = trim(strtolower((string)($payload['plan'] ?? '')));
$billing = trim(strtolower((string)($payload['billing'] ?? 'monthly')));
$paymentMethodHint = trim(strtolower((string)($payload['payment_method_hint'] ?? '')));
$paymentLast4 = trim((string)($payload['payment_last4'] ?? ''));
$paymentBrand = trim(strtolower((string)($payload['payment_brand'] ?? '')));
$paymentType = trim(strtolower((string)($payload['payment_type'] ?? '')));

$validBilling = ['monthly', 'annual'];
if (!in_array($billing, $validBilling, true)) {
    apiError('VALIDATION_ERROR', 'Invalid billing period.', 422);
}

$planCatalog = [
    'customer' => [
        'starter' => ['monthly' => 0, 'annual' => 0],
        'premium-member' => ['monthly' => 999, 'annual' => 9990],
        'vip-buyer' => ['monthly' => 4999, 'annual' => 49990],
        'professional' => ['monthly' => 7999, 'annual' => 95988],
    ],
    'vendor' => [
        'growth' => ['monthly' => 4999, 'annual' => 49990],
        'enterprise' => ['monthly' => 14999, 'annual' => 149990],
    ],
];

if (!isset($planCatalog[$requestRole])) {
    apiError('VALIDATION_ERROR', 'Invalid account role selected.', 422);
}

if (!array_key_exists($plan, $planCatalog[$requestRole])) {
    apiError('VALIDATION_ERROR', 'Invalid plan selected for this role.', 422);
}

if ($paymentMethodHint !== '' && !preg_match('/^[a-z0-9_\-]{3,40}$/', $paymentMethodHint)) {
    apiError('VALIDATION_ERROR', 'Invalid payment method hint.', 422);
}

if ($paymentLast4 !== '' && !preg_match('/^[0-9]{4}$/', $paymentLast4)) {
    apiError('VALIDATION_ERROR', 'Invalid card last4 value.', 422);
}

if ($paymentBrand !== '' && !preg_match('/^[a-z0-9_\- ]{2,24}$/', $paymentBrand)) {
    apiError('VALIDATION_ERROR', 'Invalid payment brand value.', 422);
}

if ($paymentType !== '' && !preg_match('/^[a-z0-9_\- ]{2,24}$/', $paymentType)) {
    apiError('VALIDATION_ERROR', 'Invalid payment type value.', 422);
}

$amount = $planCatalog[$requestRole][$plan][$billing];
$unitAmount = (int)round($amount * 100);

$cfg = stripeGetConfig();
if ($cfg['secret_key'] === '') {
    apiError('STRIPE_NOT_CONFIGURED', 'Stripe is not configured.', 500);
}

$planLabel = ucwords(str_replace('-', ' ', $plan));
$planName = $planLabel . ' Plan (' . ucfirst($billing) . ')';
$customerEmail = trim((string)($currentUser['email'] ?? ''));

// Update success/cancel URLs for premium plans based on user role.
$successPath = $requestRole === 'vendor'
    ? '/vendor/vendorpanel.html?page=profile&payment=success&session_id={CHECKOUT_SESSION_ID}'
    : '/customer/dashboard.html?payment=success&session_id={CHECKOUT_SESSION_ID}';
$successUrl = $cfg['app_base_url'] . $successPath;
$cancelUrl = $cfg['app_base_url'] . '/pages/premiumplans.html?payment=cancelled&role=' . $requestRole;

$stripePayload = [
    'mode' => 'payment', // Using payment mode for simplicity, could be 'subscription' if we had Stripe products set up
    'success_url' => $successUrl,
    'cancel_url' => $cancelUrl,
    'client_reference_id' => 'user_' . $userId,
    'line_items[0][price_data][currency]' => 'lkr',
    'line_items[0][price_data][unit_amount]' => (string)$unitAmount,
    'line_items[0][price_data][product_data][name]' => 'BizLink Premium: ' . $planName,
    'line_items[0][quantity]' => '1',
    'metadata[type]' => 'premium_subscription',
    'metadata[account_role]' => $requestRole,
    'metadata[plan]' => $plan,
    'metadata[billing]' => $billing,
    'metadata[user_id]' => (string)$userId,
    'metadata[user_role]' => $userRole,
    'metadata[requested_role]' => $requestRole,
    'payment_intent_data[metadata][type]' => 'premium_subscription',
    'payment_intent_data[metadata][account_role]' => $requestRole,
    'payment_intent_data[metadata][plan]' => $plan,
    'payment_intent_data[metadata][billing]' => $billing,
    'payment_intent_data[metadata][user_id]' => (string)$userId,
    'payment_intent_data[metadata][user_role]' => $userRole,
    'payment_intent_data[metadata][requested_role]' => $requestRole,
];

if ($customerEmail !== '') {
    $stripePayload['customer_email'] = $customerEmail;
}

if ($paymentMethodHint !== '') {
    $stripePayload['metadata[payment_method_hint]'] = $paymentMethodHint;
    $stripePayload['payment_intent_data[metadata][payment_method_hint]'] = $paymentMethodHint;
}

if ($paymentLast4 !== '') {
    $stripePayload['metadata[payment_last4]'] = $paymentLast4;
    $stripePayload['payment_intent_data[metadata][payment_last4]'] = $paymentLast4;
}

if ($paymentBrand !== '') {
    $stripePayload['metadata[payment_brand]'] = $paymentBrand;
    $stripePayload['payment_intent_data[metadata][payment_brand]'] = $paymentBrand;
}

if ($paymentType !== '') {
    $stripePayload['metadata[payment_type]'] = $paymentType;
    $stripePayload['payment_intent_data[metadata][payment_type]'] = $paymentType;
}

$stripeResult = stripeApiRequest('POST', 'checkout/sessions', $cfg['secret_key'], $stripePayload);
if (!$stripeResult['ok']) {
    apiError('STRIPE_CREATE_SESSION_FAILED', $stripeResult['error'] ?: 'Failed to create Stripe session.', 502);
}

$session = (array)$stripeResult['data'];
$sessionId = (string)($session['id'] ?? '');
$checkoutUrl = (string)($session['url'] ?? '');

if ($sessionId === '' || $checkoutUrl === '') {
    apiError('STRIPE_INVALID_SESSION', 'Stripe session response is missing required fields.', 502);
}

apiSuccess([
    'session_id' => $sessionId,
    'checkout_url' => $checkoutUrl,
    'plan' => $plan,
    'role' => $requestRole,
    'billing' => $billing,
    'selected_payment_method' => $paymentMethodHint,
    'amount' => $amount,
    'currency' => 'LKR',
    'publishable_key' => $cfg['publishable_key']
], 'Premium plan checkout session created.', 'STRIPE_PREMIUM_SESSION_CREATED');

$conn->close();
?>
