<?php

require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use GET.', 405);
}

requireAuth(['vendor']);

$currentUser = getCurrentUser();
$userId = (int)($currentUser['user_id'] ?? 0);

// Get vendor info
$vendorStmt = $conn->prepare('SELECT vendor_id, verification_status, kyc_status FROM vendors WHERE user_id = ? LIMIT 1');
$vendorStmt->bind_param('i', $userId);
$vendorStmt->execute();
$vendor = $vendorStmt->get_result()->fetch_assoc();
$vendorStmt->close();

if (!$vendor) {
    apiError('VENDOR_NOT_FOUND', 'Vendor profile not found.', 404);
}

$vendorId = (int)$vendor['vendor_id'];

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
    'verification_status' => $vendor['verification_status'],
    'kyc_status' => $vendor['kyc_status'],
    'documents' => $documents
], 'Verification status retrieved successfully.');

$conn->close();
