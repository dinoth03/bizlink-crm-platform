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

// Only vendors can buy premium plans
requireAuth(['vendor']);

$currentUser = getCurrentUser();
$userId = (int)($currentUser['user_id'] ?? 0);
if ($userId <= 0) {
    apiError('UNAUTHORIZED', 'Unauthorized user.', 401);
}

if (CSRF_ENABLED) {
    requireCsrfToken($conn, $userId);
}

$clientIp = getClientIpAddress();
requireRateLimit($conn, $clientIp, 'stripe_premium_by_ip', 10, 900);

$payload = readJsonPayload();
$plan = trim(strtolower((string)($payload['plan'] ?? '')));
$billing = trim(strtolower((string)($payload['billing'] ?? 'monthly')));

$validPlans = ['starter', 'professional'];
if (!in_array($plan, $validPlans, true)) {
    apiError('VALIDATION_ERROR', 'Invalid plan selected.', 422);
}

$validBilling = ['monthly', 'annual'];
if (!in_array($billing, $validBilling, true)) {
    apiError('VALIDATION_ERROR', 'Invalid billing period.', 422);
}

// Define Prices (in LKR)
$prices = [
    'starter' => [
        'monthly' => 2999,
        'annual' => 35988
    ],
    'professional' => [
        'monthly' => 7999,
        'annual' => 95988
    ]
];

$amount = $prices[$plan][$billing];
$unitAmount = (int)round($amount * 100);

$cfg = stripeGetConfig();
if ($cfg['secret_key'] === '') {
    apiError('STRIPE_NOT_CONFIGURED', 'Stripe is not configured.', 500);
}

$planName = ucfirst($plan) . ' Plan (' . ucfirst($billing) . ')';
$customerEmail = trim((string)($currentUser['email'] ?? ''));

// Update success/cancel URLs for premium plans
$successUrl = $cfg['app_base_url'] . '/vendor/vendorpanel.html?page=profile&payment=success&session_id={CHECKOUT_SESSION_ID}';
$cancelUrl = $cfg['app_base_url'] . '/pages/premiumplans.html?payment=cancelled';

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
    'metadata[plan]' => $plan,
    'metadata[billing]' => $billing,
    'metadata[user_id]' => (string)$userId,
    'payment_intent_data[metadata][type]' => 'premium_subscription',
    'payment_intent_data[metadata][plan]' => $plan,
    'payment_intent_data[metadata][billing]' => $billing,
    'payment_intent_data[metadata][user_id]' => (string)$userId,
];

if ($customerEmail !== '') {
    $stripePayload['customer_email'] = $customerEmail;
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
    'billing' => $billing,
    'amount' => $amount,
    'currency' => 'LKR',
    'publishable_key' => $cfg['publishable_key']
], 'Premium plan checkout session created.', 'STRIPE_PREMIUM_SESSION_CREATED');

$conn->close();
?>
