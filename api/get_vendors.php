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
    'category' => isset($_GET['category']) ? sanitizeString((string)$_GET['category'], 100) : '',
    'search' => isset($_GET['search']) ? sanitizeString((string)$_GET['search'], 120) : ''
];

$whereClause = ' WHERE 1=1';
$params = [];
$types = [];

$query = "SELECT 
    v.vendor_id,
    v.business_name as vendor_name,
    v.business_name as shop_name,
    u.email,
    v.business_category,
    v.avg_rating,
    v.commission_rate,
    v.verification_status as vendor_status,
    v.created_at,
    COUNT(p.product_id) as total_products
FROM vendors v
LEFT JOIN users u ON v.user_id = u.user_id
LEFT JOIN products p ON v.vendor_id = p.vendor_id";

if ($userRole === 'admin') {
} elseif ($userRole === 'vendor') {
    appendSqlCondition($whereClause, $params, $types, 'v.user_id = ?', $userId, 'i');
} else {
    apiError('FORBIDDEN', 'Customers do not have access to vendor information.', 403);
}

if ($filters['status'] !== '') {
    appendSqlCondition($whereClause, $params, $types, 'LOWER(v.verification_status) = ?', $filters['status'], 's');
}
if ($filters['category'] !== '') {
    appendSqlCondition($whereClause, $params, $types, 'LOWER(v.business_category) = ?', strtolower($filters['category']), 's');
}
if ($filters['search'] !== '') {
    $search = '%' . $filters['search'] . '%';
    $whereClause .= ' AND (v.business_name LIKE ? OR u.email LIKE ?)';
    $params[] = $search;
    $params[] = $search;
    $types[] = 's';
    $types[] = 's';
}

$countSql = 'SELECT COUNT(DISTINCT v.vendor_id) AS total FROM vendors v LEFT JOIN users u ON v.user_id = u.user_id LEFT JOIN products p ON v.vendor_id = p.vendor_id' . $whereClause;
$countStmt = $conn->prepare($countSql);
if (!$countStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare vendors count query.', 500);
}
bindDynamicParams($countStmt, $types, $params);
$countStmt->execute();
$total = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

$query = $query . $whereClause . " 
GROUP BY v.vendor_id
ORDER BY v.created_at DESC
LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare vendors query.', 500);
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

$vendors = [];
while($row = $result->fetch_assoc()) {
    $vendors[] = $row;
}
$stmt->close();

apiSuccess($vendors, 'Vendors fetched successfully.', 'VENDORS_FETCHED', 200, [
    'pagination' => [
        'page' => $pagination['page'],
        'per_page' => $pagination['per_page'],
        'total' => $total,
        'total_pages' => $pagination['per_page'] > 0 ? (int)ceil($total / $pagination['per_page']) : 0
    ],
    'filters' => $filters
]);

$conn->close();
?>
