-- =====================================================================
-- 002: Stock batches & ledger, sales/purchase documents, online shop
-- Applied automatically once (tracked in the `migrations` table).
-- Keep every statement free of ';' inside strings (the runner splits on ';').
-- =====================================================================

-- ---------- Items: online listing fields ----------
ALTER TABLE items
  ADD COLUMN brand            VARCHAR(80)   NULL AFTER category_id,
  ADD COLUMN slug             VARCHAR(160)  NULL AFTER name,
  ADD COLUMN mrp              DECIMAL(18,4) NULL COMMENT 'Compare-at / MRP shown struck through' AFTER sales_price,
  ADD COLUMN online_price     DECIMAL(18,4) NULL COMMENT 'NULL = use sales_price' AFTER mrp,
  ADD COLUMN publish_online   TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN online_stock_cap DECIMAL(18,4) NULL COMMENT 'Show at most this quantity online',
  ADD COLUMN short_description VARCHAR(300) NULL,
  ADD COLUMN long_description TEXT NULL,
  ADD COLUMN attributes       TEXT NULL COMMENT 'One "Name: value" per line - used for shop filters',
  ADD COLUMN min_order_qty    DECIMAL(18,4) NOT NULL DEFAULT 1,
  ADD COLUMN max_order_qty    DECIMAL(18,4) NULL,
  ADD COLUMN return_days      SMALLINT UNSIGNED NULL COMMENT 'NULL = shop default',
  ADD COLUMN weight_kg        DECIMAL(10,3) NULL,
  ADD KEY idx_item_online (company_id, publish_online),
  ADD KEY idx_item_slug (company_id, slug);

