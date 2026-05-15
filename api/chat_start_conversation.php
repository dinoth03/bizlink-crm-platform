<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';

$input = json_decode(file_get_contents('php://input'), true);
$targetUserId = (int)($input['target_user_id'] ?? $input['receiver_id'] ?? 0);

// Check if target is AI Bot (usually 999 or has role 'bot')
$isBotTarget = false;
if ($targetUserId > 0) {
    $botCheck = $conn->prepare('SELECT role FROM users WHERE user_id = ? LIMIT 1');
    if ($botCheck) {
        $botCheck->bind_param('i', $targetUserId);
        $botCheck->execute();
        $botUser = $botCheck->get_result()->fetch_assoc();
        if ($botUser && $botUser['role'] === 'bot') {
            $isBotTarget = true;
        }
        $botCheck->close();
    }
}

if (!$isBotTarget) {
    requireAuth(['customer', 'vendor', 'admin']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

$current = isLoggedIn() ? getCurrentUser() : ['user_id' => 0, 'full_name' => 'Guest User', 'role' => 'guest'];
$currentUserId = (int)($current['user_id'] ?? 0);

if ($isBotTarget && $currentUserId <= 0) {
    // Virtual success for guests talking to bot
    apiSuccess([
        'conversation_id' => 0,
        'conversation_key' => 'ai-conv',
        'created' => true,
        'target_user_id' => $targetUserId,
        'target_role' => 'bot',
        'is_guest' => true
    ], 'Guest AI conversation initialized.', 'CHAT_CONVERSATION_CREATED', 201);
}

if ($currentUserId <= 0 || $targetUserId <= 0 || $targetUserId === $currentUserId) {
    apiError('VALIDATION_ERROR', 'A valid target_user_id is required.', 422);
}

$userStmt = $conn->prepare('SELECT user_id, full_name, role, account_status FROM users WHERE user_id = ? AND deleted_at IS NULL LIMIT 1');
if (!$userStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare user lookup.', 500);
}

$userStmt->bind_param('i', $targetUserId);
$userStmt->execute();
$targetUser = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

if (!$targetUser) {
    apiError('USER_NOT_FOUND', 'Target user not found.', 404);
}

$role = strtolower((string)($current['role'] ?? ''));
$targetRole = strtolower((string)($targetUser['role'] ?? ''));
$allowedMap = [
    'customer' => ['vendor', 'admin', 'customer', 'bot'],
    'vendor'   => ['customer', 'admin', 'vendor', 'bot'],
    'admin'    => ['customer', 'vendor', 'admin', 'bot'],
];

if (!in_array($targetRole, $allowedMap[$role] ?? [], true)) {
    apiError('FORBIDDEN', 'You cannot start a chat with this user role.', 403);
}

$existingSql = "
SELECT c.conversation_id
FROM conversations c
JOIN conversation_participants cp1 ON cp1.conversation_id = c.conversation_id AND cp1.user_id = ? AND cp1.is_active = 1
JOIN conversation_participants cp2 ON cp2.conversation_id = c.conversation_id AND cp2.user_id = ? AND cp2.is_active = 1
WHERE c.is_active = 1
LIMIT 1
";
$existingStmt = $conn->prepare($existingSql);
if (!$existingStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare conversation lookup.', 500);
}

$existingStmt->bind_param('ii', $currentUserId, $targetUserId);
$existingStmt->execute();
$existing = $existingStmt->get_result()->fetch_assoc();
$existingStmt->close();

if ($existing) {
    apiSuccess([
        'conversation_id' => (int)$existing['conversation_id'],
        'conversation_key' => 'conv' . (int)$existing['conversation_id'],
        'created' => false,
    ], 'Conversation already exists.', 'CHAT_CONVERSATION_EXISTS');
}

$conversationType = 'customer_support';

$subject = trim($current['full_name'] . ' and ' . $targetUser['full_name'] . ' chat');

$conn->begin_transaction();

try {
    $convStmt = $conn->prepare('INSERT INTO conversations (sender_id, receiver_id, conversation_type, subject, last_message_date, is_active) VALUES (?, ?, ?, ?, NOW(), 1)');
    if (!$convStmt) {
        throw new Exception('Failed to prepare conversation insert.');
    }

    $convStmt->bind_param('iiss', $currentUserId, $targetUserId, $conversationType, $subject);
    if (!$convStmt->execute()) {
        throw new Exception('Failed to create conversation.');
    }

    $conversationId = (int)$convStmt->insert_id;
    $convStmt->close();

    $participantStmt = $conn->prepare('INSERT INTO conversation_participants (conversation_id, user_id, participant_role) VALUES (?, ?, ?)');
    if (!$participantStmt) {
        throw new Exception('Failed to prepare participant insert.');
    }

    $currentRole = $role;
    $participantStmt->bind_param('iis', $conversationId, $currentUserId, $currentRole);
    if (!$participantStmt->execute()) {
        throw new Exception('Failed to insert sender participant.');
    }

    $targetRoleValue = $targetRole;
    $participantStmt->bind_param('iis', $conversationId, $targetUserId, $targetRoleValue);
    if (!$participantStmt->execute()) {
        throw new Exception('Failed to insert receiver participant.');
    }

    $participantStmt->close();
    $conn->commit();

    apiSuccess([
        'conversation_id' => $conversationId,
        'conversation_key' => 'conv' . $conversationId,
        'created' => true,
        'target_user_id' => $targetUserId,
        'target_role' => $targetRole,
    ], 'Conversation created successfully.', 'CHAT_CONVERSATION_CREATED', 201);
} catch (Throwable $e) {
    $conn->rollback();
    apiError('CHAT_CONVERSATION_CREATE_FAILED', $e->getMessage(), 500);
}

$conn->close();
?>