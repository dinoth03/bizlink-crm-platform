<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    $conn->close();
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON payload.'
    ]);
    $conn->close();
    exit;
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
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid role selected.'
    ]);
    $conn->close();
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please provide a valid email address.'
    ]);
    $conn->close();
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Password must be at least 8 characters.'
    ]);
    $conn->close();
    exit;
}

if ($firstName === '' || $lastName === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'First name and last name are required.'
    ]);
    $conn->close();
    exit;
}

$fullName = trim($firstName . ' ' . $lastName);

$checkStmt = $conn->prepare('SELECT user_id FROM users WHERE email = ? LIMIT 1');
$checkStmt->bind_param('s', $email);
$checkStmt->execute();
$existing = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if ($existing) {
    http_response_code(409);
    echo json_encode([
        'success' => false,
        'message' => 'This email is already registered. Please login instead.'
    ]);
    $conn->close();
    exit;
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

    $status = $role === 'vendor' ? 'pending_verification' : 'active';
    $isVerified = $role === 'vendor' ? 0 : 1;

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

    $dashboardMap = [
        'admin' => '../admin/dashboard.html',
        'vendor' => '../vendor/vendorpanel.html',
        'customer' => '../customer/dashboard.html'
    ];

    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully.',
        'user' => [
            'user_id' => $userId,
            'role' => $role,
            'email' => $email,
            'full_name' => $fullName
        ],
        'dashboard' => $dashboardMap[$role] ?? '../pages/home.html'
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create account: ' . $e->getMessage()
    ]);
}

$conn->close();
?>