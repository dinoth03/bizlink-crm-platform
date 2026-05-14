<?php
require 'config.php';
require_once 'mail_service.php';
require_once 'auth_middleware.php';

header('Content-Type: application/json; charset=utf-8');

// Enforce admin-only access for security
try {
    requireAuth(['admin']);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Admin authentication required.',
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    exit;
}

$diagnostic = [
    'timestamp' => date('Y-m-d H:i:s'),
    'environment' => [
        'MAIL_DRIVER' => defined('MAIL_DRIVER') ? MAIL_DRIVER : 'undefined',
        'MAIL_FROM_ADDRESS' => defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'undefined',
        'SMTP_HOST' => defined('SMTP_HOST') ? SMTP_HOST : 'undefined',
        'SMTP_PORT' => defined('SMTP_PORT') ? SMTP_PORT : 'undefined',
        'SMTP_ENCRYPTION' => defined('SMTP_ENCRYPTION') ? SMTP_ENCRYPTION : 'undefined',
        'PHP_SAPI' => PHP_SAPI,
        'PHP_VERSION' => PHP_VERSION,
        'OS' => PHP_OS,
    ],
    'checks' => [],
    'test_send' => null
];

// Check PHPMailer
$phpmailerPath = defined('PHPMAILER_SRC_PATH') ? PHPMAILER_SRC_PATH : '';
$diagnostic['checks']['phpmailer_path_exists'] = is_dir($phpmailerPath);
$diagnostic['checks']['phpmailer_loadable'] = loadPHPMailerClasses();

// Check SMTP Connectivity (socket test)
if (defined('MAIL_DRIVER') && MAIL_DRIVER === 'smtp') {
    $host = SMTP_HOST;
    $port = (int)SMTP_PORT;
    $diagnostic['checks']['smtp_host_configured'] = !empty($host);
    
    if (!empty($host)) {
        $start = microtime(true);
        $errno = 0;
        $errstr = '';
        $prefix = strtolower(SMTP_ENCRYPTION) === 'ssl' ? 'ssl://' : '';
        $fp = @fsockopen($prefix . $host, $port, $errno, $errstr, 5);
        $duration = microtime(true) - $start;
        
        $diagnostic['checks']['smtp_socket_connect'] = [
            'success' => (bool)$fp,
            'duration_ms' => round($duration * 1000, 2),
            'error_no' => $errno,
            'error_str' => $errstr
        ];
        
        if ($fp) {
            $banner = fgets($fp, 512);
            $diagnostic['checks']['smtp_greeting'] = trim($banner);
            fclose($fp);
        }
    }
}

// Check CLI Bridge
$phpBinary = getenv('PHP_CLI_BINARY') ?: 'C:\\xampp\\php\\php.exe';
$diagnostic['checks']['cli_bridge'] = [
    'php_binary' => $phpBinary,
    'php_binary_exists' => is_file($phpBinary),
    'worker_script_exists' => is_file(__DIR__ . '/mail_cli_worker.php')
];

// Optional: Perform test send if requested
if (isset($_GET['test_to'])) {
    $to = $_GET['test_to'];
    if (filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $diagnostic['test_send'] = sendMail($to, 'BizLink Mail Diagnostic', '<p>This is a test email from BizLink CRM Diagnostic Tool.</p>', 'This is a test email from BizLink CRM Diagnostic Tool.');
    } else {
        $diagnostic['test_send'] = ['success' => false, 'message' => 'Invalid test email address.'];
    }
}

echo json_encode($diagnostic, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
