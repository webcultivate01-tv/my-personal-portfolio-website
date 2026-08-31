-- ============================================================
--  Migration: Enquiry "Spam" status
--  Adds a new "spam" value to the leads.status enum so admins and
--  managers can mark junk enquiries as Spam from the status dropdown.
--
--  Run this ONCE on an existing install. Fresh installs already
--  get this from schema.sql, so they can skip this file.
--
--  cPanel → phpMyAdmin → select your DB → Import → this file.
-- ============================================================

ALTER TABLE leads
  MODIFY COLUMN status ENUM('new','contacted','quoted','won','lost','spam') NOT NULL DEFAULT 'new';
