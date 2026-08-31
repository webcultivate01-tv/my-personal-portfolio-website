<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\User;

/**
 * "Forgot password" — self-service reset from the login screen, for admins
 * and managers alike.
 *
 * Flow (three steps, no email is sent):
 *   1. GET  /forgot-password  — ask for the account's email address.
 *   2. POST /forgot-password  — if that email belongs to a user, put a
 *      short-lived grant in the session and send them to step 3.
 *   3. GET/POST /reset-password — set a new password, then back to /login.
 *
 * NOTE ON SECURITY: by design (the panel owner's explicit choice) knowing a
 * registered email address is the ONLY thing needed to set a new password.
 * There is no mailed token and no second factor, so the login page tells an
 * attacker which addresses are real. The session throttle below slows down
 * bulk email guessing but does not stop someone who already knows an admin's
 * address. Swap in a mailed one-time link if that ever becomes a concern.
 */
class PasswordResetController extends Controller
{
    /** How long a verified email stays good for before the reset form expires. */
    private const GRANT_TTL = 600; // 10 minutes

    /** Crude anti-enumeration throttle: this many email lookups per window. */
    private const MAX_ATTEMPTS = 5;
    private const ATTEMPT_WINDOW = 900; // 15 minutes

    /** Step 1 — the "enter your email" form. */
    public function showForgot(): void
    {
        if (Auth::check()) {
            $this->redirect('/');
        }
        $this->view('auth/forgot_password', [
            'title' => 'Forgot password',
            'csrf'  => $this->csrfToken(),
            'error' => $_GET['error'] ?? null,
        ], 'auth');
    }

    /** Step 2 — does this email belong to an account? */
    public function checkEmail(): void
    {
        $this->verifyCsrf();

        $email = $this->input('email');
        if ($email === '') {
            $this->redirect('/forgot-password?error=empty');
        }
        if ($this->throttled()) {
            $this->redirect('/forgot-password?error=throttled');
        }

        $user = (new User())->findByEmail($email);
        if ($user === null) {
            $this->recordAttempt();
            $this->redirect('/forgot-password?error=notfound');
        }

        // Email checks out — hand out a grant that only step 3 accepts.
        $this->clearAttempts();
        session_regenerate_id(true);
        $_SESSION['pwreset'] = [
            'user_id'    => (int) $user['id'],
            'email'      => $user['email'],
            'expires_at' => time() + self::GRANT_TTL,
        ];
        $this->redirect('/reset-password');
    }

    /** Step 3 — the "choose a new password" form. */
    public function showReset(): void
    {
        $grant = $this->grant();
        if ($grant === null) {
            $this->redirect('/forgot-password?error=expired');
        }

        $this->view('auth/reset_password', [
            'title' => 'Set a new password',
            'csrf'  => $this->csrfToken(),
            'email' => $grant['email'],
            'error' => $_GET['error'] ?? null,
        ], 'auth');
    }

    /** Step 3 (submit) — write the new password and send them to sign in. */
    public function reset(): void
    {
        $this->verifyCsrf();

        $grant = $this->grant();
        if ($grant === null) {
            $this->redirect('/forgot-password?error=expired');
        }

        $password = $this->input('password');
        $confirm  = $this->input('password_confirm');

        if (strlen($password) < 8) {
            $this->redirect('/reset-password?error=short');
        }
        if ($password !== $confirm) {
            $this->redirect('/reset-password?error=mismatch');
        }

        $users = new User();
        // The account could have been deleted between step 2 and step 3.
        if ($users->findById($grant['user_id']) === null) {
            unset($_SESSION['pwreset']);
            $this->redirect('/forgot-password?error=notfound');
        }

        $users->updatePassword($grant['user_id'], $password);

        // Burn the grant and the session it lived in, so the link can't be reused.
        unset($_SESSION['pwreset']);
        session_regenerate_id(true);

        $this->redirect('/login?reset=1');
    }

    // ---- helpers ----

    /** The current reset grant, or null if there isn't one / it has expired. */
    private function grant(): ?array
    {
        $grant = $_SESSION['pwreset'] ?? null;
        if (!is_array($grant) || empty($grant['user_id'])) {
            return null;
        }
        if (($grant['expires_at'] ?? 0) < time()) {
            unset($_SESSION['pwreset']);
            return null;
        }
        return $grant;
    }

    /** True once too many wrong emails have been tried in this session. */
    private function throttled(): bool
    {
        $tries = $_SESSION['pwreset_tries'] ?? null;
        if (!is_array($tries) || ($tries['reset_at'] ?? 0) < time()) {
            return false;
        }
        return ($tries['count'] ?? 0) >= self::MAX_ATTEMPTS;
    }

    private function recordAttempt(): void
    {
        $tries = $_SESSION['pwreset_tries'] ?? null;
        if (!is_array($tries) || ($tries['reset_at'] ?? 0) < time()) {
            $tries = ['count' => 0, 'reset_at' => time() + self::ATTEMPT_WINDOW];
        }
        $tries['count']++;
        $_SESSION['pwreset_tries'] = $tries;
    }

    private function clearAttempts(): void
    {
        unset($_SESSION['pwreset_tries']);
    }
}
