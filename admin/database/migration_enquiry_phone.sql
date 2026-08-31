-- ============================================================
--  Migration: Enquiry phone number
--  Adds the mobile number column collected by the new field on the
--  public contact form.
--
--  Run this ONCE on an existing install. Fresh installs already
--  get this column from schema.sql, so they can skip this file.
--
--  cPanel → phpMyAdmin → select your DB → Import → this file.
-- ============================================================

ALTER TABLE leads
  ADD COLUMN phone VARCHAR(20) NULL AFTER email;
