<?php
require 'config.php';
require_once 'api_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

$payload = readJsonPayload();
if (!is_array($payload)) {
    apiError('INVALID_JSON', 'Invalid JSON payload.', 400);
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

$createTableSql = "
CREATE TABLE IF NOT EXISTS contact_inquiries (
    inquiry_id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    target_role ENUM('admin', 'vendor', 'customer') NOT NULL,
    message TEXT NOT NULL,
    inquiry_status ENUM('new', 'in_progress', 'resolved', 'closed') DEFAULT 'new',
    source_page VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_target_role (target_role),
    INDEX idx_status (inquiry_status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
";

if (!$conn->query($createTableSql)) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare contact storage.', 500, [
        ['field' => 'database', 'message' => $conn->error]
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