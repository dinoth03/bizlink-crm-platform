<?php

/**
 * Mail Service - Handles email sending via SMTP or PHP mail()
 * Supports both production (SMTP) and development (mock) modes.
 */

// SMTP Configuration (populated from environment or config)
define('MAIL_DRIVER', getenv('MAIL_DRIVER') ?: 'php');  // 'php', 'smtp', or 'dev'
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'noreply@bizlink.local');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'BizLink CRM');

// SMTP-specific config (used if MAIL_DRIVER == 'smtp')
define('SMTP_HOST', getenv('SMTP_HOST') ?: '');
define('SMTP_PORT', (int)(getenv('SMTP_PORT') ?: 587));
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
define('SMTP_ENCRYPTION', getenv('SMTP_ENCRYPTION') ?: 'tls'); // 'tls' or 'ssl'

/**
 * Send email via configured mail driver.
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @param string|null $textBody Plain text alternative (optional)
 * @return array ['success' => bool, 'message' => string, 'logId' => string|null]
 */
function sendMail(string $to, string $subject, string $htmlBody, ?string $textBody = null): array {
    $to = strtolower(trim($to));
    
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'Invalid recipient email address.',
            'logId' => null
        ];
    }

    $logId = bin2hex(random_bytes(8));

    $driver = strtolower(trim(MAIL_DRIVER));
    
    if ($driver === 'smtp') {
        return sendViaSMTP($to, $subject, $htmlBody, $textBody, $logId);
    } elseif ($driver === 'dev') {
        return sendViaDevMode($to, $subject, $htmlBody, $textBody, $logId);
    } else {
        // Default to PHP mail() function
        return sendViaPhpMail($to, $subject, $htmlBody, $textBody, $logId);
    }
}

/**
 * Send via native PHP mail() function.
 * Requires sendmail or local SMTP to be configured on the server.
 */
function sendViaPhpMail(string $to, string $subject, string $htmlBody, ?string $textBody, string $logId): array {
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_ADDRESS . '>',
        'Reply-To: ' . MAIL_FROM_ADDRESS
    ];

    $headerString = implode("\r\n", $headers);

    if (@mail($to, $subject, $htmlBody, $headerString)) {
        error_log("[Mail Log ID: {$logId}] Email sent via php mail() to {$to}: {$subject}");
        return [
            'success' => true,
            'message' => 'Email sent successfully.',
            'logId' => $logId
        ];
    } else {
        error_log("[Mail Log ID: {$logId}] Failed to send email via php mail() to {$to}: {$subject}");
        return [
            'success' => false,
            'message' => 'Failed to send email. Please try again later.',
            'logId' => $logId
        ];
    }
}

/**
 * Send via SMTP using PHP streams (no external libraries needed).
 * Supports TLS/SSL encryption.
 */
function sendViaSMTP(string $to, string $subject, string $htmlBody, ?string $textBody, string $logId): array {
    if (empty(SMTP_HOST) || empty(SMTP_USERNAME) || empty(SMTP_PASSWORD)) {
        error_log("[Mail Log ID: {$logId}] SMTP credentials not configured for {$to}");
        return [
            'success' => false,
            'message' => 'SMTP configuration missing.',
            'logId' => $logId
        ];
    }

    try {
        $encryptionPrefix = SMTP_ENCRYPTION === 'ssl' ? 'ssl://' : '';
        $socket = @fsockopen($encryptionPrefix . SMTP_HOST, SMTP_PORT, $errno, $errstr, 10);

        if (!$socket) {
            throw new Exception("SMTP connection failed: {$errstr}");
        }

        // Enable TLS if needed
        if (SMTP_ENCRYPTION === 'tls') {
            fgets($socket);
            fputs($socket, "EHLO " . SMTP_HOST . "\r\n");
            fgets($socket);
            fputs($socket, "STARTTLS\r\n");
            fgets($socket);
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        }

        // SMTP handshake
        fgets($socket); // Welcome message
        fputs($socket, "EHLO " . SMTP_HOST . "\r\n");
        fgets($socket);

        // Authenticate
        $auth = base64_encode(SMTP_USERNAME . ':' . SMTP_PASSWORD);
        fputs($socket, "AUTH LOGIN\r\n");
        fgets($socket);
        fputs($socket, base64_encode(SMTP_USERNAME) . "\r\n");
        fgets($socket);
        fputs($socket, base64_encode(SMTP_PASSWORD) . "\r\n");
        $authResponse = fgets($socket);

        if (strpos($authResponse, '235') === false) {
            throw new Exception('SMTP authentication failed');
        }

        // Send email
        fputs($socket, "MAIL FROM: <" . MAIL_FROM_ADDRESS . ">\r\n");
        fgets($socket);
        fputs($socket, "RCPT TO: <{$to}>\r\n");
        fgets($socket);
        fputs($socket, "DATA\r\n");
        fgets($socket);

        $emailBody = buildEmailMessage($to, $subject, $htmlBody, $textBody);
        fputs($socket, $emailBody . "\r\n.\r\n");
        $response = fgets($socket);

        if (strpos($response, '250') === false) {
            throw new Exception('SMTP message rejected');
        }

        fputs($socket, "QUIT\r\n");
        fclose($socket);

        error_log("[Mail Log ID: {$logId}] Email sent via SMTP to {$to}: {$subject}");
        return [
            'success' => true,
            'message' => 'Email sent successfully.',
            'logId' => $logId
        ];
    } catch (Throwable $e) {
        error_log("[Mail Log ID: {$logId}] SMTP send failed for {$to}: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to send email. Please try again later.',
            'logId' => $logId
        ];
    }
}

