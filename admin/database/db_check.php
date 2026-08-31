<?php
/**
 * ONE-TIME DATABASE DIAGNOSTIC — DELETE THIS FILE AS SOON AS YOU ARE DONE.
 *
 * The live server runs with DEBUG off, so "DB error: Database connection
 * failed." hides the real reason. This prints the real reason.
 *
 * HOW TO USE
 *   1. Change SECRET below to anything only you know.
 *   2. Upload this file to  public_html/admin/database/db_check.php
 *   3. Visit  https://yoursite.com/admin/database/db_check.php?key=YOUR_SECRET
 *   4. Read the result, fix what it reports, then DELETE THIS FILE.
 *
 * It never prints your password — only whether one is set and whether it is
 * still the placeholder.
 */

declare(strict_types=1);

const SECRET = 'change-me-before-uploading';

if (!hash_equals(SECRET, (string) ($_GET['key'] ?? ''))) {
    http_response_code(404);
    exit('Not found');
}

header('Content-Type: text/plain; charset=utf-8');

$configDir   = dirname(__DIR__) . '/config';
$localConfig = $configDir . '/config.local.php';

echo "=== Hosting admin — database diagnostic ===\n\n";

// ---- 1. Is the credentials file even there? ----
echo "1. config/config.local.php\n";
if (is_file($localConfig)) {
    echo "   FOUND  (" . filesize($localConfig) . " bytes, modified " . date('Y-m-d H:i', (int) filemtime($localConfig)) . ")\n";
} else {
    echo "   *** MISSING ***\n";
    echo "   This is almost certainly your problem.\n";
    echo "   This file is gitignored, so it is never uploaded with the rest of\n";
    echo "   the admin folder — and re-uploading admin/ deletes the copy that\n";
    echo "   was on the server. Recreate it in " . $configDir . "\n";
    echo "   using config.local.php.example as the template.\n";
}

// ---- 2. What credentials did config.php actually end up with? ----
require dirname(__DIR__) . '/config/config.php';

echo "\n2. Credentials in use\n";
printf("   DB_HOST : %s\n", DB_HOST);
printf("   DB_PORT : %s\n", DB_PORT);
printf("   DB_NAME : %s\n", DB_NAME);
printf("   DB_USER : %s\n", DB_USER);
printf("   DB_PASS : %s\n", DB_PASS === 'REPLACE_WITH_YOUR_DB_PASSWORD'
    ? '*** STILL THE PLACEHOLDER — this will always fail ***'
    : 'set (' . strlen(DB_PASS) . ' characters)');
printf("   DEBUG   : %s\n", DEBUG ? 'on' : 'off');

// ---- 3. Try the connection and show the real error ----
echo "\n3. Connection attempt\n";
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET),
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "   CONNECTED\n";

    // ---- 4. Are the hosting tables present? ----
    echo "\n4. Tables\n";
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    printf("   %d tables in %s\n", count($tables), DB_NAME);
    foreach (['hosting_services', 'hosting_renewals'] as $needed) {
        printf("   %-18s %s\n", $needed, in_array($needed, $tables, true)
            ? 'present'
            : 'MISSING — import database/migration_hosting.sql');
    }
} catch (PDOException $e) {
    echo "   FAILED\n";
    echo "   " . $e->getMessage() . "\n\n";
    echo "   What that usually means:\n";
    echo "     'Access denied'        -> wrong DB_USER/DB_PASS, or the user is not\n";
    echo "                               attached to the database in hPanel\n";
    echo "     'Unknown database'     -> wrong DB_NAME\n";
    echo "     'Connection refused' / \n";
    echo "     'No such host'         -> wrong DB_HOST (Hostinger uses localhost)\n";
}

echo "\n=== Done. NOW DELETE THIS FILE. ===\n";
