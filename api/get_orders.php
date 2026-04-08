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
    'payment_status' => isset($_GET['payment_status']) ? strtolower(trim((string)$_GET['payment_status'])) : '',
    'search' => isset($_GET['search']) ? trim((string)$_GET['search']) : '',
    'date_from' => isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '',
    'date_to' => isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : '',
    'vendor_id' => isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : 0,
    'customer_id' => isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0
];

$validOrderStatuses = ['pending', 'processing', 'shipped', 'delivered', 'completed', 'cancelled'];
$validPaymentStatuses = ['unpaid', 'paid', 'partially_paid', 'failed', 'refunded'];
$validationErrors = [];

if ($filters['status'] !== '' && !in_array($filters['status'], $validOrderStatuses, true)) {
    $validationErrors[] = ['field' => 'status', 'message' => 'Invalid order status filter.'];
}
if ($filters['payment_status'] !== '' && !in_array($filters['payment_status'], $validPaymentStatuses, true)) {
    $validationErrors[] = ['field' => 'payment_status', 'message' => 'Invalid payment status filter.'];
}
if ($filters['date_from'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'])) {
    $validationErrors[] = ['field' => 'date_from', 'message' => 'date_from must be YYYY-MM-DD.'];
}
if ($filters['date_to'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'])) {
    $validationErrors[] = ['field' => 'date_to', 'message' => 'date_to must be YYYY-MM-DD.'];
}

if (!empty($validationErrors)) {
    apiError('VALIDATION_ERROR', 'Invalid query parameters.', 422, $validationErrors);
}

$baseQuery = "SELECT 
    o.order_id,
    o.order_number,
    o.customer_id,
    u.full_name as customer_name,
    u.email,
    u.phone,
    u.city,
    o.total_amount,
    o.order_status,
    o.payment_status,
    o.created_at,
    o.order_date,
    o.shipping_address,
    o.shipping_method,
    o.tracking_number,
    o.expected_delivery_date,
    v.vendor_id,
    v.business_name as vendor_name,
    v.business_category as vendor_category,
    v.business_email as vendor_email,
    v.avg_rating as vendor_rating,
    COALESCE(SUM(oi.quantity), 0) as quantity,
    COALESCE(MAX(oi.product_name), 'Order Item') as product_name
FROM orders o
LEFT JOIN customers c ON o.customer_id = c.customer_id
LEFT JOIN users u ON c.user_id = u.user_id
LEFT JOIN vendors v ON o.vendor_id = v.vendor_id
LEFT JOIN order_items oi ON o.order_id = oi.order_id";

$whereClause = ' WHERE 1=1';
$params = [];
$types = [];

if ($userRole === 'admin') {
} elseif ($userRole === 'vendor') {
    appendSqlCondition($whereClause, $params, $types, 'v.user_id = ?', $userId, 'i');
} elseif ($userRole === 'customer') {
    appendSqlCondition($whereClause, $params, $types, 'u.user_id = ?', $userId, 'i');
} else {
    apiError('FORBIDDEN', 'Invalid role.', 403);
}

if ($filters['status'] !== '') {
    appendSqlCondition($whereClause, $params, $types, 'LOWER(o.order_status) = ?', $filters['status'], 's');
}
if ($filters['payment_status'] !== '') {
    appendSqlCondition($whereClause, $params, $types, 'LOWER(o.payment_status) = ?', $filters['payment_status'], 's');
}
if ($filters['search'] !== '') {
    $searchTerm = '%' . sanitizeString($filters['search'], 100) . '%';
    $whereClause .= ' AND (o.order_number LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types[] = 's';
    $types[] = 's';
    $types[] = 's';
}
if ($filters['date_from'] !== '') {
    appendSqlCondition($whereClause, $params, $types, 'DATE(o.created_at) >= ?', $filters['date_from'], 's');
}
if ($filters['date_to'] !== '') {
    appendSqlCondition($whereClause, $params, $types, 'DATE(o.created_at) <= ?', $filters['date_to'], 's');
}
if ($userRole === 'admin' && $filters['vendor_id'] > 0) {
    appendSqlCondition($whereClause, $params, $types, 'o.vendor_id = ?', $filters['vendor_id'], 'i');
}
if ($userRole === 'admin' && $filters['customer_id'] > 0) {
    appendSqlCondition($whereClause, $params, $types, 'o.customer_id = ?', $filters['customer_id'], 'i');
}

$countSql = "SELECT COUNT(DISTINCT o.order_id) AS total
FROM orders o
LEFT JOIN customers c ON o.customer_id = c.customer_id
LEFT JOIN users u ON c.user_id = u.user_id
LEFT JOIN vendors v ON o.vendor_id = v.vendor_id
LEFT JOIN order_items oi ON o.order_id = oi.order_id" . $whereClause;

$countStmt = $conn->prepare($countSql);
if (!$countStmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare orders count query.', 500);
}
bindDynamicParams($countStmt, $types, $params);
$countStmt->execute();
$total = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

$query = $baseQuery . $whereClause . " 
GROUP BY 
    o.order_id,
    o.order_number,
    o.customer_id,
    u.full_name,
    u.email,
    u.phone,
    u.city,
    o.total_amount,
    o.order_status,
    o.payment_status,
    o.created_at,
    o.order_date,
    o.shipping_address,
    o.shipping_method,
    o.tracking_number,
    o.expected_delivery_date,
    v.vendor_id,
    v.business_name,
    v.business_category,
    v.business_email,
    v.avg_rating
ORDER BY o.created_at DESC
LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare orders query.', 500);
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

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

apiSuccess(
    $orders,
    'Orders fetched successfully.',
    'ORDERS_FETCHED',
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
