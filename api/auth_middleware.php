<?php
/**
 * Auth Middleware - Session validation and role-based access control
 * Include this at the top of protected endpoints
 */

session_start();

define('SESSION_TIMEOUT_SECONDS', 3600);

// Auth check function
function requireAuth($allowedRoles = []) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'code' => 'unauthorized',
            'message' => 'Unauthorized - please login'
        ]);
        exit();
    }

    if (isset($_SESSION['login_time']) && (time() - (int)$_SESSION['login_time']) > SESSION_TIMEOUT_SECONDS) {
        session_unset();
        session_destroy();
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'code' => 'session_expired',
            'message' => 'Your session expired. Please sign in again.'
        ]);
        exit();
    }

    // If specific roles required, check them
    if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'code' => 'forbidden',
            'message' => 'Forbidden - insufficient permissions'
        ]);
        exit();
    }

    return true;
}

// Get current user info from session
function getCurrentUser() {
    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'role' => $_SESSION['role'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? null,
    ];
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Get user's role
function getUserRole() {
    return $_SESSION['role'] ?? null;
}
?>
