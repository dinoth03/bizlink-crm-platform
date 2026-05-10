<?php

require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

requireAuth(['admin']);

$currentUser = getCurrentUser();
$adminUserId = (int)$currentUser['user_id'];

$payload = readJsonPayload();
$documentId = (int)($payload['document_id'] ?? 0);
$status = strtolower(trim((string)($payload['status'] ?? '')));
$rejectionReason = trim((string)($payload['rejection_reason'] ?? ''));

if ($documentId <= 0 || !in_array($status, ['verified', 'rejected'], true)) {
    apiError('VALIDATION_ERROR', 'Invalid request parameters.', 422);
}

if ($status === 'rejected' && $rejectionReason === '') {
    apiError('VALIDATION_ERROR', 'Rejection reason is required.', 422);
}

// Get document info
$docStmt = $conn->prepare('SELECT vendor_id, document_type FROM vendor_verification_documents WHERE document_id = ?');
$docStmt->bind_param('i', $documentId);
$docStmt->execute();
$doc = $docStmt->get_result()->fetch_assoc();
$docStmt->close();

if (!$doc) {
    apiError('NOT_FOUND', 'Document not found.', 404);
}

$vendorId = (int)$doc['vendor_id'];

// Update document status
$updateStmt = $conn->prepare('UPDATE vendor_verification_documents SET status = ?, rejection_reason = ?, verified_at = NOW(), verified_by = ? WHERE document_id = ?');
$updateStmt->bind_param('ssii', $status, $rejectionReason, $adminUserId, $documentId);

if (!$updateStmt->execute()) {
    apiError('DB_WRITE_ERROR', 'Failed to update document status.', 500);
}
$updateStmt->close();

// Update vendor kyc_status
// Check overall status of documents for this vendor
$checkStmt = $conn->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'verified' THEN 1 ELSE 0 END) as verified,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
    FROM vendor_verification_documents WHERE vendor_id = ?");
$checkStmt->bind_param('i', $vendorId);
$checkStmt->execute();
$stats = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

$newKycStatus = 'pending';
if ($stats['rejected'] > 0) {
    $newKycStatus = 'rejected';
} elseif ($stats['pending'] == 0 && $stats['verified'] >= 3) { // Assume 3 mandatory docs
    $newKycStatus = 'verified';
} elseif ($stats['verified'] > 0) {
    $newKycStatus = 'partially_verified';
}

$updateVendor = $conn->prepare('UPDATE vendors SET kyc_status = ? WHERE vendor_id = ?');
$updateVendor->bind_param('si', $newKycStatus, $vendorId);
$updateVendor->execute();
$updateVendor->close();

// If verified, we might want to also update verification_status to 'verified' if it was 'pending'
if ($newKycStatus === 'verified') {
    $updateVendorVerify = $conn->prepare('UPDATE vendors SET verification_status = "verified", verification_date = NOW(), verified_by = ? WHERE vendor_id = ? AND verification_status = "pending"');
    $updateVendorVerify->bind_param('ii', $adminUserId, $vendorId);
    $updateVendorVerify->execute();
    $updateVendorVerify->close();
}

apiSuccess([
    'document_id' => $documentId,
    'status' => $status,
    'kyc_status' => $newKycStatus
], 'Document status updated successfully.');

$conn->close();
