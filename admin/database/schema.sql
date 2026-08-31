-- ============================================================
--  Admin panel database schema
--  Import this in cPanel > phpMyAdmin (select your DB first).
-- ============================================================

-- Users who can log in.
--   role = 'admin'   : full access (manage users, reset passwords, etc.)
--   role = 'manager' : day-to-day access, but CANNOT change their own password
--                      and cannot manage other users (only an admin can add them).
-- Onboarding fields (phone .. pan_number, plus documents) capture everything a
-- company takes when hiring an employee. Once set they are "locked": a manager
-- can no longer edit them (only an admin can, e.g. to fix a typo) — enforced in
-- App\Models\User::LOCKED_ONCE_SET and the account/admin controllers.
-- profile_photo / aadhar_front / aadhar_back / pan_card_image must all be
-- uploaded by a manager on first sign-in before they can use the panel, and are
-- then locked the same way.
CREATE TABLE IF NOT EXISTS users (
  id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name                    VARCHAR(100) NOT NULL,
  email                   VARCHAR(190) NOT NULL,
  phone                   VARCHAR(20)  NULL,
  alternate_phone         VARCHAR(20)  NULL,
  address                 TEXT         NULL,
  aadhar_number           VARCHAR(20)  NULL,
  pan_number              VARCHAR(20)  NULL,
  designation             VARCHAR(100) NULL,
  date_of_joining         DATE         NULL,
  date_of_birth           DATE         NULL,
  emergency_contact_name  VARCHAR(100) NULL,
  emergency_contact_phone VARCHAR(20)  NULL,
  profile_photo           VARCHAR(255) NULL,
  aadhar_front            VARCHAR(255) NULL,
  aadhar_back             VARCHAR(255) NULL,
  pan_card_image          VARCHAR(255) NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','manager') NOT NULL DEFAULT 'manager',
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Enquiries captured from the public contact form (stored in `leads`).
--   is_important : starred by admin/manager so it stands out.
--   is_client    : this enquiry is from an actual client — shown in green.
--   is_read      : flips to 1 the first time someone opens the enquiry.
--                  While 0, it shows a red "new" dot in the sidebar and list.
CREATE TABLE IF NOT EXISTS leads (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(120)  NOT NULL,
  email        VARCHAR(190)  NOT NULL,
  phone        VARCHAR(20)   NULL,
  subject      VARCHAR(200)  NULL,
  message      TEXT          NOT NULL,
  status       ENUM('new','contacted','quoted','won','lost','spam') NOT NULL DEFAULT 'new',
  is_important TINYINT(1)    NOT NULL DEFAULT 0,
  is_client    TINYINT(1)    NOT NULL DEFAULT 0,
  is_read      TINYINT(1)    NOT NULL DEFAULT 0,
  notes        TEXT          NULL,
  created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_leads_status    (status),
  KEY idx_leads_important (is_important),
  KEY idx_leads_client    (is_client),
  KEY idx_leads_read      (is_read),
  KEY idx_leads_created   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Internal notes on an enquiry — one row per note (a running timeline),
-- so nothing gets overwritten and every entry keeps its author + time.
CREATE TABLE IF NOT EXISTS lead_notes (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id    INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NULL,
  note       TEXT         NOT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_lead_notes_lead (lead_id),
  CONSTRAINT fk_lead_notes_lead FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE,
  CONSTRAINT fk_lead_notes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  Client Management module — admin-only. Managers never see this.
--  A client owns a running history of meetings, invoices and payments,
--  so the admin can answer "how many times have we met them" and
--  "how much do they still owe" from one screen.
-- ============================================================

-- The client record itself (contact details, what they hired you for, notes).
--   services     : the work they took, e.g. "Website design, SEO".
--   project_cost : the total agreed value of that work. Invoices are billed
--                  against it, so "still to invoice" = project_cost - invoiced.
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

-- Every meeting held with a client — date + time together in meeting_at,
-- so "how many meetings" is just COUNT(*) WHERE client_id = ?.
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

-- Invoices raised against a client.
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

-- Payments received from a client, optionally linked to one invoice.
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

-- ============================================================
--  Project Management module — admins and managers both use this,
--  so an admin and their developer(s) can track work together.
--  A project holds a list of tasks; each task is assigned to one
--  team member and carries its own comment timeline.
-- ============================================================

-- A project the team is working on. Optionally linked to a client record
-- (so "which client is this for" is answerable), but not required — internal
-- or prospective work can live here without a client yet.
CREATE TABLE IF NOT EXISTS projects (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(150) NOT NULL,
  client_id    INT UNSIGNED NULL,
  description  TEXT         NULL,
  status       ENUM('planning','in_progress','on_hold','completed','cancelled') NOT NULL DEFAULT 'planning',
  priority     ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  start_date   DATE         NULL,
  due_date     DATE         NULL,
  budget       DECIMAL(12,2) NULL,
  created_by   INT UNSIGNED NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_projects_status (status),
  KEY idx_projects_client (client_id),
  CONSTRAINT fk_projects_client     FOREIGN KEY (client_id)  REFERENCES clients(id) ON DELETE SET NULL,
  CONSTRAINT fk_projects_created_by FOREIGN KEY (created_by) REFERENCES users(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- A unit of work inside a project, assigned to one team member.
--   status   : todo -> in_progress -> review -> done, moved by an admin or
--              whoever the task is assigned to.
CREATE TABLE IF NOT EXISTS project_tasks (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  project_id   INT UNSIGNED NOT NULL,
  title        VARCHAR(200) NOT NULL,
  description  TEXT         NULL,
  assigned_to  INT UNSIGNED NULL,
  status       ENUM('todo','in_progress','review','done') NOT NULL DEFAULT 'todo',
  priority     ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  due_date     DATE         NULL,
  created_by   INT UNSIGNED NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_project_tasks_project  (project_id),
  KEY idx_project_tasks_assigned (assigned_to),
  KEY idx_project_tasks_status   (status),
  CONSTRAINT fk_project_tasks_project    FOREIGN KEY (project_id)  REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_project_tasks_assigned   FOREIGN KEY (assigned_to) REFERENCES users(id)    ON DELETE SET NULL,
  CONSTRAINT fk_project_tasks_created_by FOREIGN KEY (created_by)  REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Comments on a task — a running timeline (like lead_notes), so the admin
-- and the developer it's assigned to can leave updates without overwriting
-- each other, and every entry keeps its author + time.
CREATE TABLE IF NOT EXISTS project_task_notes (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  task_id    INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NULL,
  note       TEXT         NOT NULL,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_project_task_notes_task (task_id),
  CONSTRAINT fk_project_task_notes_task FOREIGN KEY (task_id) REFERENCES project_tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_project_task_notes_user FOREIGN KEY (user_id) REFERENCES users(id)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  Hosting & Domain Management
-- ------------------------------------------------------------

-- One row per hosting plan OR domain managed for a client. Domains renew
-- exactly like hosting does, so both live here and service_type tells them
-- apart. renewal_date drives every reminder in the module.
CREATE TABLE IF NOT EXISTS hosting_services (
  id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  service_type        ENUM('hosting','domain') NOT NULL DEFAULT 'hosting',
  client_id           INT UNSIGNED NULL,
  client_name         VARCHAR(150) NOT NULL,
  company             VARCHAR(150) NULL,
  project_id          INT UNSIGNED NULL,
  website_name        VARCHAR(150) NULL,
  website_url         VARCHAR(255) NULL,
  domain              VARCHAR(190) NULL,
  provider            VARCHAR(120) NULL,
  plan                VARCHAR(120) NULL,
  account_ref         VARCHAR(120) NULL,
  purchase_date       DATE          NULL,
  renewal_date        DATE          NOT NULL,
  last_renewed_at     DATE          NULL,
  cost                DECIMAL(12,2) NULL,
  renewal_cost        DECIMAL(12,2) NULL,
  billing_cycle       ENUM('monthly','quarterly','half_yearly','yearly','custom') NOT NULL DEFAULT 'yearly',
  custom_cycle_months SMALLINT UNSIGNED NULL,
  -- Passwords are never stored here: login_url is the public panel URL and
  -- credential_ref only says WHERE the login lives (e.g. a password manager).
  login_url           VARCHAR(255) NULL,
  credential_ref      VARCHAR(190) NULL,
  notes               TEXT NULL,
  internal_notes      TEXT NULL,
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

-- Every renewal ever made against a service, kept forever as history.
CREATE TABLE IF NOT EXISTS hosting_renewals (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  hosting_id        INT UNSIGNED NOT NULL,
  renewal_date      DATE NOT NULL,
  previous_expiry   DATE NULL,
  new_expiry        DATE NOT NULL,
  amount            DECIMAL(12,2) NULL,
  payment_status    ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'paid',
  payment_reference VARCHAR(120) NULL,
  notes             TEXT NULL,
  created_by        INT UNSIGNED NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_hosting_renewals_service (hosting_id),
  KEY idx_hosting_renewals_date    (renewal_date),
  CONSTRAINT fk_hosting_renewals_service    FOREIGN KEY (hosting_id) REFERENCES hosting_services(id) ON DELETE CASCADE,
  CONSTRAINT fk_hosting_renewals_created_by FOREIGN KEY (created_by) REFERENCES users(id)            ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  Billing module
-- ------------------------------------------------------------

-- A printable bill/receipt raised when a payment is taken from a client
-- against a project. Itemised services, the project's total cost, and the
-- running balance are all frozen here at the moment the bill is created,
-- so the document stays accurate even if the project's budget changes later.
-- Every bill also writes a matching row into client_payments (payment_id)
-- so a client's overall "received" total stays correct across the app.
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
