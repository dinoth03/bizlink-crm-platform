<?php
session_start();
require 'config.php';
require_once 'api_helpers.php';
require 'csrf_protection.php';
require 'rate_limiting.php';
require 'secure_logging.php';

function isLocalDevEnvironment(): bool {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $server = strtolower((string)($_SERVER['SERVER_NAME'] ?? ''));
    return strpos($host, 'localhost') !== false
        || strpos($host, '127.0.0.1') !== false
        || strpos($server, 'localhost') !== false
        || strpos($server, '127.0.0.1') !== false;
}

function isMissingSecurityTableError(Throwable $e): bool {
    $msg = strtolower($e->getMessage());
    return strpos($msg, 'doesn\'t exist') !== false
        && (
            strpos($msg, 'csrf_tokens') !== false
            || strpos($msg, 'rate_limit_log') !== false
        );
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
    try {
        if (!validateCsrfToken($conn, $csrfToken, null, session_id())) {
            if (!isLocalDevEnvironment()) {
                logCsrfFailure('auth_signup');
                apiError('CSRF_VALIDATION_FAILED', 'Invalid or missing CSRF token.', 403);
            }
        }
    } catch (Throwable $csrfError) {
        if (!isLocalDevEnvironment() || !isMissingSecurityTableError($csrfError)) {
            apiError('CSRF_VALIDATION_FAILED', 'Unable to validate CSRF token.', 403, [
                ['field' => 'csrf', 'message' => $csrfError->getMessage()]
            ]);
        }
    }
}

$role = strtolower(trim((string)($payload['role'] ?? '')));
$email = strtolower(trim((string)($payload['email'] ?? '')));
$password = (string)($payload['password'] ?? '');
$firstName = trim((string)($payload['first_name'] ?? ''));
$lastName = trim((string)($payload['last_name'] ?? ''));
$phone = trim((string)($payload['phone'] ?? ''));
$profile = is_array($payload['profile'] ?? null) ? $payload['profile'] : [];

$allowedRoles = ['admin', 'vendor', 'customer'];
if (!in_array($role, $allowedRoles, true)) {
    apiError('VALIDATION_ERROR', 'Invalid role selected.', 422, [
        ['field' => 'role', 'message' => 'role must be admin, vendor, or customer.']
    ]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiError('VALIDATION_ERROR', 'Please provide a valid email address.', 422, [
        ['field' => 'email', 'message' => 'Invalid email format.']
    ]);
}

if (strlen($password) < 8) {
    apiError('VALIDATION_ERROR', 'Password must be at least 8 characters.', 422, [
        ['field' => 'password', 'message' => 'Minimum length is 8.']
    ]);
}

// Rate Limiting - per IP address for signup
$clientIp = getClientIpAddress();
try {
    requireRateLimit($conn, $clientIp, 'signup_by_ip', 10, 3600); // 10 signups per hour per IP
    requireRateLimit($conn, $email, 'signup_by_email', 5, 3600); // 5 signup attempts per hour per email
} catch (Throwable $rateLimitError) {
    if (!isLocalDevEnvironment() || !isMissingSecurityTableError($rateLimitError)) {
        apiError('RATE_LIMIT_CHECK_FAILED', 'Unable to validate request limits.', 500, [
            ['field' => 'rate_limit', 'message' => $rateLimitError->getMessage()]
        ]);
    }
}

if ($firstName === '' || $lastName === '') {
    apiError('VALIDATION_ERROR', 'First name and last name are required.', 422, [
        ['field' => 'first_name', 'message' => 'first_name is required.'],
        ['field' => 'last_name', 'message' => 'last_name is required.']
    ]);
}

$fullName = trim($firstName . ' ' . $lastName);

$checkStmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
$checkStmt->bind_param('s', $email);
$checkStmt->execute();
$existing = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if ($existing) {
    apiError('EMAIL_ALREADY_EXISTS', 'This email is already registered. Please login instead.', 409);
}

function normalizeProvince(string $value): string {
    $trimmed = trim($value);
    if ($trimmed === '') return '';
    return preg_replace('/\s+Province$/i', '', $trimmed);
}

function createStoreSlug(mysqli $conn, string $businessName): string {
    $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $businessName), '-'));
    if ($base === '') {
        $base = 'vendor-store';
    }

    $slug = $base;
    $suffix = 1;

    while (true) {
        $stmt = $conn->prepare('SELECT vendor_id FROM vendors WHERE store_url_slug = ? LIMIT 1');
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$exists) {
            return $slug;
        }

        $suffix++;
        $slug = $base . '-' . $suffix;
    }
}



