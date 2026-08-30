-- Existing installations only: persistent API and admin-login rate limiting.
CREATE TABLE IF NOT EXISTS rate_limits (
  bucket VARCHAR(60) NOT NULL,
  key_hash CHAR(64) NOT NULL,
  window_started_at DATETIME NOT NULL,
  request_count INT UNSIGNED NOT NULL DEFAULT 1,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (bucket, key_hash),
  KEY rate_limits_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
