<?php
/**
 * CSRF Token Generation Endpoint
 * Frontend should call this to get a fresh CSRF token for form submission
 */

session_start();
require 'config.php';
require 'csrf_protection.php';

// Only GET requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET.'
    ]);
    $conn->close();
    exit;
}

// Generate a new CSRF token
$token = generateCsrfToken($conn, null, session_id());

if (empty($token)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to generate CSRF token.'
    ]);
    $conn->close();
    exit;
}

// Return token to client
echo json_encode([
    'success' => true,
    'token' => $token,
    'expires_in' => 3600  // Token expires in 1 hour (seconds)
]);

$conn->close();
?>
