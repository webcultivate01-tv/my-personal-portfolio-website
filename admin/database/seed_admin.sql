-- ============================================================
--  Seed the first admin login.
--  Email    : scalewithtejas@gmail.com
--  Password : ScaleWithTejas@8605105940
--  (password_hash is a real bcrypt hash of that password, generated with
--   PHP's password_hash(..., PASSWORD_DEFAULT) — the same function
--   App\Models\User::create() uses — so it verifies correctly on login.)
--
--  Run this ONCE, after schema.sql, in phpMyAdmin → your DB → SQL tab.
-- ============================================================

INSERT INTO users (name, email, password_hash, role)
VALUES (
  'Tejas Mehar',
  'scalewithtejas@gmail.com',
  '$2y$12$noK.QMDeZUQPcWYSrPCdN...kIMFQtjCcKN54EWtZUCiQZBr0AGDu',
  'admin'
);
