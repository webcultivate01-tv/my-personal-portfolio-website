<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\User;

/**
 * Admin management — only admins may reach any action here.
 * Admins add new team members with a full hire record (everything a company
 * takes on when it hires someone), reset passwords, edit any profile field
 * (including ones locked for the account owner), and delete accounts.
 */
class UserController extends Controller
{
    /** List every account with its role. */
    public function index(): void
    {
        $this->requireAdmin();

        $this->view('users/index', [
            'title'   => 'Admin Management',
            'active'  => 'users',
            'csrf'    => $this->csrfToken(),
            'users'   => (new User())->allUsers(),
            'meId'    => Auth::id(),
        ]);
    }

    /** Show the full hire-record edit form for one user. */
    public function edit(): void
    {
        $this->requireAdmin();

        $id     = (int) ($_GET['id'] ?? 0);
        $target = (new User())->findById($id);
        if ($target === null) {
            $this->flash('error', 'That user no longer exists.');
            $this->redirect('/users');
        }

        $this->view('users/edit', [
            'title'  => 'Edit ' . $target['name'],
            'active' => 'users',
            'csrf'   => $this->csrfToken(),
            'target' => $target,
        ]);
    }

    /** Admin saves the full profile for a user (locked fields included). */
    public function update(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id = $this->intInput('id');
        $users = new User();
        $target = $users->findById($id);
        if ($target === null) {
            $this->flash('error', 'That user no longer exists.');
            $this->redirect('/users');
        }

        $profile = $this->collectProfileInput();
        $profile['name'] = $this->input('name');
        $email = strtolower($this->input('email'));
        $profile['email'] = $email;

        $error = $this->validateProfile($profile, true);
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirect('/users/edit?id=' . $id);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'That email address is not valid.');
            $this->redirect('/users/edit?id=' . $id);
        }
        if ($users->emailExists($email, $id)) {
            $this->flash('error', 'Another account already uses that email.');
            $this->redirect('/users/edit?id=' . $id);
        }

        $users->adminUpdateProfile($id, $profile);
        $this->flash('success', 'Profile for "' . $profile['name'] . '" was updated.');
        $this->redirect('/users/edit?id=' . $id);
    }

    /** Create a new admin or manager with a full hire record. */
    public function store(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $name     = $this->input('name');
        $email    = strtolower($this->input('email'));
        $password = $this->input('password');
        $role     = $this->input('role');

        if (!in_array($role, User::ROLES, true)) {
            $role = 'manager';
        }

        $profile = $this->collectProfileInput();
        $profile['name']  = $name;
        $profile['email'] = $email;

        if ($password === '') {
            $this->flash('error', 'A temporary password is required.');
            $this->redirect('/users');
        }
        if (strlen($password) < 8) {
            $this->flash('error', 'Password must be at least 8 characters.');
            $this->redirect('/users');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'That email address is not valid.');
            $this->redirect('/users');
        }

        $error = $this->validateProfile($profile, true);
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirect('/users');
        }

        $users = new User();
        if ($users->emailExists($email)) {
            $this->flash('error', 'An account with that email already exists.');
            $this->redirect('/users');
        }

        $users->create($name, $email, $password, $role, $profile);
        $this->flash('success', ucfirst($role) . ' "' . $name . '" was added. They must upload their photo and ID documents on first sign-in.');
        $this->redirect('/users');
    }

    /** Admin resets any user's password. */
    public function resetPassword(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id       = $this->intInput('id');
        $password = $this->input('password');

        if ($id <= 0 || strlen($password) < 8) {
            $this->flash('error', 'New password must be at least 8 characters.');
            $this->redirect('/users');
        }

        $users = new User();
        if ($users->findById($id) === null) {
            $this->flash('error', 'That user no longer exists.');
            $this->redirect('/users');
        }

        $users->updatePassword($id, $password);
        $this->flash('success', 'Password updated.');
        $this->redirect('/users');
    }

    /** Admin deletes a user (never yourself, never the last admin). */
    public function destroy(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id    = $this->intInput('id');
        $users = new User();
        $target = $users->findById($id);

        if ($target === null) {
            $this->flash('error', 'That user no longer exists.');
            $this->redirect('/users');
        }
        if ($id === Auth::id()) {
            $this->flash('error', 'You cannot delete your own account.');
            $this->redirect('/users');
        }
        if ($target['role'] === 'admin' && $users->adminCount() <= 1) {
            $this->flash('error', 'You cannot remove the last remaining admin.');
            $this->redirect('/users');
        }

        $users->delete($id);
        $this->flash('success', 'User "' . $target['name'] . '" was removed.');
        $this->redirect('/users');
    }

    /** Read every hire-record field from POST, trimmed, into one array. */
    private function collectProfileInput(): array
    {
        return [
            'phone'                   => $this->input('phone'),
            'alternate_phone'         => $this->input('alternate_phone'),
            'address'                 => $this->input('address'),
            'aadhar_number'           => preg_replace('/\s+/', '', $this->input('aadhar_number')),
            'pan_number'              => strtoupper($this->input('pan_number')),
            'designation'             => $this->input('designation'),
            'date_of_joining'         => $this->input('date_of_joining'),
            'date_of_birth'           => $this->input('date_of_birth'),
            'emergency_contact_name'  => $this->input('emergency_contact_name'),
            'emergency_contact_phone' => $this->input('emergency_contact_phone'),
        ];
    }

    /** Validate the full hire record. Returns an error message, or null if it's all good. */
    private function validateProfile(array $p, bool $requireAll): ?string
    {
        if ($requireAll) {
            foreach (['phone', 'alternate_phone', 'address', 'aadhar_number', 'pan_number',
                      'designation', 'date_of_joining', 'date_of_birth',
                      'emergency_contact_name', 'emergency_contact_phone'] as $field) {
                if ($p[$field] === '') {
                    return 'All hire details are required — please fill in every field.';
                }
            }
        }
        if ($p['phone'] !== '' && !preg_match('/^\d{10}$/', $p['phone'])) {
            return 'Mobile number must be exactly 10 digits.';
        }
        if ($p['alternate_phone'] !== '' && !preg_match('/^\d{10}$/', $p['alternate_phone'])) {
            return 'Alternate mobile number must be exactly 10 digits.';
        }
        if ($p['phone'] !== '' && $p['phone'] === $p['alternate_phone']) {
            return 'Alternate mobile number must be different from the mobile number.';
        }
        if ($p['aadhar_number'] !== '' && !preg_match('/^\d{12}$/', $p['aadhar_number'])) {
            return 'Aadhar number must be exactly 12 digits.';
        }
        if ($p['pan_number'] !== '' && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $p['pan_number'])) {
            return 'PAN number must be in the format ABCDE1234F.';
        }
        if ($p['emergency_contact_phone'] !== '' && !preg_match('/^\d{10}$/', $p['emergency_contact_phone'])) {
            return 'Emergency contact number must be exactly 10 digits.';
        }
        foreach (['date_of_joining', 'date_of_birth'] as $field) {
            if ($p[$field] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $p[$field])) {
                return 'Dates must be valid.';
            }
        }
        return null;
    }
}
