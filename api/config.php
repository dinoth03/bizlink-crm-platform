<?php
// ============================================
// DATABASE CONFIGURATION
// ============================================

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('DB_PASSWORD') ?: ''); // XAMPP default is no password
define('DB_NAME', getenv('DB_NAME') ?: 'bizlink_crm');
define('DB_PORT', (int) (getenv('DB_PORT') ?: 3306));
define('DB_SOCKET', getenv('DB_SOCKET') ?: '');

// Optional comma-separated CORS allowlist, e.g. https://example.com,https://app.example.com
define('CORS_ALLOWED_ORIGINS', getenv('CORS_ALLOWED_ORIGINS') ?: '');

// Create Connection
if (DB_SOCKET !== '') {
    // Cloud SQL via Unix socket (Cloud Run): /cloudsql/PROJECT:REGION:INSTANCE
    $conn = new mysqli(null, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT, DB_SOCKET);
} else {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);
}

// Check Connection
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]));
}

// Set Character Set (important for Unicode)
$conn->set_charset("utf8mb4");

// ============================================
// CORS HEADERS (Allow Frontend to Call API)
// ============================================

// Resolve allowed origin for local and production deployments.
$requestOrigin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
$allowedOriginsRaw = trim((string) CORS_ALLOWED_ORIGINS);
$allowOrigin = '*';

if ($allowedOriginsRaw !== '' && $requestOrigin !== '') {
    $allowList = array_map('trim', explode(',', $allowedOriginsRaw));
    if (in_array($requestOrigin, $allowList, true)) {
        $allowOrigin = $requestOrigin;
    } else {
        $allowOrigin = $allowList[0] ?: '*';
    }
} elseif ($requestOrigin !== '') {
    $allowOrigin = $requestOrigin;
}

header('Access-Control-Allow-Origin: ' . $allowOrigin);
header('Vary: Origin');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests from browser
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

?>
