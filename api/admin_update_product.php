<?php
/**
 * Admin Update Product Moderation Status API
 * Approve, flag, or reject product listings
 * Auth: Requires authenticated admin
 */

require_once 'config.php';
require_once 'auth_middleware.php';

requireAuth(['admin']);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) throw new Exception('Database connection failed');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $productId = (int)($input['product_id'] ?? 0);
    $status = strtolower($input['status'] ?? ''); // approved, flagged, rejected
    $reason = $input['reason'] ?? '';
    
    if (!$productId || !in_array($status, ['approved', 'flagged', 'rejected'])) {
        throw new Exception('Invalid product_id or status');
    }
    
    // Update product
    $isActive = ($status !== 'rejected') ? 1 : 0;
    $updateQuery = "UPDATE products 
                    SET moderation_status = ?, 
                        is_active = ? 
                    WHERE product_id = ?";
    
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param('sii', $status, $isActive, $productId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update product: ' . $stmt->error);
    }
    
    // Store moderation reason if provided
    if ($reason) {
        $reasonQuery = "UPDATE products SET moderation_notes = ? WHERE product_id = ?";
        $stmt = $conn->prepare($reasonQuery);
        $stmt->bind_param('si', $reason, $productId);
        $stmt->execute();
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Product ' . $status . ' successfully',
        'product_id' => $productId,
        'new_status' => $status
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
