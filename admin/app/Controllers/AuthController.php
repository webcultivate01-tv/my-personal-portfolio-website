<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;

class AuthController extends Controller
{
    /** Show the login form. */
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/');
        }
        $this->view('auth/login', [
            'title' => 'Sign in',
            'csrf'  => $this->csrfToken(),
            'error' => $_GET['error'] ?? null,
            'reset' => isset($_GET['reset']),
        ], 'auth');
    }

    /** Handle the login submission. */
    public function login(): void
    {
        $this->verifyCsrf();

        $email    = $this->input('email');
        $password = $this->input('password');

        if ($email === '' || $password === '') {
            $this->redirect('/login?error=empty');
        }

        if (Auth::attempt($email, $password)) {
            $this->redirect('/');
        }

        // Same message whether email or password is wrong (don't leak which).
        $this->redirect('/login?error=invalid');
    }

    /** Log out. */
    public function logout(): void
    {
        $this->verifyCsrf();
        Auth::logout();
        $this->redirect('/login');
    }
}
