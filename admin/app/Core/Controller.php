<?php
namespace App\Core;

use App\Models\User;

/**
 * Base controller — shared helpers for every controller.
 */
abstract class Controller
{
    /** Paths a manager with incomplete onboarding documents may still reach. */
    private const ONBOARDING_ALLOWED_PATHS = ['/account/onboarding', '/account/document', '/logout'];
    /** Render a view (through the layout by default). */
    protected function view(string $view, array $data = [], ?string $layout = 'app'): void
    {
        (new View())->render($view, $data, $layout);
    }

    /** Redirect to a path relative to the panel base (e.g. '/login'). */
    protected function redirect(string $path): void
    {
        header('Location: ' . BASE_PATH . $path);
        exit;
    }

    /** Read a trimmed POST field. */
    protected function input(string $key, string $default = ''): string
    {
        return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
    }

    /** Read a POST field as an integer. */
    protected function intInput(string $key, int $default = 0): int
    {
        return isset($_POST[$key]) ? (int) $_POST[$key] : $default;
    }

    /** Stop here unless the user is logged in. */
    protected function requireAuth(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login');
        }
        $this->enforceOnboardingGate();
    }

    /**
     * A manager can't use the panel until they've uploaded their onboarding
     * documents (profile photo, Aadhar front/back, PAN card) — send them to
     * the upload page for anything outside that page itself and logout.
     */
    private function enforceOnboardingGate(): void
    {
        if (Auth::isAdmin()) {
            return;
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        if (BASE_PATH !== '' && strpos($path, BASE_PATH) === 0) {
            $path = substr($path, strlen(BASE_PATH));
        }
        $path = '/' . trim($path, '/');
        if (in_array($path, self::ONBOARDING_ALLOWED_PATHS, true)) {
            return;
        }

        $me = (new User())->findById((int) Auth::id());
        if ($me !== null && !(new User())->hasCompletedDocuments($me)) {
            $this->redirect('/account/onboarding');
        }
    }

    /** Stop here unless the logged-in user is an admin. */
    protected function requireAdmin(): void
    {
        $this->requireAuth();
        if (!Auth::isAdmin()) {
            http_response_code(403);
            $this->flash('error', 'You do not have permission to do that.');
            $this->redirect('/');
        }
    }

    // ---- Flash messages (one-shot notices shown after a redirect) ----

    /** Queue a message to show on the next page load. */
    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }

    // ---- CSRF protection ----

    protected function csrfToken(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    protected function verifyCsrf(): void
    {
        $sent = $_POST['csrf'] ?? '';
        if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
            http_response_code(419);
            exit('Invalid or expired form token. Please go back and try again.');
        }
    }
}
