<?php
/**
 * CSRF Token Generation Endpoint
 * Frontend should call this to get a fresh CSRF token for form submission
 */

session_start();
require 'config.php';
require 'csrf_protection.php';
require_once 'api_helpers.php';

// Only GET requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use GET.', 405);
}

// Generate a new CSRF token
$token = generateCsrfToken($conn, null, session_id());

if (empty($token)) {
    apiError('CSRF_TOKEN_GENERATION_FAILED', 'Failed to generate CSRF token.', 500);
}

apiSuccess([
    'token' => $token,
    'expires_in' => 3600  // Token expires in 1 hour (seconds)
], 'CSRF token generated successfully.', 'CSRF_TOKEN_CREATED');

$conn->close();
?>
