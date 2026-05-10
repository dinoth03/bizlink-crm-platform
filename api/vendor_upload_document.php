<?php

require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';
require_once 'csrf_protection.php';
require_once 'rate_limiting.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

requireAuth(['vendor']);

$currentUser = getCurrentUser();
$userId = (int)($currentUser['user_id'] ?? 0);
if ($userId <= 0) {
    apiError('UNAUTHORIZED', 'Unauthorized user.', 401);
}

if (CSRF_ENABLED) {
    requireCsrfToken($conn, $userId);
}

$clientIp = getClientIpAddress();
requireRateLimit($conn, $clientIp, 'vendor_upload_doc_by_ip', 20, 900);
requireRateLimit($conn, 'vendor_doc:' . $userId, 'vendor_upload_doc_by_user', 10, 900);

$uploadRelativeDir = '../uploads/vendor-docs';
$uploadAbsoluteDir = __DIR__ . '/../uploads/vendor-docs';
if (!is_dir($uploadAbsoluteDir)) {
    @mkdir($uploadAbsoluteDir, 0777, true);
}

$docType = sanitizeString((string)($_POST['document_type'] ?? ''), 50);
$allowedDocTypes = ['business_license', 'tax_certificate', 'identity_proof', 'other'];

if (!in_array($docType, $allowedDocTypes, true)) {
    apiError('VALIDATION_ERROR', 'Invalid document type.', 422, [
        ['field' => 'document_type', 'message' => 'Must be business_license, tax_certificate, identity_proof, or other.']
    ]);
}

if (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    apiError('VALIDATION_ERROR', 'Please select a valid file to upload.', 422, [
        ['field' => 'document', 'message' => 'File is required.']
    ]);
}

$uploadedFile = $_FILES['document'];
$tmpPath = $uploadedFile['tmp_name'];
$originalName = $uploadedFile['name'];
$fileSize = $uploadedFile['size'];
$fileType = $uploadedFile['type'];

// Max size: 10MB
if ($fileSize > 10 * 1024 * 1024) {
    apiError('VALIDATION_ERROR', 'Document must be 10MB or smaller.', 422, [
        ['field' => 'document', 'message' => 'File is too large.']
    ]);
}

$allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
$detectedMime = null;
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $detectedMime = finfo_file($finfo, $tmpPath) ?: null;
        finfo_close($finfo);
    }
}
$mimeType = $detectedMime ?: $fileType;

if (!in_array($mimeType, $allowedMimeTypes, true)) {
    apiError('VALIDATION_ERROR', 'Only PDF, JPG, and PNG files are allowed.', 422, [
        ['field' => 'document', 'message' => 'Unsupported file type: ' . $mimeType]
    ]);
}

$extension = pathinfo($originalName, PATHINFO_EXTENSION);
$savedFileName = $docType . '-' . $userId . '-' . time() . '.' . $extension;
$targetPath = $uploadAbsoluteDir . '/' . $savedFileName;

if (!move_uploaded_file($tmpPath, $targetPath)) {
    apiError('UPLOAD_FAILED', 'Failed to save the uploaded document.', 500);
}

$documentUrl = $uploadRelativeDir . '/' . $savedFileName;

// Get vendor ID
$vendorStmt = $conn->prepare('SELECT vendor_id FROM vendors WHERE user_id = ? LIMIT 1');
$vendorStmt->bind_param('i', $userId);
$vendorStmt->execute();
$vendor = $vendorStmt->get_result()->fetch_assoc();
$vendorStmt->close();

$vendorId = (int)($vendor['vendor_id'] ?? 0);
if ($vendorId <= 0) {
    apiError('VENDOR_NOT_FOUND', 'Vendor profile not found.', 404);
}

// Check if a document of this type already exists and is pending or rejected
$checkStmt = $conn->prepare('SELECT document_id FROM vendor_verification_documents WHERE vendor_id = ? AND document_type = ? AND status != "verified"');
$checkStmt->bind_param('is', $vendorId, $docType);
$checkStmt->execute();
$existingDoc = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

if ($existingDoc) {
    // Update existing
    $stmt = $conn->prepare('UPDATE vendor_verification_documents SET document_url = ?, status = "pending", rejection_reason = NULL, uploaded_at = NOW() WHERE document_id = ?');
    $stmt->bind_param('si', $documentUrl, $existingDoc['document_id']);
} else {
    // Insert new
    $stmt = $conn->prepare('INSERT INTO vendor_verification_documents (vendor_id, document_type, document_url, status, uploaded_at) VALUES (?, ?, ?, "pending", NOW())');
    $stmt->bind_param('iss', $vendorId, $docType, $documentUrl);
}

if (!$stmt->execute()) {
    apiError('DB_WRITE_ERROR', 'Failed to save document info to database.', 500);
}
$stmt->close();

// Update vendor kyc_status to pending if it was not_started or rejected
$updateKyc = $conn->prepare('UPDATE vendors SET kyc_status = "pending" WHERE vendor_id = ? AND (kyc_status = "not_started" OR kyc_status = "rejected")');
$updateKyc->bind_param('i', $vendorId);
$updateKyc->execute();
$updateKyc->close();

apiSuccess([
    'document_url' => $documentUrl,
    'status' => 'pending'
], 'Document uploaded successfully.', 'DOCUMENT_UPLOADED', 201);

$conn->close();
