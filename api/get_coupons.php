<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth_middleware.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/promotional_helpers.php';

requireAuth(['admin', 'vendor'], $conn);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    return apiError('METHOD_NOT_ALLOWED', 'GET only', 405);
}

$user = getCurrentUser();
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $perPage;

$status = sanitizeString($_GET['status'] ?? '', 20);
$search = sanitizeString($_GET['search'] ?? '', 100);

ensurePromotionalTables($conn);

$sql = 'SELECT c.*, u.email as created_by_email FROM coupons c LEFT JOIN users u ON c.created_by = u.user_id WHERE 1=1';
$types = '';
$params = [];

if ($user['role'] === 'vendor' && isset($user['vendor_id'])) {
    $sql .= ' AND (c.vendor_id = ? OR c.vendor_id IS NULL)';
    $types .= 'i';
    $params[] = (int)$user['vendor_id'];
}

if ($status === 'active') {
    $sql .= ' AND c.is_active = 1 AND c.valid_from <= NOW() AND c.valid_until >= NOW()';
} elseif ($status === 'expired') {
    $sql .= ' AND (c.is_active = 0 OR c.valid_until < NOW())';
}

if (!empty($search)) {
    $sql .= ' AND (c.coupon_code LIKE ? OR c.description LIKE ?)';
    $types .= 'ss';
    $pattern = '%' . $search . '%';
    $params[] = $pattern;
    $params[] = $pattern;
}

$sql .= ' ORDER BY c.created_at DESC LIMIT ? OFFSET ?';
$types .= 'ii';
$params[] = $perPage;
$params[] = $offset;

$stmt = $conn->prepare($sql);
if (!$stmt) {
    return apiError('DB_ERROR', 'Failed to prepare statement', 500);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$coupons = [];
while ($row = $result->fetch_assoc()) {
    $coupons[] = [
        'coupon_id' => (int)$row['coupon_id'],
        'coupon_code' => $row['coupon_code'],
        'discount_type' => $row['discount_type'],
        'discount_value' => (float)$row['discount_value'],
        'max_uses' => $row['max_uses'],
        'current_uses' => (int)$row['current_uses'],
        'min_order_amount' => (float)$row['min_order_amount'],
        'max_discount_amount' => $row['max_discount_amount'] ? (float)$row['max_discount_amount'] : null,
        'valid_from' => $row['valid_from'],
        'valid_until' => $row['valid_until'],
        'is_active' => (bool)$row['is_active'],
        'is_expired' => strtotime($row['valid_until']) < time(),
        'usage_percentage' => $row['max_uses'] ? round(($row['current_uses'] / $row['max_uses']) * 100, 2) : null,
        'created_by_email' => $row['created_by_email'],
        'created_at' => $row['created_at']
    ];
}
$stmt->close();

$countStmt = $conn->prepare('SELECT COUNT(*) as total FROM coupons c WHERE 1=1' . ($user['role'] === 'vendor' && isset($user['vendor_id']) ? ' AND (c.vendor_id = ? OR c.vendor_id IS NULL)' : ''));
if ($user['role'] === 'vendor' && isset($user['vendor_id'])) {
    $countStmt->bind_param('i', $user['vendor_id']);
}
$countStmt->execute();
$countResult = $countStmt->get_result()->fetch_assoc();
$countStmt->close();

apiSuccess([
    'coupons' => $coupons,
    'pagination' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => (int)$countResult['total'],
        'pages' => (int)ceil($countResult['total'] / $perPage)
    ]
], 'Coupons retrieved successfully');
?>
