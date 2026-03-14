USE bizlink_crm;

START TRANSACTION;

-- Ensure an admin user exists for support conversations
INSERT IGNORE INTO users (
  email, password_hash, role, full_name, phone, city, province, country, account_status, is_verified
)
VALUES (
  'support@bizlink.lk', 'demo_hash_123', 'admin', 'BizLink Support Team', '+94 11 000 0000',
  'Colombo', 'Western', 'Sri Lanka', 'active', 1
);

INSERT IGNORE INTO admins (user_id)
SELECT u.user_id
FROM users u
WHERE u.email = 'support@bizlink.lk';

-- Missing chat normalization tables
CREATE TABLE IF NOT EXISTS conversation_participants (
  participant_id INT PRIMARY KEY AUTO_INCREMENT,
  conversation_id INT NOT NULL,
  user_id INT NOT NULL,
  participant_role ENUM('admin', 'vendor', 'customer') NOT NULL,
  joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  left_at DATETIME NULL,
  is_active BOOLEAN DEFAULT TRUE,
  last_read_message_id INT NULL,
  CONSTRAINT fk_cp_conversation FOREIGN KEY (conversation_id) REFERENCES conversations(conversation_id) ON DELETE CASCADE,
  CONSTRAINT fk_cp_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  UNIQUE KEY uq_cp_conversation_user (conversation_id, user_id),
  INDEX idx_cp_user (user_id),
  INDEX idx_cp_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_reads (
  message_read_id INT PRIMARY KEY AUTO_INCREMENT,
  message_id INT NOT NULL,
  user_id INT NOT NULL,
  read_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mr_message FOREIGN KEY (message_id) REFERENCES messages(message_id) ON DELETE CASCADE,
  CONSTRAINT fk_mr_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  UNIQUE KEY uq_mr_message_user (message_id, user_id),
  INDEX idx_mr_user (user_id),
  INDEX idx_mr_read_at (read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Canonical conversations for shared project demo data
INSERT INTO conversations (
  sender_id, receiver_id, conversation_type, subject, last_message_date, is_active
)
SELECT s.user_id, r.user_id, 'vendor_customer', 'Dilani and Ceylon Tech Hub Orders', '2026-03-14 09:20:00', 1
FROM users s
JOIN users r
WHERE s.email = 'dilani.silva@gmail.com'
  AND r.email = 'niroshan.perera@ceylontech.lk'
  AND NOT EXISTS (
    SELECT 1 FROM conversations c
    WHERE c.sender_id = s.user_id AND c.receiver_id = r.user_id AND c.subject = 'Dilani and Ceylon Tech Hub Orders'
  );

INSERT INTO conversations (
  sender_id, receiver_id, conversation_type, subject, last_message_date, is_active
)
SELECT s.user_id, r.user_id, 'vendor_customer', 'Dilani and Lanka Fresh Mart Delivery', '2026-03-14 08:30:00', 1
FROM users s
JOIN users r
WHERE s.email = 'dilani.silva@gmail.com'
  AND r.email = 'tharindu.jayasekara@lankafresh.lk'
  AND NOT EXISTS (
    SELECT 1 FROM conversations c
    WHERE c.sender_id = s.user_id AND c.receiver_id = r.user_id AND c.subject = 'Dilani and Lanka Fresh Mart Delivery'
  );

INSERT INTO conversations (
  sender_id, receiver_id, conversation_type, subject, last_message_date, is_active
)
SELECT s.user_id, r.user_id, 'vendor_customer', 'Dilani and Serendib Style House Follow-up', '2026-03-13 16:10:00', 1
FROM users s
JOIN users r
WHERE s.email = 'dilani.silva@gmail.com'
  AND r.email = 'kasun.fernando@serendibstyle.lk'
  AND NOT EXISTS (
    SELECT 1 FROM conversations c
    WHERE c.sender_id = s.user_id AND c.receiver_id = r.user_id AND c.subject = 'Dilani and Serendib Style House Follow-up'
  );

INSERT INTO conversations (
  sender_id, receiver_id, conversation_type, subject, last_message_date, is_active
)
SELECT s.user_id, r.user_id, 'admin_customer', 'Support: Payment Clarification', '2026-03-14 10:00:00', 1
FROM users s
JOIN users r
WHERE s.email = 'support@bizlink.lk'
  AND r.email = 'dilani.silva@gmail.com'
  AND NOT EXISTS (
    SELECT 1 FROM conversations c
    WHERE c.sender_id = s.user_id AND c.receiver_id = r.user_id AND c.subject = 'Support: Payment Clarification'
  );

-- Participants for all existing conversations
INSERT IGNORE INTO conversation_participants (conversation_id, user_id, participant_role)
SELECT c.conversation_id, c.sender_id, us.role
FROM conversations c
JOIN users us ON us.user_id = c.sender_id;

INSERT IGNORE INTO conversation_participants (conversation_id, user_id, participant_role)
SELECT c.conversation_id, c.receiver_id, ur.role
FROM conversations c
JOIN users ur ON ur.user_id = c.receiver_id;

-- Seed messages for each canonical conversation (idempotent by content check)
INSERT INTO messages (conversation_id, sender_id, receiver_id, message_content, message_type, is_read, read_at, created_at)
SELECT c.conversation_id, s.user_id, r.user_id,
       'Hello, I placed order BLK-2026-0010. Can you confirm today delivery window?',
       'text', 1, '2026-03-14 09:02:00', '2026-03-14 09:00:00'
FROM conversations c
JOIN users s ON s.email = 'dilani.silva@gmail.com'
JOIN users r ON r.email = 'niroshan.perera@ceylontech.lk'
WHERE c.subject = 'Dilani and Ceylon Tech Hub Orders'
  AND NOT EXISTS (
    SELECT 1 FROM messages m WHERE m.conversation_id = c.conversation_id
      AND m.message_content = 'Hello, I placed order BLK-2026-0010. Can you confirm today delivery window?'
  );

INSERT INTO messages (conversation_id, sender_id, receiver_id, message_content, message_type, is_read, read_at, created_at)
SELECT c.conversation_id, r.user_id, s.user_id,
       'Sure, delivery team is scheduled between 2:00 PM and 5:00 PM in Maharagama.',
       'text', 1, '2026-03-14 09:08:00', '2026-03-14 09:06:00'
FROM conversations c
JOIN users s ON s.email = 'dilani.silva@gmail.com'
JOIN users r ON r.email = 'niroshan.perera@ceylontech.lk'
WHERE c.subject = 'Dilani and Ceylon Tech Hub Orders'
  AND NOT EXISTS (
    SELECT 1 FROM messages m WHERE m.conversation_id = c.conversation_id
      AND m.message_content = 'Sure, delivery team is scheduled between 2:00 PM and 5:00 PM in Maharagama.'
  );

INSERT INTO messages (conversation_id, sender_id, receiver_id, message_content, message_type, is_read, read_at, created_at)
SELECT c.conversation_id, s.user_id, r.user_id,
       'Thank you. Please also add one extra cinnamon pack to order BLK-2026-0011 if possible.',
       'text', 0, NULL, '2026-03-14 09:20:00'
FROM conversations c
JOIN users s ON s.email = 'dilani.silva@gmail.com'
JOIN users r ON r.email = 'tharindu.jayasekara@lankafresh.lk'
WHERE c.subject = 'Dilani and Lanka Fresh Mart Delivery'
  AND NOT EXISTS (
    SELECT 1 FROM messages m WHERE m.conversation_id = c.conversation_id
      AND m.message_content = 'Thank you. Please also add one extra cinnamon pack to order BLK-2026-0011 if possible.'
  );

INSERT INTO messages (conversation_id, sender_id, receiver_id, message_content, message_type, is_read, read_at, created_at)
SELECT c.conversation_id, r.user_id, s.user_id,
       'Added. We will update invoice and send revised total in a few minutes.',
       'text', 0, NULL, '2026-03-14 09:24:00'
FROM conversations c
JOIN users s ON s.email = 'dilani.silva@gmail.com'
JOIN users r ON r.email = 'tharindu.jayasekara@lankafresh.lk'
WHERE c.subject = 'Dilani and Lanka Fresh Mart Delivery'
  AND NOT EXISTS (
    SELECT 1 FROM messages m WHERE m.conversation_id = c.conversation_id
      AND m.message_content = 'Added. We will update invoice and send revised total in a few minutes.'
  );

INSERT INTO messages (conversation_id, sender_id, receiver_id, message_content, message_type, is_read, read_at, created_at)
SELECT c.conversation_id, s.user_id, r.user_id,
       'Hi, can I exchange the Batik Office Shirt (BLK-2026-0013) for a medium size?',
       'text', 1, '2026-03-13 15:50:00', '2026-03-13 15:40:00'
FROM conversations c
JOIN users s ON s.email = 'dilani.silva@gmail.com'
JOIN users r ON r.email = 'kasun.fernando@serendibstyle.lk'
WHERE c.subject = 'Dilani and Serendib Style House Follow-up'
  AND NOT EXISTS (
    SELECT 1 FROM messages m WHERE m.conversation_id = c.conversation_id
      AND m.message_content = 'Hi, can I exchange the Batik Office Shirt (BLK-2026-0013) for a medium size?'
  );

INSERT INTO messages (conversation_id, sender_id, receiver_id, message_content, message_type, is_read, read_at, created_at)
SELECT c.conversation_id, r.user_id, s.user_id,
       'Yes, absolutely. Please keep the original tag and we will arrange pickup tomorrow morning.',
       'text', 1, '2026-03-13 16:00:00', '2026-03-13 15:55:00'
FROM conversations c
JOIN users s ON s.email = 'dilani.silva@gmail.com'
JOIN users r ON r.email = 'kasun.fernando@serendibstyle.lk'
WHERE c.subject = 'Dilani and Serendib Style House Follow-up'
  AND NOT EXISTS (
    SELECT 1 FROM messages m WHERE m.conversation_id = c.conversation_id
      AND m.message_content = 'Yes, absolutely. Please keep the original tag and we will arrange pickup tomorrow morning.'
  );

INSERT INTO messages (conversation_id, sender_id, receiver_id, message_content, message_type, is_read, read_at, created_at)
SELECT c.conversation_id, r.user_id, s.user_id,
       'Hello Dilani, support here. Your payment reversal for BLK-2026-0014 has been completed.',
       'text', 0, NULL, '2026-03-14 10:00:00'
FROM conversations c
JOIN users s ON s.email = 'dilani.silva@gmail.com'
JOIN users r ON r.email = 'support@bizlink.lk'
WHERE c.subject = 'Support: Payment Clarification'
  AND NOT EXISTS (
    SELECT 1 FROM messages m WHERE m.conversation_id = c.conversation_id
      AND m.message_content = 'Hello Dilani, support here. Your payment reversal for BLK-2026-0014 has been completed.'
  );

-- Message read receipts for already-read messages
INSERT IGNORE INTO message_reads (message_id, user_id, read_at)
SELECT m.message_id, m.receiver_id, COALESCE(m.read_at, m.created_at)
FROM messages m
WHERE m.is_read = 1;

COMMIT;

SELECT COUNT(*) AS conversations_count FROM conversations;
SELECT COUNT(*) AS participants_count FROM conversation_participants;
SELECT COUNT(*) AS messages_count FROM messages;
SELECT COUNT(*) AS message_reads_count FROM message_reads;
