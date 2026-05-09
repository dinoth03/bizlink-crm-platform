<?php
/**
 * Admin Update Vendor Approval Status API
 * Approve or reject vendor applications
 * Auth: Requires authenticated admin
 */

require_once 'config.php';
require_once 'auth_middleware.php';

requireAuth(['admin']);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) throw new Exception('Database connection failed');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $vendorId = (int)($input['vendor_id'] ?? 0);
    $status = strtolower($input['status'] ?? ''); // approved, rejected
    $notes = $input['notes'] ?? '';
    
    if (!$vendorId || !in_array($status, ['approved', 'rejected'])) {
        throw new Exception('Invalid vendor_id or status');
    }
    
    // Update vendor
    $updateQuery = "UPDATE vendors 
                    SET approval_status = ?, 
                        is_active = ? 
                    WHERE vendor_id = ?";
    
    $isActive = ($status === 'approved') ? 1 : 0;
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param('sii', $status, $isActive, $vendorId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update vendor: ' . $stmt->error);
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'message' => 'Vendor ' . $status . ' successfully',
        'vendor_id' => $vendorId,
        'new_status' => $status
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
