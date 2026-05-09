<?php
/**
 * Admin Product Moderation API
 * List products for moderation review with vendor info and sales data
 * Auth: Requires authenticated admin
 */

require_once 'config.php';
require_once 'auth_middleware.php';

requireAuth(['admin']);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) throw new Exception('Database connection failed');
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = 20;
    $offset = ($page - 1) * $limit;
    $modStatus = $_GET['status'] ?? 'all'; // all, pending, approved, flagged, rejected
    $search = $_GET['search'] ?? '';
    
    // Build query
    $whereClause = '';
    if ($modStatus !== 'all') {
        $modStatus = strtolower($modStatus);
        $whereClause .= " AND (p.moderation_status = '" . $conn->real_escape_string($modStatus) . "' OR (p.moderation_status IS NULL AND '$modStatus' = 'pending'))";
    }
    if ($search) {
        $searchTerm = $conn->real_escape_string($search);
        $whereClause .= " AND (p.product_name LIKE '%$searchTerm%' OR v.business_name LIKE '%$searchTerm%')";
    }
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as count FROM products p 
                   JOIN vendors v ON p.vendor_id = v.vendor_id 
                   WHERE p.is_active = 1 $whereClause";
    $result = $conn->query($countQuery);
    $totalCount = $result->fetch_assoc()['count'] ?? 0;
    $totalPages = ceil($totalCount / $limit);
    
    // Get products
    $productQuery = "SELECT 
                    p.product_id,
                    p.product_name,
                    p.category,
                    p.price,
                    p.stock,
                    p.moderation_status,
                    p.product_date,
                    v.vendor_id,
                    v.business_name,
                    COUNT(DISTINCT oi.order_id) as sales_count,
                    COALESCE(SUM(oi.quantity), 0) as total_quantity_sold
                    FROM products p
                    JOIN vendors v ON p.vendor_id = v.vendor_id
                    LEFT JOIN order_items oi ON p.product_id = oi.product_id
                    WHERE p.is_active = 1 $whereClause
                    GROUP BY p.product_id
                    ORDER BY p.product_date DESC
                    LIMIT $limit OFFSET $offset";
    
    $result = $conn->query($productQuery);
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'product_id' => (int)$row['product_id'],
            'product_name' => $row['product_name'],
            'category' => $row['category'],
            'price' => (float)$row['price'],
            'stock' => (int)$row['stock'],
            'moderation_status' => $row['moderation_status'] ?? 'pending',
            'product_date' => $row['product_date'],
            'vendor_id' => (int)$row['vendor_id'],
            'business_name' => $row['business_name'],
            'sales_count' => (int)$row['sales_count'],
            'total_quantity_sold' => (int)$row['total_quantity_sold']
        ];
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'products' => $products,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_count' => $totalCount,
                'per_page' => $limit
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
