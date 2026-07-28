<?php
namespace App\Core;

use App\Models\User;

/**
 * Session-based authentication helper.
 */
class Auth
{
    /** Attempt login with email + password. Returns true on success. */
    public static function attempt(string $email, string $password): bool
    {
        $user = (new User())->findByEmail($email);
        if ($user === null) {
            return false;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        // Prevent session fixation: new ID on privilege change.
        session_regenerate_id(true);
        $_SESSION['user_id']   = (int) $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'] ?? 'manager';
        return true;
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function name(): string
    {
        return $_SESSION['user_name'] ?? 'Admin';
    }

    /** Current user's role: 'admin' or 'manager'. */
    public static function role(): string
    {
        return $_SESSION['user_role'] ?? 'manager';
    }

    /** True when the logged-in user has full (admin) privileges. */
    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
