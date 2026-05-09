<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';

requireAuth(['vendor']);

$currentUser = getCurrentUser();
$userId = (int)($currentUser['user_id'] ?? 0);

if ($userId <= 0) {
    apiError('UNAUTHORIZED', 'Unauthorized user.', 401);
}

// Get vendor ID
$vendorStmt = $conn->prepare('SELECT vendor_id FROM vendors WHERE user_id = ? LIMIT 1');
$vendorStmt->bind_param('i', $userId);
$vendorStmt->execute();
$vendorRow = $vendorStmt->get_result()->fetch_assoc();
$vendorStmt->close();

if (!$vendorRow) {
    apiError('VENDOR_NOT_FOUND', 'Vendor profile not found.', 404);
}

$vendorId = (int)$vendorRow['vendor_id'];

$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;
$search = trim((string)($_GET['search'] ?? ''));

$whereClause = 'WHERE p.vendor_id = ?';
$params = [$vendorId];

if ($search !== '') {
    $whereClause .= ' AND p.product_name LIKE ?';
    $params[] = '%' . $search . '%';
}

// Get total count
$countSql = 'SELECT COUNT(*) as total FROM products p ' . $whereClause;
$countStmt = $conn->prepare($countSql);
if ($search !== '') {
    $countStmt->bind_param('is', ...$params);
} else {
    $countStmt->bind_param('i', $vendorId);
}
$countStmt->execute();
$countRow = $countStmt->get_result()->fetch_assoc();
$countStmt->close();
$total = (int)$countRow['total'];

// Get products
$sql = 'SELECT product_id, product_name, description, price, stock_quantity, category, primary_image_url, is_active, created_at, updated_at
        FROM products p
        ' . $whereClause . '
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?';

$stmt = $conn->prepare($sql);
if (!$stmt) {
    apiError('DB_QUERY_ERROR', 'Failed to prepare query.', 500);
}

if ($search !== '') {
    $stmt->bind_param('isii', $vendorId, $search, $limit, $offset);
} else {
    $stmt->bind_param('iii', $vendorId, $limit, $offset);
}

$stmt->execute();
$result = $stmt->get_result();
$products = [];

while ($row = $result->fetch_assoc()) {
    $products[] = [
        'product_id' => (int)$row['product_id'],
        'product_name' => $row['product_name'],
        'description' => $row['description'],
        'price' => (float)$row['price'],
        'stock_quantity' => (int)$row['stock_quantity'],
        'category' => $row['category'],
        'image_url' => $row['primary_image_url'],
        'is_active' => (bool)$row['is_active'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
}

$stmt->close();

apiSuccess([
    'products' => $products,
    'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'pages' => (int)ceil($total / $limit)
    ]
], 'Products fetched successfully.', 'PRODUCTS_FETCHED');

$conn->close();
?>
