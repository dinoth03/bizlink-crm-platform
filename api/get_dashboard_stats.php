<?php
require 'config.php';

// Collect all statistics
$stats = array();

// 1. Total Orders
$result = $conn->query("SELECT COUNT(*) as count FROM orders");
$row = $result->fetch_assoc();
$stats['total_orders'] = (int)$row['count'];

// 2. Total Revenue (completed orders only)
$result = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE order_status = 'completed'");
$row = $result->fetch_assoc();
$stats['total_revenue'] = (float)($row['total'] ?? 0);

// 3. Active Customers
$result = $conn->query("SELECT COUNT(*) as count FROM customers c JOIN users u ON c.user_id = u.user_id WHERE u.account_status = 'active'");
$row = $result->fetch_assoc();
$stats['active_customers'] = (int)$row['count'];

// 4. Active Vendors
$result = $conn->query("SELECT COUNT(*) as count FROM vendors WHERE verification_status = 'verified'");
$row = $result->fetch_assoc();
$stats['active_vendors'] = (int)$row['count'];

// 5. Total Products
$result = $conn->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1");
$row = $result->fetch_assoc();
$stats['total_products'] = (int)$row['count'];

// 6. Pending Orders
$result = $conn->query("SELECT COUNT(*) as count FROM orders WHERE order_status = 'pending'");
$row = $result->fetch_assoc();
$stats['pending_orders'] = (int)$row['count'];

// Send response
echo json_encode([
    'success' => true,
    'data' => $stats,
    'timestamp' => date('Y-m-d H:i:s')
]);

$conn->close();
?>
