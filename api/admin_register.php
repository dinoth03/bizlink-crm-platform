<?php
/**
 * Admin Registration Endpoint
 * Handles admin account creation with secure email verification token generation
 * 
 * POST /api/admin_register.php
 * Body: { email, password, first_name, last_name, admin_level?, department? }
 */

session_start();
require_once 'config.php';
require_once 'api_helpers.php';
require_once 'auth_token_utils.php';
require_once 'mail_service.php';
require_once 'admin_email_templates.php';
require_once 'admin_security_log.php';
require_once 'csrf_protection.php';
require_once 'rate_limiting.php';
require_once 'secure_logging.php';

// Only POST requests allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

// Parse and validate JSON payload
$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    apiError('INVALID_JSON', 'Invalid JSON payload.', 400);
}

// CSRF Protection
if (CSRF_ENABLED) {
    $csrfToken = getCsrfTokenFromRequest();
    try {
        if (!validateCsrfToken($conn, $csrfToken, null, session_id())) {
            logAdminSecurityEvent($conn, null, 'ADMIN_REGISTER', 'CSRF_FAILED', 'CSRF token validation failed');
            apiError('CSRF_VALIDATION_FAILED', 'Invalid or missing CSRF token.', 403);
        }
    } catch (Throwable $e) {
        error_log("[Admin Register] CSRF validation error: " . $e->getMessage());
        // Continue anyway for local dev
    }
}

// Extract and trim input
$email = strtolower(trim((string)($payload['email'] ?? '')));
$password = (string)($payload['password'] ?? '');
$firstName = trim((string)($payload['first_name'] ?? ''));
$lastName = trim((string)($payload['last_name'] ?? ''));
$adminLevel = strtolower(trim((string)($payload['admin_level'] ?? 'manager')));
$department = trim((string)($payload['department'] ?? ''));

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    logAdminSecurityEvent($conn, null, 'ADMIN_REGISTER', 'VALIDATION_FAILED', 'Invalid email format', metadata: ['email' => $email]);
    apiError('VALIDATION_ERROR', 'Please provide a valid email address.', 422, [
        ['field' => 'email', 'message' => 'Invalid email format.']
    ]);
}

// Validate password strength (minimum 12 characters for admin)
if (strlen($password) < 12) {
    logAdminSecurityEvent($conn, null, 'ADMIN_REGISTER', 'VALIDATION_FAILED', 'Password too weak');
    apiError('VALIDATION_ERROR', 'Admin password must be at least 12 characters.', 422, [
        ['field' => 'password', 'message' => 'Minimum length is 12 characters.']
    ]);
}

// Validate names
if ($firstName === '' || $lastName === '') {
    logAdminSecurityEvent($conn, null, 'ADMIN_REGISTER', 'VALIDATION_FAILED', 'Missing name fields');
    apiError('VALIDATION_ERROR', 'First name and last name are required.', 422, [
        ['field' => 'first_name', 'message' => 'first_name is required.'],
        ['field' => 'last_name', 'message' => 'last_name is required.']
    ]);
}

// Validate admin level
$validAdminLevels = ['superadmin', 'manager', 'support'];
if (!in_array($adminLevel, $validAdminLevels, true)) {
    $adminLevel = 'manager'; // Default to manager if invalid
}

// Rate limiting - prevent spam registrations
$clientIp = getClientIpAddress();
try {
    requireRateLimit($conn, $clientIp, 'admin_register_by_ip', 5, 3600); // 5 per hour per IP
    requireRateLimit($conn, $email, 'admin_register_by_email', 3, 3600); // 3 per hour per email
} catch (Throwable $rateLimitError) {
    logAdminSecurityEvent($conn, null, 'ADMIN_REGISTER', 'RATE_LIMIT_EXCEEDED', 'Signup rate limit exceeded', metadata: ['email' => $email]);
    apiError('RATE_LIMIT_EXCEEDED', 'Too many registration attempts. Please try again later.', 429);
}

// Check if email already exists
$fullName = trim($firstName . ' ' . $lastName);

