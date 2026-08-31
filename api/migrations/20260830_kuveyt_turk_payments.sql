SET @add_payment_token_hash = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'payment_token_hash'),
  'SELECT 1',
  'ALTER TABLE orders ADD COLUMN payment_token_hash CHAR(64) NULL AFTER payment_status'
);
PREPARE payment_migration_statement FROM @add_payment_token_hash;
EXECUTE payment_migration_statement;
DEALLOCATE PREPARE payment_migration_statement;

SET @add_payment_token_expires_at = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'payment_token_expires_at'),
  'SELECT 1',
  'ALTER TABLE orders ADD COLUMN payment_token_expires_at DATETIME NULL AFTER payment_token_hash'
);
PREPARE payment_migration_statement FROM @add_payment_token_expires_at;
EXECUTE payment_migration_statement;
DEALLOCATE PREPARE payment_migration_statement;

CREATE TABLE IF NOT EXISTS payment_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  merchant_order_id VARCHAR(64) NOT NULL,
  amount INT UNSIGNED NOT NULL,
  currency_code CHAR(4) NOT NULL DEFAULT '0949',
  status ENUM('initiated','awaiting_3d','provisioning','paid','failed','unknown') NOT NULL DEFAULT 'initiated',
  gateway_order_id VARCHAR(80) NULL,
  provision_number VARCHAR(80) NULL,
  rrn VARCHAR(80) NULL,
  stan VARCHAR(80) NULL,
  response_code VARCHAR(20) NULL,
  response_message VARCHAR(500) NULL,
  reference_id VARCHAR(160) NULL,
  business_key VARCHAR(160) NULL,
  callback_count INT UNSIGNED NOT NULL DEFAULT 0,
  last_callback_at DATETIME NULL,
  completed_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY payment_attempts_merchant_order_unique (merchant_order_id),
  KEY payment_attempts_order_status (order_id, status),
  CONSTRAINT payment_attempts_order_fk FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
