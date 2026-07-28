<?php
/**
 * Public contact-form endpoint.
 *
 * Receives a POST from the contact form on contact.html (via fetch) and
 * saves the message into the same `leads` table the admin panel reads,
 * so every enquiry shows up under Admin → Enquiries.
 *
 * Returns JSON: { ok: true } or { ok: false, error: "..." }.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// Reuse the admin panel's config (DB credentials + helpers) and models.
require __DIR__ . '/admin/config/config.php';

spl_autoload_register(function (string $class): void {
    $prefix  = 'App\\';
    $baseDir = __DIR__ . '/admin/app/';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Models\Lead;

/** Send a JSON response and stop. */
function respond(bool $ok, string $error = '', int $code = 200): void
{
    http_response_code($code);
    echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed.', 405);
}

// Honeypot: real users never fill this hidden field; bots do.
if (trim((string) ($_POST['company'] ?? '')) !== '') {
    respond(true); // pretend success, save nothing
}

$name    = trim((string) ($_POST['name'] ?? ''));
$email   = trim((string) ($_POST['email'] ?? ''));
$subject = trim((string) ($_POST['subject'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));

if ($name === '' || $email === '' || $message === '') {
    respond(false, 'Please fill in your name, email, and message.', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Please enter a valid email address.', 422);
}

// Trim to the column limits so nothing is silently truncated by MySQL.
$name    = mb_substr($name, 0, 120);
$email   = mb_substr($email, 0, 190);
$subject = mb_substr($subject, 0, 200);

try {
    (new Lead())->create($name, $email, $subject, $message);
} catch (\Throwable $e) {
    respond(false, DEBUG ? $e->getMessage() : 'Something went wrong. Please email me directly.', 500);
}

respond(true);
