<?php
/**
 * Admin Email Verification - Diagnostic Test
 * Tests all endpoints and returns debug information
 * Access: GET /api/admin_diagnostic.php
 */

header('Content-Type: application/json; charset=utf-8');
ob_start();
error_reporting(E_ALL);

$errors = [];
$config_check = [];

// Check 1: Database connection
try {
    require_once 'config.php';
    if ($conn->connect_error) {
        $errors[] = "Database connection failed: " . $conn->connect_error;
    } else {
        $config_check[] = "✓ Database connection successful";
    }
} catch (Throwable $e) {
    $errors[] = "config.php error: " . $e->getMessage();
}

// Check 2: Required functions exist
$functions_to_check = [
    'apiSuccess' => 'api_helpers.php',
    'apiError' => 'api_helpers.php',
    'buildPublicUrl' => 'auth_token_utils.php',
    'hashAuthToken' => 'auth_token_utils.php',
    'generateSecureToken' => 'auth_token_utils.php',
    'sendMail' => 'mail_service.php',
    'requireRateLimit' => 'rate_limiting.php',
    'logAdminSecurityEvent' => 'admin_security_log.php',
    'getAdminRegistrationVerificationEmailHtml' => 'admin_email_templates.php',
];

foreach ($functions_to_check as $func => $file) {
    if (function_exists($func)) {
        $config_check[] = "✓ Function $func exists ($file)";
    } else {
        $errors[] = "✗ Missing function $func (from $file)";
    }
}

// Check 3: Email configuration
if (defined('SMTP_HOST') && SMTP_HOST !== '') {
    $config_check[] = "✓ SMTP configured: " . SMTP_HOST . ":" . SMTP_PORT;
} else {
    $errors[] = "⚠ SMTP not configured (will use PHP mail() or dev mode)";
}

// Check 4: Test token generation
try {
    require_once 'auth_token_utils.php';
    $testToken = generateSecureToken(32);
    $testHash = hashAuthToken($testToken);
    if (strlen($testToken) === 64 && strlen($testHash) === 64) {
        $config_check[] = "✓ Token generation working (64-char token generated)";
    } else {
        $errors[] = "✗ Token generation failed: Wrong length";
    }
} catch (Throwable $e) {
    $errors[] = "✗ Token generation error: " . $e->getMessage();
}

// Check 5: buildPublicUrl function
try {
    $testUrl = buildPublicUrl('test.php', ['param' => 'value']);
    if (strpos($testUrl, 'test.php?param=value') !== false) {
        $config_check[] = "✓ buildPublicUrl working: " . substr($testUrl, 0, 50) . "...";
    } else {
        $errors[] = "✗ buildPublicUrl returned unexpected format: " . $testUrl;
    }
} catch (Throwable $e) {
    $errors[] = "✗ buildPublicUrl error: " . $e->getMessage();
}

// Check 6: Admin security log table
try {
    require_once 'admin_security_log.php';
    ensureAdminSecurityLogTable($conn);
    $config_check[] = "✓ Admin security log table ensured";
} catch (Throwable $e) {
    $errors[] = "✗ Admin security log table error: " . $e->getMessage();
}

// Check 7: Test admin user lookup
if (isset($conn)) {
    try {
        $stmt = $conn->prepare('SELECT COUNT(*) as admin_count FROM users WHERE role = "admin"');
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $adminCount = $result['admin_count'] ?? 0;
        $config_check[] = "✓ Found $adminCount admin users in database";
        $stmt->close();
    } catch (Throwable $e) {
        $errors[] = "✗ Admin user query error: " . $e->getMessage();
    }
}

// Fetch any output that was buffered
$buffered_output = ob_get_clean();
if ($buffered_output !== '') {
    $errors[] = "⚠ Buffered output detected (may indicate errors): " . substr($buffered_output, 0, 100);
}

// Return diagnostic report
http_response_code(200);
echo json_encode([
    'success' => empty($errors),
    'diagnostic_report' => [
        'checks_passed' => count($config_check),
        'errors_found' => count($errors),
        'details' => $config_check,
        'errors' => $errors,
        'timestamp' => date('c'),
        'php_version' => phpversion(),
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;
?>
