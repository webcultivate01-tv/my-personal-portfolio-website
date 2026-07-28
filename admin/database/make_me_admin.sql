-- ============================================================
--  Make your account an admin.
--  Use this if your login shows as "Manager" and you need full access.
--
--  cPanel / XAMPP → phpMyAdmin → select your DB → SQL tab →
--  paste ONE of the options below → Go.
-- ============================================================

-- Option A (recommended): promote by your exact login email.
--   Replace the email with the one you sign in with.
UPDATE users SET role = 'admin' WHERE email = 'you@example.com';

-- Option B: if you're the only/first account, promote it without knowing the email.
-- UPDATE users SET role = 'admin' ORDER BY id ASC LIMIT 1;

-- Check the result:
-- SELECT id, name, email, role FROM users;
