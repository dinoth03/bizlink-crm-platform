<?php

require_once 'api_helpers.php';

function stripeGetConfig(): array {
    $publishableKey = trim((string)(getenv('STRIPE_PUBLISHABLE_KEY') ?: ''));
    $secretKey = trim((string)(getenv('STRIPE_SECRET_KEY') ?: ''));
    $webhookSecret = trim((string)(getenv('STRIPE_WEBHOOK_SECRET') ?: ''));
    $appBaseUrl = trim((string)(getenv('STRIPE_APP_BASE_URL') ?: ''));

    if ($appBaseUrl === '') {
        $origin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
        if ($origin !== '') {
            $appBaseUrl = rtrim($origin, '/');
            if (stripos($appBaseUrl, 'localhost') !== false || stripos($appBaseUrl, '127.0.0.1') !== false) {
                $appBaseUrl .= '/bizlink-crm-platform';
            }
        } else {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $appBaseUrl = $scheme . '://' . $host;
        }
    }

    $successUrl = trim((string)(getenv('STRIPE_SUCCESS_URL') ?: ''));
    $cancelUrl = trim((string)(getenv('STRIPE_CANCEL_URL') ?: ''));

    if ($successUrl === '') {
        $successUrl = rtrim($appBaseUrl, '/') . '/customer/dashboard.html?payment_status=success&session_id={CHECKOUT_SESSION_ID}';
    }
    if ($cancelUrl === '') {
        $cancelUrl = rtrim($appBaseUrl, '/') . '/customer/dashboard.html?payment_status=cancelled';
    }

    return [
        'publishable_key' => $publishableKey,
        'secret_key' => $secretKey,
        'webhook_secret' => $webhookSecret,
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'app_base_url' => $appBaseUrl
    ];
}

function stripeApiRequest(string $method, string $endpoint, string $secretKey, array $formFields = []): array {
    if (!function_exists('curl_init')) {
        return [
            'ok' => false,
            'status' => 500,
            'error' => 'cURL extension is required for Stripe integration.',
            'data' => null
        ];
    }

    $url = 'https://api.stripe.com/v1/' . ltrim($endpoint, '/');
    $ch = curl_init();

    $method = strtoupper($method);
    if ($method === 'GET' && !empty($formFields)) {
        $url .= '?' . http_build_query($formFields);
    }

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ];

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($formFields);
    }

    curl_setopt_array($ch, $options);

    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        return [
            'ok' => false,
            'status' => 500,
            'error' => 'Stripe request failed: ' . $curlError,
            'data' => null
        ];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return [
            'ok' => false,
            'status' => max(500, $statusCode),
            'error' => 'Invalid response received from Stripe.',
            'data' => null
        ];
    }

    if ($statusCode < 200 || $statusCode >= 300) {
        $stripeMessage = (string)($decoded['error']['message'] ?? 'Stripe API error.');
        return [
            'ok' => false,
            'status' => $statusCode,
            'error' => $stripeMessage,
            'data' => $decoded
        ];
    }

    return [
        'ok' => true,
        'status' => $statusCode,
        'error' => '',
        'data' => $decoded
    ];
}

function stripeParseSignatureHeader(string $signatureHeader): array {
    $parts = array_filter(array_map('trim', explode(',', $signatureHeader)));
    $parsed = ['t' => '', 'v1' => []];

    foreach ($parts as $part) {
        $pair = explode('=', $part, 2);
        if (count($pair) !== 2) {
            continue;
        }

        $key = trim($pair[0]);
        $value = trim($pair[1]);

        if ($key === 't') {
            $parsed['t'] = $value;
        }
        if ($key === 'v1') {
            $parsed['v1'][] = $value;
        }
    }

    return $parsed;
}

function stripeVerifyWebhookSignature(string $payload, string $signatureHeader, string $webhookSecret, int $toleranceSeconds = 300): bool {
    if ($payload === '' || $signatureHeader === '' || $webhookSecret === '') {
        return false;
    }

    $parsed = stripeParseSignatureHeader($signatureHeader);
    $timestamp = (int)($parsed['t'] ?? 0);
    $signatures = (array)($parsed['v1'] ?? []);

    if ($timestamp <= 0 || empty($signatures)) {
        return false;
    }

    if (abs(time() - $timestamp) > $toleranceSeconds) {
        return false;
    }

    $signedPayload = $timestamp . '.' . $payload;
    $expectedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);

    foreach ($signatures as $candidate) {
        if (hash_equals($expectedSignature, $candidate)) {
            return true;
        }
    }

    return false;
}
