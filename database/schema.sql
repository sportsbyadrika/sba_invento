-- =====================================================================
-- SBA Invento - SaaS Accounting & Inventory
-- Database schema (MySQL 8.0+ / MariaDB 10.6+), utf8mb4
-- All DATETIME columns are stored in UTC. Convert to the user's
-- timezone only when displaying.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Tenancy: companies, users and the user <-> company link
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS companies (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code               VARCHAR(20)  NOT NULL,
  name               VARCHAR(150) NOT NULL,
  legal_name         VARCHAR(200) NULL,
  country_code       CHAR(2)      NOT NULL DEFAULT 'AE',
  state_code         VARCHAR(10)  NULL COMMENT 'Emirate (UAE) / State code (India GST)',
  tax_regime         ENUM('NONE','UAE_VAT','IN_GST') NOT NULL DEFAULT 'NONE',
  tax_number         VARCHAR(30)  NULL COMMENT 'TRN (UAE) / GSTIN (India)',
  base_currency      CHAR(3)      NOT NULL DEFAULT 'AED',
  fiscal_year_start  TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Month 1-12',
  timezone           VARCHAR(64)  NOT NULL DEFAULT 'Asia/Dubai',
  date_format        VARCHAR(20)  NOT NULL DEFAULT 'd-m-Y',
  email              VARCHAR(150) NULL,
  phone              VARCHAR(40)  NULL,
  address            TEXT         NULL,
  plan               VARCHAR(30)  NOT NULL DEFAULT 'starter',
  max_users          SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  status             ENUM('active','suspended') NOT NULL DEFAULT 'active',
  created_by         INT UNSIGNED NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_company_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username             VARCHAR(50)  NOT NULL,
  name                 VARCHAR(120) NOT NULL,
  email                VARCHAR(150) NOT NULL,
  phone                VARCHAR(40)  NULL,
  password_hash        VARCHAR(255) NOT NULL,
  is_super_admin       TINYINT(1)   NOT NULL DEFAULT 0,
  status               ENUM('active','locked','disabled') NOT NULL DEFAULT 'active',
  failed_attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_at            DATETIME NULL,
  must_change_password TINYINT(1)   NOT NULL DEFAULT 0,
  password_changed_at  DATETIME NULL,
  timezone             VARCHAR(64)  NULL,
  last_login_at        DATETIME NULL,
  last_login_ip        VARCHAR(45)  NULL,
  default_company_id   INT UNSIGNED NULL,
  created_by           INT UNSIGNED NULL,
  created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_username (username),
  KEY idx_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_companies (
  user_id     INT UNSIGNED NOT NULL,
  company_id  INT UNSIGNED NOT NULL,
  role        ENUM('admin','accountant','sales','purchase','viewer') NOT NULL DEFAULT 'viewer',
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, company_id),
  KEY idx_uc_company (company_id),
  CONSTRAINT fk_uc_user    FOREIGN KEY (user_id)    REFERENCES users(id)     ON DELETE CASCADE,
  CONSTRAINT fk_uc_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Security: login log, password resets, IP geo cache, audit trail
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_logs (
  id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id            INT UNSIGNED NULL,
  username_attempted VARCHAR(100) NOT NULL,
  event              ENUM('login','logout','failed','locked','reset_request','password_reset') NOT NULL,
  success            TINYINT(1) NOT NULL DEFAULT 0,
  reason             VARCHAR(120) NULL,
  ip_address         VARCHAR(45)  NOT NULL,
  country            VARCHAR(80)  NULL,
  region             VARCHAR(80)  NULL,
  city               VARCHAR(80)  NULL,
  isp                VARCHAR(150) NULL,
  browser            VARCHAR(60)  NULL,
  browser_version    VARCHAR(30)  NULL,
  os                 VARCHAR(60)  NULL,
  device_type        VARCHAR(20)  NULL,
  platform           VARCHAR(60)  NULL,
  screen             VARCHAR(20)  NULL,
  language           VARCHAR(20)  NULL,
  client_timezone    VARCHAR(64)  NULL,
  user_agent         VARCHAR(500) NULL,
  session_hash       CHAR(64)     NULL,
  created_at         DATETIME NOT NULL COMMENT 'UTC',
  KEY idx_ll_user (user_id, created_at),
  KEY idx_ll_ip (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ip_locations (
  ip_address  VARCHAR(45) PRIMARY KEY,
  country     VARCHAR(80)  NULL,
  region      VARCHAR(80)  NULL,
  city        VARCHAR(80)  NULL,
  isp         VARCHAR(150) NULL,
  fetched_at  DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  token_hash  CHAR(64) NOT NULL,
  expires_at  DATETIME NOT NULL,
  used_at     DATETIME NULL,
  ip_address  VARCHAR(45) NULL,
  created_at  DATETIME NOT NULL,
  UNIQUE KEY uq_token (token_hash),
  CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id  INT UNSIGNED NULL,
  user_id     INT UNSIGNED NULL,
  entity      VARCHAR(60) NOT NULL,
  entity_id   VARCHAR(40) NULL,
  action      VARCHAR(30) NOT NULL,
  changes     JSON NULL,
  ip_address  VARCHAR(45) NULL,
  created_at  DATETIME NOT NULL,
  KEY idx_audit_company (company_id, created_at),
  KEY idx_audit_entity (entity, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Company masters: currencies, exchange rates, taxes, number series
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS currencies (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id     INT UNSIGNED NOT NULL,
  code           CHAR(3) NOT NULL,
  name           VARCHAR(60) NOT NULL,
  symbol         VARCHAR(10) NOT NULL,
  decimal_places TINYINT UNSIGNED NOT NULL DEFAULT 2,
  exchange_rate  DECIMAL(18,8) NOT NULL DEFAULT 1 COMMENT '1 unit of this currency = X base currency',
  is_base        TINYINT(1) NOT NULL DEFAULT 0,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cur (company_id, code),
  CONSTRAINT fk_cur_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS exchange_rates (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id     INT UNSIGNED NOT NULL,
  currency_code  CHAR(3) NOT NULL,
  rate           DECIMAL(18,8) NOT NULL,
  effective_date DATE NOT NULL,
  source         VARCHAR(30) NOT NULL DEFAULT 'manual',
  created_by     INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rate (company_id, currency_code, effective_date),
  CONSTRAINT fk_er_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tax_rates (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id  INT UNSIGNED NOT NULL,
  name        VARCHAR(60) NOT NULL,
  code        VARCHAR(20) NOT NULL,
  tax_type    ENUM('VAT','GST','CESS','OTHER') NOT NULL DEFAULT 'VAT',
  category    ENUM('standard','zero','exempt','reverse_charge','out_of_scope') NOT NULL DEFAULT 'standard',
  rate        DECIMAL(7,3) NOT NULL DEFAULT 0,
  output_account_id INT UNSIGNED NULL,
  input_account_id  INT UNSIGNED NULL,
  is_default  TINYINT(1) NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tax (company_id, code),
  CONSTRAINT fk_tax_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS number_series (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id   INT UNSIGNED NOT NULL,
  doc_type     VARCHAR(30) NOT NULL,
  prefix       VARCHAR(20) NOT NULL,
  next_number  INT UNSIGNED NOT NULL DEFAULT 1,
  padding      TINYINT UNSIGNED NOT NULL DEFAULT 5,
  UNIQUE KEY uq_series (company_id, doc_type),
  CONSTRAINT fk_ns_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Accountant: chart of accounts, journals (single general ledger)
-- Every module (sales, purchases, banking, inventory) must post into
-- journals/journal_lines so that all reports come from one ledger.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS accounts (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id       INT UNSIGNED NOT NULL,
  parent_id        INT UNSIGNED NULL,
  code             VARCHAR(20)  NOT NULL,
  name             VARCHAR(150) NOT NULL,
  account_type     ENUM('asset','liability','equity','income','expense') NOT NULL,
  sub_type         VARCHAR(30)  NULL COMMENT 'bank, cash, receivable, payable, tax, stock, cogs ...',
  is_group         TINYINT(1) NOT NULL DEFAULT 0,
  currency_code    CHAR(3) NULL,
  opening_balance  DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_type     ENUM('Dr','Cr') NOT NULL DEFAULT 'Dr',
  description      VARCHAR(255) NULL,
  is_system        TINYINT(1) NOT NULL DEFAULT 0,
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  created_by       INT UNSIGNED NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_acc_code (company_id, code),
  KEY idx_acc_parent (company_id, parent_id),
  KEY idx_acc_type (company_id, account_type),
  CONSTRAINT fk_acc_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_acc_parent  FOREIGN KEY (parent_id)  REFERENCES accounts(id)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS journals (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id     INT UNSIGNED NOT NULL,
  number         VARCHAR(30) NOT NULL,
  journal_date   DATE NOT NULL,
  reference      VARCHAR(60) NULL,
  narration      VARCHAR(500) NULL,
  currency_code  CHAR(3) NOT NULL,
  exchange_rate  DECIMAL(18,8) NOT NULL DEFAULT 1,
  total_amount   DECIMAL(18,4) NOT NULL DEFAULT 0 COMMENT 'Transaction currency',
  total_base     DECIMAL(18,4) NOT NULL DEFAULT 0 COMMENT 'Base currency',
  status         ENUM('draft','posted','void') NOT NULL DEFAULT 'draft',
  source_type    VARCHAR(30) NOT NULL DEFAULT 'manual' COMMENT 'manual, invoice, bill, payment ...',
  source_id      INT UNSIGNED NULL,
  created_by     INT UNSIGNED NULL,
  posted_by      INT UNSIGNED NULL,
  posted_at      DATETIME NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_journal_no (company_id, number),
  KEY idx_journal_date (company_id, journal_date),
  KEY idx_journal_source (company_id, source_type, source_id),
  CONSTRAINT fk_j_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS journal_lines (
  id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  journal_id   INT UNSIGNED NOT NULL,
  company_id   INT UNSIGNED NOT NULL,
  line_no      SMALLINT UNSIGNED NOT NULL,
  account_id   INT UNSIGNED NOT NULL,
  contact_id   INT UNSIGNED NULL,
  description  VARCHAR(255) NULL,
  debit        DECIMAL(18,4) NOT NULL DEFAULT 0,
  credit       DECIMAL(18,4) NOT NULL DEFAULT 0,
  debit_base   DECIMAL(18,4) NOT NULL DEFAULT 0,
  credit_base  DECIMAL(18,4) NOT NULL DEFAULT 0,
  KEY idx_jl_journal (journal_id),
  KEY idx_jl_account (company_id, account_id),
  CONSTRAINT fk_jl_journal FOREIGN KEY (journal_id) REFERENCES journals(id) ON DELETE CASCADE,
  CONSTRAINT fk_jl_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Inventory masters
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS units (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id  INT UNSIGNED NOT NULL,
  code        VARCHAR(10) NOT NULL,
  name        VARCHAR(50) NOT NULL,
  uqc         VARCHAR(10) NULL COMMENT 'GST Unit Quantity Code',
  decimals    TINYINT UNSIGNED NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_unit (company_id, code),
  CONSTRAINT fk_unit_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS item_categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id  INT UNSIGNED NOT NULL,
  parent_id   INT UNSIGNED NULL,
  name        VARCHAR(100) NOT NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cat (company_id, name),
  CONSTRAINT fk_cat_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS warehouses (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id  INT UNSIGNED NOT NULL,
  code        VARCHAR(20) NOT NULL,
  name        VARCHAR(100) NOT NULL,
  address     VARCHAR(255) NULL,
  is_default  TINYINT(1) NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_wh (company_id, code),
  CONSTRAINT fk_wh_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS items (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id          INT UNSIGNED NOT NULL,
  sku                 VARCHAR(40)  NOT NULL,
  name                VARCHAR(150) NOT NULL,
  item_type           ENUM('goods','service') NOT NULL DEFAULT 'goods',
  category_id         INT UNSIGNED NULL,
  unit_id             INT UNSIGNED NULL,
  hsn_sac             VARCHAR(12)  NULL COMMENT 'HSN (goods) / SAC (services) for GST',
  barcode             VARCHAR(60)  NULL,
  tax_rate_id         INT UNSIGNED NULL,
  sales_price         DECIMAL(18,4) NOT NULL DEFAULT 0,
  purchase_price      DECIMAL(18,4) NOT NULL DEFAULT 0,
  sales_account_id    INT UNSIGNED NULL,
  purchase_account_id INT UNSIGNED NULL,
  inventory_account_id INT UNSIGNED NULL,
  track_inventory     TINYINT(1) NOT NULL DEFAULT 1,
  reorder_level       DECIMAL(18,4) NOT NULL DEFAULT 0,
  opening_stock       DECIMAL(18,4) NOT NULL DEFAULT 0,
  description         VARCHAR(500) NULL,
  is_active           TINYINT(1) NOT NULL DEFAULT 1,
  created_by          INT UNSIGNED NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sku (company_id, sku),
  KEY idx_item_name (company_id, name),
  CONSTRAINT fk_item_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Contacts (customers & vendors share one table)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contacts (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id       INT UNSIGNED NOT NULL,
  contact_type     ENUM('customer','vendor') NOT NULL,
  code             VARCHAR(20)  NOT NULL,
  name             VARCHAR(150) NOT NULL,
  contact_person   VARCHAR(100) NULL,
  email            VARCHAR(150) NULL,
  phone            VARCHAR(40)  NULL,
  tax_treatment    VARCHAR(30)  NULL,
  tax_number       VARCHAR(30)  NULL COMMENT 'TRN / GSTIN',
  place_of_supply  VARCHAR(10)  NULL COMMENT 'Emirate / State code',
  currency_code    CHAR(3) NULL,
  payment_terms    SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Days',
  credit_limit     DECIMAL(18,4) NOT NULL DEFAULT 0,
  billing_address  TEXT NULL,
  shipping_address TEXT NULL,
  account_id       INT UNSIGNED NULL COMMENT 'Receivable/Payable control account',
  opening_balance  DECIMAL(18,4) NOT NULL DEFAULT 0,
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  created_by       INT UNSIGNED NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_contact_code (company_id, contact_type, code),
  KEY idx_contact_name (company_id, contact_type, name),
  CONSTRAINT fk_contact_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Banking
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bank_accounts (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id     INT UNSIGNED NOT NULL,
  account_id     INT UNSIGNED NOT NULL COMMENT 'Linked ledger in chart of accounts',
  bank_name      VARCHAR(100) NOT NULL,
  account_name   VARCHAR(100) NOT NULL,
  account_number VARCHAR(40)  NOT NULL,
  iban           VARCHAR(40)  NULL,
  swift_ifsc     VARCHAR(20)  NULL,
  branch         VARCHAR(100) NULL,
  currency_code  CHAR(3) NOT NULL,
  account_kind   ENUM('bank','cash','card','wallet') NOT NULL DEFAULT 'bank',
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_bank_company (company_id),
  CONSTRAINT fk_bank_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  CONSTRAINT fk_bank_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
