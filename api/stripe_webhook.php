<?php

require 'config.php';
require_once 'api_helpers.php';
require_once 'stripe_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

$cfg = stripeGetConfig();
if ($cfg['webhook_secret'] === '') {
    apiError('STRIPE_WEBHOOK_NOT_CONFIGURED', 'Stripe webhook secret is missing.', 500);
}

$payload = file_get_contents('php://input');
$signatureHeader = (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');

if ($payload === false || $payload === '') {
    apiError('INVALID_PAYLOAD', 'Missing webhook payload.', 400);
}

if (!stripeVerifyWebhookSignature($payload, $signatureHeader, $cfg['webhook_secret'])) {
    apiError('INVALID_SIGNATURE', 'Invalid Stripe webhook signature.', 400);
}

$event = json_decode($payload, true);
if (!is_array($event)) {
    apiError('INVALID_JSON', 'Invalid webhook JSON payload.', 400);
}

$eventId = (string)($event['id'] ?? '');
$eventType = (string)($event['type'] ?? '');
$eventObject = (array)($event['data']['object'] ?? []);

if ($eventId === '' || $eventType === '') {
    apiError('INVALID_EVENT', 'Webhook event is missing id or type.', 400);
}

$conn->query(
    'CREATE TABLE IF NOT EXISTS stripe_webhook_events (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        event_id VARCHAR(255) NOT NULL,
        event_type VARCHAR(120) NOT NULL,
        processed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        payload_json LONGTEXT NULL,
        UNIQUE KEY uniq_event_id (event_id),
        INDEX idx_event_type (event_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$insertEventStmt = $conn->prepare(
    'INSERT INTO stripe_webhook_events (event_id, event_type, payload_json) VALUES (?, ?, ?)'
);
if (!$insertEventStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare webhook event insert.', 500);
}

$eventPayloadJson = json_encode($event);
$insertEventStmt->bind_param('sss', $eventId, $eventType, $eventPayloadJson);
$insertEventOk = $insertEventStmt->execute();
$insertEventError = $insertEventStmt->error;
$insertEventStmt->close();

if (!$insertEventOk) {
    if (stripos($insertEventError, 'Duplicate entry') !== false) {
        apiSuccess(['event_id' => $eventId], 'Duplicate webhook ignored.', 'STRIPE_WEBHOOK_DUPLICATE', 200);
    }
    apiError('DB_WRITE_ERROR', 'Unable to persist webhook event.', 500);
}

function extractOrderIdFromStripeEvent(array $eventObject): int {
    $orderId = (int)($eventObject['metadata']['order_id'] ?? 0);
    if ($orderId > 0) {
        return $orderId;
    }

    $clientReference = (string)($eventObject['client_reference_id'] ?? '');
    if (ctype_digit($clientReference)) {
        return (int)$clientReference;
    }

    return 0;
}

function upsertStripePaymentRecord(mysqli $conn, array $data): void {
    $orderId = (int)($data['order_id'] ?? 0);
    if ($orderId <= 0) {
        return;
    }

    $amount = (float)($data['amount'] ?? 0);
    $paymentStatus = (string)($data['payment_status'] ?? 'pending');
    $paymentIntentId = (string)($data['payment_intent_id'] ?? '');
    $checkoutSessionId = (string)($data['checkout_session_id'] ?? '');
    $refundAmount = (float)($data['refund_amount'] ?? 0);
    $gatewayResponse = (string)($data['gateway_response'] ?? '{}');

    $existingStmt = $conn->prepare(
        'SELECT payment_id FROM payments
         WHERE (transaction_reference = ? AND transaction_reference <> "")
            OR (transaction_id = ? AND transaction_id <> "")
            OR order_id = ?
         ORDER BY payment_id DESC
         LIMIT 1'
    );
    if (!$existingStmt) {
        return;
    }

    $existingStmt->bind_param('ssi', $checkoutSessionId, $paymentIntentId, $orderId);
    $existingStmt->execute();
    $existing = $existingStmt->get_result()->fetch_assoc();
    $existingStmt->close();

    if ($existing) {
        $paymentId = (int)$existing['payment_id'];
        $updateStmt = $conn->prepare(
            'UPDATE payments
             SET payment_amount = ?,
                 payment_status = ?,
                 transaction_id = ?,
                 transaction_reference = ?,
                 gateway_name = ?,
                 gateway_response = ?,
                 refund_amount = ?,
                 refund_date = CASE WHEN ? > 0 THEN NOW() ELSE refund_date END,
                 updated_at = NOW()
             WHERE payment_id = ?'
        );

        if ($updateStmt) {
            $gatewayName = 'stripe';
            $updateStmt->bind_param(
                'dsssssddi',
                $amount,
                $paymentStatus,
                $paymentIntentId,
                $checkoutSessionId,
                $gatewayName,
                $gatewayResponse,
                $refundAmount,
                $refundAmount,
                $paymentId
            );
            $updateStmt->execute();
            $updateStmt->close();
        }

        return;
    }

    $insertStmt = $conn->prepare(
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
            refund_amount,
            refund_date,
            created_at,
            updated_at
         ) VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, CASE WHEN ? > 0 THEN NOW() ELSE NULL END, NOW(), NOW())'
    );

    if (!$insertStmt) {
        return;
    }

    $paymentMethod = 'credit_card';
    $gatewayName = 'stripe';
    $insertStmt->bind_param(
        'isdsssssdd',
        $orderId,
        $paymentMethod,
        $amount,
        $paymentStatus,
        $paymentIntentId,
        $checkoutSessionId,
        $gatewayName,
        $gatewayResponse,
        $refundAmount,
        $refundAmount
    );
    $insertStmt->execute();
    $insertStmt->close();
}

function updateOrderPaymentStatus(mysqli $conn, int $orderId, string $paymentStatus): void {
    if ($orderId <= 0 || $paymentStatus === '') {
        return;
    }

    $stmt = $conn->prepare('UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE order_id = ? LIMIT 1');
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('si', $paymentStatus, $orderId);
    $stmt->execute();
    $stmt->close();
}

$orderId = extractOrderIdFromStripeEvent($eventObject);
$checkoutSessionId = (string)($eventObject['id'] ?? '');
$paymentIntentId = (string)($eventObject['payment_intent'] ?? '');

if ($eventType === 'checkout.session.completed' || $eventType === 'checkout.session.async_payment_succeeded') {
    $amountTotal = ((int)($eventObject['amount_total'] ?? 0)) / 100;
    $gatewayResponse = json_encode([
        'event_id' => $eventId,
        'event_type' => $eventType,
        'payment_status' => (string)($eventObject['payment_status'] ?? ''),
        'checkout_status' => (string)($eventObject['status'] ?? '')
    ]);

    upsertStripePaymentRecord($conn, [
        'order_id' => $orderId,
        'amount' => $amountTotal,
        'payment_status' => 'completed',
        'payment_intent_id' => $paymentIntentId,
        'checkout_session_id' => $checkoutSessionId,
        'gateway_response' => $gatewayResponse
    ]);

    updateOrderPaymentStatus($conn, $orderId, 'paid');
}

if ($eventType === 'payment_intent.payment_failed') {
    $paymentIntentId = (string)($eventObject['id'] ?? $paymentIntentId);
    $orderId = (int)($eventObject['metadata']['order_id'] ?? $orderId);
    $amount = ((int)($eventObject['amount'] ?? 0)) / 100;

    $gatewayResponse = json_encode([
        'event_id' => $eventId,
        'event_type' => $eventType,
        'last_payment_error' => $eventObject['last_payment_error']['message'] ?? ''
    ]);

    upsertStripePaymentRecord($conn, [
        'order_id' => $orderId,
        'amount' => $amount,
        'payment_status' => 'failed',
        'payment_intent_id' => $paymentIntentId,
        'checkout_session_id' => '',
        'gateway_response' => $gatewayResponse
    ]);

    updateOrderPaymentStatus($conn, $orderId, 'failed');
}

if ($eventType === 'charge.refunded') {
    $paymentIntentId = (string)($eventObject['payment_intent'] ?? $paymentIntentId);
    $amountCaptured = ((int)($eventObject['amount'] ?? 0)) / 100;
    $amountRefunded = ((int)($eventObject['amount_refunded'] ?? 0)) / 100;

    if ($orderId <= 0 && $paymentIntentId !== '') {
        $lookupStmt = $conn->prepare('SELECT order_id FROM payments WHERE transaction_id = ? ORDER BY payment_id DESC LIMIT 1');
        if ($lookupStmt) {
            $lookupStmt->bind_param('s', $paymentIntentId);
            $lookupStmt->execute();
            $lookup = $lookupStmt->get_result()->fetch_assoc();
            $lookupStmt->close();
            $orderId = (int)($lookup['order_id'] ?? 0);
        }
    }

    $gatewayResponse = json_encode([
        'event_id' => $eventId,
        'event_type' => $eventType,
        'amount_refunded' => $amountRefunded
    ]);

    upsertStripePaymentRecord($conn, [
        'order_id' => $orderId,
        'amount' => $amountCaptured,
        'payment_status' => 'refunded',
        'payment_intent_id' => $paymentIntentId,
        'checkout_session_id' => '',
        'refund_amount' => $amountRefunded,
        'gateway_response' => $gatewayResponse
    ]);

    if ($amountRefunded > 0) {
        updateOrderPaymentStatus($conn, $orderId, 'refunded');
    }
}

apiSuccess([
    'event_id' => $eventId,
    'event_type' => $eventType
], 'Webhook processed.', 'STRIPE_WEBHOOK_PROCESSED', 200);

$conn->close();
