<?php
/**
 * Customer Analytics API
 * Returns customer-specific stats for dashboard: total orders, spending, wishlist, loyalty info
 * Auth: Requires authenticated customer
 */

require_once 'config.php';
require_once 'auth_middleware.php';

// Enforce customer role
requireAuth(['customer']);

try {
    $customerId = $_SESSION['user_id'];
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }
    
    // Total orders (all time)
    $totalOrdersQuery = "SELECT COUNT(*) as count FROM orders WHERE customer_id = ? AND status != 'cancelled'";
    $stmt = $conn->prepare($totalOrdersQuery);
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $totalOrders = $result->fetch_assoc()['count'] ?? 0;
    
    // In progress orders (pending, processing, shipped, out_for_delivery)
    $inProgressQuery = "SELECT COUNT(*) as count FROM orders WHERE customer_id = ? AND status IN ('pending', 'processing', 'shipped', 'out_for_delivery')";
    $stmt = $conn->prepare($inProgressQuery);
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $inProgress = $result->fetch_assoc()['count'] ?? 0;
    
    // Wishlist items
    $wishlistQuery = "SELECT COUNT(*) as count FROM wishlists WHERE customer_id = ?";
    $stmt = $conn->prepare($wishlistQuery);
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $wishlistCount = $result->fetch_assoc()['count'] ?? 0;
    
    // Vendors followed (unique vendors with orders)
    $vendorsQuery = "SELECT COUNT(DISTINCT p.vendor_id) as count FROM orders o 
                     JOIN order_items oi ON o.order_id = oi.order_id 
                     JOIN products p ON oi.product_id = p.product_id 
                     WHERE o.customer_id = ?";
    $stmt = $conn->prepare($vendorsQuery);
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $vendorsCount = $result->fetch_assoc()['count'] ?? 0;
    
    // Total spending
    $spendingQuery = "SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE customer_id = ? AND status != 'cancelled'";
    $stmt = $conn->prepare($spendingQuery);
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $totalSpent = (float)($result->fetch_assoc()['total'] ?? 0);
    
    // Last 30 days spending breakdown
    $last30DaysQuery = "SELECT 
                        DATE(order_date) as order_date, 
                        COALESCE(SUM(total_amount), 0) as daily_total 
                        FROM orders 
                        WHERE customer_id = ? 
                        AND status != 'cancelled'
                        AND order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                        GROUP BY DATE(order_date)
                        ORDER BY order_date DESC";
    $stmt = $conn->prepare($last30DaysQuery);
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $spendingByDay = [];
    while ($row = $result->fetch_assoc()) {
        $spendingByDay[] = [
            'date' => $row['order_date'],
            'amount' => (float)$row['daily_total']
        ];
    }
    
    // Recent orders (last 5)
    $recentOrdersQuery = "SELECT 
                         order_id, 
                         order_date, 
                         status, 
                         total_amount,
                         payment_status
                         FROM orders 
                         WHERE customer_id = ?
                         ORDER BY order_date DESC
                         LIMIT 5";
    $stmt = $conn->prepare($recentOrdersQuery);
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $recentOrders = [];
    while ($row = $result->fetch_assoc()) {
        $recentOrders[] = [
            'order_id' => (int)$row['order_id'],
            'order_date' => $row['order_date'],
            'status' => $row['status'],
            'amount' => (float)$row['total_amount'],
            'payment_status' => $row['payment_status']
        ];
    }
    
    // Loyalty tier calculation (simple: based on total spending)
    $tier = 'Silver';
    $tierColor = '#c0c0c0';
    if ($totalSpent >= 50000) {
        $tier = 'Platinum';
        $tierColor = '#e5e4e2';
    } elseif ($totalSpent >= 25000) {
        $tier = 'Gold';
        $tierColor = '#ffd700';
    } elseif ($totalSpent >= 10000) {
        $tier = 'Silver';
        $tierColor = '#c0c0c0';
    }
    
    // Calculate loyalty points (1 point per 100 rupees spent)
    $loyaltyPoints = intval($totalSpent / 100);
    
    $conn->close();
    
    $response = [
        'success' => true,
        'data' => [
            'total_orders' => (int)$totalOrders,
            'in_progress_orders' => (int)$inProgress,
            'wishlist_count' => (int)$wishlistCount,
            'vendors_count' => (int)$vendorsCount,
            'total_spent' => $totalSpent,
            'loyalty_tier' => $tier,
            'loyalty_tier_color' => $tierColor,
            'loyalty_points' => $loyaltyPoints,
            'spending_by_day' => $spendingByDay,
            'recent_orders' => $recentOrders
        ]
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching customer analytics: ' . $e->getMessage()
    ]);
}
