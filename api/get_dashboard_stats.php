<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';

// Require authentication
requireAuth();

$userRole = getUserRole();
$userId = getCurrentUser()['user_id'];

// If customer role is requesting stats, deny them
if ($userRole === 'customer') {
    apiError('FORBIDDEN', 'Customers do not have access to dashboard stats.', 403);
}

$stats = [];

if ($userRole === 'admin') {
    // Admin sees all statistics
    
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

} elseif ($userRole === 'vendor') {
    // Vendor sees only their own statistics
    
    // 1. Their own Orders
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as count FROM orders o 
         JOIN vendors v ON o.vendor_id = v.vendor_id 
         WHERE v.user_id = ?"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stats['total_orders'] = (int)$row['count'];
    $stmt->close();

    // 2. Their own Revenue
    $stmt = $conn->prepare(
        "SELECT SUM(total_amount) as total FROM orders o 
         JOIN vendors v ON o.vendor_id = v.vendor_id 
         WHERE v.user_id = ? AND o.order_status = 'completed'"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stats['total_revenue'] = (float)($row['total'] ?? 0);
    $stmt->close();

    // 3. Their customer count (unique customers who purchased from them)
    $stmt = $conn->prepare(
        "SELECT COUNT(DISTINCT o.user_id) as count FROM orders o 
         JOIN vendors v ON o.vendor_id = v.vendor_id 
         WHERE v.user_id = ?"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stats['customer_count'] = (int)$row['count'];
    $stmt->close();

    // 4. Their product count
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as count FROM products p 
         JOIN vendors v ON p.vendor_id = v.vendor_id 
         WHERE v.user_id = ? AND p.is_active = 1"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stats['total_products'] = (int)$row['count'];
    $stmt->close();

    // 5. Their pending orders
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as count FROM orders o 
         JOIN vendors v ON o.vendor_id = v.vendor_id 
         WHERE v.user_id = ? AND o.order_status = 'pending'"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stats['pending_orders'] = (int)$row['count'];
    $stmt->close();

    // 6. Their shop status
    $stmt = $conn->prepare("SELECT verification_status, store_url_slug FROM vendors WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stats['shop_status'] = $row['verification_status'] ?? 'unknown';
    $stats['shop_url'] = $row['store_url_slug'] ?? 'your-shop';
    $stmt->close();
}

apiSuccess($stats, 'Dashboard stats fetched successfully.', 'DASHBOARD_STATS_FETCHED');

$conn->close();
?>
