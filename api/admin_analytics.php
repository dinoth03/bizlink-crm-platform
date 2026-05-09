<?php
/**
 * Admin Analytics API
 * System-wide reporting: total orders, revenue, vendors, customers, products, platforms stats
 * Auth: Requires authenticated admin
 */

require_once 'config.php';
require_once 'auth_middleware.php';

// Enforce admin role
requireAuth(['admin']);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    
    // Total metrics
    $totalOrdersQuery = "SELECT COUNT(*) as count FROM orders WHERE status != 'cancelled'";
    $result = $conn->query($totalOrdersQuery);
    $totalOrders = $result->fetch_assoc()['count'] ?? 0;
    
    $totalRevenueQuery = "SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE status != 'cancelled' AND payment_status = 'paid'";
    $result = $conn->query($totalRevenueQuery);
    $totalRevenue = (float)($result->fetch_assoc()['total'] ?? 0);
    
    $totalCustomersQuery = "SELECT COUNT(*) as count FROM customers";
    $result = $conn->query($totalCustomersQuery);
    $totalCustomers = $result->fetch_assoc()['count'] ?? 0;
    
    $totalVendorsQuery = "SELECT COUNT(*) as count FROM vendors";
    $result = $conn->query($totalVendorsQuery);
    $totalVendors = $result->fetch_assoc()['count'] ?? 0;
    
    $activeVendorsQuery = "SELECT COUNT(*) as count FROM vendors WHERE is_active = 1 AND approval_status = 'approved'";
    $result = $conn->query($activeVendorsQuery);
    $activeVendors = $result->fetch_assoc()['count'] ?? 0;
    
    $pendingVendorsQuery = "SELECT COUNT(*) as count FROM vendors WHERE approval_status = 'pending'";
    $result = $conn->query($pendingVendorsQuery);
    $pendingVendors = $result->fetch_assoc()['count'] ?? 0;
    
    $totalProductsQuery = "SELECT COUNT(*) as count FROM products WHERE is_active = 1";
    $result = $conn->query($totalProductsQuery);
    $totalProducts = $result->fetch_assoc()['count'] ?? 0;
    
    $pendingProductsQuery = "SELECT COUNT(*) as count FROM products WHERE moderation_status = 'pending'";
    $result = $conn->query($pendingProductsQuery);
    $pendingProducts = $result->fetch_assoc()['count'] ?? 0;
    
    $flaggedProductsQuery = "SELECT COUNT(*) as count FROM products WHERE moderation_status = 'flagged'";
    $result = $conn->query($flaggedProductsQuery);
    $flaggedProducts = $result->fetch_assoc()['count'] ?? 0;
    
    // Daily revenue (last 30 days)
    $dailyRevenueQuery = "SELECT 
                          DATE(order_date) as order_date, 
                          COALESCE(SUM(total_amount), 0) as daily_revenue 
                          FROM orders 
                          WHERE status != 'cancelled' AND payment_status = 'paid'
                          AND order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                          GROUP BY DATE(order_date)
                          ORDER BY order_date ASC";
    $result = $conn->query($dailyRevenueQuery);
    $dailyRevenue = [];
    while ($row = $result->fetch_assoc()) {
        $dailyRevenue[] = [
            'date' => $row['order_date'],
            'revenue' => (float)$row['daily_revenue']
        ];
    }
    
    // Top vendors by sales
    $topVendorsQuery = "SELECT 
                        v.vendor_id,
                        v.business_name,
                        COUNT(DISTINCT o.order_id) as order_count,
                        COALESCE(SUM(o.total_amount), 0) as total_sales
                        FROM vendors v
                        JOIN products p ON v.vendor_id = p.vendor_id
                        JOIN order_items oi ON p.product_id = oi.product_id
                        JOIN orders o ON oi.order_id = o.order_id
                        WHERE o.status != 'cancelled' AND o.payment_status = 'paid'
                        GROUP BY v.vendor_id
                        ORDER BY total_sales DESC
                        LIMIT 10";
    $result = $conn->query($topVendorsQuery);
    $topVendors = [];
    while ($row = $result->fetch_assoc()) {
        $topVendors[] = [
            'vendor_id' => (int)$row['vendor_id'],
            'business_name' => $row['business_name'],
            'order_count' => (int)$row['order_count'],
            'total_sales' => (float)$row['total_sales']
        ];
    }
    
    // Order status distribution
    $statusDistQuery = "SELECT 
                        status, 
                        COUNT(*) as count 
                        FROM orders 
                        WHERE status != 'cancelled'
                        GROUP BY status";
    $result = $conn->query($statusDistQuery);
    $statusDist = [];
    while ($row = $result->fetch_assoc()) {
        $statusDist[$row['status']] = (int)$row['count'];
    }
    
    // Pending disputes
    $pendingDisputesQuery = "SELECT COUNT(*) as count FROM disputes WHERE status = 'open'";
    $result = $conn->query($pendingDisputesQuery);
    $pendingDisputes = $result->fetch_assoc()['count'] ?? 0;
    
    $conn->close();
    
    $response = [
        'success' => true,
        'data' => [
            'total_orders' => (int)$totalOrders,
            'total_revenue' => $totalRevenue,
            'total_customers' => (int)$totalCustomers,
            'total_vendors' => (int)$totalVendors,
            'active_vendors' => (int)$activeVendors,
            'pending_vendors' => (int)$pendingVendors,
            'total_products' => (int)$totalProducts,
            'pending_products' => (int)$pendingProducts,
            'flagged_products' => (int)$flaggedProducts,
            'pending_disputes' => (int)$pendingDisputes,
            'daily_revenue' => $dailyRevenue,
            'top_vendors' => $topVendors,
            'order_status_distribution' => $statusDist
        ]
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching admin analytics: ' . $e->getMessage()
    ]);
}
