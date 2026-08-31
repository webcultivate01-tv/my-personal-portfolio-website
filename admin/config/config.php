<?php
/**
 * Central configuration.
 *
 * On Hostinger hPanel: hPanel -> Databases -> create a MySQL database + user,
 * attach the user to the database with All Privileges, then paste the exact
 * names below. Hostinger prefixes them with your account ID, e.g.
 *   DB name : u123456789_admin
 *   DB user : u123456789_admin
 * DB_HOST stays 'localhost' and DB_PORT stays '3306' for Hostinger.
 */

declare(strict_types=1);

// ---- Database (edit these for your host) ----
// Real credentials live in config.local.php, which is gitignored and never
// committed. Copy config.local.php.example -> config.local.php and fill in
// your values (locally for XAMPP, and again directly on the live server —
// upload it there separately since it won't come through git).
$localDbConfig = __DIR__ . '/config.local.php';
if (file_exists($localDbConfig)) {
    require $localDbConfig;
} else {
    define('DB_HOST', 'localhost');
    define('DB_PORT', '3306');
    define('DB_NAME', 'u284906959_scalewithtejas');   // <-- from hPanel Databases
    define('DB_USER', 'u284906959_scalewithtejas');   // <-- from hPanel Databases
    define('DB_PASS', 'REPLACE_WITH_YOUR_DB_PASSWORD'); // <-- from hPanel Databases
    define('DB_CHARSET', 'utf8mb4');
}

// ---- App ----
define('APP_NAME', 'Tejas Mehar — Admin');

// Base URL path where the panel lives (no trailing slash).
// Auto-detected from the request so it works unchanged whether the panel is at
//   http://localhost/portfolio/admin   (local XAMPP)
//   https://yoursite.com/admin         (cPanel)
// To force a fixed value instead, just replace the block below with e.g.
//   define('BASE_PATH', '/admin');
if (!defined('BASE_PATH')) {
    // SCRIPT_NAME for an admin request is  <path>/admin/index.php  → its dir is the base.
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/admin/index.php');
    $base   = rtrim(dirname($script), '/');
    define('BASE_PATH', $base === '/' ? '' : $base);
}

// ---- Session / security ----
define('SESSION_NAME', 'tm_admin_sess');

// Auto-detect local (XAMPP) vs live server, same idea as BASE_PATH above,
// so DEBUG/SECURE_COOKIES don't need to be remembered/flipped on deploy.
if (!defined('IS_LOCAL_ENV')) {
    $httpHost = $_SERVER['HTTP_HOST'] ?? '';
    define('IS_LOCAL_ENV', $httpHost === 'localhost' || str_starts_with($httpHost, 'localhost:') || str_starts_with($httpHost, '127.0.0.1'));
}

// True only over HTTPS on the live server (Hostinger's free SSL covers this).
define('SECURE_COOKIES', !IS_LOCAL_ENV);

// Show errors while building locally; silenced automatically on the live server.
define('DEBUG', IS_LOCAL_ENV);

if (DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// ---- Tiny view helpers ----

/** Escape output for safe HTML. Use everywhere you print user data. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Build a URL inside the panel: url('/login') -> '/admin/login'. */
function url(string $path = '/'): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

/** Build a URL to a static asset: asset('css/admin.css'). */
function asset(string $path): string
{
    return BASE_PATH . '/assets/' . ltrim($path, '/');
}
