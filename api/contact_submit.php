<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ]);
    $conn->close();
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON payload.'
    ]);
    $conn->close();
    exit;
}

$role = strtolower(trim((string)($payload['role'] ?? 'customer')));
$name = trim((string)($payload['name'] ?? ''));
$email = strtolower(trim((string)($payload['email'] ?? '')));
$message = trim((string)($payload['message'] ?? ''));

$allowedRoles = ['admin', 'vendor', 'customer'];
if (!in_array($role, $allowedRoles, true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid role selection.'
    ]);
    $conn->close();
    exit;
}

if ($name === '' || strlen($name) < 2) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please enter your full name.'
    ]);
    $conn->close();
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please enter a valid email address.'
    ]);
    $conn->close();
    exit;
}

if ($message === '' || strlen($message) < 10) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Please enter a message with at least 10 characters.'
    ]);
    $conn->close();
    exit;
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
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to prepare contact storage: ' . $conn->error
    ]);
    $conn->close();
    exit;
}

$sourcePage = trim((string)($payload['source_page'] ?? '/pages/contact.html'));
$ipAddress = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
$userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

$stmt = $conn->prepare('INSERT INTO contact_inquiries (full_name, email, target_role, message, source_page, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)');
$stmt->bind_param('sssssss', $name, $email, $role, $message, $sourcePage, $ipAddress, $userAgent);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to submit your inquiry: ' . $stmt->error
    ]);
    $stmt->close();
    $conn->close();
    exit;
}

$inquiryId = (int)$stmt->insert_id;
$stmt->close();

echo json_encode([
    'success' => true,
    'message' => 'Your message has been sent successfully.',
    'inquiry_id' => $inquiryId
]);

$conn->close();
?>