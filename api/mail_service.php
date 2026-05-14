<?php

/**
 * Mail Service - Handles email sending via SMTP sockets, PHP mail(), or dev logging.
 */

$MAIL_SETTINGS = [];
$mailSettingsPath = __DIR__ . '/mail_settings.local.php';
if (is_file($mailSettingsPath)) {
    $loadedSettings = require $mailSettingsPath;
    if (is_array($loadedSettings)) {
        $MAIL_SETTINGS = $loadedSettings;
    }
}

function mailSetting(string $key, string $default = ''): string {
    global $MAIL_SETTINGS;

    $envValue = getenv($key);
    if ($envValue !== false && trim((string)$envValue) !== '') {
        return trim((string)$envValue);
    }

    $localValue = $MAIL_SETTINGS[$key] ?? '';
    if (is_string($localValue) && trim($localValue) !== '') {
        return trim($localValue);
    }

    return $default;
}

// SMTP Configuration (populated from environment or config)
// Default to SMTP so OTP can be actually delivered when credentials are configured.
// Use 'dev' only when you intentionally want to log emails instead of sending.
// For production with sendmail: set to 'php'
define('MAIL_DRIVER', mailSetting('MAIL_DRIVER', 'smtp'));  // 'php', 'smtp', or 'dev'
define('MAIL_FROM_ADDRESS', mailSetting('MAIL_FROM_ADDRESS', 'noreply@bizlink.local'));
define('MAIL_FROM_NAME', mailSetting('MAIL_FROM_NAME', 'BizLink CRM'));

// SMTP-specific config (used if MAIL_DRIVER == 'smtp')
define('SMTP_HOST', mailSetting('SMTP_HOST', ''));
define('SMTP_PORT', (int)mailSetting('SMTP_PORT', '587'));
define('SMTP_USERNAME', mailSetting('SMTP_USERNAME', ''));
define('SMTP_PASSWORD', mailSetting('SMTP_PASSWORD', ''));
define('SMTP_ENCRYPTION', mailSetting('SMTP_ENCRYPTION', 'tls')); // 'tls' or 'ssl'
define('PHPMAILER_SRC_PATH', mailSetting('PHPMAILER_SRC_PATH', __DIR__ . '/../vendor/phpmailer/phpmailer/src'));

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
        $result = sendViaSmtpWithOptionalPHPMailer($to, $subject, $htmlBody, $textBody, $logId);

        if (!$result['success'] && PHP_SAPI !== 'cli') {
            $bridgeResult = sendViaCliPhpBridge($to, $subject, $htmlBody, $textBody, $logId);
            if ($bridgeResult['success']) {
                return $bridgeResult;
            }
        }

        return $result;
    } elseif ($driver === 'dev') {
        $result = sendViaDevMode($to, $subject, $htmlBody, $textBody, $logId);
    } else {
        // Default to PHP mail() function
        $result = sendViaPhpMail($to, $subject, $htmlBody, $textBody, $logId);
    }
    
    file_put_contents(__DIR__ . '/mail_debug.log', date('Y-m-d H:i:s') . " - To: $to - Driver: $driver - Success: " . ($result['success'] ? 'true' : 'false') . " - Msg: " . ($result['message'] ?? '') . " - Details: " . ($result['details'] ?? '') . "\n", FILE_APPEND);
    return $result;
}

function sendViaSmtpWithOptionalPHPMailer(string $to, string $subject, string $htmlBody, ?string $textBody, string $logId): array {
    if (loadPHPMailerClasses()) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->Port = SMTP_PORT;
            $mail->CharSet = 'UTF-8';

            if (strtolower(SMTP_ENCRYPTION) === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif (strtolower(SMTP_ENCRYPTION) === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];

            $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody ?: strip_tags($htmlBody);
            $mail->send();

            error_log("[Mail Log ID: {$logId}] Email sent via PHPMailer SMTP to {$to}: {$subject}");
            return [
                'success' => true,
                'message' => 'Email sent successfully.',
                'logId' => $logId
            ];
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
            error_log("[Mail Log ID: {$logId}] PHPMailer send failed for {$to}: " . $errorMessage);
            // If PHPMailer failed, we might still want to try the fallback or return the error
            if (strpos($errorMessage, 'SMTP connect() failed') !== false) {
                $errorMessage = "SMTP connection failed. Check your host and port settings.";
            } elseif (strpos($errorMessage, 'Authentication failed') !== false) {
                $errorMessage = "SMTP Authentication failed. Check your username and password.";
            }
            
            // Store the error message to potentially return it if fallback also fails
            $GLOBALS['LAST_MAIL_ERROR'] = $errorMessage;
        }
    }

    return sendViaSMTP($to, $subject, $htmlBody, $textBody, $logId);
}

