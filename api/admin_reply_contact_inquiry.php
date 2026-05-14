<?php
require 'config.php';
require_once 'api_helpers.php';
require_once 'auth_middleware.php';
require_once 'mail_service.php';
require_once 'csrf_protection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

// Enforce admin-only access
try {
    requireAuth(['admin']);
} catch (Throwable $e) {
    apiError('AUTH_REQUIRED', $e->getMessage(), 401);
}

$payload = readJsonPayload();
$errors = validateRequired($payload, ['inquiry_id', 'reply_message']);

if (!empty($errors)) {
    apiError('VALIDATION_ERROR', 'Missing required fields.', 422, $errors);
}

// CSRF Protection
$csrfToken = $payload['csrf_token'] ?? '';
if (!validateCsrfToken($conn, $csrfToken, $_SESSION['user_id'] ?? null, session_id())) {
    apiError('CSRF_VALIDATION_FAILED', 'Security validation failed. Please refresh and try again.', 403);
}

$inquiryId = (int)$payload['inquiry_id'];
$replyMessage = trim($payload['reply_message']);
$recipientEmail = isset($payload['recipient_email']) ? trim((string)$payload['recipient_email']) : '';

// Fetch inquiry details
$stmt = $conn->prepare("SELECT full_name, email, message, admin_notes FROM contact_inquiries WHERE inquiry_id = ?");
$stmt->bind_param("i", $inquiryId);
$stmt->execute();
$result = $stmt->get_result();
$inquiry = $result->fetch_assoc();
$stmt->close();

if (!$inquiry) {
    apiError('NOT_FOUND', 'Inquiry not found.', 404);
}

$targetEmail = $recipientEmail !== '' ? strtolower($recipientEmail) : strtolower(trim((string)$inquiry['email']));
if (!filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
    apiError('VALIDATION_ERROR', 'Invalid recipient email address.', 422);
}

// Update inquiry status to resolved and append to admin notes
$timestamp = date('Y-m-d H:i:s');
$newNotes = ($inquiry['admin_notes'] ? $inquiry['admin_notes'] . "\n\n" : "") . "[{$timestamp} Reply Sent]: " . $replyMessage;

$updateStmt = $conn->prepare("UPDATE contact_inquiries SET inquiry_status = 'resolved', admin_notes = ? WHERE inquiry_id = ?");
$updateStmt->bind_param("si", $newNotes, $inquiryId);
$updateStmt->execute();
$updateStmt->close();

// Send email
$subject = "Re: Your Inquiry - BizLink CRM";
$htmlBody = "
    <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
        <h2>Hi " . htmlspecialchars($inquiry['full_name']) . ",</h2>
        <p>Thank you for contacting us. Here is our response to your inquiry:</p>
        
        <div style='background:#f4f4f4; padding:15px; border-radius:5px; margin:20px 0; font-style: italic;'>
            <strong>Your original message:</strong><br>
            " . nl2br(htmlspecialchars($inquiry['message'])) . "
        </div>
        
        <div style='background:#eef3ff; padding:15px; border-radius:5px; margin:20px 0; border-left:5px solid #1c3faa;'>
            <strong>Our response:</strong><br>
            " . nl2br(htmlspecialchars($replyMessage)) . "
        </div>
        
        <p>If you have any further questions, feel free to reply to this email.</p>
        <p>Best regards,<br>The BizLink Team</p>
    </div>
";

$mailResult = sendMail($targetEmail, $subject, $htmlBody);

if ($mailResult['success']) {
    apiSuccess([
        'target_email' => $targetEmail,
        'email_sent' => true,
        'mail_message' => $mailResult['message']
    ], 'Reply sent successfully.', 'REPLY_SENT');
} else {
    error_log('[Contact Reply] Reply saved for inquiry ' . $inquiryId . ' but email delivery failed to ' . $targetEmail . ': ' . $mailResult['message']);
    apiSuccess([
        'target_email' => $targetEmail,
        'email_sent' => false,
        'mail_message' => $mailResult['message'],
        'log_id' => $mailResult['logId'] ?? null
    ], 'Reply recorded in system, but email delivery failed. Error: ' . ($mailResult['message'] ?? 'Unknown error'), 'REPLY_SAVED_EMAIL_FAILED');
}

$conn->close();
?>