try {
    $checkStmt = $conn->prepare('SELECT user_id, is_verified FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1');
    if (!$checkStmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if ($existing) {
        logAdminSecurityEvent($conn, null, 'ADMIN_REGISTER', 'EMAIL_DUPLICATE', 'Duplicate email registration attempt', metadata: ['email' => $email]);
        apiError('EMAIL_ALREADY_EXISTS', 'This email is already registered. Please use a different email or login.', 409);
    }
} catch (Throwable $e) {
    error_log("[Admin Register] Email check failed: " . $e->getMessage());
    logAdminSecurityEvent($conn, null, 'ADMIN_REGISTER', 'DB_ERROR', 'Email duplicate check failed');
    apiError('INTERNAL_ERROR', 'Failed to check email. Please try again.', 500);
}

// Begin transaction for data consistency
$conn->begin_transaction();

try {
    // Hash password using bcrypt
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // Generate secure verification token
    $verificationToken = generateSecureToken(32);
    $tokenHash = hashAuthToken($verificationToken);
    $tokenExpirySeconds = 86400; // 24 hours

    // Create user record (users table)
    $createUserStmt = $conn->prepare(
        'INSERT INTO users 
        (email, password_hash, full_name, role, account_status, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())'
    );

    if (!$createUserStmt) {
        throw new Exception('User insert prepare failed: ' . $conn->error);
    }

    $role = 'admin';
    $accountStatus = 'pending_verification';

    $createUserStmt->bind_param('sssss', $hashedPassword, $email, $fullName, $role, $accountStatus);
    $createUserStmt->execute();
    $newUserId = $createUserStmt->insert_id;
    $createUserStmt->close();

    if ($newUserId <= 0) {
        throw new Exception('Failed to create user record');
    }

    // Create admin profile record (admins table)
    $createAdminStmt = $conn->prepare(
        'INSERT INTO admins (user_id, admin_level, department, created_at) 
         VALUES (?, ?, ?, NOW())'
    );

    if (!$createAdminStmt) {
        throw new Exception('Admin profile insert prepare failed: ' . $conn->error);
    }

    $createAdminStmt->bind_param('iss', $newUserId, $adminLevel, $department);
    $createAdminStmt->execute();
    $createAdminStmt->close();

    // Store verification token
    $storeTokenStmt = $conn->prepare(
        'INSERT INTO email_verification_tokens 
        (user_id, token_hash, expires_at, created_at) 
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND), NOW())'
    );

    if (!$storeTokenStmt) {
        throw new Exception('Token insert prepare failed: ' . $conn->error);
    }

    $storeTokenStmt->bind_param('isi', $newUserId, $tokenHash, $tokenExpirySeconds);
    $storeTokenStmt->execute();
    $storeTokenStmt->close();

    // Commit transaction
    $conn->commit();

    // Log successful registration
    logAdminSecurityEvent(
        $conn,
        $newUserId,
        'ADMIN_REGISTER',
        'SUCCESS',
        'Admin account created successfully',
        metadata: ['email' => $email, 'admin_level' => $adminLevel]
    );

    // Send verification email
    $emailHtml = getAdminRegistrationVerificationEmailHtml($verificationToken, $fullName, $email);
    @$mailResult = sendMail($email, 'Verify Your BizLink Admin Account', $emailHtml);
    
    if (!is_array($mailResult)) {
        $mailResult = ['success' => false, 'message' => 'Email send failed', 'logId' => null];
    }

    // Log email send result
    logAdminSecurityEvent(
        $conn,
        $newUserId,
        'ADMIN_EMAIL_SENT',
        $mailResult['success'] ? 'SUCCESS' : 'FAILED',
        $mailResult['message'],
        metadata: ['email' => $email, 'mail_log_id' => $mailResult['logId']]
    );

    // Return success response
    apiSuccess([
        'user_id' => $newUserId,
        'email' => $email,
        'full_name' => $fullName,
        'admin_level' => $adminLevel,
        'email_sent' => $mailResult['success'],
        'verification_method' => 'token',
        'message' => 'Please check your email to verify your account. Verification link expires in 24 hours.'
    ], 'Admin registration successful. Verification email sent.', 'ADMIN_REGISTERED');
    $conn->close();
    exit;

} catch (Throwable $e) {
    // Rollback transaction on error
    $conn->rollback();

    error_log("[Admin Register] Registration error: " . $e->getMessage());
    logAdminSecurityEvent(
        $conn,
        null,
        'ADMIN_REGISTER',
        'ERROR',
        'Registration failed: ' . $e->getMessage(),
        metadata: ['email' => $email]
    );

    apiError('REGISTRATION_FAILED', 'Failed to create admin account. Please try again.', 500, [
        ['field' => 'server', 'message' => 'Internal server error']
    ]);
} finally {
    $conn->close();
}

?>
