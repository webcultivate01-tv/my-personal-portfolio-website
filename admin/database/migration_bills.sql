-- ============================================================
--  Migration: Bills (Billing module)
--  Adds the `bills` table used by the Billing module — a proper,
--  printable receipt generated whenever a payment is taken from a
--  client against a project: itemised services, project cost,
--  amount paid, and the running balance due, frozen at the moment
--  the bill was raised.
--
--  Run this ONCE, and ONLY if your database was created before this
--  table existed. Fresh installs (schema.sql) already have it.
--
--  cPanel -> phpMyAdmin -> select your DB -> Import -> this file.
-- ============================================================

CREATE TABLE IF NOT EXISTS bills (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  bill_number    VARCHAR(50)   NOT NULL,
  client_id      INT UNSIGNED  NOT NULL,
  project_id     INT UNSIGNED  NULL,
  bill_date      DATE          NOT NULL,
  items_json     TEXT          NOT NULL,
  project_cost   DECIMAL(12,2) NULL,
  total_paid     DECIMAL(12,2) NOT NULL,
  amount_paid    DECIMAL(12,2) NOT NULL,
  balance_due    DECIMAL(12,2) NULL,
  payment_method ENUM('cash','bank_transfer','upi','card','cheque','other') NOT NULL DEFAULT 'other',
  notes          TEXT          NULL,
  payment_id     INT UNSIGNED  NULL,
  created_by     INT UNSIGNED  NULL,
  created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bills_number (bill_number),
  KEY idx_bills_client  (client_id),
  KEY idx_bills_project (project_id),
  KEY idx_bills_date    (bill_date),
  CONSTRAINT fk_bills_client     FOREIGN KEY (client_id)  REFERENCES clients(id)         ON DELETE CASCADE,
  CONSTRAINT fk_bills_project    FOREIGN KEY (project_id) REFERENCES projects(id)        ON DELETE SET NULL,
  CONSTRAINT fk_bills_payment    FOREIGN KEY (payment_id) REFERENCES client_payments(id) ON DELETE SET NULL,
  CONSTRAINT fk_bills_created_by FOREIGN KEY (created_by) REFERENCES users(id)           ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
