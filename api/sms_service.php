<?php
// Lightweight SMS service abstraction.
// Supports Twilio (if TWILIO_SID/TWILIO_AUTH_TOKEN/TWILIO_FROM present)
// or a generic HTTP API using SMS_API_URL and SMS_API_KEY.
require_once __DIR__ . '/api_helpers.php';

function smsSetting(string $key, $default = '') {
    // load from env or mail_settings local
    $val = getenv($key);
    if ($val !== false && $val !== '') return $val;
    return $default;
}

function sendSms(string $to, string $message): array {
    $to = trim($to);
    if ($to === '') {
        return ['ok' => false, 'error' => 'NO_RECIPIENT'];
    }

    $twilioSid = smsSetting('TWILIO_SID', '');
    $twilioToken = smsSetting('TWILIO_AUTH_TOKEN', '');
    $twilioFrom = smsSetting('TWILIO_FROM', '');

    if ($twilioSid !== '' && $twilioToken !== '' && $twilioFrom !== '') {
        // Use Twilio REST API
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . urlencode($twilioSid) . '/Messages.json';
        $post = http_build_query([
            'From' => $twilioFrom,
            'To' => $to,
            'Body' => $message
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_USERPWD, $twilioSid . ':' . $twilioToken);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $resp = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            error_log("[SMS] Twilio curl error: {$err}");
            return ['ok' => false, 'error' => 'CURL_ERROR', 'detail' => $err];
        }

        $decoded = json_decode($resp, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['ok' => true, 'provider' => 'twilio', 'response' => $decoded ?? $resp];
        }

        return ['ok' => false, 'provider' => 'twilio', 'status' => $httpCode, 'response' => $decoded ?? $resp];
    }

    // Generic HTTP provider
    $apiUrl = smsSetting('SMS_API_URL', '');
    $apiKey = smsSetting('SMS_API_KEY', '');
    $from = smsSetting('SMS_FROM', '');

    if ($apiUrl !== '') {
        $payload = ['to' => $to, 'message' => $message];
        if ($from !== '') $payload['from'] = $from;

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $resp = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            error_log("[SMS] Generic provider curl error: {$err}");
            return ['ok' => false, 'error' => 'CURL_ERROR', 'detail' => $err];
        }

        $decoded = json_decode($resp, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['ok' => true, 'provider' => 'generic', 'response' => $decoded ?? $resp];
        }

        return ['ok' => false, 'provider' => 'generic', 'status' => $httpCode, 'response' => $decoded ?? $resp];
    }

    // No SMS configured
    return ['ok' => false, 'error' => 'SMS_NOT_CONFIGURED'];
}

?>