/**
 * Development mode: logs email instead of sending.
 * Used for local development without real SMTP setup.
 */
function sendViaDevMode(string $to, string $subject, string $htmlBody, ?string $textBody, string $logId): array {
    $devLog = [
        'logId' => $logId,
        'timestamp' => date('Y-m-d H:i:s'),
        'to' => $to,
        'subject' => $subject,
        'htmlBody' => $htmlBody,
        'textBody' => $textBody
    ];

    error_log("[Mail Log ID: {$logId}] DEV MODE - Email logged instead of sent");
    error_log(json_encode($devLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return [
        'success' => true,
        'message' => 'Email logged in development mode.',
        'logId' => $logId
    ];
}

/**
 * Build a complete SMTP-formatted email message.
 */
function buildEmailMessage(string $to, string $subject, string $htmlBody, ?string $textBody): string {
    $boundary = '----=_Part_' . uniqid();
    $headers = [
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_ADDRESS . '>',
        'To: ' . $to,
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'Date: ' . date('r'),
        'Message-ID: <' . time() . '.' . uniqid() . '@' . $_SERVER['SERVER_NAME'] . '>'
    ];

    $body = implode("\r\n", $headers) . "\r\n\r\n";

    if ($textBody) {
        $body .= "--" . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $body .= $textBody . "\r\n\r\n";
    }

    $body .= "--" . $boundary . "\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $htmlBody . "\r\n\r\n";
    $body .= "--" . $boundary . "--";

    return $body;
}

/**
 * Generate verification email HTML.
 */
function getVerificationEmailHtml(string $verificationLink, string $userName = 'User'): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; }
        .btn { display: inline-block; background: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; margin: 20px 0; }
        .footer { text-align: center; color: #999; font-size: 12px; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Welcome to BizLink CRM</h1>
        <p>Hi {$userName},</p>
        <p>Thank you for signing up! Please verify your email address to activate your account.</p>
        <div style="text-align: center;">
            <a href="{$verificationLink}" class="btn">Verify Email</a>
        </div>
        <p>Or copy and paste this link in your browser:</p>
        <p style="word-break: break-all; color: #666;">{$verificationLink}</p>
        <p>This link will expire in 24 hours.</p>
        <div class="footer">
            <p>&copy; 2026 BizLink CRM. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Generate password reset email HTML.
 */
function getPasswordResetEmailHtml(string $resetLink, string $userName = 'User'): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; }
        .btn { display: inline-block; background: #28a745; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; margin: 20px 0; }
        .warning { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        .footer { text-align: center; color: #999; font-size: 12px; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Password Reset Request</h1>
        <p>Hi {$userName},</p>
        <p>We received a request to reset your BizLink CRM password. Click the button below to create a new password.</p>
        <div style="text-align: center;">
            <a href="{$resetLink}" class="btn">Reset Password</a>
        </div>
        <p>Or copy and paste this link in your browser:</p>
        <p style="word-break: break-all; color: #666;">{$resetLink}</p>
        <div class="warning">
            <strong>⚠️ Security Notice:</strong> This link will expire in 30 minutes. If you did not request a password reset, please ignore this email.
        </div>
        <div class="footer">
            <p>&copy; 2026 BizLink CRM. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
}
?>
