<?php
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

// Create Connection
if (DB_SOCKET !== '') {
    // Cloud SQL via Unix socket (Cloud Run): /cloudsql/PROJECT:REGION:INSTANCE
    $conn = new mysqli(null, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT, DB_SOCKET);
} else {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);
}

// Check Connection
if ($conn->connect_error) {
    apiError('DB_CONNECTION_ERROR', 'Database connection failed.', 500, [
        ['field' => 'database', 'message' => $conn->connect_error]
    ]);
}

// Set Character Set (important for Unicode)
$conn->set_charset("utf8mb4");

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
