USE bizlink_crm;

START TRANSACTION;

INSERT IGNORE INTO users (email, password_hash, role, full_name, phone, city, province, country, account_status, is_verified)
VALUES
('kasun@bizlink.lk', 'demo_hash_123', 'admin', 'Kasun Perera', '+94 71 900 1100', 'Colombo', 'Western', 'Sri Lanka', 'active', 1);

CREATE TABLE IF NOT EXISTS notification_reads (
    notification_read_id INT PRIMARY KEY AUTO_INCREMENT,
    notification_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_reads_notification FOREIGN KEY (notification_id) REFERENCES notifications(notification_id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_reads_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY uq_notification_read (notification_id, user_id),
    INDEX idx_notification_reads_user (user_id),
    INDEX idx_notification_reads_read_at (read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO notifications (
    user_id, notification_type, title, message, related_entity_type, related_entity_id,
    priority, action_url, created_at
)
SELECT u.user_id, 'system', 'New vendor verification request',
       'PowerIT Lanka submitted business documents and is waiting for admin review.',
       'vendor', NULL, 'high', '../admin/dashboard.html', '2026-03-14 08:10:00'
FROM users u
WHERE u.email = 'kasun@bizlink.lk'
  AND NOT EXISTS (
      SELECT 1 FROM notifications n
      WHERE n.user_id = u.user_id AND n.title = 'New vendor verification request'
  );

INSERT INTO notifications (
    user_id, notification_type, title, message, related_entity_type, related_entity_id,
    priority, action_url, created_at
)
SELECT u.user_id, 'payment', 'Daily payout batch completed',
       'Vendor settlements worth Rs. 186,400 were processed successfully this morning.',
       'payment', NULL, 'medium', '../admin/order.html', '2026-03-14 07:30:00'
FROM users u
WHERE u.email = 'kasun@bizlink.lk'
  AND NOT EXISTS (
      SELECT 1 FROM notifications n
      WHERE n.user_id = u.user_id AND n.title = 'Daily payout batch completed'
  );

INSERT INTO notifications (
    user_id, notification_type, title, message, related_entity_type, related_entity_id,
    priority, action_url, created_at
)
SELECT u.user_id, 'message', 'Support escalation received',
       'A vendor requested a manual review for delayed delivery on order BLK-2026-0008.',
       'order', NULL, 'high', '../pages/chat.html?email=kasun@bizlink.lk', '2026-03-13 18:20:00'
FROM users u
WHERE u.email = 'kasun@bizlink.lk'
  AND NOT EXISTS (
      SELECT 1 FROM notifications n
      WHERE n.user_id = u.user_id AND n.title = 'Support escalation received'
  );

INSERT INTO notifications (
    user_id, notification_type, title, message, related_entity_type, related_entity_id,
    priority, action_url, created_at
)
SELECT u.user_id, 'review', 'Marketplace reputation improved',
       'Average vendor rating increased to 4.7 after today''s verified reviews were published.',
       'review', NULL, 'low', '../admin/dashboard.html', '2026-03-13 15:00:00'
FROM users u
WHERE u.email = 'kasun@bizlink.lk'
  AND NOT EXISTS (
      SELECT 1 FROM notifications n
      WHERE n.user_id = u.user_id AND n.title = 'Marketplace reputation improved'
  );

INSERT INTO notifications (
    user_id, notification_type, title, message, related_entity_type, related_entity_id,
    priority, action_url, created_at
)
SELECT u.user_id, 'order_status', 'New laptop order placed',
       'Order BLK-2026-0001 from Dilani Silva is ready for fulfillment.',
       'order', NULL, 'high', '../vendor/vendorpanel.html', '2026-03-14 08:45:00'
FROM users u
WHERE u.email = 'niroshan.perera@ceylontech.lk'
  AND NOT EXISTS (
      SELECT 1 FROM notifications n
      WHERE n.user_id = u.user_id AND n.title = 'New laptop order placed'
  );

INSERT INTO notifications (
    user_id, notification_type, title, message, related_entity_type, related_entity_id,
    priority, action_url, created_at
)
SELECT u.user_id, 'payment', 'Payment confirmed for BLK-2026-0007',
       'Rs. 16,800 has been captured and is scheduled for the next payout cycle.',
       'payment', NULL, 'medium', '../vendor/vendorpanel.html', '2026-03-14 07:55:00'
FROM users u
WHERE u.email = 'niroshan.perera@ceylontech.lk'
  AND NOT EXISTS (
      SELECT 1 FROM notifications n
      WHERE n.user_id = u.user_id AND n.title = 'Payment confirmed for BLK-2026-0007'
  );

INSERT INTO notifications (
    user_id, notification_type, title, message, related_entity_type, related_entity_id,
    priority, action_url, created_at
)
SELECT u.user_id, 'review', 'New 5-star review received',
       'Chathura Bandara praised fast packaging and delivery quality for his recent order.',
       'review', NULL, 'low', '../vendor/vendorpanel.html', '2026-03-13 19:05:00'
FROM users u
WHERE u.email = 'niroshan.perera@ceylontech.lk'
  AND NOT EXISTS (
      SELECT 1 FROM notifications n
      WHERE n.user_id = u.user_id AND n.title = 'New 5-star review received'
  );

INSERT INTO notifications (
    user_id, notification_type, title, message, related_entity_type, related_entity_id,
    priority, action_url, created_at
)
SELECT u.user_id, 'order_status', 'Fresh grocery order received',
       'Order BLK-2026-0006 needs packing before the afternoon dispatch window.',
       'order', NULL, 'high', '../vendor/vendorpanel.html', '2026-03-14 09:05:00'
FROM users u
WHERE u.email = 'tharindu.jayasekara@lankafresh.lk'
  AND NOT EXISTS (
      SELECT 1 FROM notifications n
      WHERE n.user_id = u.user_id AND n.title = 'Fresh grocery order received'
  );

INSERT INTO notifications (
    user_id, notification_type, title, message, related_entity_type, related_entity_id,
    priority, action_url, created_at
)
SELECT u.user_id, 'promotion', 'Weekend campaign approved',
       'Your Sinhala and English promotion banners are now live across the marketplace.',
       'promotion', NULL, 'medium', '../vendor/vendorpanel.html', '2026-03-13 14:15:00'
FROM users u
WHERE u.email = 'tharindu.jayasekara@lankafresh.lk'
  AND NOT EXISTS (
      SELECT 1 FROM notifications n
      WHERE n.user_id = u.user_id AND n.title = 'Weekend campaign approved'
  );

INSERT INTO notifications (
    user_id, notification_type, title, message, related_entity_type, related_entity_id,
    priority, action_url, created_at
)
SELECT u.user_id, 'order_status', 'Fashion order awaiting shipment',
       'Order BLK-2026-0003 should be shipped today to avoid an SLA breach.',
       'order', NULL, 'high', '../vendor/vendorpanel.html', '2026-03-14 08:25:00'
FROM users u
WHERE u.email = 'kasun.fernando@serendibstyle.lk'
  AND NOT EXISTS (
      SELECT 1 FROM notifications n
      WHERE n.user_id = u.user_id AND n.title = 'Fashion order awaiting shipment'
  );

INSERT INTO notifications (
    user_id, notification_type, title, message, related_entity_type, related_entity_id,
    priority, action_url, created_at
)
SELECT u.user_id, 'message', 'Customer asked about size exchange',
       'Dilani Silva sent a sizing question in chat for the latest batik office shirt order.',
       'message', NULL, 'medium', '../pages/chat.html?email=kasun.fernando@serendibstyle.lk', '2026-03-13 17:10:00'
FROM users u
WHERE u.email = 'kasun.fernando@serendibstyle.lk'
  AND NOT EXISTS (
      SELECT 1 FROM notifications n
      WHERE n.user_id = u.user_id AND n.title = 'Customer asked about size exchange'
  );

INSERT IGNORE INTO notification_reads (notification_id, user_id, read_at)
SELECT n.notification_id, n.user_id, '2026-03-13 15:05:00'
FROM notifications n
JOIN users u ON u.user_id = n.user_id
WHERE u.email = 'kasun@bizlink.lk'
  AND n.title = 'Marketplace reputation improved';

UPDATE notifications n
JOIN users u ON u.user_id = n.user_id
SET n.is_read = 1,
    n.read_at = '2026-03-13 15:05:00'
WHERE u.email = 'kasun@bizlink.lk'
  AND n.title = 'Marketplace reputation improved';

INSERT IGNORE INTO notification_reads (notification_id, user_id, read_at)
SELECT n.notification_id, n.user_id, '2026-03-13 19:10:00'
FROM notifications n
JOIN users u ON u.user_id = n.user_id
WHERE u.email = 'niroshan.perera@ceylontech.lk'
  AND n.title = 'New 5-star review received';

UPDATE notifications n
JOIN users u ON u.user_id = n.user_id
SET n.is_read = 1,
    n.read_at = '2026-03-13 19:10:00'
WHERE u.email = 'niroshan.perera@ceylontech.lk'
  AND n.title = 'New 5-star review received';

COMMIT;

SELECT
    COUNT(*) AS notifications_count,
    SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) AS unread_notifications_count
FROM notifications;

SELECT COUNT(*) AS notification_reads_count FROM notification_reads;