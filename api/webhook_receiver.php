<?php
require_once 'config.php';
require_once 'api_helpers.php';

// Generic webhook receiver - stores incoming webhooks and optionally dispatches notifications.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError('METHOD_NOT_ALLOWED', 'Use POST for webhooks.', 405);
}

$raw = file_get_contents('php://input');
$headers = getallheaders();
$eventId = isset($headers['X-Event-ID']) ? $headers['X-Event-ID'] : (isset($headers['x-event-id']) ? $headers['x-event-id'] : null);
$eventType = isset($headers['X-Event-Type']) ? $headers['X-Event-Type'] : (isset($headers['x-event-type']) ? $headers['x-event-type'] : null);

// Persist webhook events table if missing
$createSql = "CREATE TABLE IF NOT EXISTS webhook_events (
    webhook_event_id INT PRIMARY KEY AUTO_INCREMENT,
    event_id VARCHAR(255) DEFAULT NULL,
    event_type VARCHAR(255) DEFAULT NULL,
    payload_json LONGTEXT,
    headers_json TEXT,
    received_at DATETIME DEFAULT NOW()
);";
$conn->query($createSql);

$insert = $conn->prepare('INSERT INTO webhook_events (event_id, event_type, payload_json, headers_json) VALUES (?, ?, ?, ?)');
if ($insert) {
    $payloadJson = $raw === '' ? '' : $raw;
    $headersJson = json_encode($headers);
    $insert->bind_param('ssss', $eventId, $eventType, $payloadJson, $headersJson);
    $insert->execute();
    $insert->close();
}

// Basic dispatch: if payload contains order_id, create system notifications for vendor and customer.
$decoded = json_decode($raw, true);
if (is_array($decoded)) {
    $orderId = isset($decoded['order_id']) ? (int)$decoded['order_id'] : 0;
    $status = isset($decoded['status']) ? trim((string)$decoded['status']) : '';

    if ($orderId > 0 && $status !== '') {
        // Lookup order and related users
        $stmt = $conn->prepare('SELECT o.order_id, o.order_number, o.customer_id, o.vendor_id, u.user_id AS customer_user_id, v.user_id AS vendor_user_id
                                FROM orders o
                                LEFT JOIN customers c ON o.customer_id = c.customer_id
                                LEFT JOIN users u ON c.user_id = u.user_id
                                LEFT JOIN vendors vnd ON o.vendor_id = vnd.vendor_id
                                LEFT JOIN users v ON vnd.user_id = v.user_id
                                WHERE o.order_id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($row) {
                $orderNumber = $row['order_number'] ?? '';
                $customerUserId = (int)($row['customer_user_id'] ?? 0);
                $vendorUserId = (int)($row['vendor_user_id'] ?? 0);

                $title = 'Order update';
                $message = sprintf('Order %s is now: %s', $orderNumber, $status);

                $ins = $conn->prepare('INSERT INTO notifications (user_id, notification_type, title, message, related_entity_type, related_entity_id, priority, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
                if ($ins) {
                    $type = 'order_status';
                    $rel = 'order';
                    $priority = 'normal';
                    if ($customerUserId > 0) {
                        $ins->bind_param('issssis', $customerUserId, $type, $title, $message, $rel, $orderId, $priority);
                        $ins->execute();
                    }
                    if ($vendorUserId > 0) {
                        $ins->bind_param('issssis', $vendorUserId, $type, $title, $message, $rel, $orderId, $priority);
                        $ins->execute();
                    }
                    $ins->close();
                }
            }
        }
    }
}

apiSuccess(['received' => true], 'Webhook received and stored.', 'WEBHOOK_RECEIVED');

$conn->close();

?>
