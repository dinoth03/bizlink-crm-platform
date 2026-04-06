<?php
// ============================================
// ERROR REPORTING - Enable all error logging
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to users
ini_set('log_errors', 1);

// Create logs directory if it doesn't exist
$logsDir = dirname(__FILE__) . '/../logs';
if (!file_exists($logsDir)) {
    @mkdir($logsDir, 0777, true);
}

// Set error log file location
$errorLogFile = $logsDir . '/api_errors.log';
ini_set('error_log', $errorLogFile);

// Log connection start
error_log("=== API REQUEST STARTED at " . date('Y-m-d H:i:s') . " from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " ===");

require_once 'api_helpers.php';
// ============================================
// DATABASE CONFIGURATION
// ============================================

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: ''); // XAMPP default is no password
define('DB_NAME', getenv('DB_NAME') ?: 'bizlink_crm');
define('DB_PORT', (int) (getenv('DB_PORT') ?: 3306));
define('DB_SOCKET', getenv('DB_SOCKET') ?: '');

// Log connection details
error_log("DB_CONFIG: Host=" . DB_HOST . ", User=" . DB_USER . ", Database=" . DB_NAME . ", Port=" . DB_PORT);

// ============================================
// SECURITY CONFIGURATION
// ============================================

// Frontend origin whitelist (comma-separated) - NEVER use wildcard in production
// Examples: 'https://app.example.com,https://admin.example.com'
// For local dev: 'http://localhost,http://localhost:3000,http://127.0.0.1'
define('ALLOWED_ORIGINS', getenv('ALLOWED_ORIGINS') ?: 'http://localhost,http://127.0.0.1');

// CSRF protection enabled by default
define('CSRF_ENABLED', (bool)(getenv('CSRF_ENABLED') ?? true));

// Enable security headers by default
define('SECURITY_HEADERS_ENABLED', (bool)(getenv('SECURITY_HEADERS_ENABLED') ?? true));

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Create Connection
$conn = null;
try {
    if (DB_SOCKET !== '') {
        // Cloud SQL via Unix socket (Cloud Run): /cloudsql/PROJECT:REGION:INSTANCE
        error_log("Attempting connection via Unix socket: " . DB_SOCKET);
        $conn = new mysqli(null, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT, DB_SOCKET);
    } else {
        error_log("Attempting connection to " . DB_HOST . ":" . DB_PORT);
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);
    }
    
    if (!$conn) {
        throw new Exception("Connection failed: " . mysqli_connect_error());
    }
    
    $conn->set_charset("utf8mb4");
    error_log("Database connection successful!");
} catch (Throwable $exception) {
    $errorMsg = $exception->getMessage();
    error_log("DATABASE CONNECTION FAILED: " . $errorMsg);
    apiError('DB_CONNECTION_ERROR', 'Database connection failed. Check server logs.', 500, [
        ['field' => 'database', 'message' => $errorMsg]
    ]);
}

// ============================================
// CORS HEADERS - Restrict to whitelist (no wildcard)
// ============================================

$requestOrigin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$allowOrigin = '';

// Parse allowed origins list
$allowedOriginsRaw = trim((string) ALLOWED_ORIGINS);
if ($allowedOriginsRaw !== '') {
    $allowList = array_map('trim', explode(',', $allowedOriginsRaw));
    
    // Only allow origins in whitelist
    if ($requestOrigin !== '' && in_array($requestOrigin, $allowList, true)) {
        $allowOrigin = $requestOrigin;
    } elseif (!empty($allowList) && $allowList[0] !== '') {
        // If no matching origin found, don't set Access-Control-Allow-Origin
        // This prevents unauthorized cross-origin access
        $allowOrigin = '';
    }
}

// Only send CORS headers if origin is whitelisted
if ($allowOrigin !== '') {
    header('Access-Control-Allow-Origin: ' . $allowOrigin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
    header('Access-Control-Max-Age: 3600'); // Preflight cache
    header('Access-Control-Allow-Credentials: true');
}

// ============================================
// SECURITY HEADERS
// ============================================

if (SECURITY_HEADERS_ENABLED) {
    // Content Security Policy - Prevent XSS
    // Adjust 'script-src' based on your real domains
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self'; connect-src 'self'");
    
    // Prevent clickjacking attacks
    header('X-Frame-Options: SAME-ORIGIN');
    
    // Prevent MIME sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Referrer policy - limit referrer information
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Force HTTPS in production
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
    
    // Disable plugins
    header('X-Permitted-Cross-Domain-Policies: none');
    
    // Additional security policies
    header('X-XSS-Protection: 1; mode=block');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

header('Content-Type: application/json; charset=utf-8');

// Handle preflight requests from browser
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

?>
