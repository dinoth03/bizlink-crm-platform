<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';

// Require authentication
requireAuth();

$userId = getCurrentUser()['user_id'];

function mapStatus(?string $accountStatus): string {
    $status = strtolower((string)$accountStatus);
    if ($status === 'active') return 'online';
    if ($status === 'pending_verification') return 'away';
    return 'offline';
}

$currentUserStmt = $conn->prepare('SELECT user_id, full_name, role FROM users WHERE user_id = ? LIMIT 1');
$currentUserStmt->bind_param('i', $userId);
$currentUserStmt->execute();
$currentUserRes = $currentUserStmt->get_result();
$currentUser = $currentUserRes->fetch_assoc();
$currentUserStmt->close();

if (!$currentUser) {
    apiError('USER_NOT_FOUND', 'Current user not found.', 404);
}

$convSql = "
SELECT DISTINCT c.conversation_id, c.subject, c.last_message_date
FROM conversations c
JOIN conversation_participants cp ON cp.conversation_id = c.conversation_id
WHERE cp.user_id = ? AND cp.is_active = 1 AND c.is_active = 1
ORDER BY COALESCE(c.last_message_date, c.created_at) DESC
";
$convStmt = $conn->prepare($convSql);
$convStmt->bind_param('i', $userId);
$convStmt->execute();
$convRes = $convStmt->get_result();

$contacts = [];
$conversations = [];

while ($conv = $convRes->fetch_assoc()) {
    $conversationId = (int)$conv['conversation_id'];

    $contactSql = "
    SELECT u.user_id, u.full_name, u.role, u.email, u.phone, u.province, u.account_status,
           v.business_name
    FROM conversation_participants cp
    JOIN users u ON cp.user_id = u.user_id
    LEFT JOIN vendors v ON v.user_id = u.user_id
    WHERE cp.conversation_id = ? AND cp.user_id <> ?
    LIMIT 1
    ";
    $contactStmt = $conn->prepare($contactSql);
    $contactStmt->bind_param('ii', $conversationId, $userId);
    $contactStmt->execute();
    $contactRes = $contactStmt->get_result();
    $contact = $contactRes->fetch_assoc();
    $contactStmt->close();

    if (!$contact) {
        continue;
    }

    $contactId = (int)$contact['user_id'];
    $contactKey = 'u' . $contactId;

    if (!isset($contacts[$contactKey])) {
        $initials = '';
        foreach (explode(' ', $contact['full_name']) as $part) {
            if ($part !== '') {
                $initials .= strtoupper($part[0]);
            }
        }
        $contacts[$contactKey] = [
            'id' => $contactKey,
            'userId' => $contactId,
            'name' => $contact['full_name'],
            'initials' => substr($initials, 0, 2),
            'role' => $contact['role'],
            'color' => $contact['role'] === 'vendor' ? '#50C878' : ($contact['role'] === 'admin' ? '#000080' : '#FF8C00'),
            'status' => mapStatus($contact['account_status']),
            'company' => $contact['business_name'] ?: '—',
            'phone' => $contact['phone'] ?: '—',
            'email' => $contact['email'],
            'province' => $contact['province'] ?: '—',
            'joined' => '2026'
        ];
    }

    $unreadSql = "
    SELECT COUNT(*) AS unread_count
    FROM messages m
    WHERE m.conversation_id = ?
      AND m.sender_id <> ?
      AND NOT EXISTS (
          SELECT 1 FROM message_reads mr
          WHERE mr.message_id = m.message_id AND mr.user_id = ?
      )
    ";
    $unreadStmt = $conn->prepare($unreadSql);
    $unreadStmt->bind_param('iii', $conversationId, $userId, $userId);
    $unreadStmt->execute();
    $unreadRes = $unreadStmt->get_result();
    $unreadRow = $unreadRes->fetch_assoc();
    $unreadStmt->close();

    $msgSql = "
    SELECT m.message_id, m.sender_id, m.message_content, m.message_type, m.file_url, m.created_at,
           CASE WHEN EXISTS (
             SELECT 1 FROM message_reads mr
             WHERE mr.message_id = m.message_id AND mr.user_id = ?
           ) THEN 1 ELSE 0 END AS is_read_by_me
    FROM messages m
    WHERE m.conversation_id = ?
    ORDER BY m.created_at ASC
    ";
    $msgStmt = $conn->prepare($msgSql);
    $msgStmt->bind_param('ii', $userId, $conversationId);
    $msgStmt->execute();
    $msgRes = $msgStmt->get_result();

    $messages = [];
    while ($m = $msgRes->fetch_assoc()) {
        $from = ((int)$m['sender_id'] === $userId) ? 'me' : $contactKey;
        $dt = new DateTime($m['created_at']);
        $messages[] = [
            'id' => 'm' . (int)$m['message_id'],
            'from' => $from,
            'text' => $m['message_content'],
            'type' => $m['message_type'] === 'text' ? null : $m['message_type'],
            'time' => $dt->format('g:i A'),
            'date' => $dt->format('M j, Y'),
            'status' => $from === 'me' ? ((int)$m['is_read_by_me'] === 1 ? 'read' : 'delivered') : null
        ];
    }
    $msgStmt->close();

    $conversations[] = [
        'id' => 'conv' . $conversationId,
        'conversationId' => $conversationId,
        'contactId' => $contactKey,
        'pinned' => false,
        'muted' => false,
        'unread' => (int)$unreadRow['unread_count'],
        'messages' => $messages,
        'quickReplies' => [
            'Thanks, noted.',
            'Please share an update.',
            'I will confirm shortly.'
        ]
    ];
}
$convStmt->close();

apiSuccess([
    'current_user' => [
        'id' => $currentUser['user_id'],
        'name' => $currentUser['full_name'],
        'role' => $currentUser['role']
    ],
    'contacts' => array_values($contacts),
    'conversations' => $conversations
], 'Chat data fetched successfully.', 'CHAT_DATA_FETCHED');

$conn->close();
?>
