-- Stripe payment integration migration for BizLink CRM
-- Run this on the same database used by the API (default: bizlink_crm)

USE bizlink_crm;

-- Keep Stripe webhook events idempotent (avoid double processing)
CREATE TABLE IF NOT EXISTS stripe_webhook_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id VARCHAR(255) NOT NULL,
    event_type VARCHAR(120) NOT NULL,
    processed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    payload_json LONGTEXT NULL,
    UNIQUE KEY uniq_event_id (event_id),
    INDEX idx_event_type (event_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Helpful indexes for Stripe lookups (safe to run multiple times)
SET @db_name = DATABASE();

SET @has_idx_transaction_reference = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'payments'
      AND index_name = 'idx_transaction_reference'
);

SET @sql_idx_transaction_reference = IF(
    @has_idx_transaction_reference = 0,
    'ALTER TABLE payments ADD INDEX idx_transaction_reference (transaction_reference)',
    'SELECT "idx_transaction_reference already exists"'
);
PREPARE stmt_idx_transaction_reference FROM @sql_idx_transaction_reference;
EXECUTE stmt_idx_transaction_reference;
DEALLOCATE PREPARE stmt_idx_transaction_reference;

SET @has_idx_gateway_name = (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = @db_name
      AND table_name = 'payments'
      AND index_name = 'idx_gateway_name'
);

SET @sql_idx_gateway_name = IF(
    @has_idx_gateway_name = 0,
    'ALTER TABLE payments ADD INDEX idx_gateway_name (gateway_name)',
    'SELECT "idx_gateway_name already exists"'
);
PREPARE stmt_idx_gateway_name FROM @sql_idx_gateway_name;
EXECUTE stmt_idx_gateway_name;
DEALLOCATE PREPARE stmt_idx_gateway_name;