try {
    $conn->begin_transaction();

    $province = '';
    $city = '';
    if ($role === 'vendor') {
        $province = normalizeProvince((string)($profile['locationProvince'] ?? ''));
    }
    if ($role === 'customer') {
        $city = trim((string)($profile['city'] ?? ''));
    }

    // All users: immediately active (no verification required)
    $status = 'active';
    $isVerified = 1;

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $insertUser = $conn->prepare(
        'INSERT INTO users (email, password_hash, phone, role, full_name, city, province, account_status, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insertUser->bind_param('ssssssssi', $email, $passwordHash, $phone, $role, $fullName, $city, $province, $status, $isVerified);
    $insertUser->execute();
    $userId = (int)$insertUser->insert_id;
    $insertUser->close();

    if ($role === 'admin') {
        $department = trim((string)($profile['department'] ?? ''));
        $adminLevel = 'manager';
        $permissions = json_encode([
            'title' => (string)($profile['title'] ?? ''),
            'access_code' => (string)($profile['accessCode'] ?? ''),
            'reason' => (string)($profile['accessReason'] ?? '')
        ]);

        $insertAdmin = $conn->prepare('INSERT INTO admins (user_id, admin_level, permissions, department) VALUES (?, ?, ?, ?)');
        $insertAdmin->bind_param('isss', $userId, $adminLevel, $permissions, $department);
        $insertAdmin->execute();
        $insertAdmin->close();
    }

    if ($role === 'vendor') {
        $businessName = trim((string)($profile['businessName'] ?? ''));
        if ($businessName === '') {
            $businessName = $fullName . "'s Store";
        }

        $businessRegNo = trim((string)($profile['businessRegNo'] ?? ''));
        $industry = trim((string)($profile['industry'] ?? ''));
        $employeeRange = trim((string)($profile['employeeRange'] ?? ''));
        $storeSlug = createStoreSlug($conn, $businessName);

        $insertVendor = $conn->prepare(
            'INSERT INTO vendors (user_id, business_name, business_registration_number, business_type, business_category, business_phone, business_email, verification_status, store_url_slug) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $vendorStatus = 'pending';
        $insertVendor->bind_param(
            'issssssss',
            $userId,
            $businessName,
            $businessRegNo,
            $employeeRange,
            $industry,
            $phone,
            $email,
            $vendorStatus,
            $storeSlug
        );
        $insertVendor->execute();
        $insertVendor->close();
    }

    if ($role === 'customer') {
        $preferredLanguage = trim((string)($profile['preferredLanguage'] ?? 'en'));
        if ($preferredLanguage === '') {
            $preferredLanguage = 'en';
        }

        $insertCustomer = $conn->prepare('INSERT INTO customers (user_id, preferred_language) VALUES (?, ?)');
        $insertCustomer->bind_param('is', $userId, $preferredLanguage);
        $insertCustomer->execute();
        $insertCustomer->close();
    }



    $conn->commit();

    apiSuccess([
        'user' => [
            'user_id' => $userId,
            'role' => $role,
            'email' => $email,
            'full_name' => $fullName
        ],
        'dashboard' => '../pages/index.html'
    ], 'Account created successfully. You can now login.', 'ACCOUNT_CREATED', 201);
} catch (Throwable $e) {
    $conn->rollback();
    apiError('INTERNAL_ERROR', 'Failed to create account.', 500, [
        ['field' => 'server', 'message' => $e->getMessage()]
    ]);
}

$conn->close();
?>