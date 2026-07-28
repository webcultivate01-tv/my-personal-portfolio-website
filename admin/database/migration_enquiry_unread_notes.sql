-- ============================================================
--  Migration: Unread indicator + Notes timeline
--
--  1. Adds `is_read` to `leads` so brand-new enquiries can be flagged
--     with a red dot (in the sidebar and in the list) until a team
--     member opens them. Existing rows are backfilled as read (1) so
--     you don't get a wall of false "new" dots on first run; new
--     enquiries from the contact form are inserted with is_read = 0.
--
--  2. Adds a `lead_notes` table so "Internal notes" becomes a running
--     timeline (one row per note, with author + timestamp) instead of
--     a single field that gets overwritten every time. Any existing
--     text in `leads.notes` is copied over as the first note so
--     nothing is lost.
--
--  Run this ONCE on an existing install. Fresh installs already get
--  this from schema.sql, so they can skip this file.
--
--  cPanel → phpMyAdmin → select your DB → Import → this file.
-- ============================================================

ALTER TABLE leads
  ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 1 AFTER is_client,
  ADD KEY idx_leads_read (is_read);

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

INSERT INTO lead_notes (lead_id, user_id, note, created_at)
SELECT id, NULL, notes, created_at FROM leads WHERE notes IS NOT NULL AND notes <> '';
