<?php
require 'auth_middleware.php';
require 'config.php';
require_once 'api_helpers.php';
require_once 'csrf_protection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Method not allowed. Use POST.', 405);
}

requireAuth(['admin']);

$userId = (int)(getCurrentUser()['user_id'] ?? 0);
if ($userId <= 0) {
    apiError('UNAUTHORIZED', 'Unauthorized user.', 401);
}

if (CSRF_ENABLED) {
    requireCsrfToken($conn, $userId);
}

$payload = readJsonPayload();
$orderId = (int)($payload['order_id'] ?? 0);
$reason = sanitizeString((string)($payload['reason'] ?? ''), 500);

if ($orderId <= 0) {
    apiError('VALIDATION_ERROR', 'order_id is required and must be positive.', 422, [
        ['field' => 'order_id', 'message' => 'order_id must be > 0.']
    ]);
}

$conn->begin_transaction();

try {
    $orderStmt = $conn->prepare('SELECT order_id, payment_status, order_status FROM orders WHERE order_id = ? LIMIT 1');
    if (!$orderStmt) {
        throw new Exception('Failed to prepare order query.');
    }
    $orderStmt->bind_param('i', $orderId);
    $orderStmt->execute();
    $order = $orderStmt->get_result()->fetch_assoc();
    $orderStmt->close();

    if (!$order) {
        apiError('ORDER_NOT_FOUND', 'Order not found.', 404);
    }

    $updateStmt = $conn->prepare('UPDATE orders SET payment_status = "refunded", order_status = "refunded", updated_at = NOW() WHERE order_id = ? LIMIT 1');
    if (!$updateStmt) {
        throw new Exception('Failed to prepare refund update query.');
    }
    $updateStmt->bind_param('i', $orderId);
    $updateStmt->execute();
    $affected = (int)$updateStmt->affected_rows;
    $updateStmt->close();

    $noteTitle = 'Order refunded';
    $noteMessage = $reason !== '' ? ('Your order has been refunded. Reason: ' . $reason) : 'Your order has been refunded.';
    $notifStmt = $conn->prepare(
        'INSERT INTO notifications (user_id, notification_type, title, message, related_entity_type, related_entity_id, priority)
         SELECT c.user_id, "order_status", ?, ?, "order", ?, "high"
         FROM orders o
         INNER JOIN customers c ON c.customer_id = o.customer_id
         WHERE o.order_id = ? LIMIT 1'
    );
    if ($notifStmt) {
        $notifStmt->bind_param('ssii', $noteTitle, $noteMessage, $orderId, $orderId);
        $notifStmt->execute();
        $notifStmt->close();
    }

    $conn->commit();

    apiSuccess([
        'order_id' => $orderId,
        'payment_status' => 'refunded',
        'order_status' => 'refunded',
        'affected_rows' => $affected
    ], 'Order refunded successfully.', 'ORDER_REFUNDED');
} catch (Throwable $e) {
    $conn->rollback();
    apiError('INTERNAL_ERROR', 'Failed to refund order.', 500, [
        ['field' => 'server', 'message' => $e->getMessage()]
    ]);
}

$conn->close();
?>
