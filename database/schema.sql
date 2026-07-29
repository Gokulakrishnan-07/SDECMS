-- ============================================================================
-- Swami Dayananda Educational Cost Management System (SECMS)
-- MySQL 8.0+ schema — normalized, foreign keys, indexes, audit support.
-- Import:  mysql -u root < database/schema.sql   (then seed.sql)
-- ============================================================================

CREATE DATABASE IF NOT EXISTS secms
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE secms;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS po_status_history;
DROP TABLE IF EXISTS po_items;
DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS purchase_orders;
DROP TABLE IF EXISTS purchase_requests;
DROP TABLE IF EXISTS sanction_counters;
DROP TABLE IF EXISTS sanctions;
DROP TABLE IF EXISTS budget_periods;
DROP TABLE IF EXISTS budgets;
DROP TABLE IF EXISTS department_units;
DROP TABLE IF EXISTS financial_year_periods;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS login_history;
DROP TABLE IF EXISTS system_settings;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS departments;
DROP TABLE IF EXISTS financial_years;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- Financial years (Indian FY: April – March)
-- ----------------------------------------------------------------------------
CREATE TABLE financial_years (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    label       VARCHAR(20)  NOT NULL UNIQUE,          -- e.g. '2025-26'
    year_code   SMALLINT UNSIGNED NOT NULL,            -- used in sanction numbers, e.g. 2026
    start_date  DATE NOT NULL,
    end_date    DATE NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fy_active (is_active)
) ENGINE=InnoDB;