CREATE TABLE IF NOT EXISTS item_images (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id  INT UNSIGNED NOT NULL,
  item_id     INT UNSIGNED NOT NULL,
  file        VARCHAR(200) NOT NULL,
  thumb       VARCHAR(200) NOT NULL,
  alt         VARCHAR(150) NULL,
  sort_order  SMALLINT NOT NULL DEFAULT 0,
  is_main     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_img_item (item_id, sort_order),
  CONSTRAINT fk_img_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Stock: FIFO batches + ledger ----------
CREATE TABLE IF NOT EXISTS stock_batches (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id    INT UNSIGNED NOT NULL,
  item_id       INT UNSIGNED NOT NULL,
  warehouse_id  INT UNSIGNED NULL,
  source_type   VARCHAR(20) NOT NULL COMMENT 'opening, receipt, return, adjustment',
  source_id     INT UNSIGNED NULL,
  batch_no      VARCHAR(40) NULL,
  qty_in        DECIMAL(18,4) NOT NULL,
  qty_remaining DECIMAL(18,4) NOT NULL,
  unit_cost     DECIMAL(18,4) NOT NULL COMMENT 'Base currency, incl. landed cost (freight)',
  mrp           DECIMAL(18,4) NULL,
  received_at   DATE NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_batch_fifo (company_id, item_id, qty_remaining, received_at, id),
  CONSTRAINT fk_batch_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_moves (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id   INT UNSIGNED NOT NULL,
  item_id      INT UNSIGNED NOT NULL,
  batch_id     INT UNSIGNED NULL,
  move_date    DATE NOT NULL,
  qty          DECIMAL(18,4) NOT NULL COMMENT '+ in / - out',
  unit_cost    DECIMAL(18,4) NOT NULL,
  value        DECIMAL(18,4) NOT NULL,
  source_type  VARCHAR(20) NOT NULL,
  source_id    INT UNSIGNED NULL,
  reference    VARCHAR(60) NULL,
  created_by   INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_move_item (company_id, item_id, move_date),
  KEY idx_move_source (company_id, source_type, source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Opening stock already entered on items becomes the first batch
INSERT INTO stock_batches (company_id, item_id, source_type, qty_in, qty_remaining, unit_cost, received_at)
SELECT company_id, id, 'opening', opening_stock, opening_stock, purchase_price, CURRENT_DATE
  FROM items WHERE track_inventory = 1 AND opening_stock > 0;
INSERT INTO stock_moves (company_id, item_id, batch_id, move_date, qty, unit_cost, value, source_type, reference)
SELECT b.company_id, b.item_id, b.id, b.received_at, b.qty_in, b.unit_cost, b.qty_in * b.unit_cost, 'opening', 'Opening stock'
  FROM stock_batches b WHERE b.source_type = 'opening';

-- ---------- Documents: purchase orders, quotes, invoices, credit notes ----------
CREATE TABLE IF NOT EXISTS documents (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id      INT UNSIGNED NOT NULL,
  doc_type        ENUM('po','quote','invoice','credit_note') NOT NULL,
  number          VARCHAR(30) NOT NULL,
  doc_date        DATE NOT NULL,
  due_date        DATE NULL COMMENT 'Invoice due / quote valid until / PO expected',
  contact_id      INT UNSIGNED NOT NULL,
  reference       VARCHAR(60) NULL,
  currency_code   CHAR(3) NOT NULL,
  exchange_rate   DECIMAL(18,8) NOT NULL DEFAULT 1,
  place_of_supply VARCHAR(10) NULL,
  subtotal        DECIMAL(18,4) NOT NULL DEFAULT 0 COMMENT 'After line discounts, before tax',
  discount_total  DECIMAL(18,4) NOT NULL DEFAULT 0,
  tax_total       DECIMAL(18,4) NOT NULL DEFAULT 0,
  freight         DECIMAL(18,4) NOT NULL DEFAULT 0 COMMENT 'PO: freight (landed cost) / invoice: delivery charge',
  total           DECIMAL(18,4) NOT NULL DEFAULT 0,
  total_base      DECIMAL(18,4) NOT NULL DEFAULT 0,
  amount_paid     DECIMAL(18,4) NOT NULL DEFAULT 0,
  status          VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'draft, sent, accepted, declined, converted, open, partial, received, closed, posted, paid, void',
  source_type     VARCHAR(20) NOT NULL DEFAULT 'manual',
  source_id       INT UNSIGNED NULL,
  journal_id      INT UNSIGNED NULL,
  notes           VARCHAR(1000) NULL,
  terms           VARCHAR(1000) NULL,
  billing_address VARCHAR(500) NULL,
  shipping_address VARCHAR(500) NULL,
  created_by      INT UNSIGNED NULL,
  posted_by       INT UNSIGNED NULL,
  posted_at       DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_doc_no (company_id, doc_type, number),
  KEY idx_doc_list (company_id, doc_type, doc_date),
  KEY idx_doc_contact (company_id, contact_id),
  CONSTRAINT fk_doc_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS document_lines (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id   INT UNSIGNED NOT NULL,
  company_id    INT UNSIGNED NOT NULL,
  line_no       SMALLINT UNSIGNED NOT NULL,
  item_id       INT UNSIGNED NULL,
  description   VARCHAR(255) NULL,
  qty           DECIMAL(18,4) NOT NULL DEFAULT 1,
  received_qty  DECIMAL(18,4) NOT NULL DEFAULT 0,
  unit_price    DECIMAL(18,4) NOT NULL DEFAULT 0,
  discount_pct  DECIMAL(7,3) NOT NULL DEFAULT 0,
  tax_rate_id   INT UNSIGNED NULL,
  tax_pct       DECIMAL(7,3) NOT NULL DEFAULT 0,
  tax_amount    DECIMAL(18,4) NOT NULL DEFAULT 0,
  line_total    DECIMAL(18,4) NOT NULL DEFAULT 0 COMMENT 'qty x price - discount, before tax',
  cost_total    DECIMAL(18,4) NOT NULL DEFAULT 0 COMMENT 'FIFO cost of goods (invoices)',
  KEY idx_dl_doc (document_id),
  KEY idx_dl_item (company_id, item_id),
  CONSTRAINT fk_dl_doc FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Online shop ----------
CREATE TABLE IF NOT EXISTS shop_settings (
  company_id        INT UNSIGNED PRIMARY KEY,
  enabled           TINYINT(1) NOT NULL DEFAULT 0,
  slug              VARCHAR(60) NOT NULL,
  store_name        VARCHAR(120) NOT NULL,
  tagline           VARCHAR(200) NULL,
  contact_email     VARCHAR(150) NULL,
  contact_phone     VARCHAR(40) NULL,
  delivery_fee      DECIMAL(18,4) NOT NULL DEFAULT 0,
  free_delivery_min DECIMAL(18,4) NULL,
  cod_enabled       TINYINT(1) NOT NULL DEFAULT 1,
  cod_max           DECIMAL(18,4) NULL,
  cod_fee           DECIMAL(18,4) NOT NULL DEFAULT 0,
  return_days       SMALLINT UNSIGNED NOT NULL DEFAULT 7,
  low_stock_badge   SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  terms             TEXT NULL,
  privacy           TEXT NULL,
  returns_policy    TEXT NULL,
  shipping_policy   TEXT NULL,
  updated_at        DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_shop_slug (slug),
  CONSTRAINT fk_shop_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_customers (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id      INT UNSIGNED NOT NULL,
  contact_id      INT UNSIGNED NULL COMMENT 'Linked accounting customer',
  name            VARCHAR(120) NOT NULL,
  phone_code      VARCHAR(6) NOT NULL,
  phone           VARCHAR(20) NOT NULL,
  email           VARCHAR(150) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  status          ENUM('active','disabled') NOT NULL DEFAULT 'active',
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until    DATETIME NULL,
  last_login_at   DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sc_email (company_id, email),
  UNIQUE KEY uq_sc_phone (company_id, phone_code, phone),
  CONSTRAINT fk_sc_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_addresses (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id  INT UNSIGNED NOT NULL,
  label        VARCHAR(40) NOT NULL,
  name         VARCHAR(120) NOT NULL,
  phone        VARCHAR(30) NOT NULL,
  line1        VARCHAR(200) NOT NULL,
  line2        VARCHAR(200) NULL,
  city         VARCHAR(80) NOT NULL,
  region       VARCHAR(80) NULL COMMENT 'Emirate / state code',
  postal_code  VARCHAR(20) NULL,
  country      CHAR(2) NOT NULL DEFAULT 'AE',
  is_default   TINYINT(1) NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_addr_customer (customer_id),
  CONSTRAINT fk_addr_customer FOREIGN KEY (customer_id) REFERENCES shop_customers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_orders (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id     INT UNSIGNED NOT NULL,
  number         VARCHAR(30) NOT NULL,
  customer_id    INT UNSIGNED NOT NULL,
  status         VARCHAR(20) NOT NULL DEFAULT 'placed' COMMENT 'placed, confirmed, packed, shipped, out_for_delivery, delivered, cancelled',
  payment_method VARCHAR(20) NOT NULL DEFAULT 'cod',
  payment_status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, paid, refunded',
  ship_name      VARCHAR(120) NOT NULL,
  ship_phone     VARCHAR(30) NOT NULL,
  ship_label     VARCHAR(40) NULL,
  ship_address   VARCHAR(500) NOT NULL,
  ship_city      VARCHAR(80) NOT NULL,
  ship_region    VARCHAR(80) NULL,
  ship_country   CHAR(2) NOT NULL,
  currency_code  CHAR(3) NOT NULL,
  subtotal       DECIMAL(18,4) NOT NULL DEFAULT 0,
  tax_total      DECIMAL(18,4) NOT NULL DEFAULT 0,
  delivery_fee   DECIMAL(18,4) NOT NULL DEFAULT 0,
  cod_fee        DECIMAL(18,4) NOT NULL DEFAULT 0,
  total          DECIMAL(18,4) NOT NULL DEFAULT 0,
  invoice_id     INT UNSIGNED NULL,
  courier        VARCHAR(60) NULL,
  tracking_no    VARCHAR(60) NULL,
  customer_note  VARCHAR(500) NULL,
  placed_at      DATETIME NOT NULL,
  delivered_at   DATETIME NULL,
  cancelled_at   DATETIME NULL,
  updated_at     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_so_no (company_id, number),
  KEY idx_so_status (company_id, status, placed_at),
  KEY idx_so_customer (customer_id, placed_at),
  CONSTRAINT fk_so_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_order_lines (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id    INT UNSIGNED NOT NULL,
  item_id     INT UNSIGNED NOT NULL,
  sku         VARCHAR(40) NOT NULL,
  name        VARCHAR(150) NOT NULL,
  image       VARCHAR(200) NULL,
  qty         DECIMAL(18,4) NOT NULL,
  unit_price  DECIMAL(18,4) NOT NULL COMMENT 'Price paid (snapshot, tax exclusive)',
  tax_rate_id INT UNSIGNED NULL,
  tax_pct     DECIMAL(7,3) NOT NULL DEFAULT 0,
  tax_amount  DECIMAL(18,4) NOT NULL DEFAULT 0,
  line_total  DECIMAL(18,4) NOT NULL,
  returned_qty DECIMAL(18,4) NOT NULL DEFAULT 0,
  KEY idx_sol_order (order_id),
  KEY idx_sol_item (item_id),
  CONSTRAINT fk_sol_order FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_order_events (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id    INT UNSIGNED NOT NULL,
  status      VARCHAR(30) NOT NULL,
  note        VARCHAR(300) NULL,
  actor       VARCHAR(20) NOT NULL DEFAULT 'system' COMMENT 'customer, staff, system',
  actor_id    INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL,
  KEY idx_soe_order (order_id, id),
  CONSTRAINT fk_soe_order FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_returns (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id     INT UNSIGNED NOT NULL,
  number         VARCHAR(30) NOT NULL,
  order_id       INT UNSIGNED NOT NULL,
  customer_id    INT UNSIGNED NOT NULL,
  status         VARCHAR(20) NOT NULL DEFAULT 'requested' COMMENT 'requested, approved, rejected, received, refunded',
  reason         VARCHAR(60) NOT NULL,
  details        VARCHAR(1000) NULL,
  refund_amount  DECIMAL(18,4) NOT NULL DEFAULT 0,
  refund_method  VARCHAR(30) NULL,
  refund_ref     VARCHAR(60) NULL,
  credit_note_id INT UNSIGNED NULL,
  staff_note     VARCHAR(500) NULL,
  created_at     DATETIME NOT NULL,
  updated_at     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ret_no (company_id, number),
  KEY idx_ret_status (company_id, status),
  CONSTRAINT fk_ret_order FOREIGN KEY (order_id) REFERENCES shop_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_return_lines (
  id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  return_id      INT UNSIGNED NOT NULL,
  order_line_id  BIGINT UNSIGNED NOT NULL,
  item_id        INT UNSIGNED NOT NULL,
  qty            DECIMAL(18,4) NOT NULL,
  amount         DECIMAL(18,4) NOT NULL COMMENT 'Incl. tax',
  KEY idx_srl_return (return_id),
  CONSTRAINT fk_srl_return FOREIGN KEY (return_id) REFERENCES shop_returns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shop_reviews (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id  INT UNSIGNED NOT NULL,
  item_id     INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  order_id    INT UNSIGNED NOT NULL,
  rating      TINYINT UNSIGNED NOT NULL,
  title       VARCHAR(120) NULL,
  body        VARCHAR(2000) NULL,
  status      ENUM('pending','approved','hidden') NOT NULL DEFAULT 'pending',
  created_at  DATETIME NOT NULL,
  UNIQUE KEY uq_review (customer_id, item_id),
  KEY idx_rev_item (company_id, item_id, status),
  CONSTRAINT fk_rev_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
