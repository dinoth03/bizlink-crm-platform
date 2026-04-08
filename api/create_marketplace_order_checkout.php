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
requireRateLimit($conn, $clientIp, 'marketplace_buy_now_by_ip', 40, 900);
requireRateLimit($conn, 'user:' . $userId, 'marketplace_buy_now_by_user', 25, 900);

$payload = readJsonPayload();
$productId = isset($payload['product_id']) ? (int)$payload['product_id'] : 0;
$quantity = isset($payload['quantity']) ? (int)$payload['quantity'] : 1;

if ($productId <= 0) {
    apiError('VALIDATION_ERROR', 'product_id is required.', 422, [
        ['field' => 'product_id', 'message' => 'product_id must be a positive integer.']
    ]);
}
if ($quantity <= 0 || $quantity > 99) {
    apiError('VALIDATION_ERROR', 'quantity must be between 1 and 99.', 422, [
        ['field' => 'quantity', 'message' => 'quantity must be between 1 and 99.']
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
$customer = $customerStmt->get_result()->fetch_assoc();
$customerStmt->close();

$customerId = (int)($customer['customer_id'] ?? 0);
if ($customerId <= 0) {
    apiError('CUSTOMER_NOT_FOUND', 'Customer profile not found for this user.', 404);
}

$productStmt = $conn->prepare(
    'SELECT p.product_id, p.product_name, p.vendor_id, p.price, p.quantity_in_stock, p.is_active, v.business_name
     FROM products p
     LEFT JOIN vendors v ON p.vendor_id = v.vendor_id
     WHERE p.product_id = ?
     LIMIT 1'
);
if (!$productStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare product query.', 500);
}
$productStmt->bind_param('i', $productId);
$productStmt->execute();
$product = $productStmt->get_result()->fetch_assoc();
$productStmt->close();

if (!$product) {
    apiError('PRODUCT_NOT_FOUND', 'Selected product was not found.', 404);
}
if ((int)($product['is_active'] ?? 0) !== 1) {
    apiError('PRODUCT_UNAVAILABLE', 'Selected product is not currently available.', 409);
}

$availableStock = (int)($product['quantity_in_stock'] ?? 0);
if ($availableStock > 0 && $quantity > $availableStock) {
    apiError('INSUFFICIENT_STOCK', 'Requested quantity exceeds available stock.', 409, [
        ['field' => 'quantity', 'message' => 'Available stock: ' . $availableStock]
    ]);
}

$price = (float)($product['price'] ?? 0);
if ($price <= 0) {
    apiError('INVALID_PRODUCT_PRICE', 'Selected product has an invalid price.', 422);
}

$orderSubtotal = round($price * $quantity, 2);
$orderTotal = $orderSubtotal;
$currency = 'LKR';
$shippingAddress = 'To be confirmed at checkout';
$orderNotes = 'Created from marketplace Buy Now with Stripe checkout';

$userInfoStmt = $conn->prepare('SELECT city, country FROM users WHERE user_id = ? LIMIT 1');
if ($userInfoStmt) {
    $userInfoStmt->bind_param('i', $userId);
    $userInfoStmt->execute();
    $userInfo = $userInfoStmt->get_result()->fetch_assoc();
    $userInfoStmt->close();

    $city = trim((string)($userInfo['city'] ?? ''));
    $country = trim((string)($userInfo['country'] ?? 'Sri Lanka'));
    if ($city !== '') {
        $shippingAddress = $city . ', ' . ($country !== '' ? $country : 'Sri Lanka');
    }
}

function generateOrderNumber(): string {
    return 'BL-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

function generateUniqueOrderNumber(mysqli $conn, int $maxAttempts = 5): string {
    $attempt = 0;
    while ($attempt < $maxAttempts) {
        $candidate = generateOrderNumber();
        $checkStmt = $conn->prepare('SELECT order_id FROM orders WHERE order_number = ? LIMIT 1');
        if (!$checkStmt) {
            apiError('DB_QUERY_ERROR', 'Failed to prepare order number check.', 500);
        }

        $checkStmt->bind_param('s', $candidate);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();

        if (!$exists) {
            return $candidate;
        }

        $attempt++;
    }

    return '';
}

$orderNumber = generateUniqueOrderNumber($conn, 5);

if ($orderNumber === '') {
    apiError('ORDER_NUMBER_GENERATION_FAILED', 'Unable to generate a unique order number.', 500);
}

$conn->begin_transaction();

try {
    $orderStatus = 'pending';
    $paymentStatus = 'unpaid';
    $vendorId = (int)$product['vendor_id'];

    $insertOrderStmt = $conn->prepare(
        'INSERT INTO orders (
            order_number,
            customer_id,
            vendor_id,
            order_status,
            payment_status,
            order_date,
            shipping_address,
            subtotal,
            discount_amount,
            tax_amount,
            shipping_cost,
            commission_amount,
            total_amount,
            currency,
            notes,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, 0, 0, 0, 0, ?, ?, ?, NOW(), NOW())'
    );

    if (!$insertOrderStmt) {
        throw new Exception('Failed to prepare order insert query.');
    }

    $insertOrderStmt->bind_param(
        'siisssddss',
        $orderNumber,
        $customerId,
        $vendorId,
        $orderStatus,
        $paymentStatus,
        $shippingAddress,
        $orderSubtotal,
        $orderTotal,
        $currency,
        $orderNotes
    );

    if (!$insertOrderStmt->execute()) {
        $err = $insertOrderStmt->error;
        $insertOrderStmt->close();
        throw new Exception('Failed to create order: ' . $err);
    }

    $orderId = (int)$insertOrderStmt->insert_id;
    $insertOrderStmt->close();

    $insertItemStmt = $conn->prepare(
        'INSERT INTO order_items (
            order_id,
            product_id,
            variant_id,
            product_name,
            price_at_purchase,
            quantity,
            discount_applied,
            subtotal,
            tax_amount,
            total_amount,
            created_at
        ) VALUES (?, ?, NULL, ?, ?, ?, 0, ?, 0, ?, NOW())'
    );

    if (!$insertItemStmt) {
        throw new Exception('Failed to prepare order item insert query.');
    }

    $productName = (string)$product['product_name'];
    $insertItemStmt->bind_param(
        'iisdidd',
        $orderId,
        $productId,
        $productName,
        $price,
        $quantity,
        $orderSubtotal,
        $orderTotal
    );

    if (!$insertItemStmt->execute()) {
        $err = $insertItemStmt->error;
        $insertItemStmt->close();
        throw new Exception('Failed to create order item: ' . $err);
    }
    $insertItemStmt->close();

    $unitAmount = (int)round($price * 100);
    if ($unitAmount <= 0) {
        throw new Exception('Invalid Stripe unit amount.');
    }

    $customerEmail = trim((string)($currentUser['email'] ?? ''));

    $successUrl = $cfg['success_url'] . '&order_id=' . $orderId;
    $cancelUrl = $cfg['cancel_url'] . '&order_id=' . $orderId;

    $stripePayload = [
        'mode' => 'payment',
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'client_reference_id' => (string)$orderId,
        'line_items[0][price_data][currency]' => strtolower($currency),
        'line_items[0][price_data][unit_amount]' => (string)$unitAmount,
        'line_items[0][price_data][product_data][name]' => (string)$product['product_name'],
        'line_items[0][quantity]' => (string)$quantity,
        'metadata[order_id]' => (string)$orderId,
        'metadata[order_number]' => $orderNumber,
        'metadata[customer_id]' => (string)$customerId,
        'metadata[product_id]' => (string)$productId,
        'payment_intent_data[metadata][order_id]' => (string)$orderId,
        'payment_intent_data[metadata][order_number]' => $orderNumber,
        'payment_intent_data[metadata][customer_id]' => (string)$customerId
    ];

    if ($customerEmail !== '') {
        $stripePayload['customer_email'] = $customerEmail;
    }

    $stripeResult = stripeApiRequest('POST', 'checkout/sessions', $cfg['secret_key'], $stripePayload);
    if (!$stripeResult['ok']) {
        throw new Exception($stripeResult['error'] ?: 'Failed to create Stripe checkout session.');
    }

    $session = (array)$stripeResult['data'];
    $sessionId = (string)($session['id'] ?? '');
    $checkoutUrl = (string)($session['url'] ?? '');
    $paymentIntentId = (string)($session['payment_intent'] ?? '');

    if ($sessionId === '' || $checkoutUrl === '') {
        throw new Exception('Stripe session response is missing required fields.');
    }

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

    if (!$insertPaymentStmt) {
        throw new Exception('Failed to prepare payment insert query.');
    }

    $paymentMethod = 'credit_card';
    $pendingStatus = 'pending';
    $gatewayName = 'stripe';
    $gatewayResponse = json_encode([
        'stripe_checkout_session_id' => $sessionId,
        'created_via' => 'create_marketplace_order_checkout.php',
        'product_id' => $productId,
        'quantity' => $quantity
    ]);

    $insertPaymentStmt->bind_param(
        'isdsssss',
        $orderId,
        $paymentMethod,
        $orderTotal,
        $pendingStatus,
        $paymentIntentId,
        $sessionId,
        $gatewayName,
        $gatewayResponse
    );

    if (!$insertPaymentStmt->execute()) {
        $err = $insertPaymentStmt->error;
        $insertPaymentStmt->close();
        throw new Exception('Failed to create payment record: ' . $err);
    }
    $insertPaymentStmt->close();

    $conn->commit();

    apiSuccess([
        'order_id' => $orderId,
        'order_number' => $orderNumber,
        'product_id' => $productId,
        'product_name' => (string)$product['product_name'],
        'quantity' => $quantity,
        'amount' => $orderTotal,
        'currency' => $currency,
        'checkout_url' => $checkoutUrl,
        'session_id' => $sessionId
    ], 'Marketplace order created and Stripe checkout started.', 'MARKETPLACE_CHECKOUT_CREATED');
} catch (Throwable $exception) {
    $conn->rollback();
    apiError('MARKETPLACE_CHECKOUT_FAILED', $exception->getMessage(), 500);
}

$conn->close();
