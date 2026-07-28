-- ============================================================
--  Migration: Client Management module
--  Adds the clients, client_meetings, client_invoices and
--  client_payments tables used by the admin-only Client Management
--  module (managers cannot see or reach this module at all).
--
--  Run this ONCE on an existing install. Fresh installs already get
--  these tables from schema.sql, so they can skip this file.
--
--  cPanel → phpMyAdmin → select your DB → Import → this file.
-- ============================================================

CREATE TABLE IF NOT EXISTS clients (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(150) NOT NULL,
  company      VARCHAR(150) NULL,
  email        VARCHAR(190) NULL,
  phone        VARCHAR(20)  NULL,
  address      TEXT         NULL,
  services     TEXT         NULL,
  project_cost DECIMAL(12,2) NULL,
  notes        TEXT         NULL,
  created_by   INT UNSIGNED NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_clients_name (name),
  CONSTRAINT fk_clients_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS client_meetings (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id         INT UNSIGNED NOT NULL,
  meeting_at        DATETIME     NOT NULL,
  duration_minutes  SMALLINT UNSIGNED NULL,
  notes             TEXT         NULL,
  created_by        INT UNSIGNED NULL,
  created_at        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_client_meetings_client (client_id),
  KEY idx_client_meetings_at     (meeting_at),
  CONSTRAINT fk_client_meetings_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_client_meetings_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS client_invoices (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id      INT UNSIGNED NOT NULL,
  invoice_number VARCHAR(50)  NOT NULL,
  amount         DECIMAL(12,2) NOT NULL,
  issue_date     DATE         NOT NULL,
  due_date       DATE         NULL,
  status         ENUM('unpaid','paid','overdue','cancelled') NOT NULL DEFAULT 'unpaid',
  notes          TEXT         NULL,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_client_invoices_client (client_id),
  KEY idx_client_invoices_status (status),
  CONSTRAINT fk_client_invoices_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS client_payments (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id    INT UNSIGNED NOT NULL,
  invoice_id   INT UNSIGNED NULL,
  amount       DECIMAL(12,2) NOT NULL,
  payment_date DATE         NOT NULL,
  method       ENUM('cash','bank_transfer','upi','card','cheque','other') NOT NULL DEFAULT 'other',
  notes        TEXT         NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_client_payments_client  (client_id),
  KEY idx_client_payments_invoice (invoice_id),
  CONSTRAINT fk_client_payments_client  FOREIGN KEY (client_id)  REFERENCES clients(id)         ON DELETE CASCADE,
  CONSTRAINT fk_client_payments_invoice FOREIGN KEY (invoice_id) REFERENCES client_invoices(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
