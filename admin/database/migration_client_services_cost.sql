-- ============================================================
--  Migration: services + total project cost on a client
--  Adds the two columns the Client Management module uses to record
--  what work a client took and what the whole project is worth.
--
--  Run this ONCE, and ONLY if you imported `migration_clients.sql`
--  before these columns existed. Fresh installs (schema.sql) and any
--  install importing the current `migration_clients.sql` already have
--  them, and re-running this would error with "duplicate column".
--
--  cPanel → phpMyAdmin → select your DB → Import → this file.
-- ============================================================

ALTER TABLE clients
  ADD COLUMN services     TEXT          NULL AFTER address,
  ADD COLUMN project_cost DECIMAL(12,2) NULL AFTER services;
