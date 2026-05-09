<?php
/**
 * Admin Resolve Dispute API
 * Resolve disputes with refund, replacement, or rejection actions
 * Auth: Requires authenticated admin
 */

require_once 'config.php';
require_once 'auth_middleware.php';

requireAuth(['admin']);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) throw new Exception('Database connection failed');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $disputeId = (int)($input['dispute_id'] ?? 0);
    $resolution = strtolower($input['resolution'] ?? ''); // refunded, replaced, resolved
    $notes = $input['notes'] ?? '';
    
    if (!$disputeId || !in_array($resolution, ['refunded', 'replaced', 'resolved'])) {
        throw new Exception('Invalid dispute_id or resolution');
    }
    
    // Ensure disputes table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'disputes'");
    if ($tableCheck->num_rows == 0) {
        throw new Exception('Disputes table does not exist');
    }
    
    // Get dispute details
    $disputeQuery = "SELECT * FROM disputes WHERE dispute_id = ?";
    $stmt = $conn->prepare($disputeQuery);
    $stmt->bind_param('i', $disputeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $dispute = $result->fetch_assoc();
    
    if (!$dispute) {
        throw new Exception('Dispute not found');
    }
    
    // Update dispute status
    $updateQuery = "UPDATE disputes 
                    SET status = ?, 
                        resolution_notes = ?, 
                        resolved_date = NOW() 
                    WHERE dispute_id = ?";
    
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param('ssi', $resolution, $notes, $disputeId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to resolve dispute: ' . $stmt->error);
    }
    
    // If refunded, update order payment status
    if ($resolution === 'refunded') {
        $refundQuery = "UPDATE orders SET payment_status = 'refunded' WHERE order_id = ?";
        $stmt = $conn->prepare($refundQuery);
        $stmt->bind_param('i', $dispute['order_id']);
        $stmt->execute();
    }
    
    // If replaced, update order status
    if ($resolution === 'replaced') {
        $replaceQuery = "UPDATE orders SET status = 'processing' WHERE order_id = ?";
        $stmt = $conn->prepare($replaceQuery);
        $stmt->bind_param('i', $dispute['order_id']);
        $stmt->execute();
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Dispute resolved: ' . $resolution,
        'dispute_id' => $disputeId,
        'resolution' => $resolution
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
