<?php
/**
 * Admin Order Disputes API
 * List and manage order disputes with customer/vendor info
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
    $dispStatus = $_GET['status'] ?? 'all'; // all, open, resolved, refunded, replaced
    $search = $_GET['search'] ?? '';
    
    // Build query
    $whereClause = '';
    if ($dispStatus !== 'all') {
        $whereClause .= " AND d.status = '" . $conn->real_escape_string($dispStatus) . "'";
    }
    if ($search) {
        $searchTerm = $conn->real_escape_string($search);
        $whereClause .= " AND (o.order_number LIKE '%$searchTerm%' OR c.email LIKE '%$searchTerm%')";
    }
    
    // Check if disputes table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'disputes'");
    if ($tableCheck->num_rows == 0) {
        // Create disputes table if it doesn't exist
        $createTable = "CREATE TABLE IF NOT EXISTS disputes (
            dispute_id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            customer_id INT NOT NULL,
            vendor_id INT NOT NULL,
            issue_description TEXT,
            status ENUM('open', 'resolved', 'refunded', 'replaced') DEFAULT 'open',
            resolution_notes TEXT,
            created_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_date DATETIME,
            FOREIGN KEY (order_id) REFERENCES orders(order_id),
            FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
            FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id)
        )";
        $conn->query($createTable);
    }
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as count FROM disputes d WHERE 1=1 $whereClause";
    $result = $conn->query($countQuery);
    $totalCount = $result->fetch_assoc()['count'] ?? 0;
    $totalPages = ceil($totalCount / $limit);
    
    // Get disputes
    $disputeQuery = "SELECT 
                    d.dispute_id,
                    d.order_id,
                    d.customer_id,
                    d.vendor_id,
                    d.issue_description,
                    d.status,
                    d.resolution_notes,
                    d.created_date,
                    d.resolved_date,
                    o.order_number,
                    o.total_amount,
                    o.order_date,
                    c.email as customer_email,
                    COALESCE(cu.full_name, 'Unknown') as customer_name,
                    v.business_name,
                    v.email as vendor_email
                    FROM disputes d
                    JOIN orders o ON d.order_id = o.order_id
                    JOIN customers c ON d.customer_id = c.customer_id
                    LEFT JOIN users cu ON c.user_id = cu.user_id
                    JOIN vendors v ON d.vendor_id = v.vendor_id
                    WHERE 1=1 $whereClause
                    ORDER BY d.created_date DESC
                    LIMIT $limit OFFSET $offset";
    
    $result = $conn->query($disputeQuery);
    $disputes = [];
    while ($row = $result->fetch_assoc()) {
        $disputes[] = [
            'dispute_id' => (int)$row['dispute_id'],
            'order_id' => (int)$row['order_id'],
            'order_number' => $row['order_number'],
            'customer_id' => (int)$row['customer_id'],
            'customer_email' => $row['customer_email'],
            'customer_name' => $row['customer_name'],
            'vendor_id' => (int)$row['vendor_id'],
            'vendor_email' => $row['vendor_email'],
            'business_name' => $row['business_name'],
            'issue_description' => $row['issue_description'],
            'total_amount' => (float)$row['total_amount'],
            'order_date' => $row['order_date'],
            'status' => $row['status'],
            'resolution_notes' => $row['resolution_notes'],
            'created_date' => $row['created_date'],
            'resolved_date' => $row['resolved_date'],
            'days_open' => intval((strtotime('now') - strtotime($row['created_date'])) / 86400)
        ];
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'disputes' => $disputes,
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
