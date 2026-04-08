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

requireAuth(['customer']);

$currentUser = getCurrentUser();
$userId = (int)($currentUser['user_id'] ?? 0);
if ($userId <= 0) {
    apiError('UNAUTHORIZED', 'Unauthorized user.', 401);
}

if (CSRF_ENABLED) {
    requireCsrfToken($conn, $userId);
}

$clientIp = getClientIpAddress();
requireRateLimit($conn, $clientIp, 'stripe_checkout_by_ip', 30, 900);
requireRateLimit($conn, 'user:' . $userId, 'stripe_checkout_by_user', 20, 900);

$payload = readJsonPayload();
$orderId = isset($payload['order_id']) ? (int)$payload['order_id'] : 0;

if ($orderId <= 0) {
    apiError('VALIDATION_ERROR', 'order_id is required.', 422, [
        ['field' => 'order_id', 'message' => 'order_id must be a positive integer.']
    ]);
}

$cfg = stripeGetConfig();
if ($cfg['secret_key'] === '') {
    apiError('STRIPE_NOT_CONFIGURED', 'Stripe is not configured. Add STRIPE_SECRET_KEY to environment.', 500);
}

$customerStmt = $conn->prepare('SELECT customer_id FROM customers WHERE user_id = ? LIMIT 1');
if (!$customerStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare customer query.', 500);
}
$customerStmt->bind_param('i', $userId);
$customerStmt->execute();
$customerRow = $customerStmt->get_result()->fetch_assoc();
$customerStmt->close();

$customerId = (int)($customerRow['customer_id'] ?? 0);
if ($customerId <= 0) {
    apiError('CUSTOMER_NOT_FOUND', 'Customer profile not found for this user.', 404);
}

$orderStmt = $conn->prepare(
    'SELECT order_id, order_number, customer_id, total_amount, currency, payment_status
     FROM orders
     WHERE order_id = ? AND customer_id = ?
     LIMIT 1'
);
if (!$orderStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare order query.', 500);
}
$orderStmt->bind_param('ii', $orderId, $customerId);
$orderStmt->execute();
$order = $orderStmt->get_result()->fetch_assoc();
$orderStmt->close();

if (!$order) {
    apiError('ORDER_NOT_FOUND', 'Order not found for this customer.', 404);
}

$currentPaymentStatus = strtolower((string)($order['payment_status'] ?? ''));
$payableStatuses = ['unpaid', 'failed', 'partially_paid'];
if (!in_array($currentPaymentStatus, $payableStatuses, true)) {
    apiError('ORDER_NOT_PAYABLE', 'This order is not payable in its current state.', 409, [
        ['field' => 'payment_status', 'message' => 'Current status: ' . $currentPaymentStatus]
    ]);
}

$totalAmount = (float)($order['total_amount'] ?? 0);
$unitAmount = (int)round($totalAmount * 100);
if ($unitAmount <= 0) {
    apiError('INVALID_ORDER_TOTAL', 'Order total must be greater than zero.', 422);
}

$currency = strtolower(trim((string)($order['currency'] ?? 'lkr')));
if ($currency === '') {
    $currency = 'lkr';
}

$customerEmail = trim((string)($currentUser['email'] ?? ''));

$successUrl = $cfg['success_url'] . '&order_id=' . $orderId;
$cancelUrl = $cfg['cancel_url'] . '&order_id=' . $orderId;

$stripePayload = [
    'mode' => 'payment',
    'success_url' => $successUrl,
    'cancel_url' => $cancelUrl,
    'client_reference_id' => (string)$orderId,
    'line_items[0][price_data][currency]' => $currency,
    'line_items[0][price_data][unit_amount]' => (string)$unitAmount,
    'line_items[0][price_data][product_data][name]' => 'Order #' . (string)$order['order_number'],
    'line_items[0][quantity]' => '1',
    'metadata[order_id]' => (string)$orderId,
    'metadata[order_number]' => (string)$order['order_number'],
    'metadata[customer_id]' => (string)$customerId,
    'payment_intent_data[metadata][order_id]' => (string)$orderId,
    'payment_intent_data[metadata][order_number]' => (string)$order['order_number'],
    'payment_intent_data[metadata][customer_id]' => (string)$customerId
];

if ($customerEmail !== '') {
    $stripePayload['customer_email'] = $customerEmail;
}

$stripeResult = stripeApiRequest('POST', 'checkout/sessions', $cfg['secret_key'], $stripePayload);
if (!$stripeResult['ok']) {
    apiError('STRIPE_CREATE_SESSION_FAILED', $stripeResult['error'] ?: 'Failed to create Stripe checkout session.', 502, [], [
        'stripe_status' => $stripeResult['status']
    ]);
}

$session = (array)$stripeResult['data'];
$sessionId = (string)($session['id'] ?? '');
$checkoutUrl = (string)($session['url'] ?? '');
$paymentIntentId = (string)($session['payment_intent'] ?? '');

if ($sessionId === '' || $checkoutUrl === '') {
    apiError('STRIPE_INVALID_SESSION', 'Stripe session response is missing required fields.', 502);
}

$existingPaymentStmt = $conn->prepare('SELECT payment_id FROM payments WHERE transaction_reference = ? LIMIT 1');
if (!$existingPaymentStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare payment lookup query.', 500);
}
$existingPaymentStmt->bind_param('s', $sessionId);
$existingPaymentStmt->execute();
$existingPayment = $existingPaymentStmt->get_result()->fetch_assoc();
$existingPaymentStmt->close();

if ($existingPayment) {
    $paymentId = (int)$existingPayment['payment_id'];
    $updatePaymentStmt = $conn->prepare(
        'UPDATE payments
         SET payment_amount = ?, payment_status = ?, transaction_id = ?, gateway_name = ?, updated_at = NOW()
         WHERE payment_id = ?'
    );
    if ($updatePaymentStmt) {
        $pendingStatus = 'pending';
        $gatewayName = 'stripe';
        $updatePaymentStmt->bind_param('dsssi', $totalAmount, $pendingStatus, $paymentIntentId, $gatewayName, $paymentId);
        $updatePaymentStmt->execute();
        $updatePaymentStmt->close();
    }
} else {
    $insertPaymentStmt = $conn->prepare(
        'INSERT INTO payments (
            order_id,
            payment_method,
            payment_amount,
            payment_date,
            payment_status,
            transaction_id,
            transaction_reference,
            gateway_name,
            gateway_response,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, NOW(), NOW())'
    );

    if ($insertPaymentStmt) {
        $paymentMethod = 'credit_card';
        $pendingStatus = 'pending';
        $gatewayName = 'stripe';
        $gatewayResponse = json_encode([
            'stripe_checkout_session_id' => $sessionId,
            'created_via' => 'create_stripe_checkout_session.php'
        ]);
        $insertPaymentStmt->bind_param(
            'isdsssss',
            $orderId,
            $paymentMethod,
            $totalAmount,
            $pendingStatus,
            $paymentIntentId,
            $sessionId,
            $gatewayName,
            $gatewayResponse
        );
        $insertPaymentStmt->execute();
        $insertPaymentStmt->close();
    }
}

apiSuccess([
    'session_id' => $sessionId,
    'checkout_url' => $checkoutUrl,
    'order_id' => $orderId,
    'order_number' => (string)$order['order_number'],
    'amount' => $totalAmount,
    'currency' => strtoupper($currency),
    'publishable_key' => $cfg['publishable_key']
], 'Stripe checkout session created.', 'STRIPE_CHECKOUT_SESSION_CREATED');

$conn->close();
