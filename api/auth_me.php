<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';

requireAuth(['admin', 'vendor', 'customer']);

$current = getCurrentUser();
$userId = (int)($current['user_id'] ?? 0);

$userSql = "SELECT user_id, email, role, full_name, phone, city, country, account_status, is_verified, created_at, last_login
            FROM users
            WHERE user_id = ? AND deleted_at IS NULL
            LIMIT 1";
$userStmt = $conn->prepare($userSql);
if (!$userStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare user query.', 500);
}
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$user) {
    apiError('USER_NOT_FOUND', 'Authenticated user was not found.', 404);
}

$profile = [];
$role = strtolower((string)$user['role']);

if ($role === 'vendor') {
    $vendorSql = "SELECT vendor_id, business_name, business_category, business_email, business_phone, verification_status
                  FROM vendors
                  WHERE user_id = ?
                  LIMIT 1";
    $vendorStmt = $conn->prepare($vendorSql);
    if ($vendorStmt) {
        $vendorStmt->bind_param('i', $userId);
        $vendorStmt->execute();
        $profile = $vendorStmt->get_result()->fetch_assoc() ?: [];
        $vendorStmt->close();
    }
} elseif ($role === 'customer') {
    $customerSql = "SELECT customer_id, preferred_language
                    FROM customers
                    WHERE user_id = ?
                    LIMIT 1";
    $customerStmt = $conn->prepare($customerSql);
    if ($customerStmt) {
        $customerStmt->bind_param('i', $userId);
        $customerStmt->execute();
        $profile = $customerStmt->get_result()->fetch_assoc() ?: [];
        $customerStmt->close();
    }
} elseif ($role === 'admin') {
    $adminSql = "SELECT admin_id, admin_level, department
                 FROM admins
                 WHERE user_id = ?
                 LIMIT 1";
    $adminStmt = $conn->prepare($adminSql);
    if ($adminStmt) {
        $adminStmt->bind_param('i', $userId);
        $adminStmt->execute();
        $profile = $adminStmt->get_result()->fetch_assoc() ?: [];
        $adminStmt->close();
    }
}

apiSuccess([
    'user' => $user,
    'profile' => $profile,
    'session' => [
        'user_id' => (int)$current['user_id'],
        'email' => $current['email'],
        'role' => $current['role'],
        'full_name' => $current['full_name'],
        'login_time' => (int)($_SESSION['login_time'] ?? 0),
        'last_activity' => (int)($_SESSION['last_activity'] ?? 0)
    ]
], 'Authenticated user retrieved.', 'AUTH_ME_SUCCESS');

$conn->close();
?>
