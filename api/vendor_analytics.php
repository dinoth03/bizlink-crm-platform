<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';

// Vendor analytics endpoint
requireAuth(['vendor']);

$current = getCurrentUser();
$userId = (int)($current['user_id'] ?? 0);
if ($userId <= 0) {
    apiError('UNAUTHORIZED', 'Unauthorized user.', 401);
}

$data = [];

// Total orders for vendor
$stmt = $conn->prepare(
    "SELECT COUNT(o.order_id) AS cnt FROM orders o JOIN vendors v ON o.vendor_id = v.vendor_id WHERE v.user_id = ?"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$data['total_orders'] = (int)($row['cnt'] ?? 0);
$stmt->close();

// Total revenue (completed orders)
$stmt = $conn->prepare(
    "SELECT IFNULL(SUM(o.total_amount),0) AS total FROM orders o JOIN vendors v ON o.vendor_id = v.vendor_id WHERE v.user_id = ? AND o.order_status = 'completed'"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$data['total_revenue'] = (float)($row['total'] ?? 0);
$stmt->close();

// Pending orders
$stmt = $conn->prepare(
    "SELECT COUNT(o.order_id) AS cnt FROM orders o JOIN vendors v ON o.vendor_id = v.vendor_id WHERE v.user_id = ? AND o.order_status = 'pending'"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$data['pending_orders'] = (int)($row['cnt'] ?? 0);
$stmt->close();

// Sales by day (last 30 days)
$stmt = $conn->prepare(
    "SELECT DATE(o.created_at) AS day, IFNULL(SUM(o.total_amount),0) AS revenue
     FROM orders o
     JOIN vendors v ON o.vendor_id = v.vendor_id
     WHERE v.user_id = ? AND o.order_status = 'completed' AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     GROUP BY DATE(o.created_at)
     ORDER BY DATE(o.created_at) ASC"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
$sales = [];
while ($r = $res->fetch_assoc()) {
    $sales[] = [ 'day' => $r['day'], 'revenue' => (float)$r['revenue'] ];
}
$data['sales_last_30_days'] = $sales;
$stmt->close();

// Top products by revenue (last 90 days)
$stmt = $conn->prepare(
    "SELECT oi.product_id, oi.product_name, SUM(oi.quantity) AS qty_sold, SUM(oi.total_amount) AS revenue
     FROM order_items oi
     JOIN orders o ON oi.order_id = o.order_id
     JOIN vendors v ON o.vendor_id = v.vendor_id
     WHERE v.user_id = ? AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
     GROUP BY oi.product_id, oi.product_name
     ORDER BY revenue DESC
     LIMIT 5"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
$tops = [];
while ($r = $res->fetch_assoc()) {
    $tops[] = [
        'product_id' => (int)$r['product_id'],
        'product_name' => $r['product_name'],
        'qty_sold' => (int)$r['qty_sold'],
        'revenue' => (float)$r['revenue']
    ];
}
$data['top_products'] = $tops;
$stmt->close();

// Recent orders (last 7 days)
$stmt = $conn->prepare(
    "SELECT o.order_id, o.order_number, o.total_amount, o.order_status, o.created_at
     FROM orders o
     JOIN vendors v ON o.vendor_id = v.vendor_id
     WHERE v.user_id = ? AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     ORDER BY o.created_at DESC
     LIMIT 10"
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
$recent = [];
while ($r = $res->fetch_assoc()) {
    $recent[] = [
        'order_id' => (int)$r['order_id'],
        'order_number' => $r['order_number'],
        'total_amount' => (float)$r['total_amount'],
        'order_status' => $r['order_status'],
        'created_at' => $r['created_at']
    ];
}
$data['recent_orders'] = $recent;
$stmt->close();

apiSuccess($data, 'Vendor analytics fetched.', 'VENDOR_ANALYTICS_FETCHED');

$conn->close();
?>
