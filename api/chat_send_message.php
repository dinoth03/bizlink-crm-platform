<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    $conn->close();
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;
$senderId = isset($input['sender_id']) ? (int)$input['sender_id'] : 0;
$messageContent = trim($input['message_content'] ?? '');

if ($conversationId <= 0 || $senderId <= 0 || $messageContent === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'conversation_id, sender_id, and message_content are required']);
    $conn->close();
    exit;
}

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
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No active receiver found for this conversation']);
    $conn->close();
    exit;
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
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    $conn->close();
    exit;
}

$touchStmt = $conn->prepare('UPDATE conversations SET last_message_date = NOW() WHERE conversation_id = ?');
$touchStmt->bind_param('i', $conversationId);
$touchStmt->execute();
$touchStmt->close();

$readSelfStmt = $conn->prepare('INSERT IGNORE INTO message_reads (message_id, user_id, read_at) VALUES (?, ?, NOW())');
$readSelfStmt->bind_param('ii', $messageId, $senderId);
$readSelfStmt->execute();
$readSelfStmt->close();

echo json_encode([
    'success' => true,
    'data' => [
        'message_id' => $messageId,
        'conversation_id' => $conversationId,
        'sender_id' => $senderId,
        'receiver_id' => $receiverId,
        'message_content' => $messageContent,
        'created_at' => date('Y-m-d H:i:s')
    ]
]);

$conn->close();
?>
