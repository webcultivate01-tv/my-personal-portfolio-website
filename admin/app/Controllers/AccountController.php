<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\User;

/**
 * "My account" — a signed-in user's own profile.
 *
 * Only admins may change their own password here. Managers can view the
 * page but the password form is disabled for them: a manager cannot change
 * their own password — an admin has to reset it from Admin Management.
 *
 * Profile fields split three ways (see App\Models\User):
 *  - LOCKED_ONCE_SET  (email, phone, alt. phone, address, Aadhar #, PAN #):
 *    set by an admin at hire time, view-only for the manager forever after.
 *  - ADMIN_ONLY       (name, role, designation, date of joining): HR data,
 *    view-only for the manager, editable by an admin.
 *  - SELF_EDITABLE    (date of birth, emergency contact): the manager can
 *    change these on their own account whenever they like.
 * Identity documents (photo, Aadhar front/back, PAN card) must be uploaded
 * once via the onboarding page and are then locked the same way.
 */
class AccountController extends Controller
{
    private const MAX_UPLOAD_BYTES = 5 * 1024 * 1024; // 5MB
    private const ALLOWED_EXT = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'pdf' => 'application/pdf'];

    public function index(): void
    {
        $this->requireAuth();

        $this->view('account/index', [
            'title'   => 'My Account',
            'active'  => 'account',
            'csrf'    => $this->csrfToken(),
            'user'    => (new User())->findById((int) Auth::id()),
            'canEdit' => Auth::isAdmin(),
        ]);
    }

    /** Manager (or admin) updates their own self-editable fields. Locked/admin-only fields are never touched here. */
    public function updateProfile(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $dob   = $this->input('date_of_birth');
        $ecName = $this->input('emergency_contact_name');
        $ecPhone = $this->input('emergency_contact_phone');

        if ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
            $this->flash('error', 'Date of birth must be valid.');
            $this->redirect('/account');
        }
        if ($ecPhone !== '' && !preg_match('/^\d{10}$/', $ecPhone)) {
            $this->flash('error', 'Emergency contact number must be exactly 10 digits.');
            $this->redirect('/account');
        }

        (new User())->updateSelfEditable((int) Auth::id(), [
            'date_of_birth'           => $dob,
            'emergency_contact_name'  => $ecName,
            'emergency_contact_phone' => $ecPhone,
        ]);
        $this->flash('success', 'Your details were updated.');
        $this->redirect('/account');
    }

    /** Change your own password. Admins only. */
    public function updatePassword(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        // Managers are not allowed to change their own password.
        if (!Auth::isAdmin()) {
            http_response_code(403);
            $this->flash('error', 'Managers cannot change their own password. Ask an admin to reset it.');
            $this->redirect('/account');
        }

        $current = $this->input('current_password');
        $new     = $this->input('new_password');
        $confirm = $this->input('confirm_password');

        $users = new User();
        $me     = $users->findAuthById((int) Auth::id());

        if ($me === null) {
            $this->flash('error', 'Could not verify your account.');
            $this->redirect('/account');
        }
        if (!password_verify($current, $me['password_hash'])) {
            $this->flash('error', 'Your current password is incorrect.');
            $this->redirect('/account');
        }
        if (strlen($new) < 8) {
            $this->flash('error', 'New password must be at least 8 characters.');
            $this->redirect('/account');
        }
        if ($new !== $confirm) {
            $this->flash('error', 'The new passwords do not match.');
            $this->redirect('/account');
        }

        $users->updatePassword((int) Auth::id(), $new);
        $this->flash('success', 'Your password has been changed.');
        $this->redirect('/account');
    }

    /** First-sign-in gate for managers: upload photo + ID documents. */
    public function onboarding(): void
    {
        $this->requireAuthWithoutGate();

        $user = (new User())->findById((int) Auth::id());
        if ($user !== null && (new User())->hasCompletedDocuments($user)) {
            $this->redirect('/');
        }

        $this->view('account/onboarding', [
            'title'  => 'Complete your profile',
            'active' => 'account',
            'csrf'   => $this->csrfToken(),
            'user'   => $user,
        ], 'app');
    }

    /** Store the four uploaded documents. All are required together, once. */
    public function storeOnboarding(): void
    {
        $this->requireAuthWithoutGate();
        $this->verifyCsrf();

        $userId = (int) Auth::id();
        $users  = new User();
        $user   = $users->findById($userId);
        if ($user !== null && $users->hasCompletedDocuments($user)) {
            $this->redirect('/');
        }

        $paths = [];
        foreach (User::DOCUMENT_FIELDS as $field) {
            $result = $this->storeUpload($field, $userId);
            if ($result === null) {
                $this->flash('error', 'Please choose a valid image or PDF (max 5MB) for every field, including "' . $this->fieldLabel($field) . '".');
                $this->redirect('/account/onboarding');
            }
            $paths[$field] = $result;
        }

        foreach ($paths as $field => $path) {
            $users->setDocument($userId, $field, $path);
        }

        $this->flash('success', 'Your documents were uploaded. Welcome aboard!');
        $this->redirect('/');
    }

    /** Stream a stored document to the owner or an admin. Never served directly by Apache. */
    public function document(): void
    {
        $this->requireAuthWithoutGate();

        $id    = (int) ($_GET['id'] ?? 0);
        $field = (string) ($_GET['field'] ?? '');

        if (!in_array($field, User::DOCUMENT_FIELDS, true)) {
            http_response_code(404);
            exit;
        }
        if ($id !== (int) Auth::id() && !Auth::isAdmin()) {
            http_response_code(403);
            exit;
        }

        $user = (new User())->findById($id);
        $relative = $user[$field] ?? null;
        if ($user === null || $relative === null) {
            http_response_code(404);
            exit;
        }

        $base = realpath(__DIR__ . '/../../storage/uploads');
        $full = realpath($base . '/' . $relative);
        if ($full === false || strpos($full, $base) !== 0 || !is_file($full)) {
            http_response_code(404);
            exit;
        }

        $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
        header('Content-Type: ' . (self::ALLOWED_EXT[$ext] ?? 'application/octet-stream'));
        header('Content-Disposition: inline; filename="' . $field . '.' . $ext . '"');
        header('Content-Length: ' . filesize($full));
        readfile($full);
        exit;
    }

    /** requireAuth without re-running the onboarding gate — used by the onboarding pages themselves. */
    private function requireAuthWithoutGate(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login');
        }
    }

    /** Validate + move one uploaded file. Returns the stored relative path, or null on failure. */
    private function storeUpload(string $field, int $userId): ?string
    {
        $file = $_FILES[$field] ?? null;
        if ($file === null || $file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0 || $file['size'] > self::MAX_UPLOAD_BYTES) {
            return null;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        // Profile photo must be an image; ID documents may also be a scanned PDF.
        $allowed = $field === 'profile_photo'
            ? ['jpg' => self::ALLOWED_EXT['jpg'], 'jpeg' => self::ALLOWED_EXT['jpeg'], 'png' => self::ALLOWED_EXT['png']]
            : self::ALLOWED_EXT;
        if (!isset($allowed[$ext])) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if ($mime !== $allowed[$ext]) {
            return null;
        }

        $dir = __DIR__ . '/../../storage/uploads/' . $userId;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            return null;
        }

        $filename = $field . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
            return null;
        }

        return $userId . '/' . $filename;
    }

    private function fieldLabel(string $field): string
    {
        return [
            'profile_photo' => 'profile photo',
            'aadhar_front'  => 'Aadhar card (front)',
            'aadhar_back'   => 'Aadhar card (back)',
            'pan_card_image' => 'PAN card',
        ][$field] ?? $field;
    }
}
