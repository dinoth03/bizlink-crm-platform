<?php
/**
 * Admin Vendor Management API
 * List vendors with approval status, contact info, and metrics
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
    $status = $_GET['status'] ?? 'all'; // all, pending, approved, rejected
    $search = $_GET['search'] ?? '';
    
    // Build query
    $whereClause = '';
    if ($status !== 'all') {
        $whereClause .= " AND approval_status = '" . $conn->real_escape_string($status) . "'";
    }
    if ($search) {
        $searchTerm = $conn->real_escape_string($search);
        $whereClause .= " AND (business_name LIKE '%$searchTerm%' OR email LIKE '%$searchTerm%' OR contact_person LIKE '%$searchTerm%')";
    }
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as count FROM vendors WHERE 1=1 $whereClause";
    $result = $conn->query($countQuery);
    $totalCount = $result->fetch_assoc()['count'] ?? 0;
    $totalPages = ceil($totalCount / $limit);
    
    // Get vendors
    $vendorQuery = "SELECT 
                    v.vendor_id,
                    v.business_name,
                    v.contact_person,
                    v.email,
                    v.phone,
                    v.district,
                    v.approval_status,
                    v.is_active,
                    v.registration_date,
                    COUNT(DISTINCT p.product_id) as product_count,
                    COUNT(DISTINCT o.order_id) as order_count,
                    COALESCE(SUM(o.total_amount), 0) as total_revenue
                    FROM vendors v
                    LEFT JOIN products p ON v.vendor_id = p.vendor_id
                    LEFT JOIN order_items oi ON p.product_id = oi.product_id
                    LEFT JOIN orders o ON oi.order_id = o.order_id AND o.status != 'cancelled'
                    WHERE 1=1 $whereClause
                    GROUP BY v.vendor_id
                    ORDER BY v.registration_date DESC
                    LIMIT $limit OFFSET $offset";
    
    $result = $conn->query($vendorQuery);
    $vendors = [];
    while ($row = $result->fetch_assoc()) {
        $vendors[] = [
            'vendor_id' => (int)$row['vendor_id'],
            'business_name' => $row['business_name'],
            'contact_person' => $row['contact_person'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'district' => $row['district'],
            'approval_status' => $row['approval_status'],
            'is_active' => (bool)$row['is_active'],
            'registration_date' => $row['registration_date'],
            'product_count' => (int)$row['product_count'],
            'order_count' => (int)$row['order_count'],
            'total_revenue' => (float)$row['total_revenue']
        ];
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'vendors' => $vendors,
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
