<?php
/**
 * Auth Middleware - Session validation and role-based access control
 * Include this at the top of protected endpoints
 */

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}
require_once 'api_helpers.php';

define('SESSION_TIMEOUT_SECONDS', 3600);

// Auth check function
function requireAuth($allowedRoles = []) {
    if (!isset($_SESSION['user_id'], $_SESSION['role'], $_SESSION['email'])) {
        apiError('UNAUTHORIZED', 'Unauthorized - please login.', 401);
    }

    $referenceTime = isset($_SESSION['last_activity']) ? (int)$_SESSION['last_activity'] : (int)($_SESSION['login_time'] ?? 0);
    if ($referenceTime > 0 && (time() - $referenceTime) > SESSION_TIMEOUT_SECONDS) {
        session_unset();
        session_destroy();
        apiError('SESSION_EXPIRED', 'Your session expired. Please sign in again.', 401);
    }

    // If specific roles required, check them
    if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles)) {
        apiError('FORBIDDEN', 'Forbidden - insufficient permissions.', 403);
    }

    $_SESSION['last_activity'] = time();

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
