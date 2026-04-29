-- Google Generative AI Integration Database Migration
-- Run this SQL to prepare your database for AI bot support

-- 1. Update users table to support 'bot' role
ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'vendor', 'customer', 'bot') DEFAULT 'customer' 
COMMENT 'User role: admin, vendor, customer, or bot';

-- 2. Update messages table to track message type (human vs AI)
ALTER TABLE messages 
ADD COLUMN IF NOT EXISTS message_type ENUM('text', 'ai', 'system') DEFAULT 'text' AFTER message_content
COMMENT 'Type of message: text (user), ai (bot), system (notifications)';

-- 3. Add index for AI messages (optional - for performance)
ALTER TABLE messages 
ADD INDEX idx_message_type (message_type) 
COMMENT 'Index for filtering AI messages';

-- 4. Create AI Bot user (if not exists)
INSERT INTO users (email, full_name, phone_number, role, password_hash, account_status, email_verified, created_at, updated_at)
SELECT 
  'ai-bot@bizlink-crm.local',
  'AI Assistant',
  '+1-800-AI-HELP-0',
  'bot',
  SHA2(CONCAT('secure-bot-password-', UNIX_TIMESTAMP()), 256),
  'active',
  1,
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM users WHERE email = 'ai-bot@bizlink-crm.local' AND role = 'bot'
);

-- 5. Verify tables are updated
SELECT 'Users table updated' AS migration_status;
SHOW COLUMNS FROM users WHERE Field = 'role';
SHOW COLUMNS FROM messages WHERE Field = 'message_type';

-- Optional: Query to find AI Bot user
SELECT user_id, full_name, email, role, account_status FROM users WHERE role = 'bot' AND full_name = 'AI Assistant';

-- Optional: View AI messages
-- SELECT m.message_id, m.sender_id, m.message_content, m.message_type, m.created_at
-- FROM messages m
-- WHERE m.message_type = 'ai'
-- ORDER BY m.created_at DESC
-- LIMIT 10;
