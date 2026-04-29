<?php
/**
 * AI Chatbot Response Endpoint
 * Generates AI responses for user messages using Google Generative AI
 */

require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';
require_once 'google_generative_ai_helper.php';

// Require authentication
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

// Check if Google AI is configured
if (!GoogleGenerativeAIHelper::isConfigured()) {
    apiError('CONFIG_ERROR', 'Google Generative AI is not configured.', 503);
}

$input = json_decode(file_get_contents('php://input'), true);
$conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;
$userMessage = trim($input['message_content'] ?? '');
$userId = getCurrentUser()['user_id'];

if ($conversationId <= 0 || $userMessage === '') {
    apiError('VALIDATION_ERROR', 'conversation_id and message_content are required.', 422);
}

// Verify user is participant in this conversation
$participantSql = "SELECT 1 FROM conversation_participants WHERE conversation_id = ? AND user_id = ? AND is_active = 1 LIMIT 1";
$participantStmt = $conn->prepare($participantSql);
if (!$participantStmt) {
    apiError('DB_ERROR', 'Failed to prepare statement.', 500);
}

$participantStmt->bind_param('ii', $conversationId, $userId);
$participantStmt->execute();
if ($participantStmt->get_result()->num_rows === 0) {
    apiError('FORBIDDEN', 'You are not a participant in this conversation.', 403);
}
$participantStmt->close();

// Get conversation history for context (last 5 messages)
$historySql = "
SELECT sender_id, message_content 
FROM messages 
WHERE conversation_id = ? 
ORDER BY created_at DESC 
LIMIT 5
";
$historyStmt = $conn->prepare($historySql);
if (!$historyStmt) {
    apiError('DB_ERROR', 'Failed to prepare statement.', 500);
}

$historyStmt->bind_param('i', $conversationId);
$historyStmt->execute();
$historyResult = $historyStmt->get_result();

$conversationHistory = [];
while ($row = $historyResult->fetch_assoc()) {
    $conversationHistory[] = [
        'role' => $row['sender_id'] == $userId ? 'user' : 'model',
        'content' => $row['message_content'],
    ];
}
$historyStmt->close();

// Reverse to get chronological order
$conversationHistory = array_reverse($conversationHistory);

try {
    // Generate AI response
    $aiHelper = new GoogleGenerativeAIHelper();
    $aiResponse = $aiHelper->generateResponse($userMessage, $conversationHistory);

    if (!$aiResponse['success']) {
        error_log("AI Response Generation Failed: " . json_encode($aiResponse));
        apiError('AI_ERROR', 'Failed to generate AI response: ' . $aiResponse['error'], 500);
    }

    // Get AI Bot user ID (create if not exists)
    $aiBotUserId = getOrCreateAIBotUser($conn);

    // Store the AI response in the database
    $insertSql = "
    INSERT INTO messages (conversation_id, sender_id, receiver_id, message_content, message_type, is_read, created_at)
    VALUES (?, ?, ?, ?, 'ai', 0, NOW())
    ";
    $insertStmt = $conn->prepare($insertSql);
    if (!$insertStmt) {
        apiError('DB_ERROR', 'Failed to prepare insert statement.', 500);
    }

    $insertStmt->bind_param('iiis', $conversationId, $aiBotUserId, $userId, $aiResponse['response']);
    if (!$insertStmt->execute()) {
        error_log("Failed to insert AI message: " . $insertStmt->error);
        apiError('DB_ERROR', 'Failed to store AI response in database.', 500);
    }

    $messageId = $insertStmt->insert_id;
    $insertStmt->close();

    // Update last message timestamp
    $touchStmt = $conn->prepare('UPDATE conversations SET last_message_date = NOW() WHERE conversation_id = ?');
    if ($touchStmt) {
        $touchStmt->bind_param('i', $conversationId);
        $touchStmt->execute();
        $touchStmt->close();
    }

    apiSuccess([
        'message_id' => $messageId,
        'conversation_id' => $conversationId,
        'sender_id' => $aiBotUserId,
        'receiver_id' => $userId,
        'message_content' => $aiResponse['response'],
        'message_type' => 'ai',
        'created_at' => date('Y-m-d H:i:s'),
        'model' => $aiResponse['model'] ?? 'gemini-pro',
    ], 'AI Response generated successfully.', 'AI_RESPONSE_SENT', 201);

} catch (Exception $e) {
    error_log("AI Bot Response Exception: " . $e->getMessage());
    apiError('INTERNAL_ERROR', 'An error occurred: ' . $e->getMessage(), 500);
} finally {
    $conn->close();
}

/**
 * Get or create the AI Bot user account
 */
function getOrCreateAIBotUser($conn) {
    global $conn;

    // Check if AI Bot already exists
    $checkSql = "SELECT user_id FROM users WHERE full_name = 'AI Assistant' AND role = 'bot' LIMIT 1";
    $checkStmt = $conn->prepare($checkSql);
    if ($checkStmt) {
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $checkStmt->close();
            return (int)$row['user_id'];
        }
        $checkStmt->close();
    }

    // Create AI Bot user
    $email = 'ai-bot@bizlink-crm.local';
    $fullName = 'AI Assistant';
    $role = 'bot';
    $passwordHash = password_hash('secure-bot-password-' . time(), PASSWORD_BCRYPT);
    $status = 'active';

    $insertSql = "
    INSERT INTO users (email, full_name, role, password_hash, account_status, email_verified, created_at)
    VALUES (?, ?, ?, ?, ?, 1, NOW())
    ";
    $insertStmt = $conn->prepare($insertSql);
    if (!$insertStmt) {
        throw new Exception('Failed to prepare AI Bot creation statement');
    }

    $insertStmt->bind_param('sssss', $email, $fullName, $role, $passwordHash, $status);
    if (!$insertStmt->execute()) {
        throw new Exception('Failed to create AI Bot user: ' . $insertStmt->error);
    }

    $aiBotUserId = $insertStmt->insert_id;
    $insertStmt->close();

    error_log("Created new AI Bot user with ID: $aiBotUserId");
    return $aiBotUserId;
}

?>
