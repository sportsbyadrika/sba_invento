-- 003: customer password reset for the online shop
ALTER TABLE shop_customers
  ADD COLUMN reset_token_hash CHAR(64) NULL,
  ADD COLUMN reset_expires_at DATETIME NULL
