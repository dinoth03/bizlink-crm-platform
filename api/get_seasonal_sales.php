<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/promotional_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    return apiError('METHOD_NOT_ALLOWED', 'GET only', 405);
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
$offset = ($page - 1) * $perPage;
$activeOnly = isset($_GET['active_only']) ? (bool)$_GET['active_only'] : true;
$vendorId = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : null;

ensurePromotionalTables($conn);

$sql = 'SELECT ss.*, COUNT(ssi.seasonal_sale_item_id) as item_count FROM seasonal_sales ss LEFT JOIN seasonal_sale_items ssi ON ss.seasonal_sale_id = ssi.seasonal_sale_id WHERE 1=1';
$types = '';
$params = [];

if ($activeOnly) {
    $sql .= ' AND ss.is_active = 1 AND ss.starts_at <= NOW() AND ss.ends_at >= NOW()';
}

if ($vendorId) {
    $sql .= ' AND (ss.vendor_id = ? OR ss.vendor_id IS NULL)';
    $types .= 'i';
    $params[] = $vendorId;
}

$sql .= ' GROUP BY ss.seasonal_sale_id ORDER BY ss.starts_at DESC LIMIT ? OFFSET ?';
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

$sales = [];
while ($row = $result->fetch_assoc()) {
    $saleId = (int)$row['seasonal_sale_id'];
    
    $itemsStmt = $conn->prepare('SELECT ssi.product_id, ssi.category, ssi.applies_to_type, p.product_name FROM seasonal_sale_items ssi LEFT JOIN products p ON ssi.product_id = p.product_id WHERE ssi.seasonal_sale_id = ?');
    $itemsStmt->bind_param('i', $saleId);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();
    
    $saleItems = [];
    while ($item = $itemsResult->fetch_assoc()) {
        $saleItems[] = [
            'product_id' => $item['product_id'] ? (int)$item['product_id'] : null,
            'product_name' => $item['product_name'],
            'category' => $item['category'],
            'applies_to_type' => $item['applies_to_type']
        ];
    }
    $itemsStmt->close();
    
    $sales[] = [
        'seasonal_sale_id' => $saleId,
        'sale_name' => $row['sale_name'],
        'sale_description' => $row['sale_description'],
        'discount_type' => $row['discount_type'],
        'discount_value' => (float)$row['discount_value'],
        'max_discount_per_item' => $row['max_discount_per_item'] ? (float)$row['max_discount_per_item'] : null,
        'starts_at' => $row['starts_at'],
        'ends_at' => $row['ends_at'],
        'is_active' => (bool)$row['is_active'],
        'is_ongoing' => strtotime($row['starts_at']) <= time() && strtotime($row['ends_at']) >= time(),
        'items_count' => (int)$row['item_count'],
        'items' => $saleItems,
        'banner_image_url' => $row['banner_image_url'],
        'created_at' => $row['created_at']
    ];
}
$stmt->close();

$countSql = 'SELECT COUNT(*) as total FROM seasonal_sales ss WHERE 1=1' .
    ($activeOnly ? ' AND ss.is_active = 1 AND ss.starts_at <= NOW() AND ss.ends_at >= NOW()' : '') .
    ($vendorId ? ' AND (ss.vendor_id = ? OR ss.vendor_id IS NULL)' : '');

$countStmt = $conn->prepare($countSql);
if ($countStmt) {
    if ($vendorId) {
        $countStmt->bind_param('i', $vendorId);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result()->fetch_assoc();
    $countStmt->close();
} else {
    $countResult = ['total' => 0];
}

apiSuccess([
    'seasonal_sales' => $sales,
    'pagination' => [
        'page' => $page,
        'per_page' => $perPage,
        'total' => (int)$countResult['total'],
        'pages' => (int)ceil(max(1, $countResult['total']) / $perPage)
    ]
], 'Seasonal sales retrieved successfully');
?>
