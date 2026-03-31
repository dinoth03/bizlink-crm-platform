<?php
/**
 * Admin Login Endpoint
 * Authenticates admin users and enforces email verification requirement
 * 
 * POST /api/admin_login.php
 * Body: { email, password }
 */

session_start();
require_once 'config.php';
require_once 'api_helpers.php';
require_once 'admin_security_log.php';
require_once 'csrf_protection.php';
require_once 'rate_limiting.php';
require_once 'secure_logging.php';

// Only POST requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

// Parse JSON payload
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    apiError('INVALID_JSON', 'Invalid JSON payload.', 400);
}

// CSRF Protection
if (CSRF_ENABLED) {
    $csrfToken = getCsrfTokenFromRequest();
    try {
        if (!validateCsrfToken($conn, $csrfToken, null, session_id())) {
            logAdminSecurityEvent($conn, null, 'ADMIN_LOGIN', 'CSRF_FAILED', 'CSRF token validation failed');
            apiError('CSRF_VALIDATION_FAILED', 'Invalid or missing CSRF token.', 403);
        }
    } catch (Throwable $e) {
        error_log("[Admin Login] CSRF validation error: " . $e->getMessage());
        // Continue - some environments might not have CSRF table
    }
}

// Extract and normalize credentials
$email = strtolower(trim((string)($payload['email'] ?? '')));
$password = (string)($payload['password'] ?? '');
$clientIp = getClientIpAddress();
$requestUserAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

// Validate input
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    logAdminSecurityEvent($conn, null, 'ADMIN_LOGIN', 'INVALID_EMAIL', 'Invalid email format', $clientIp, $requestUserAgent, ['email' => $email]);
    apiError('VALIDATION_ERROR', 'Please provide a valid email address.', 422, [
        ['field' => 'email', 'message' => 'Invalid email format.']
    ]);
}

if ($password === '' || strlen($password) < 8) {
    logAdminSecurityEvent($conn, null, 'ADMIN_LOGIN', 'INVALID_PASSWORD', 'Invalid password format', $clientIp, $requestUserAgent, ['email' => $email]);
    apiError('VALIDATION_ERROR', 'Email and password are required.', 422, [
        ['field' => 'password', 'message' => 'password is required.']
    ]);
}

// Rate limiting per IP and email
try {
    requireRateLimit($conn, $clientIp, 'admin_login_by_ip', 20, 900); // 20 per 15 min per IP
    requireRateLimit($conn, $email, 'admin_login_by_email', 10, 900); // 10 per 15 min per email
} catch (Throwable $rateLimitError) {
    logAdminSecurityEvent($conn, null, 'ADMIN_LOGIN', 'RATE_LIMIT_EXCEEDED', 'Too many login attempts', $clientIp, $requestUserAgent, ['email' => $email]);
    apiError('RATE_LIMIT_EXCEEDED', 'Too many login attempts. Please try again later.', 429);
}

