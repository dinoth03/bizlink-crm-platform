<?php
session_start();
require 'config.php';
require_once 'api_helpers.php';

// Only admins can access
if (($_SESSION['role'] ?? '') !== 'admin') {
    apiError('UNAUTHORIZED', 'Only admins can access this endpoint.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use GET.', 405);
}

// Get pending vendors (verification_status = 'pending')
$stmt = $conn->prepare(
    'SELECT v.vendor_id, v.user_id, u.email, u.full_name, u.phone, u.created_at,
            v.business_name, v.business_registration_number, v.business_type, 
            v.business_category, v.business_email, v.business_phone, v.verification_status,
            u.province, u.city, u.account_status
     FROM vendors v
     JOIN users u ON v.user_id = u.user_id
     WHERE v.verification_status = "pending"
     ORDER BY v.created_at DESC
     LIMIT 100'
);
$stmt->execute();
$result = $stmt->get_result();
$vendors = [];

while ($row = $result->fetch_assoc()) {
    $vendors[] = [
        'vendor_id' => (int)$row['vendor_id'],
        'user_id' => (int)$row['user_id'],
        'email' => $row['email'],
        'full_name' => $row['full_name'],
        'phone' => $row['phone'],
        'business_name' => $row['business_name'],
        'business_registration_number' => $row['business_registration_number'],
        'business_type' => $row['business_type'],
        'business_category' => $row['business_category'],
        'business_email' => $row['business_email'],
        'business_phone' => $row['business_phone'],
        'province' => $row['province'],
        'city' => $row['city'],
        'created_at' => $row['created_at'],
        'account_status' => $row['account_status']
    ];
}

$stmt->close();
$conn->close();

apiSuccess($vendors, 'Pending vendors retrieved successfully.', 'PENDING_VENDORS_RETRIEVED');
?>
