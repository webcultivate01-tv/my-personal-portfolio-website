-- ============================================================
--  Migration: Enquiry Management flags
--  Adds two toggle flags to the existing `leads` table so the
--  Enquiries module can mark an enquiry as Important (star) and
--  flag it as a Client enquiry (shown in green).
--
--  Run this ONCE on an existing install. Fresh installs already
--  get these columns from schema.sql, so they can skip this file.
--
--  cPanel → phpMyAdmin → select your DB → Import → this file.
-- ============================================================

ALTER TABLE leads
  ADD COLUMN is_important TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
  ADD COLUMN is_client    TINYINT(1) NOT NULL DEFAULT 0 AFTER is_important,
  ADD KEY idx_leads_important (is_important),
  ADD KEY idx_leads_client    (is_client);
