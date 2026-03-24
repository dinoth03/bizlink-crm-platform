<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';

requireAuth();

$userRole = getUserRole();
$userId = getCurrentUser()['user_id'];

$pagination = getPaginationParams($_GET, 20, 100);
$filters = [
    'status' => isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : '',
    'city' => isset($_GET['city']) ? sanitizeString((string)$_GET['city'], 100) : '',
    'search' => isset($_GET['search']) ? sanitizeString((string)$_GET['search'], 120) : ''
];

$validationErrors = [];
if ($filters['status'] !== '' && !in_array($filters['status'], ['active', 'inactive', 'suspended', 'pending_verification'], true)) {
    $validationErrors[] = ['field' => 'status', 'message' => 'Invalid status filter.'];
}

if (!empty($validationErrors)) {
    apiError('VALIDATION_ERROR', 'Invalid query parameters.', 422, $validationErrors);
}

$baseQuery = "SELECT 
    c.customer_id,
    u.user_id,
    u.full_name,
    u.email,
    u.phone,
    u.address,
    u.city,
    u.country,
    u.account_status as customer_status,
    COUNT(o.order_id) as total_orders,
    SUM(o.total_amount) as total_spent,
    c.created_at
FROM customers c
LEFT JOIN users u ON c.user_id = u.user_id
LEFT JOIN orders o ON c.customer_id = o.customer_id";

$whereClause = ' WHERE 1=1';
$params = [];
$types = [];

if ($userRole === 'admin') {
} elseif ($userRole === 'vendor') {
    $baseQuery = "SELECT DISTINCT
        c.customer_id,
        u.user_id,
        u.full_name,
        u.email,
        u.phone,
        u.address,
        u.city,
        u.country,
        u.account_status as customer_status,
        COUNT(o.order_id) as total_orders,
        SUM(o.total_amount) as total_spent,
        c.created_at
FROM customers c
LEFT JOIN users u ON c.user_id = u.user_id
LEFT JOIN orders o ON c.customer_id = o.customer_id
LEFT JOIN vendors v ON o.vendor_id = v.vendor_id";
    $whereClause .= ' AND (v.user_id = ? OR o.order_id IS NULL)';
    $params[] = $userId;
    $types[] = 'i';
} elseif ($userRole === 'customer') {
    $whereClause .= ' AND u.user_id = ?';
    $params[] = $userId;
    $types[] = 'i';
} else {
    apiError('FORBIDDEN', 'Invalid role.', 403);
}

if ($filters['status'] !== '') {
    appendSqlCondition($whereClause, $params, $types, 'LOWER(u.account_status) = ?', $filters['status'], 's');
}
if ($filters['city'] !== '') {
    appendSqlCondition($whereClause, $params, $types, 'LOWER(u.city) = ?', strtolower($filters['city']), 's');
}
if ($filters['search'] !== '') {
    $search = '%' . $filters['search'] . '%';
    $whereClause .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types[] = 's';
    $types[] = 's';
    $types[] = 's';
}

$countSql = 'SELECT COUNT(DISTINCT c.customer_id) AS total FROM customers c LEFT JOIN users u ON c.user_id = u.user_id LEFT JOIN orders o ON c.customer_id = o.customer_id';
if ($userRole === 'vendor') {
    $countSql .= ' LEFT JOIN vendors v ON o.vendor_id = v.vendor_id';
}
$countSql .= $whereClause;

$countStmt = $conn->prepare($countSql);
if (!$countStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare customers count query.', 500);
}
bindDynamicParams($countStmt, $types, $params);
$countStmt->execute();
$total = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

$query = $baseQuery . $whereClause . " 
GROUP BY c.customer_id
ORDER BY c.created_at DESC
LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare customers query.', 500);
}
$finalParams = $params;
$finalTypes = $types;
$finalParams[] = $pagination['limit'];
$finalParams[] = $pagination['offset'];
$finalTypes[] = 'i';
$finalTypes[] = 'i';
bindDynamicParams($stmt, $finalTypes, $finalParams);
$stmt->execute();
$result = $stmt->get_result();

$customers = [];
while ($row = $result->fetch_assoc()) {
    $customers[] = $row;
}
$stmt->close();

apiSuccess(
    $customers,
    'Customers fetched successfully.',
    'CUSTOMERS_FETCHED',
    200,
    [
        'pagination' => [
            'page' => $pagination['page'],
            'per_page' => $pagination['per_page'],
            'total' => $total,
            'total_pages' => $pagination['per_page'] > 0 ? (int)ceil($total / $pagination['per_page']) : 0
        ],
        'filters' => $filters
    ]
);

$conn->close();
?>
