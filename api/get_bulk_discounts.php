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

$productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : null;
$category = isset($_GET['category']) ? sanitizeString($_GET['category'], 100) : null;

ensurePromotionalTables($conn);

$sql = 'SELECT bd.*, p.product_name FROM bulk_discounts bd LEFT JOIN products p ON bd.product_id = p.product_id WHERE 1=1';
$types = '';
$params = [];

if ($user['role'] === 'vendor' && isset($user['vendor_id'])) {
    $sql .= ' AND bd.vendor_id = ?';
    $types .= 'i';
    $params[] = (int)$user['vendor_id'];
}

if ($productId) {
    $sql .= ' AND bd.product_id = ?';
    $types .= 'i';
    $params[] = $productId;
}

if ($category) {
    $sql .= ' AND bd.category = ?';
    $types .= 's';
    $params[] = $category;
}

$sql .= ' AND bd.is_active = 1 ORDER BY bd.min_quantity ASC LIMIT ? OFFSET ?';
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

$discounts = [];
while ($row = $result->fetch_assoc()) {
    $discounts[] = [
        'bulk_discount_id' => (int)$row['bulk_discount_id'],
        'product_id' => $row['product_id'] ? (int)$row['product_id'] : null,
        'product_name' => $row['product_name'],
        'category' => $row['category'],
        'min_quantity' => (int)$row['min_quantity'],
        'max_quantity' => $row['max_quantity'] ? (int)$row['max_quantity'] : null,
        'discount_type' => $row['discount_type'],
        'discount_value' => (float)$row['discount_value'],
        'is_active' => (bool)$row['is_active'],
        'created_at' => $row['created_at']
    ];
}
$stmt->close();

$countSql = 'SELECT COUNT(*) as total FROM bulk_discounts bd WHERE 1=1' . 
    ($user['role'] === 'vendor' && isset($user['vendor_id']) ? ' AND bd.vendor_id = ?' : '') .
    ($productId ? ' AND bd.product_id = ?' : '') .
    ($category ? ' AND bd.category = ?' : '');

$countStmt = $conn->prepare($countSql);
if ($countStmt) {
    $countParams = [];
    $countTypes = '';
    
    if ($user['role'] === 'vendor' && isset($user['vendor_id'])) {
        $countTypes .= 'i';
        $countParams[] = (int)$user['vendor_id'];
    }
    if ($productId) {
        $countTypes .= 'i';
        $countParams[] = $productId;
    }
    if ($category) {
        $countTypes .= 's';
        $countParams[] = $category;
    }
    
    if (!empty($countParams)) {
        $countStmt->bind_param($countTypes, ...$countParams);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result()->fetch_assoc();
    $countStmt->close();
} else {
    $countResult = ['total' => 0];
}

apiSuccess([
    'bulk_discounts' => $discounts,
    'pagination' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => (int)$countResult['total'],
        'pages' => (int)ceil(max(1, $countResult['total']) / $perPage)
    ]
], 'Bulk discounts retrieved successfully');
?>
