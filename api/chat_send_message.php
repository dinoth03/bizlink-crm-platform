<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';

// Require authentication
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;
$messageContent = trim($input['message_content'] ?? '');

// Get sender from authenticated session (not from request)
$senderId = getCurrentUser()['user_id'];

if ($conversationId <= 0 || $messageContent === '') {
    apiError('VALIDATION_ERROR', 'conversation_id and message_content are required.', 422, [
        ['field' => 'conversation_id', 'message' => 'conversation_id must be > 0.'],
        ['field' => 'message_content', 'message' => 'message_content cannot be empty.']
    ]);
}

if (strlen($messageContent) > 2000) {
    apiError('VALIDATION_ERROR', 'message_content is too long.', 422, [
        ['field' => 'message_content', 'message' => 'Maximum 2000 characters allowed.']
    ]);
}

// Verify sender is a participant in this conversation
$participantSql = "SELECT 1 FROM conversation_participants WHERE conversation_id = ? AND user_id = ? AND is_active = 1 LIMIT 1";
$participantStmt = $conn->prepare($participantSql);
$participantStmt->bind_param('ii', $conversationId, $senderId);
$participantStmt->execute();
$participantRes = $participantStmt->get_result();
if ($participantRes->num_rows === 0) {
    apiError('FORBIDDEN', 'You are not a participant in this conversation.', 403);
}
$participantStmt->close();

$receiverSql = "
SELECT cp.user_id
FROM conversation_participants cp
WHERE cp.conversation_id = ? AND cp.user_id <> ? AND cp.is_active = 1
LIMIT 1
";
$receiverStmt = $conn->prepare($receiverSql);
$receiverStmt->bind_param('ii', $conversationId, $senderId);
$receiverStmt->execute();
$receiverRes = $receiverStmt->get_result();
$receiverRow = $receiverRes->fetch_assoc();
$receiverStmt->close();

if (!$receiverRow) {
    apiError('RECEIVER_NOT_FOUND', 'No active receiver found for this conversation.', 404);
}
$receiverId = (int)$receiverRow['user_id'];

$insertSql = "
INSERT INTO messages (conversation_id, sender_id, receiver_id, message_content, message_type, is_read, created_at)
VALUES (?, ?, ?, ?, 'text', 0, NOW())
";
$insertStmt = $conn->prepare($insertSql);
$insertStmt->bind_param('iiis', $conversationId, $senderId, $receiverId, $messageContent);
$ok = $insertStmt->execute();
$messageId = $insertStmt->insert_id;
$insertStmt->close();

if (!$ok) {
    apiError('DB_WRITE_ERROR', 'Failed to send message.', 500);
}

$touchStmt = $conn->prepare('UPDATE conversations SET last_message_date = NOW() WHERE conversation_id = ?');
$touchStmt->bind_param('i', $conversationId);
$touchStmt->execute();
$touchStmt->close();

$readSelfStmt = $conn->prepare('INSERT IGNORE INTO message_reads (message_id, user_id, read_at) VALUES (?, ?, NOW())');
$readSelfStmt->bind_param('ii', $messageId, $senderId);
$readSelfStmt->execute();
$readSelfStmt->close();

apiSuccess([
        'message_id' => $messageId,
        'conversation_id' => $conversationId,
        'sender_id' => $senderId,
        'receiver_id' => $receiverId,
        'message_content' => $messageContent,
        'created_at' => date('Y-m-d H:i:s')
], 'Message sent successfully.', 'MESSAGE_SENT', 201);

$conn->close();
?>
