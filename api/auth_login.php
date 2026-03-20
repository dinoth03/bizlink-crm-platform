<?php
session_start();
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

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email and password are required.'
    ]);
    $conn->close();
    exit;
}

$stmt = $conn->prepare(
    'SELECT user_id, email, password_hash, role, full_name, account_status FROM users WHERE email = ? AND role = ? AND deleted_at IS NULL LIMIT 1'
);
$stmt->bind_param('ss', $email, $role);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid credentials or role mismatch.'
    ]);
    $conn->close();
    exit;
}

$status = strtolower((string)$user['account_status']);
if (in_array($status, ['inactive', 'suspended'], true)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Your account is not active. Please contact support.'
    ]);
    $conn->close();
    exit;
}

$storedHash = (string)$user['password_hash'];
$isValid = password_verify($password, $storedHash);

// Support legacy plain-text records from old seed/demo data.
if (!$isValid && hash_equals($storedHash, $password)) {
    $isValid = true;
}

if (!$isValid) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Incorrect password.'
    ]);
    $conn->close();
    exit;
}

$updateStmt = $conn->prepare('UPDATE users SET last_login = NOW() WHERE user_id = ?');
$updateStmt->bind_param('i', $user['user_id']);
$updateStmt->execute();
$updateStmt->close();

// Set PHP session variables for server-side authentication
$_SESSION['user_id'] = (int)$user['user_id'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = $user['role'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['login_time'] = time();

$dashboardMap = [
    'admin' => '../admin/dashboard.html',
    'vendor' => '../vendor/vendorpanel.html',
    'customer' => '../customer/dashboard.html'
];

echo json_encode([
    'success' => true,
    'message' => 'Login successful.',
    'user' => [
        'user_id' => (int)$user['user_id'],
        'role' => $user['role'],
        'email' => $user['email'],
        'full_name' => $user['full_name']
    ],
    'dashboard' => $dashboardMap[$role] ?? '../pages/home.html'
]);

$conn->close();
?>