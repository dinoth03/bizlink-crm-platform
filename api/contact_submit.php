<?php
require 'config.php';
require_once 'api_helpers.php';
require_once 'csrf_protection.php';
require_once 'rate_limiting.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

$payload = readJsonPayload();
if (!is_array($payload)) {
    apiError('INVALID_JSON', 'Invalid JSON payload.', 400);
}

// CSRF protection for public endpoint (no session required)
$csrfToken = $payload['csrf_token'] ?? '';
if (!validateCsrfToken($conn, $csrfToken, null, session_id())) {
    apiError('CSRF_VALIDATION_FAILED', 'Security validation failed. Please refresh and try again.', 403);
}

// Rate limit: max 5 submissions per IP per hour
$clientIp = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
$rateLimitResult = checkRateLimit($conn, $clientIp, 'contact_submit_by_ip', 5, 3600);
if (!$rateLimitResult->allowed) {
    apiError('RATE_LIMIT_EXCEEDED', 'Too many submissions. Please try again later.', 429, [
        ['field' => 'rate_limit', 'message' => 'Maximum 5 submissions per hour per IP.']
    ]);
}

$role = strtolower(trim((string)($payload['role'] ?? 'customer')));
$name = trim((string)($payload['name'] ?? ''));
$email = strtolower(trim((string)($payload['email'] ?? '')));
$message = trim((string)($payload['message'] ?? ''));

$allowedRoles = ['admin', 'vendor', 'customer'];
if (!in_array($role, $allowedRoles, true)) {
    apiError('VALIDATION_ERROR', 'Invalid role selection.', 422, [
        ['field' => 'role', 'message' => 'role must be admin, vendor, or customer.']
    ]);
}

if ($name === '' || strlen($name) < 2) {
    apiError('VALIDATION_ERROR', 'Please enter your full name.', 422, [
        ['field' => 'name', 'message' => 'name must be at least 2 characters.']
    ]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    apiError('VALIDATION_ERROR', 'Please enter a valid email address.', 422, [
        ['field' => 'email', 'message' => 'Invalid email format.']
    ]);
}

if ($message === '' || strlen($message) < 10) {
    apiError('VALIDATION_ERROR', 'Please enter a message with at least 10 characters.', 422, [
        ['field' => 'message', 'message' => 'message must be at least 10 characters.']
    ]);
}

$sourcePage = trim((string)($payload['source_page'] ?? '/pages/contact.html'));
$ipAddress = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
$userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

$stmt = $conn->prepare('INSERT INTO contact_inquiries (full_name, email, target_role, message, source_page, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)');
$stmt->bind_param('sssssss', $name, $email, $role, $message, $sourcePage, $ipAddress, $userAgent);

if (!$stmt->execute()) {
    apiError('DB_WRITE_ERROR', 'Failed to submit your inquiry.', 500, [
        ['field' => 'database', 'message' => $stmt->error]
    ]);
}

$inquiryId = (int)$stmt->insert_id;
$stmt->close();

apiSuccess([
    'inquiry_id' => $inquiryId
], 'Your message has been sent successfully.', 'CONTACT_SUBMITTED', 201);

$conn->close();
?>