<?php
/**
 * ONE-TIME SETUP SCRIPT — creates your first ADMIN login.
 *
 * 1. Edit the three values below.
 * 2. Visit this file once in your browser:
 *      https://yoursite.com/admin/database/create_admin.php
 * 3. DELETE this file immediately afterwards (it can create accounts).
 *
 * The account it creates has the 'admin' role, so it can reach Admin
 * Management and add further admins/managers from inside the panel.
 */

declare(strict_types=1);

// ---- EDIT THESE ----
$name     = 'Tejas Mehar';
$email    = 'you@example.com';
$password = 'change-this-now';   // use a strong password, min 8 chars
// --------------------

require __DIR__ . '/../config/config.php';

// Minimal autoloader so we can reuse the app's Model/Database/User.
spl_autoload_register(function (string $class): void {
    $prefix  = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Models\User;

header('Content-Type: text/plain; charset=utf-8');

if (strlen($password) < 8) {
    exit("Password must be at least 8 characters. Edit this file and try again.\n");
}

$users = new User();

if ($users->emailExists(strtolower($email))) {
    exit("An account with that email already exists — nothing to do.\nDelete this file now.\n");
}

$id = $users->create($name, strtolower($email), $password, 'admin');

echo "✅ Admin account created (id #{$id}).\n";
echo "   Email: {$email}\n";
echo "   Role:  admin (full access)\n\n";
echo "IMPORTANT: delete this file now, then sign in at /admin/\n";
