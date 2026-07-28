-- ============================================================
--  Migration: add user roles (admin / manager)
--  Run this ONCE on an existing install that was created before
--  roles existed. Fresh installs already get the column from
--  schema.sql, so they can skip this file.
--
--  cPanel → phpMyAdmin → select your DB → Import → this file.
-- ============================================================

-- 1. Add the role column (existing rows default to 'manager').
ALTER TABLE users
  ADD COLUMN role ENUM('admin','manager') NOT NULL DEFAULT 'manager' AFTER password_hash,
  ADD KEY idx_users_role (role);

-- 2. Promote the very first account (your original login) to admin,
--    so you don't get locked out of the new management features.
UPDATE users SET role = 'admin' ORDER BY id ASC LIMIT 1;
