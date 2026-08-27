CREATE TABLE IF NOT EXISTS orders (
  id CHAR(36) NOT NULL,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(32) NOT NULL,
  size VARCHAR(2) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  delivery_type VARCHAR(40) NOT NULL,
  city VARCHAR(80) NULL,
  district VARCHAR(80) NULL,
  address VARCHAR(500) NULL,
  unit_price INT UNSIGNED NOT NULL DEFAULT 499,
  total INT UNSIGNED NOT NULL,
  payment_status VARCHAR(20) NOT NULL DEFAULT 'waiting',
  order_status VARCHAR(20) NOT NULL DEFAULT 'preorder',
  production_status VARCHAR(30) NOT NULL DEFAULT 'waiting',
  delivery_status VARCHAR(30) NOT NULL DEFAULT 'waiting',
  notes VARCHAR(2000) NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  INDEX orders_created_at_idx (created_at),
  INDEX orders_status_idx (order_status, payment_status),
  CONSTRAINT size_chk CHECK (size IN ('S', 'M', 'L', 'XL')),
  CONSTRAINT qty_chk CHECK (quantity BETWEEN 1 AND 20),
  CONSTRAINT price_chk CHECK (unit_price = 499),
  CONSTRAINT total_chk CHECK (total = quantity * unit_price),
  CONSTRAINT pay_chk CHECK (payment_status IN ('waiting', 'paid', 'rejected')),
  CONSTRAINT order_chk CHECK (order_status IN ('preorder', 'confirmed', 'cancelled')),
  CONSTRAINT prod_chk CHECK (production_status IN ('waiting', 'queued', 'in_production', 'ready')),
  CONSTRAINT delivery_chk CHECK (delivery_status IN ('waiting', 'ready_for_pickup', 'shipped', 'delivered')),
  CONSTRAINT address_chk CHECK (
    delivery_type <> 'Adrese Kargo'
    OR (city IS NOT NULL AND district IS NOT NULL AND address IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS admins (
  id CHAR(36) NOT NULL,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  UNIQUE KEY admins_email_unique (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS admin_sessions (
  id CHAR(36) NOT NULL,
  admin_id CHAR(36) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME(3) NOT NULL,
  created_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  UNIQUE KEY admin_sessions_token_unique (token_hash),
  INDEX admin_sessions_admin_idx (admin_id),
  INDEX admin_sessions_expiry_idx (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  scope VARCHAR(64) NOT NULL,
  client_key CHAR(64) NOT NULL,
  window_key BIGINT UNSIGNED NOT NULL,
  request_count INT UNSIGNED NOT NULL DEFAULT 1,
  updated_at DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
  PRIMARY KEY (scope, client_key, window_key),
  INDEX rate_limits_updated_idx (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
