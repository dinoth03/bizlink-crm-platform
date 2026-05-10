<?php

require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use GET.', 405);
}

requireAuth(['admin']);

// Get vendors who have pending documents or are in pending kyc status
$sql = "SELECT v.vendor_id, v.business_name, v.business_registration_number, v.kyc_status, v.verification_status, u.email, u.full_name,
        (SELECT COUNT(*) FROM vendor_verification_documents WHERE vendor_id = v.vendor_id AND status = 'pending') as pending_docs_count
        FROM vendors v
        JOIN users u ON v.user_id = u.user_id
        WHERE v.kyc_status = 'pending' OR v.kyc_status = 'partially_verified'
        ORDER BY v.updated_at DESC";

$result = $conn->query($sql);
$requests = [];
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

apiSuccess([
    'requests' => $requests,
    'total_pending' => count($requests)
], 'Verification requests retrieved successfully.');

$conn->close();