-- Financial year periods — optional sub-divisions of a year (Quarter 1..4,
-- Half Year 1..2, or custom names) used for per-period budget allocation.
CREATE TABLE financial_year_periods (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    financial_year_id INT UNSIGNED NOT NULL,
    name              VARCHAR(50) NOT NULL,
    sort_order        INT NOT NULL DEFAULT 0,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_fyp_fy FOREIGN KEY (financial_year_id) REFERENCES financial_years(id) ON DELETE CASCADE,
    INDEX idx_fyp_fy (financial_year_id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Departments
-- workflow_type:
--   'full'   = School/College procurement — Sanction commits budget, then
--              Requisition draws sanction balance, then PO payment books expense.
--   'simple' = service departments — approved Requisition books the expense.
-- has_units  = requisitions must pick a sub-unit (Transport, Civil Works).
-- ----------------------------------------------------------------------------
CREATE TABLE departments (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL UNIQUE,
    code          VARCHAR(10)  NOT NULL UNIQUE,          -- sanction prefix: COL, SCH, FAR …
    icon          VARCHAR(50)  NOT NULL DEFAULT 'fa-building',
    workflow_type ENUM('full','simple') NOT NULL DEFAULT 'simple',
    has_units     TINYINT(1)   NOT NULL DEFAULT 0,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Department sub-units (e.g. Transport → School/College/Trust Transport;
-- Civil Works → Study Centre / Auditorium / Learning Centre / Conference Hall).
-- Budget & sanctions stay at department level; units tag requisitions/reports.
CREATE TABLE department_units (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id INT UNSIGNED NOT NULL,
    unit_code     VARCHAR(20)  NOT NULL UNIQUE,
    unit_name     VARCHAR(100) NOT NULL,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_unit_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    INDEX idx_unit_dept (department_id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Users
-- ----------------------------------------------------------------------------
CREATE TABLE users (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    email          VARCHAR(150) NOT NULL UNIQUE,
    password       VARCHAR(255) NOT NULL,
    role           ENUM('administrator','principal','accounts','department_head','viewer') NOT NULL DEFAULT 'viewer',
    department_id  INT UNSIGNED NULL,
    phone          VARCHAR(20)  NULL,
    theme          ENUM('light','dark','auto') NOT NULL DEFAULT 'auto',
    notify_email   TINYINT(1) NOT NULL DEFAULT 1,
    notify_inapp   TINYINT(1) NOT NULL DEFAULT 1,
    is_active      TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at  DATETIME NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_department FOREIGN KEY (department_id)
        REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_users_role (role),
    INDEX idx_users_active (is_active)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Budgets — one row per department per financial year
-- ----------------------------------------------------------------------------
CREATE TABLE budgets (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id     INT UNSIGNED NOT NULL,
    financial_year_id INT UNSIGNED NOT NULL,
    allocated_amount  DECIMAL(15,2) NOT NULL DEFAULT 0,
    committed_amount  DECIMAL(15,2) NOT NULL DEFAULT 0,   -- reserved by approved sanctions (full workflow), not yet spent
    used_amount       DECIMAL(15,2) NOT NULL DEFAULT 0,   -- actual expense booked (available = allocated - committed - used)
    status            ENUM('active','inactive') NOT NULL DEFAULT 'active',
    approval_status   ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    remarks           VARCHAR(500) NULL,
    created_by        INT UNSIGNED NULL,
    approved_by       INT UNSIGNED NULL,
    approved_at       DATETIME NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_budget UNIQUE (department_id, financial_year_id),
    CONSTRAINT fk_budgets_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    CONSTRAINT fk_budgets_fy         FOREIGN KEY (financial_year_id) REFERENCES financial_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_budgets_creator    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_budgets_approver   FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_budgets_fy (financial_year_id),
    INDEX idx_budgets_status (approval_status)
) ENGINE=InnoDB;

-- Optional per-period allocation breakdown of a budget (sums into the budget).
CREATE TABLE budget_periods (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    budget_id        INT UNSIGNED NOT NULL,
    period_id        INT UNSIGNED NOT NULL,
    allocated_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_bp_budget FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE,
    CONSTRAINT fk_bp_period FOREIGN KEY (period_id) REFERENCES financial_year_periods(id) ON DELETE CASCADE,
    CONSTRAINT uq_budget_period UNIQUE (budget_id, period_id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Sanctions — auto numbered  <DEPT>-<YEAR>-<RUNNING>
-- ----------------------------------------------------------------------------
CREATE TABLE sanctions (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sanction_no       VARCHAR(30) NOT NULL UNIQUE,        -- e.g. COL-2026-001 (read-only)
    department_id     INT UNSIGNED NOT NULL,
    financial_year_id INT UNSIGNED NOT NULL,
    amount            DECIMAL(15,2) NOT NULL,
    requisitioned_amount DECIMAL(15,2) NOT NULL DEFAULT 0, -- sum of approved requisitions drawn from this sanction
    last_subdivision  SMALLINT UNSIGNED NOT NULL DEFAULT 0, -- 0=none, 1=A … 26=Z, 27=a … 52=z
    purpose           VARCHAR(255) NOT NULL,
    remarks           VARCHAR(500) NULL,
    status            ENUM('pending','verified','approved','rejected') NOT NULL DEFAULT 'pending',
    created_by        INT UNSIGNED NULL,
    verified_by       INT UNSIGNED NULL,
    verified_at       DATETIME NULL,
    approved_by       INT UNSIGNED NULL,
    approved_at       DATETIME NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sanctions_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    CONSTRAINT fk_sanctions_fy         FOREIGN KEY (financial_year_id) REFERENCES financial_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_sanctions_creator    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_sanctions_verifier   FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_sanctions_approver   FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_sanctions_no (sanction_no),
    INDEX idx_sanctions_status (status),
    INDEX idx_sanctions_dept_fy (department_id, financial_year_id)
) ENGINE=InnoDB;

-- Running-number counters per department per financial year (locked in
-- transactions to guarantee unique, gap-free sanction numbers).
CREATE TABLE sanction_counters (
    department_id     INT UNSIGNED NOT NULL,
    financial_year_id INT UNSIGNED NOT NULL,
    last_number       INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (department_id, financial_year_id),
    CONSTRAINT fk_counter_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    CONSTRAINT fk_counter_fy         FOREIGN KEY (financial_year_id) REFERENCES financial_years(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Purchase Requests
-- ----------------------------------------------------------------------------
CREATE TABLE purchase_requests (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pr_no             VARCHAR(30) NOT NULL UNIQUE,        -- subdivision of parent sanction, e.g. COL-2026-001-A
    sanction_id       INT UNSIGNED NULL,                 -- parent approved sanction
    subdivision_code  VARCHAR(2) NULL,                   -- A..Z then a..z within the parent sanction
    unit_id           INT UNSIGNED NULL,                 -- department sub-unit (Transport / Civil Works)
    department_id     INT UNSIGNED NOT NULL,
    financial_year_id INT UNSIGNED NOT NULL,
    title             VARCHAR(200) NOT NULL,
    description       TEXT NULL,
    amount            DECIMAL(15,2) NOT NULL,
    status            ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
    attachment_path   VARCHAR(255) NULL,
    attachment_name   VARCHAR(255) NULL,
    remarks           VARCHAR(500) NULL,
    reject_reason     VARCHAR(500) NULL,
    created_by        INT UNSIGNED NULL,
    approved_by       INT UNSIGNED NULL,
    approved_at       DATETIME NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pr_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    CONSTRAINT fk_pr_fy         FOREIGN KEY (financial_year_id) REFERENCES financial_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_pr_sanction   FOREIGN KEY (sanction_id) REFERENCES sanctions(id) ON DELETE SET NULL,
    CONSTRAINT fk_pr_unit       FOREIGN KEY (unit_id) REFERENCES department_units(id) ON DELETE SET NULL,
    CONSTRAINT fk_pr_creator    FOREIGN KEY (created_by)  REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_pr_approver   FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_pr_status (status),
    INDEX idx_pr_sanction (sanction_id),
    INDEX idx_pr_dept_fy (department_id, financial_year_id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Purchase Orders
-- ----------------------------------------------------------------------------
CREATE TABLE purchase_orders (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_no               VARCHAR(30) NOT NULL UNIQUE,      -- e.g. PO-2026-001
    purchase_request_id INT UNSIGNED NULL,
    department_id       INT UNSIGNED NOT NULL,
    financial_year_id   INT UNSIGNED NOT NULL,
    vendor_name         VARCHAR(200) NOT NULL,
    vendor_gstin        VARCHAR(20)  NULL,
    vendor_address      VARCHAR(500) NULL,
    subtotal            DECIMAL(15,2) NOT NULL DEFAULT 0,
    gst_percent         DECIMAL(5,2)  NOT NULL DEFAULT 0,
    gst_amount          DECIMAL(15,2) NOT NULL DEFAULT 0,
    total_amount        DECIMAL(15,2) NOT NULL DEFAULT 0,
    invoice_no          VARCHAR(50) NULL,
    status              ENUM('draft','issued','received','paid','cancelled') NOT NULL DEFAULT 'draft',
    remarks             VARCHAR(500) NULL,
    created_by          INT UNSIGNED NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_po_pr         FOREIGN KEY (purchase_request_id) REFERENCES purchase_requests(id) ON DELETE SET NULL,
    CONSTRAINT fk_po_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    CONSTRAINT fk_po_fy         FOREIGN KEY (financial_year_id) REFERENCES financial_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_po_creator    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_po_status (status),
    INDEX idx_po_dept_fy (department_id, financial_year_id)
) ENGINE=InnoDB;

CREATE TABLE po_items (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id INT UNSIGNED NOT NULL,
    item_name         VARCHAR(200) NOT NULL,
    description       VARCHAR(500) NULL,
    quantity          DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price        DECIMAL(15,2) NOT NULL DEFAULT 0,
    amount            DECIMAL(15,2) NOT NULL DEFAULT 0,
    CONSTRAINT fk_poi_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE po_status_history (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id INT UNSIGNED NOT NULL,
    status            VARCHAR(20) NOT NULL,
    remarks           VARCHAR(500) NULL,
    changed_by        INT UNSIGNED NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_posh_po   FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_posh_user FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Expenses (actual spending; drives budget utilisation and charts)
-- ----------------------------------------------------------------------------
CREATE TABLE expenses (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    department_id     INT UNSIGNED NOT NULL,
    financial_year_id INT UNSIGNED NOT NULL,
    purchase_order_id INT UNSIGNED NULL,
    category          VARCHAR(100) NOT NULL DEFAULT 'General',
    description       VARCHAR(500) NULL,
    amount            DECIMAL(15,2) NOT NULL,
    expense_date      DATE NOT NULL,
    created_by        INT UNSIGNED NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_exp_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    CONSTRAINT fk_exp_fy         FOREIGN KEY (financial_year_id) REFERENCES financial_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_exp_po         FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE SET NULL,
    CONSTRAINT fk_exp_creator    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_exp_date (expense_date),
    INDEX idx_exp_dept_fy (department_id, financial_year_id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Notifications (one row per recipient)
-- ----------------------------------------------------------------------------
CREATE TABLE notifications (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    type       VARCHAR(50) NOT NULL,                     -- budget_alert, pr_new, pr_approved …
    title      VARCHAR(150) NOT NULL,
    message    VARCHAR(500) NOT NULL,
    link       VARCHAR(255) NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notif_user_read (user_id, is_read),
    INDEX idx_notif_created (created_at)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Audit logs & login history
-- ----------------------------------------------------------------------------
CREATE TABLE audit_logs (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    action      VARCHAR(50) NOT NULL,                    -- create, update, delete, approve, login …
    table_name  VARCHAR(50) NOT NULL,
    record_id   INT UNSIGNED NULL,
    description VARCHAR(500) NULL,
    ip_address  VARCHAR(45) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_table (table_name, record_id),
    INDEX idx_audit_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE login_history (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NOT NULL,
    ip_address   VARCHAR(45) NULL,
    user_agent   VARCHAR(255) NULL,
    logged_in_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lh_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_lh_user (user_id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- System settings (key/value)
-- ----------------------------------------------------------------------------
CREATE TABLE system_settings (
    `key`      VARCHAR(100) PRIMARY KEY,
    `value`    VARCHAR(500) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
