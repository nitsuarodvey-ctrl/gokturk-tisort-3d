CREATE TABLE orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_number VARCHAR(30) NOT NULL,
  name VARCHAR(60) NOT NULL,
  surname VARCHAR(60) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  phone_normalized VARCHAR(20) NOT NULL,
  email VARCHAR(190) NOT NULL,
  delivery_type ENUM('cargo','pickup') NOT NULL,
  city VARCHAR(80) NULL,
  district VARCHAR(80) NULL,
  address VARCHAR(600) NULL,
  postal_code VARCHAR(12) NULL,
  notes VARCHAR(500) NULL,
  unit_price INT UNSIGNED NOT NULL DEFAULT 499,
  total INT UNSIGNED NOT NULL,
  payment_status ENUM('waiting','paid','failed','refunded') NOT NULL DEFAULT 'waiting',
  payment_token_hash CHAR(64) NULL,
  payment_token_expires_at DATETIME NULL,
  order_status ENUM('received','preparing','ready','shipped','delivered','cancelled') NOT NULL DEFAULT 'received',
  delivery_status ENUM('pending','ready','shipped','delivered') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY orders_order_number_unique (order_number),
  KEY orders_phone_lookup (phone_normalized), KEY orders_email_lookup (email), KEY orders_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_attempts (
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

CREATE TABLE order_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id BIGINT UNSIGNED NOT NULL,
  product_id VARCHAR(80) NOT NULL,
  product_name VARCHAR(160) NOT NULL,
  size ENUM('S','M','L','XL') NOT NULL,
  quantity TINYINT UNSIGNED NOT NULL,
  unit_price INT UNSIGNED NOT NULL,
  line_total INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY order_items_order_id (order_id),
  CONSTRAINT order_items_order_fk FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(20) NULL,
  subject VARCHAR(160) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('new','read','closed') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY contact_messages_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
  bucket VARCHAR(60) NOT NULL,
  key_hash CHAR(64) NOT NULL,
  window_started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  request_count INT UNSIGNED NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (bucket, key_hash),
  KEY rate_limits_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
