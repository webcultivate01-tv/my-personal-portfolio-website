-- ============================================================
--  Migration: Hosting & Domain Management module
--
--  Adds the two tables behind the Hosting module:
--    hosting_services  — one row per hosting plan OR domain the studio
--                        manages for a client, with its renewal date.
--    hosting_renewals  — the renewal history of each of those services.
--
--  One table covers both hosting and domains (service_type tells them
--  apart) because they behave identically: both are bought from a
--  provider, both expire, and both need the same renewal reminders.
--
--  Run this ONCE on an existing install. Fresh installs already get
--  these tables from schema.sql, so they can skip this file.
--
--  hPanel / cPanel -> phpMyAdmin -> select your DB -> Import -> this file.
-- ============================================================

CREATE TABLE IF NOT EXISTS hosting_services (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,

  -- What this record is. Domains renew exactly like hosting does, so they
  -- share the module. Only the label and the badge colour differ.
  service_type        ENUM('hosting','domain') NOT NULL DEFAULT 'hosting',

  -- Client. client_id links to the Client Management record when there is
  -- one. client_name is always filled so the row still reads correctly if
  -- the client record is later deleted (the FK goes NULL, the name stays).
  client_id           INT UNSIGNED NULL,
  client_name         VARCHAR(150) NOT NULL,
  company             VARCHAR(150) NULL,

  -- Website / project this service belongs to.
  project_id          INT UNSIGNED NULL,
  website_name        VARCHAR(150) NULL,
  website_url         VARCHAR(255) NULL,
  domain              VARCHAR(190) NULL,

  -- Hosting / domain details.
  provider            VARCHAR(120) NULL,          -- Hostinger, GoDaddy, Cloudflare...
  plan                VARCHAR(120) NULL,          -- Premium Shared, Business, .com 1yr...
  account_ref         VARCHAR(120) NULL,          -- hosting account / service ID (never a password)

  purchase_date       DATE          NULL,
  renewal_date        DATE          NOT NULL,     -- current expiry, drives every reminder
  last_renewed_at     DATE          NULL,         -- date of the most recent renewal, for "Renewed this month"

  cost                DECIMAL(12,2) NULL,         -- what it cost to buy
  renewal_cost        DECIMAL(12,2) NULL,         -- what the next renewal will cost

  billing_cycle       ENUM('monthly','quarterly','half_yearly','yearly','custom') NOT NULL DEFAULT 'yearly',
  custom_cycle_months SMALLINT UNSIGNED NULL,     -- only used when billing_cycle = 'custom'

  -- Access + notes.
  --   login_url        : the provider's control-panel URL (public, safe to store)
  --   credential_ref   : WHERE the login is kept (e.g. "Bitwarden > Hostinger > abc.com").
  --                      Passwords are deliberately NOT stored by this module —
  --                      keep them in a password manager and point at it here.
  login_url           VARCHAR(255) NULL,
  credential_ref      VARCHAR(190) NULL,
  notes               TEXT NULL,                  -- shown on the record
  internal_notes      TEXT NULL,                  -- team-only remarks

  created_by          INT UNSIGNED NULL,
  created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (id),
  KEY idx_hosting_renewal  (renewal_date),
  KEY idx_hosting_client   (client_id),
  KEY idx_hosting_project  (project_id),
  KEY idx_hosting_type     (service_type),
  KEY idx_hosting_provider (provider),
  CONSTRAINT fk_hosting_client     FOREIGN KEY (client_id)  REFERENCES clients(id)  ON DELETE SET NULL,
  CONSTRAINT fk_hosting_project    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
  CONSTRAINT fk_hosting_created_by FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every renewal ever made against a service: what was paid, when, and what
-- the expiry moved from/to. Kept forever so a client can be shown their
-- full hosting history ("purchased 2025, renewed 2026, next due 2027").
CREATE TABLE IF NOT EXISTS hosting_renewals (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  hosting_id        INT UNSIGNED NOT NULL,
  renewal_date      DATE NOT NULL,                -- the day it was renewed
  previous_expiry   DATE NULL,                    -- expiry before this renewal
  new_expiry        DATE NOT NULL,                -- expiry after this renewal
  amount            DECIMAL(12,2) NULL,
  payment_status    ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'paid',
  payment_reference VARCHAR(120) NULL,            -- UPI ref / invoice no / txn id
  notes             TEXT NULL,
  created_by        INT UNSIGNED NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_hosting_renewals_service (hosting_id),
  KEY idx_hosting_renewals_date    (renewal_date),
  CONSTRAINT fk_hosting_renewals_service    FOREIGN KEY (hosting_id) REFERENCES hosting_services(id) ON DELETE CASCADE,
  CONSTRAINT fk_hosting_renewals_created_by FOREIGN KEY (created_by) REFERENCES users(id)            ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
