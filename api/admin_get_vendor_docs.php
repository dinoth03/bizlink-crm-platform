<?php

require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use GET.', 405);
}

requireAuth(['admin']);

$vendorId = (int)($_GET['vendor_id'] ?? 0);
if ($vendorId <= 0) {
    apiError('VALIDATION_ERROR', 'Vendor ID is required.', 422);
}

// Get vendor info
$vendorStmt = $conn->prepare('SELECT business_name, kyc_status, verification_status FROM vendors WHERE vendor_id = ? LIMIT 1');
$vendorStmt->bind_param('i', $vendorId);
$vendorStmt->execute();
$vendor = $vendorStmt->get_result()->fetch_assoc();
$vendorStmt->close();

if (!$vendor) {
    apiError('VENDOR_NOT_FOUND', 'Vendor profile not found.', 404);
}

// Get documents
$docsStmt = $conn->prepare('SELECT document_id, document_type, document_url, status, rejection_reason, uploaded_at, verified_at FROM vendor_verification_documents WHERE vendor_id = ?');
$docsStmt->bind_param('i', $vendorId);
$docsStmt->execute();
$result = $docsStmt->get_result();
$documents = [];
while ($row = $result->fetch_assoc()) {
    $documents[] = $row;
}
$docsStmt->close();

apiSuccess([
    'vendor_id' => $vendorId,
    'business_name' => $vendor['business_name'],
    'verification_status' => $vendor['verification_status'],
    'kyc_status' => $vendor['kyc_status'],
    'documents' => $documents
], 'Vendor documents retrieved successfully.');

$conn->close();
