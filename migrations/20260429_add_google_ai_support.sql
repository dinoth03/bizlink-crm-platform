-- Google Generative AI Integration Database Migration
-- Run this SQL to prepare your database for AI bot support

-- 1. Update messages table message_type enum to include 'ai' and 'system' types
ALTER TABLE messages MODIFY COLUMN message_type ENUM('text','file','image','audio','ai','system') DEFAULT 'text';

-- 2. Add index for AI messages (optional - for performance)
ALTER TABLE messages ADD INDEX IF NOT EXISTS idx_message_type (message_type);

-- 3. Create AI Bot user (if not exists)
INSERT IGNORE INTO users (email, password_hash, phone, role, full_name, profile_picture_url, account_status, is_verified, created_at, updated_at)
VALUES (
  'ai-bot@bizlink-crm.local',
  SHA2(CONCAT('secure-bot-password-', UNIX_TIMESTAMP()), 256),
  '+94-800-AI-BOT-0',
  'bot',
  'BizLink AI Assistant',
  'https://api.dicebear.com/7.x/bottts/svg?seed=bizlink-ai',
  'active',
  1,
  NOW(),
  NOW()
);

-- 4. Verification query
SELECT 'AI Support Activated' AS status;
SELECT COUNT(*) as ai_bot_count FROM users WHERE role = 'bot' AND email = 'ai-bot@bizlink-crm.local';
SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'messages' AND COLUMN_NAME = 'message_type';