function loadPHPMailerClasses(): bool {
    if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
        return true;
    }

    $composerAutoload = __DIR__ . '/../vendor/autoload.php';
    if (is_file($composerAutoload)) {
        require_once $composerAutoload;
        if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            return true;
        }
    }

    $required = [
        PHPMAILER_SRC_PATH . '/Exception.php',
        PHPMAILER_SRC_PATH . '/PHPMailer.php',
        PHPMAILER_SRC_PATH . '/SMTP.php'
    ];

    foreach ($required as $file) {
        if (!is_file($file)) {
            return false;
        }
    }

    foreach ($required as $file) {
        require_once $file;
    }

    return class_exists('\\PHPMailer\\PHPMailer\\PHPMailer');
}

function sendViaCliPhpBridge(string $to, string $subject, string $htmlBody, ?string $textBody, string $logId): array {
    $phpBinary = getenv('PHP_CLI_BINARY');
    if ($phpBinary === false || trim($phpBinary) === '') {
        // Try common XAMPP/WAMP paths on Windows
        $commonPaths = [
            'C:\\xampp\\php\\php.exe',
            'C:\\wamp64\\bin\\php\\php8.2.0\\php.exe',
            'C:\\wamp64\\bin\\php\\php8.1.0\\php.exe',
            'C:\\wamp\\bin\\php\\php.exe',
            'php' // Try system path
        ];
        foreach ($commonPaths as $path) {
            if ($path === 'php' || is_file($path)) {
                $phpBinary = $path;
                break;
            }
        }
    }

    if (empty($phpBinary)) {
        $phpBinary = 'php'; // Fallback to system path
    }

    $workerScript = __DIR__ . '/mail_cli_worker.php';
    if (!is_file($phpBinary) || !is_file($workerScript)) {
        error_log("[Mail Log ID: {$logId}] CLI bridge unavailable: missing PHP binary or worker script");
        return [
            'success' => false,
            'message' => 'Failed to send email. CLI bridge unavailable.',
            'logId' => $logId
        ];
    }

    $payloadFile = tempnam(sys_get_temp_dir(), 'bizlink_mail_');
    if ($payloadFile === false) {
        error_log("[Mail Log ID: {$logId}] CLI bridge unavailable: could not create temp file");
        return [
            'success' => false,
            'message' => 'Failed to send email. Could not create temporary file.',
            'logId' => $logId
        ];
    }

    $payload = [
        'to' => $to,
        'subject' => $subject,
        'htmlBody' => $htmlBody,
        'textBody' => $textBody,
    ];

    file_put_contents($payloadFile, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    $command = escapeshellarg($phpBinary) . ' ' . escapeshellarg($workerScript) . ' ' . escapeshellarg($payloadFile);
    $output = [];
    $exitCode = 0;
    exec($command . ' 2>&1', $output, $exitCode);

    @unlink($payloadFile);

    $response = json_decode(implode("\n", $output), true);
    if (is_array($response) && !empty($response['success'])) {
        error_log("[Mail Log ID: {$logId}] Email sent via CLI bridge to {$to}: {$subject}");
        $response['logId'] = $logId;
        return $response;
    }

    $cliMessage = is_array($response) && isset($response['message']) ? $response['message'] : implode("\n", $output);
    error_log("[Mail Log ID: {$logId}] CLI bridge failed for {$to}: {$cliMessage}");

    $finalMessage = 'Failed to send email. ' . ($GLOBALS['LAST_MAIL_ERROR'] ?? 'Please check SMTP settings.');

    return [
        'success' => false,
        'message' => $finalMessage,
        'logId' => $logId,
        'details' => $cliMessage
    ];
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
        $isSsl = strtolower(SMTP_ENCRYPTION) === 'ssl';
        $isTls = strtolower(SMTP_ENCRYPTION) === 'tls';
        $encryptionPrefix = $isSsl ? 'ssl://' : '';
        $socket = @fsockopen($encryptionPrefix . SMTP_HOST, SMTP_PORT, $errno, $errstr, 10);

        if (!$socket) {
            throw new Exception("SMTP connection failed: {$errstr}");
        }

        stream_set_timeout($socket, 15);

        $banner = fgets($socket);
        if ($banner === false || strpos($banner, '220') !== 0) {
            throw new Exception('SMTP server did not return greeting.');
        }

        fputs($socket, "EHLO " . SMTP_HOST . "\r\n");
        smtpReadMultilineResponse($socket);

        if ($isTls) {
            fputs($socket, "STARTTLS\r\n");
            $startTlsResponse = fgets($socket);
            if ($startTlsResponse === false || strpos($startTlsResponse, '220') !== 0) {
                throw new Exception('SMTP STARTTLS not accepted by server.');
            }

            $cryptoEnabled = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoEnabled !== true) {
                throw new Exception('Unable to enable TLS encryption for SMTP connection.');
            }

            fputs($socket, "EHLO " . SMTP_HOST . "\r\n");
            smtpReadMultilineResponse($socket);
        }

        // Authenticate
        fputs($socket, "AUTH LOGIN\r\n");
        $authLogin = fgets($socket);
        if ($authLogin === false || strpos($authLogin, '334') !== 0) {
            throw new Exception('SMTP AUTH LOGIN command rejected.');
        }

        fputs($socket, base64_encode(SMTP_USERNAME) . "\r\n");
        $userResponse = fgets($socket);
        if ($userResponse === false || strpos($userResponse, '334') !== 0) {
            throw new Exception('SMTP username rejected.');
        }

        fputs($socket, base64_encode(SMTP_PASSWORD) . "\r\n");
        $authResponse = fgets($socket);

        if ($authResponse === false || strpos($authResponse, '235') !== 0) {
            throw new Exception('SMTP authentication failed');
        }

        // Send email
        fputs($socket, "MAIL FROM: <" . MAIL_FROM_ADDRESS . ">\r\n");
        $mailFromResponse = fgets($socket);
        if ($mailFromResponse === false || strpos($mailFromResponse, '250') !== 0) {
            throw new Exception('SMTP MAIL FROM rejected.');
        }

        fputs($socket, "RCPT TO: <{$to}>\r\n");
        $rcptResponse = fgets($socket);
        if ($rcptResponse === false || (strpos($rcptResponse, '250') !== 0 && strpos($rcptResponse, '251') !== 0)) {
            throw new Exception('SMTP RCPT TO rejected.');
        }

        fputs($socket, "DATA\r\n");
        $dataResponse = fgets($socket);
        if ($dataResponse === false || strpos($dataResponse, '354') !== 0) {
            throw new Exception('SMTP DATA command rejected.');
        }

        $emailBody = buildEmailMessage($to, $subject, $htmlBody, $textBody);
        fputs($socket, $emailBody . "\r\n.\r\n");
        $response = fgets($socket);

        if ($response === false || strpos($response, '250') !== 0) {
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
        $errorMessage = $e->getMessage();
        if (strpos($errorMessage, 'Authentication rejected') !== false || strpos($errorMessage, 'AUTH LOGIN command rejected') !== false) {
             $errorMessage = "SMTP Authentication failed. Please check your App Password.";
        }
        return [
            'success' => false,
            'message' => 'Failed to send email. ' . $errorMessage,
            'logId' => $logId
        ];
    }
}

function smtpReadMultilineResponse($socket): string {
    $response = '';
    while (($line = fgets($socket)) !== false) {
        $response .= $line;
        // SMTP multiline replies use code + '-' for continuation and code + space for final line.
        if (preg_match('/^\d{3}\s/', $line)) {
            break;
        }
    }
    return $response;
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
 * Generate admin verification code email HTML.
 */
function getAdminVerificationCodeEmailHtml(string $verificationCode, string $userName = 'Admin'): string {
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; }
        .code-box { text-align: center; margin: 24px 0; }
        .code { display: inline-block; font-size: 32px; font-weight: 700; letter-spacing: 6px; padding: 14px 20px; border-radius: 10px; background: #eef3ff; color: #1c3faa; }
        .footer { text-align: center; color: #999; font-size: 12px; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Admin Email Verification</h1>
        <p>Hi {$userName},</p>
        <p>Your BizLink admin verification code is:</p>
        <div class="code-box">
            <span class="code">{$verificationCode}</span>
        </div>
        <p>Enter this 6-digit code in the login verification prompt. This code expires in 15 minutes.</p>
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
