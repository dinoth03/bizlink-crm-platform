<?php
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
require 'config.php';
require_once 'api_helpers.php';
require 'csrf_protection.php';
require 'rate_limiting.php';
require 'secure_logging.php';

$requestIpAddress = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
$requestUserAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

$maxFailedAttempts = 5;
$lockoutWindowMinutes = 15;

function logFailedLoginAttempt(mysqli $conn, ?int $userId, string $email, string $role, string $reason, string $ipAddress, string $userAgent): void {
    $normalizedEmail = substr(strtolower(trim($email)), 0, 255);
    $normalizedRole = substr(strtolower(trim($role)), 0, 20);
    $normalizedReason = substr(strtolower(trim($reason)), 0, 100);

    if ($stmt = $conn->prepare('INSERT INTO failed_login_attempts (user_id, email, role, failure_reason, ip_address, user_agent, attempted_at) VALUES (?, ?, ?, ?, ?, ?, NOW())')) {
        $stmt->bind_param('isssss', $userId, $normalizedEmail, $normalizedRole, $normalizedReason, $ipAddress, $userAgent);
        $stmt->execute();
        $stmt->close();
    }
}

function getLockoutInfo(mysqli $conn, string $email, string $role, int $windowMinutes, int $maxAttempts): array {
    $default = [
        'is_locked' => false,
        'attempt_count' => 0,
        'remaining_seconds' => 0,
    ];

    $sql = "SELECT COUNT(*) AS attempt_count, MAX(attempted_at) AS last_attempt_at
            FROM failed_login_attempts
            WHERE email = ?
              AND role = ?
              AND failure_reason IN ('incorrect_password', 'invalid_credentials_or_role')
              AND attempted_at >= (NOW() - INTERVAL ? MINUTE)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return $default;
    }

    $stmt->bind_param('ssi', $email, $role, $windowMinutes);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $attemptCount = (int)($row['attempt_count'] ?? 0);
    $lastAttemptAt = (string)($row['last_attempt_at'] ?? '');
    if ($attemptCount < $maxAttempts || $lastAttemptAt === '') {
        return $default;
    }

    $elapsedSql = "SELECT TIMESTAMPDIFF(SECOND, ?, NOW()) AS elapsed_seconds";
    $elapsedStmt = $conn->prepare($elapsedSql);
    if (!$elapsedStmt) {
        return $default;
    }

    $elapsedStmt->bind_param('s', $lastAttemptAt);
    $elapsedStmt->execute();
    $elapsedRow = $elapsedStmt->get_result()->fetch_assoc();
    $elapsedStmt->close();

    $elapsedSeconds = max(0, (int)($elapsedRow['elapsed_seconds'] ?? 0));
    $windowSeconds = $windowMinutes * 60;
    $remainingSeconds = max(0, $windowSeconds - $elapsedSeconds);

    return [
        'is_locked' => $remainingSeconds > 0,
        'attempt_count' => $attemptCount,
        'remaining_seconds' => $remainingSeconds,
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    apiError('INVALID_JSON', 'Invalid JSON payload.', 400);
}

// CSRF Protection
if (CSRF_ENABLED) {
    $csrfToken = getCsrfTokenFromRequest();
    if (!validateCsrfToken($conn, $csrfToken, null, session_id())) {
        logCsrfFailure('auth_login');
        apiError('CSRF_VALIDATION_FAILED', 'Invalid or missing CSRF token.', 403);
    }
}

$role = strtolower(trim((string)($payload['role'] ?? '')));
$email = strtolower(trim((string)($payload['email'] ?? '')));
$password = (string)($payload['password'] ?? '');

