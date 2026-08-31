-- ============================================================
--  Migration: Monthly Clients module
--
--  Adds the four tables behind recurring/monthly retainer clients:
--    monthly_clients       — one row per recurring client: their contract,
--                            what they pay every cycle, and where their
--                            billing has reached (next_billing_date).
--    monthly_invoices      — one invoice per billing period, kept forever.
--    monthly_payments      — every payment (full or partial) taken against
--                            an invoice, each with its own receipt number.
--    monthly_client_pauses — the pause/resume history of a client.
--
--  Nothing that can be worked out is stored twice: an invoice's amount paid,
--  its balance, and whether it is paid / partially paid / overdue are all
--  derived from monthly_payments + due_date at read time (see the models),
--  so a row can never drift out of date. Only the lifecycle a human chooses
--  (draft / sent / cancelled, active / paused / cancelled) is stored.
--
--  Run this ONCE on an existing install. Fresh installs already get these
--  tables from schema.sql, so they can skip this file.
--
--  hPanel / cPanel -> phpMyAdmin -> select your DB -> Import -> this file.
-- ============================================================

CREATE TABLE IF NOT EXISTS monthly_clients (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- Who is billed.
  client_name         VARCHAR(150) NOT NULL,
  company             VARCHAR(150) NULL,
  email               VARCHAR(190) NULL,
  mobile              VARCHAR(20)  NULL,
  billing_address     TEXT         NULL,

  -- What they are billed for.
  service_name        VARCHAR(190) NOT NULL,
  service_description TEXT         NULL,

  -- What one month costs, and how often it is actually invoiced. An invoice
  -- covers monthly_amount x (months in the billing cycle), so a 5,000/month
  -- client on a quarterly cycle is invoiced 15,000 every three months.
  monthly_amount      DECIMAL(12,2) NOT NULL,
  billing_frequency   ENUM('monthly','quarterly','half_yearly','yearly') NOT NULL DEFAULT 'monthly',

  -- Standing discount and tax applied to each new invoice. Both are only
  -- defaults — either can be overridden when the invoice is generated.
  discount_type       ENUM('none','percent','amount') NOT NULL DEFAULT 'none',
  discount_value      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  tax_percent         DECIMAL(5,2)  NOT NULL DEFAULT 0.00,

  -- How and when they pay. payment_terms decides an invoice's due date:
  -- invoice_date + the term's days (see MonthlyClient::TERM_DAYS).
  payment_method      ENUM('upi','bank_transfer','cash','card','other') NOT NULL DEFAULT 'upi',
  payment_terms       ENUM('due_on_receipt','net_7','net_15','net_30','net_45','net_60') NOT NULL DEFAULT 'net_7',

  -- Contract.
  start_date          DATE NOT NULL,
  contract_end_date   DATE NULL,
  renewal_date        DATE NULL,
  contract_notes      TEXT NULL,

  -- Where billing has reached: the first day of the period the NEXT invoice
  -- will cover. Moves forward one cycle each time an invoice is generated.
  next_billing_date   DATE NOT NULL,

  -- Lifecycle the admin controls. "Payment Due" and "Overdue" are never
  -- stored — they are derived from the client's unpaid invoices.
  status              ENUM('active','paused','cancelled') NOT NULL DEFAULT 'active',

  -- Current pause (all null once resumed; the full history lives in
  -- monthly_client_pauses).
  paused_on           DATE NULL,
  pause_reason        VARCHAR(255) NULL,
  resume_date         DATE NULL,

  -- Cancellation. Kept forever — cancelling stops future billing but never
  -- removes invoices, payments or receipts.
  cancelled_on        DATE NULL,
  cancellation_reason VARCHAR(255) NULL,
  cancellation_notes  TEXT NULL,

  notes               TEXT NULL,

  created_by          INT UNSIGNED NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_monthly_clients_status (status),
  KEY idx_monthly_clients_next   (next_billing_date),
  KEY idx_monthly_clients_name   (client_name),
  CONSTRAINT fk_monthly_clients_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One invoice per billing cycle. The service text and every amount are frozen
-- at generation time, so the invoice stays a correct historical document even
-- if the client's rate, discount or tax changes later.
--
-- uq_monthly_invoice_period is what makes a duplicate invoice for the same
-- client and the same billing period impossible, even if the form is
-- double-submitted.
CREATE TABLE IF NOT EXISTS monthly_invoices (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  monthly_client_id   INT UNSIGNED NOT NULL,
  invoice_number      VARCHAR(50)  NOT NULL,

  invoice_date        DATE NOT NULL,
  due_date            DATE NOT NULL,
  period_start        DATE NOT NULL,
  period_end          DATE NOT NULL,

  service_name        VARCHAR(190) NOT NULL,
  service_description TEXT NULL,

  recurring_amount    DECIMAL(12,2) NOT NULL,   -- before discount and tax
  discount_amount     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  tax_percent         DECIMAL(5,2)  NOT NULL DEFAULT 0.00,
  tax_amount          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_amount        DECIMAL(12,2) NOT NULL,   -- recurring - discount + tax

  -- Only the part a human chooses is stored. paid / partially_paid / overdue
  -- are derived from the payments and the due date every time it is read.
  status              ENUM('draft','sent','cancelled') NOT NULL DEFAULT 'sent',

  notes               TEXT NULL,
  created_by          INT UNSIGNED NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_monthly_invoice_number (invoice_number),
  UNIQUE KEY uq_monthly_invoice_period (monthly_client_id, period_start),
  KEY idx_monthly_invoices_client (monthly_client_id),
  KEY idx_monthly_invoices_due    (due_date),
  KEY idx_monthly_invoices_status (status),
  CONSTRAINT fk_monthly_invoices_client     FOREIGN KEY (monthly_client_id) REFERENCES monthly_clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_monthly_invoices_created_by FOREIGN KEY (created_by)        REFERENCES users(id)           ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every payment taken against an invoice. A partial payment is just a smaller
-- amount — several rows can point at one invoice, and the invoice's balance is
-- the sum of them subtracted from its total.
--
-- balance_after is the only derived figure kept on the row: it freezes the
-- remaining balance as it stood when the receipt was handed over, so a
-- reprinted receipt always shows what the client was originally told.
CREATE TABLE IF NOT EXISTS monthly_payments (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id        INT UNSIGNED NOT NULL,
  monthly_client_id INT UNSIGNED NOT NULL,
  receipt_number    VARCHAR(50)  NOT NULL,

  payment_date      DATE NOT NULL,
  amount            DECIMAL(12,2) NOT NULL,
  method            ENUM('upi','bank_transfer','cash','card','other') NOT NULL DEFAULT 'upi',
  reference         VARCHAR(120) NULL,          -- UPI ref / txn id / cheque no
  notes             TEXT NULL,
  balance_after     DECIMAL(12,2) NOT NULL,     -- invoice balance right after this payment

  created_by        INT UNSIGNED NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  UNIQUE KEY uq_monthly_payment_receipt (receipt_number),
  KEY idx_monthly_payments_invoice (invoice_id),
  KEY idx_monthly_payments_client  (monthly_client_id),
  KEY idx_monthly_payments_date    (payment_date),
  CONSTRAINT fk_monthly_payments_invoice    FOREIGN KEY (invoice_id)        REFERENCES monthly_invoices(id) ON DELETE CASCADE,
  CONSTRAINT fk_monthly_payments_client     FOREIGN KEY (monthly_client_id) REFERENCES monthly_clients(id)  ON DELETE CASCADE,
  CONSTRAINT fk_monthly_payments_created_by FOREIGN KEY (created_by)        REFERENCES users(id)            ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every pause a client has been through, kept as history. The row stays open
-- (resumed_on NULL) while the client is paused, and is closed on resume.
CREATE TABLE IF NOT EXISTS monthly_client_pauses (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  monthly_client_id INT UNSIGNED NOT NULL,
  paused_on         DATE NOT NULL,
  reason            VARCHAR(255) NULL,
  resume_date       DATE NULL,                  -- when it was meant to resume
  resumed_on        DATE NULL,                  -- when it actually did
  notes             TEXT NULL,
  created_by        INT UNSIGNED NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_monthly_pauses_client (monthly_client_id),
  CONSTRAINT fk_monthly_pauses_client     FOREIGN KEY (monthly_client_id) REFERENCES monthly_clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_monthly_pauses_created_by FOREIGN KEY (created_by)        REFERENCES users(id)           ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