try {
    // Fetch admin user from database
    $stmt = $conn->prepare(
        'SELECT u.user_id, u.password_hash, u.full_name, u.role, u.is_verified, u.account_status,
                a.admin_level, a.permissions
         FROM users u
         LEFT JOIN admins a ON u.user_id = a.user_id
         WHERE u.email = ? AND u.role = ? AND u.deleted_at IS NULL
         LIMIT 1'
    );

    if (!$stmt) {
        throw new Exception('Query prepare failed: ' . $conn->error);
    }

    $role = 'admin';
    $stmt->bind_param('ss', $email, $role);
    $stmt->execute();
    $adminRecord = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$adminRecord) {
        logAdminSecurityEvent($conn, null, 'ADMIN_LOGIN', 'USER_NOT_FOUND', 'Admin not found', $clientIp, $requestUserAgent, ['email' => $email]);
        apiError('INVALID_CREDENTIALS', 'Invalid email or password.', 401);
    }

    $userId = (int)$adminRecord['user_id'];
    $passwordHash = (string)$adminRecord['password_hash'];
    $fullName = (string)$adminRecord['full_name'];
    $isVerified = (int)$adminRecord['is_verified'];
    $accountStatus = (string)$adminRecord['account_status'];
    $adminLevel = (string)($adminRecord['admin_level'] ?? 'manager');

    // Verify password
    if (!password_verify($password, $passwordHash)) {
        logAdminSecurityEvent(
            $conn,
            $userId,
            'ADMIN_LOGIN',
            'INVALID_PASSWORD',
            'Password verification failed',
            $clientIp,
            $requestUserAgent,
            ['email' => $email]
        );
        apiError('INVALID_CREDENTIALS', 'Invalid email or password.', 401);
    }

    // **CRITICAL: Check if email is verified before allowing login**
    if ($isVerified !== 1) {
        logAdminSecurityEvent(
            $conn,
            $userId,
            'ADMIN_LOGIN',
            'EMAIL_NOT_VERIFIED',
            'Attempt to login with unverified email',
            $clientIp,
            $requestUserAgent,
            ['email' => $email, 'account_status' => $accountStatus]
        );
        apiError('EMAIL_NOT_VERIFIED', 'Your email has not been verified yet. Please check your email for the verification link.', 403, [
            ['field' => 'verification', 'message' => 'Email verification required. Check your inbox.']
        ]);
    }

    // Check account status
    if ($accountStatus !== 'active') {
        logAdminSecurityEvent(
            $conn,
            $userId,
            'ADMIN_LOGIN',
            'ACCOUNT_INACTIVE',
            'Attempt to login with inactive account',
            $clientIp,
            $requestUserAgent,
            ['email' => $email, 'account_status' => $accountStatus]
        );
        apiError('ACCOUNT_DISABLED', 'Your admin account is not active. Please contact support.', 403, [
            ['field' => 'account', 'message' => 'Account status: ' . $accountStatus]
        ]);
    }

    // Configure secure session
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

    // Regenerate session ID for security
    session_regenerate_id(true);

    // Set session variables
    $_SESSION['user_id'] = $userId;
    $_SESSION['email'] = $email;
    $_SESSION['role'] = 'admin';
    $_SESSION['full_name'] = $fullName;
    $_SESSION['admin_level'] = $adminLevel;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();

    // Update last login timestamp
    $updateStmt = $conn->prepare('UPDATE users SET last_login = NOW() WHERE user_id = ?');
    if ($updateStmt) {
        $updateStmt->bind_param('i', $userId);
        $updateStmt->execute();
        $updateStmt->close();
    }

    // Log login activity
    $loginStmt = $conn->prepare('INSERT INTO login_activity (user_id, login_at, ip_address, user_agent) VALUES (?, NOW(), ?, ?)');
    if ($loginStmt) {
        $loginStmt->bind_param('iss', $userId, $clientIp, $requestUserAgent);
        $loginStmt->execute();
        $loginStmt->close();
    }

    // Log successful login
    logAdminSecurityEvent(
        $conn,
        $userId,
        'ADMIN_LOGIN',
        'SUCCESS',
        'Admin logged in successfully',
        $clientIp,
        $requestUserAgent,
        ['email' => $email, 'admin_level' => $adminLevel]
    );

    // Return success response
    apiSuccess([
        'user_id' => $userId,
        'email' => $email,
        'full_name' => $fullName,
        'role' => 'admin',
        'admin_level' => $adminLevel,
        'session_id' => session_id()
    ], 'Login successful.', 'LOGIN_SUCCESS');
    $conn->close();
    exit;

} catch (Throwable $e) {
    error_log("[Admin Login] Error: " . $e->getMessage());
    logAdminSecurityEvent(
        $conn,
        null,
        'ADMIN_LOGIN',
        'ERROR',
        'Login error: ' . $e->getMessage(),
        $clientIp,
        $requestUserAgent,
        ['email' => $email]
    );
    apiError('INTERNAL_ERROR', 'An error occurred during login.', 500);
    exit;
} finally {
    $conn->close();
}

?>