$allowedRoles = ['admin', 'vendor', 'customer'];
if (!in_array($role, $allowedRoles, true)) {
    logFailedLoginAttempt($conn, null, $email, $role, 'invalid_role', $requestIpAddress, $requestUserAgent);
    apiError('VALIDATION_ERROR', 'Invalid role selected.', 422, [
        ['field' => 'role', 'message' => 'role must be admin, vendor, or customer.']
    ]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    apiError('VALIDATION_ERROR', 'Email and password are required.', 422, [
        ['field' => 'email', 'message' => 'Valid email is required.'],
        ['field' => 'password', 'message' => 'password is required.']
    ]);
}

// Rate Limiting - per IP address and per email/role combination
$clientIp = getClientIpAddress();
requireRateLimit($conn, $clientIp, 'login_by_ip', 20, 900); // 20 attempts per 15 min per IP
requireRateLimit($conn, $email . ':' . $role, 'login_by_account', 5, 900); // 5 attempts per 15 min per account

$lockout = getLockoutInfo($conn, $email, $role, $lockoutWindowMinutes, $maxFailedAttempts);
if ($lockout['is_locked']) {
    $remainingSeconds = (int)$lockout['remaining_seconds'];
    $remainingMinutes = (int)ceil($remainingSeconds / 60);
    apiError('LOGIN_LOCKED', 'Too many failed login attempts. Try again in ' . $remainingMinutes . ' minute(s).', 429, [], null, [
        'lockout' => [
            'max_attempts' => $maxFailedAttempts,
            'window_minutes' => $lockoutWindowMinutes,
            'remaining_seconds' => $remainingSeconds
        ]
    ]);
}

$stmt = $conn->prepare(
    'SELECT user_id, email, password_hash, role, full_name, account_status, is_verified FROM users WHERE email = ? AND role = ? AND deleted_at IS NULL LIMIT 1'
);
$stmt->bind_param('ss', $email, $role);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    logFailedLoginAttempt($conn, null, $email, $role, 'invalid_credentials_or_role', $requestIpAddress, $requestUserAgent);
    apiError('INVALID_CREDENTIALS', 'Invalid credentials or role mismatch.', 401);
}

$status = strtolower((string)$user['account_status']);

if ($role === 'admin' && (int)($user['is_verified'] ?? 0) !== 1) {
    logFailedLoginAttempt($conn, (int)$user['user_id'], $email, $role, 'email_not_verified', $requestIpAddress, $requestUserAgent);
    apiError('EMAIL_NOT_VERIFIED', 'Please verify your admin email code before logging in.', 403);
}

if (in_array($status, ['inactive', 'suspended'], true)) {
    logFailedLoginAttempt($conn, (int)$user['user_id'], $email, $role, 'account_not_active', $requestIpAddress, $requestUserAgent);
    if ($role === 'vendor' || $role === 'customer') {
        apiError('ACCOUNT_PENDING_APPROVAL', 'Your account is pending admin approval.', 403);
    }
    apiError('ACCOUNT_NOT_ACTIVE', 'Your account is not active. Please contact support.', 403);
}

$storedHash = (string)$user['password_hash'];
$isValid = password_verify($password, $storedHash);

// Support legacy plain-text records from old seed/demo data.
if (!$isValid && hash_equals($storedHash, $password)) {
    $isValid = true;
}

if (!$isValid) {
    logFailedLoginAttempt($conn, (int)$user['user_id'], $email, $role, 'incorrect_password', $requestIpAddress, $requestUserAgent);
    apiError('INVALID_CREDENTIALS', 'Incorrect password.', 401);
}

$updateStmt = $conn->prepare('UPDATE users SET last_login = NOW() WHERE user_id = ?');
$updateStmt->bind_param('i', $user['user_id']);
$updateStmt->execute();
$updateStmt->close();

// Record successful login activity for audit/history purposes.
$ipAddress = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
$userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
if ($auditStmt = $conn->prepare('INSERT INTO login_activity (user_id, login_at, ip_address, user_agent) VALUES (?, NOW(), ?, ?)')) {
    $userId = (int)$user['user_id'];
    $auditStmt->bind_param('iss', $userId, $ipAddress, $userAgent);
    $auditStmt->execute();
    $auditStmt->close();
}

// Set PHP session variables for server-side authentication
session_regenerate_id(true);
$_SESSION['user_id'] = (int)$user['user_id'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = $user['role'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['login_time'] = time();
$_SESSION['last_activity'] = time();

$dashboardMap = [
    'admin' => '../admin/dashboard.html',
    'vendor' => '../vendor/vendorpanel.html',
    'customer' => '../customer/dashboard.html'
];

apiSuccess([
    'user' => [
        'user_id' => (int)$user['user_id'],
        'role' => $user['role'],
        'email' => $user['email'],
        'full_name' => $user['full_name']
    ],
    'dashboard' => $dashboardMap[$role] ?? '../pages/home.html'
], 'Login successful.', 'LOGIN_SUCCESS');

$conn->close();
?>