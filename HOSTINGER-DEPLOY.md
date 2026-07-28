# Deploying to Hostinger (hPanel) — tejasmehar.in

No build step, no `composer install`, no `npm install` — this project has **zero
external dependencies**. It's plain HTML/CSS/JS (Tailwind loads from a CDN) plus
a hand-rolled PHP admin panel using only PDO (built into PHP). You only need to
upload files and set up the database.

## 0. Requirements on the Hostinger plan
- PHP **8.1 or newer** (the admin panel uses `match`, `str_starts_with`, etc.)
- MySQL/MariaDB database
- Free SSL (Hostinger auto-issues this once the domain points to it)

## 1. Point the domain
In hPanel, make sure `tejasmehar.in` is added and pointing at this hosting
account (Domains → tejasmehar.in → connect/point to hosting). DNS propagation
can take a few hours.

## 2. Set the PHP version
hPanel → **Advanced → PHP Configuration** → select **PHP 8.1** (or 8.2/8.3) →
Save.

## 3. Create the database
hPanel → **Databases → MySQL Databases**:
1. Create a new database (e.g. `admin`) → Hostinger names it
   `u123456789_admin`.
2. Create a database user with a strong password → Hostinger names it
   `u123456789_admin`.
3. Attach the user to the database with **All Privileges**.
4. Note the exact DB name, username, and password — you'll need them in step 5.

## 4. Upload the code
hPanel → **Files → File Manager** → go into `public_html/` for
`tejasmehar.in` (if it's not empty, clear out the default placeholder files
first).

Two options:
- **Zip upload (recommended):** upload `portfolio-deploy.zip` (prepared below)
  via File Manager's Upload button, then right-click it → **Extract** directly
  into `public_html/`.
- **FTP:** use the FTP credentials from hPanel → Files → FTP Accounts with
  FileZilla or similar, and upload the project folder contents into
  `public_html/`.

Either way, when done `public_html/` should contain `index.html`,
`contact.php`, `css/`, `js/`, `public/`, `admin/`, etc. directly (not nested
inside another folder).

## 5. Set the database credentials
File Manager → edit `public_html/admin/config/config.php` → replace:
```php
define('DB_NAME', 'REPLACE_WITH_YOUR_DB_NAME');
define('DB_USER', 'REPLACE_WITH_YOUR_DB_USER');
define('DB_PASS', 'REPLACE_WITH_YOUR_DB_PASSWORD');
```
with the values from step 3. `DB_HOST`/`DB_PORT` are already correct for
Hostinger (`localhost` / `3306`). `DEBUG` and `SECURE_COOKIES` switch
automatically based on the domain, so nothing else to change here.

## 6. Import the database schema
hPanel → **Databases → phpMyAdmin** → open your database → **Import** →
choose `public_html/admin/database/schema.sql` (download it from File
Manager first, or use phpMyAdmin's "Choose File") → **Go**.

## 7. Create your admin login
1. File Manager → edit `public_html/admin/database/create_admin.php` → set
   your real `$name`, `$email`, `$password` at the top.
2. Visit `https://tejasmehar.in/admin/database/create_admin.php` once in your
   browser — it prints a confirmation.
3. **Delete `create_admin.php` immediately after** (File Manager → delete)
   — anyone who finds that URL before you delete it can create an account.

## 8. Verify SSL
hPanel → **Security → SSL** should show the certificate as active for
`tejasmehar.in` (usually automatic within a few minutes to an hour of DNS
pointing correctly). The root `.htaccess` added to this project force-redirects
HTTP → HTTPS, so double check the padlock shows before relying on that.

## 9. Test everything
- `https://tejasmehar.in/` — the portfolio loads, images/nav/animations work.
- `https://tejasmehar.in/contact.html` — submit the form.
- `https://tejasmehar.in/admin/` — log in with the account from step 7, then
  check **Enquiries** for the test submission from above.

## 10. Post-launch checklist
- [ ] `admin/database/create_admin.php` deleted from the server
- [ ] Real photo in place of the `placehold.co` placeholders (see root
      `README.md` → "Make it yours")
- [ ] Social links (`href="#"`) replaced with real GitHub/LinkedIn URLs
- [ ] Meeting screenshots dropped in `public/meetings/` if you want that
      section populated (see `public/meetings/README.txt`)
