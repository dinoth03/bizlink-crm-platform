<?php
// ============================================
// ERROR REPORTING & DIAGNOSTICS
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors inline, log them instead
ini_set('log_errors', 1);

// Log file for debugging
$errorLogFile = dirname(__FILE__) . '/../logs/api_errors.log';
if (!file_exists(dirname($errorLogFile))) {
    @mkdir(dirname($errorLogFile), 0777, true);
}
ini_set('error_log', $errorLogFile);

require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';

// Verify connection exists
if (!isset($conn) || $conn === null) {
    apiError('DB_CONNECTION_FAILED', 'Database connection is not available. Check server logs.', 500, [
        ['field' => 'database', 'message' => 'Connection object is missing.']
    ]);
}

$isAuthenticated = isLoggedIn();
$userRole = isLoggedIn() ? getUserRole() : null;
$userId = isLoggedIn() ? getCurrentUser()['user_id'] : null;

$pagination = getPaginationParams($_GET, 24, 100);
$filters = [
    'category' => isset($_GET['category']) ? sanitizeString((string)$_GET['category'], 100) : '',
    'search' => isset($_GET['search']) ? sanitizeString((string)$_GET['search'], 100) : '',
    'min_price' => isset($_GET['min_price']) ? (float)$_GET['min_price'] : null,
    'max_price' => isset($_GET['max_price']) ? (float)$_GET['max_price'] : null,
    'status' => isset($_GET['status']) ? strtolower(trim((string)$_GET['status'])) : '',
    'vendor_id' => isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : 0
];

$validationErrors = [];
if ($filters['status'] !== '' && !in_array($filters['status'], ['active', 'inactive'], true)) {
    $validationErrors[] = ['field' => 'status', 'message' => 'status must be active or inactive.'];
}
if ($filters['min_price'] !== null && $filters['min_price'] < 0) {
    $validationErrors[] = ['field' => 'min_price', 'message' => 'min_price cannot be negative.'];
}
if ($filters['max_price'] !== null && $filters['max_price'] < 0) {
    $validationErrors[] = ['field' => 'max_price', 'message' => 'max_price cannot be negative.'];
}
if ($filters['min_price'] !== null && $filters['max_price'] !== null && $filters['min_price'] > $filters['max_price']) {
    $validationErrors[] = ['field' => 'price_range', 'message' => 'min_price cannot exceed max_price.'];
}

if (!empty($validationErrors)) {
    apiError('VALIDATION_ERROR', 'Invalid query parameters.', 422, $validationErrors);
}

$baseQuery = "SELECT 
    p.product_id,
    p.product_name,
    p.product_description,
    p.category,
    p.vendor_id,
    v.business_name as shop_name,
    p.price as base_price,
    p.quantity_in_stock as stock_quantity,
    p.is_active as product_status,
    p.primary_image_url as image_url,
    p.created_at
FROM products p
LEFT JOIN vendors v ON p.vendor_id = v.vendor_id";

$whereClause = ' WHERE 1=1';
$params = [];
$types = [];

if ($isAuthenticated && $userRole === 'vendor') {
    // Vendors should only see their own catalog in vendor dashboard contexts.
    $whereClause .= ' AND v.user_id = ?';
    $params[] = $userId;
    $types[] = 'i';
} else {
    $whereClause .= ' AND p.is_active = 1';
}

if ($filters['category'] !== '') {
    appendSqlCondition($whereClause, $params, $types, 'LOWER(p.category) = ?', strtolower($filters['category']), 's');
}
if ($filters['search'] !== '') {
    $searchTerm = '%' . $filters['search'] . '%';
    $whereClause .= ' AND (p.product_name LIKE ? OR v.business_name LIKE ?)';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types[] = 's';
    $types[] = 's';
}
if ($filters['min_price'] !== null) {
    appendSqlCondition($whereClause, $params, $types, 'p.price >= ?', $filters['min_price'], 'd');
}
if ($filters['max_price'] !== null) {
    appendSqlCondition($whereClause, $params, $types, 'p.price <= ?', $filters['max_price'], 'd');
}
if ($filters['status'] !== '') {
    appendSqlCondition($whereClause, $params, $types, 'p.is_active = ?', $filters['status'] === 'active' ? 1 : 0, 'i');
}
if ($filters['vendor_id'] > 0) {
    appendSqlCondition($whereClause, $params, $types, 'p.vendor_id = ?', $filters['vendor_id'], 'i');
}

$countSql = 'SELECT COUNT(*) AS total FROM products p LEFT JOIN vendors v ON p.vendor_id = v.vendor_id' . $whereClause;
$countStmt = $conn->prepare($countSql);
if (!$countStmt) {
    $error = $conn->error ?: 'Unknown prepare error';
    error_log("COUNT QUERY PREPARE FAILED: " . $error . " | SQL: " . $countSql);
    apiError('DB_QUERY_ERROR', 'Failed to prepare products count query: ' . $error, 500, [
        ['field' => 'database', 'message' => $error]
    ]);
}

try {
    bindDynamicParams($countStmt, $types, $params);
    $countStmt->execute();
    $total = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
} catch (Exception $e) {
    $error = $e->getMessage() ?: $conn->error ?: 'Unknown execute error';
    error_log("COUNT QUERY EXECUTE FAILED: " . $error);
    apiError('DB_QUERY_ERROR', 'Failed to execute products count query: ' . $error, 500, [
        ['field' => 'database', 'message' => $error]
    ]);
} finally {
    $countStmt->close();
}

$query = $baseQuery . $whereClause . " 
ORDER BY p.created_at DESC
LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    $error = $conn->error ?: 'Unknown prepare error';
    error_log("PRODUCTS QUERY PREPARE FAILED: " . $error . " | SQL: " . $query);
    apiError('DB_QUERY_ERROR', 'Failed to prepare products query: ' . $error, 500, [
        ['field' => 'database', 'message' => $error]
    ]);
}

$finalParams = $params;
$finalTypes = $types;
$finalParams[] = $pagination['limit'];
$finalParams[] = $pagination['offset'];
$finalTypes[] = 'i';
$finalTypes[] = 'i';

try {
    bindDynamicParams($stmt, $finalTypes, $finalParams);
    $stmt->execute();
    $result = $stmt->get_result();
} catch (Exception $e) {
    $error = $e->getMessage() ?: $conn->error ?: 'Unknown execute error';
    error_log("PRODUCTS QUERY EXECUTE FAILED: " . $error);
    apiError('DB_QUERY_ERROR', 'Failed to execute products query: ' . $error, 500, [
        ['field' => 'database', 'message' => $error]
    ]);
}

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();

apiSuccess(
    $products,
    'Products fetched successfully.',
    'PRODUCTS_FETCHED',
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
