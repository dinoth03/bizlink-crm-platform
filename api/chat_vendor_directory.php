<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';

// requireAuth(['customer', 'vendor', 'admin']); // Allow guests to view the directory
$currentUser = getCurrentUser(); // This might return null for guests
$currentUserId = (int)($currentUser['user_id'] ?? 0);
$search = isset($_GET['search']) ? sanitizeString((string)$_GET['search'], 120) : '';

$sql = "
SELECT
    u.user_id,
    u.full_name,
    u.email,
    u.phone,
    u.province,
    u.account_status,
    u.created_at,
    v.business_name
FROM vendors v
INNER JOIN users u ON u.user_id = v.user_id
WHERE u.deleted_at IS NULL
  AND u.user_id <> ?
  AND (u.account_status IS NULL OR u.account_status IN ('active', 'pending_verification'))
";

$params = [$currentUserId];
$types = ['i'];

if ($search !== '') {
    $searchLike = '%' . $search . '%';
    $sql .= " AND (
        v.business_name LIKE ?
        OR u.full_name LIKE ?
        OR u.email LIKE ?
        OR u.phone LIKE ?
        OR u.province LIKE ?
    )";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $types[] = 's';
    $types[] = 's';
    $types[] = 's';
    $types[] = 's';
    $types[] = 's';
}

$sql .= ' ORDER BY v.business_name ASC LIMIT 200';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare vendor directory query.', 500);
}

bindDynamicParams($stmt, $types, $params);
$stmt->execute();
$result = $stmt->get_result();

$vendors = [];
while ($row = $result->fetch_assoc()) {
    $displayName = trim((string)($row['business_name'] ?? ''));
    if ($displayName === '') {
        $displayName = trim((string)($row['full_name'] ?? ''));
    }
    if ($displayName === '') {
        $displayName = 'Vendor';
    }

    $initials = '';
    foreach (explode(' ', $displayName) as $part) {
        $part = trim($part);
        if ($part !== '') {
            $initials .= strtoupper($part[0]);
        }
    }

    $joined = '—';
    if (!empty($row['created_at'])) {
        $dt = new DateTime($row['created_at']);
        $joined = $dt->format('M Y');
    }

    $status = 'offline';
    $accountStatus = strtolower((string)($row['account_status'] ?? ''));
    if ($accountStatus === 'active') {
        $status = 'online';
    } elseif ($accountStatus === 'pending_verification') {
        $status = 'away';
    }

    $vendors[] = [
        'id' => 'u' . (int)$row['user_id'],
        'userId' => (int)$row['user_id'],
        'name' => $displayName,
        'owner_name' => (string)($row['full_name'] ?? ''),
        'initials' => substr($initials, 0, 2),
        'role' => 'vendor',
        'color' => '#50C878',
        'status' => $status,
        'company' => $displayName,
        'phone' => (string)($row['phone'] ?: '—'),
        'email' => (string)($row['email'] ?? '—'),
        'province' => (string)($row['province'] ?: '—'),
        'joined' => $joined,
        'conversationId' => null,
        'hasConversation' => false,
    ];
}

$stmt->close();

apiSuccess($vendors, 'Vendor directory fetched.', 'CHAT_VENDOR_DIRECTORY_FETCHED');

$conn->close();
?>