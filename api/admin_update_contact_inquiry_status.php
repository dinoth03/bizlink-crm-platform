<?php
require 'config.php';
require_once 'api_helpers.php';
require_once 'auth_middleware.php';
require_once 'csrf_protection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

$payload = readJsonPayload();
if (!is_array($payload)) {
    apiError('INVALID_JSON', 'Invalid JSON payload.', 400);
}

// Enforce admin-only access
try {
    requireAuth($conn, 'admin');
} catch (Throwable $e) {
    apiError('AUTH_REQUIRED', $e->getMessage(), 401);
}

// CSRF protection (user is authenticated)
$csrfToken = $payload['csrf_token'] ?? '';
$userId = $_SESSION['user_id'] ?? null;
if (!validateCsrfToken($conn, $csrfToken, $userId, session_id())) {
    apiError('CSRF_VALIDATION_FAILED', 'Security validation failed. Please refresh and try again.', 403);
}

$inquiryId = isset($payload['inquiry_id']) ? (int)$payload['inquiry_id'] : 0;
$newStatus = strtolower(trim((string)($payload['status'] ?? '')));
$adminNotes = trim((string)($payload['admin_notes'] ?? ''));

if ($inquiryId <= 0) {
    apiError('VALIDATION_ERROR', 'Invalid inquiry ID.', 422, [
        ['field' => 'inquiry_id', 'message' => 'inquiry_id must be a positive integer.']
    ]);
}

$allowedStatuses = ['new', 'in_progress', 'resolved', 'closed'];
if (!in_array($newStatus, $allowedStatuses, true)) {
    apiError('VALIDATION_ERROR', 'Invalid status value.', 422, [
        ['field' => 'status', 'message' => 'status must be one of: ' . implode(', ', $allowedStatuses)]
    ]);
}

// Verify inquiry exists
$checkStmt = $conn->prepare('SELECT inquiry_id FROM contact_inquiries WHERE inquiry_id = ?');
$checkStmt->bind_param('i', $inquiryId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
if ($checkResult->num_rows === 0) {
    $checkStmt->close();
    apiError('NOT_FOUND', 'Inquiry not found.', 404);
}
$checkStmt->close();

// Update status and admin notes
$updateStmt = $conn->prepare(
    'UPDATE contact_inquiries 
     SET inquiry_status = ?, admin_notes = ?, updated_at = CURRENT_TIMESTAMP 
     WHERE inquiry_id = ?'
);
$updateStmt->bind_param('ssi', $newStatus, $adminNotes, $inquiryId);

if (!$updateStmt->execute()) {
    $updateStmt->close();
    apiError('DB_WRITE_ERROR', 'Failed to update inquiry status.', 500, [
        ['field' => 'database', 'message' => $updateStmt->error]
    ]);
}

$affectedRows = $updateStmt->affected_rows;
$updateStmt->close();

// Fetch updated inquiry
$fetchStmt = $conn->prepare(
    'SELECT inquiry_id, full_name, email, target_role, message, inquiry_status, admin_notes, 
            created_at, updated_at 
     FROM contact_inquiries 
     WHERE inquiry_id = ?'
);
$fetchStmt->bind_param('i', $inquiryId);
$fetchStmt->execute();
$result = $fetchStmt->get_result();
$inquiry = $result->fetch_assoc();
$fetchStmt->close();

apiSuccess([
    'inquiry' => [
        'inquiry_id' => (int)$inquiry['inquiry_id'],
        'full_name' => $inquiry['full_name'],
        'email' => $inquiry['email'],
        'target_role' => $inquiry['target_role'],
        'message' => $inquiry['message'],
        'status' => $inquiry['inquiry_status'],
        'admin_notes' => $inquiry['admin_notes'],
        'created_at' => $inquiry['created_at'],
        'updated_at' => $inquiry['updated_at']
    ]
], 'Inquiry status updated successfully.', 'INQUIRY_UPDATED', 200);

$conn->close();
?>
