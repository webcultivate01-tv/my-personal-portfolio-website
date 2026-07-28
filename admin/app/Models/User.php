<?php
namespace App\Models;

use App\Core\Model;

class User extends Model
{
    /** Roles the app understands. */
    public const ROLES = ['admin', 'manager'];

    /**
     * Hire-time fields. Once a value is set, a manager can never edit it again
     * (only an admin can, from Admin Management). Collected in full when an
     * admin adds a new team member — see UserController::store().
     */
    public const LOCKED_ONCE_SET = [
        'email', 'phone', 'alternate_phone', 'address', 'aadhar_number', 'pan_number',
    ];

    /** HR-controlled fields — an admin sets/edits these, a manager only views them. */
    public const ADMIN_ONLY = ['name', 'role', 'designation', 'date_of_joining'];

    /** A manager may edit these on their own account at any time, no locking. */
    public const SELF_EDITABLE = ['date_of_birth', 'emergency_contact_name', 'emergency_contact_phone'];

    /** Identity documents a manager must upload on first sign-in. Locked once uploaded. */
    public const DOCUMENT_FIELDS = ['profile_photo', 'aadhar_front', 'aadhar_back', 'pan_card_image'];

    private const PROFILE_COLUMNS = 'id, name, email, phone, alternate_phone, address,
        aadhar_number, pan_number, designation, date_of_joining, date_of_birth,
        emergency_contact_name, emergency_contact_phone,
        profile_photo, aadhar_front, aadhar_back, pan_card_image,
        role, created_at';

    public function findByEmail(string $email): ?array
    {
        return $this->one(
            'SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1',
            [$email]
        );
    }

    public function findById(int $id): ?array
    {
        return $this->one(
            'SELECT ' . self::PROFILE_COLUMNS . ' FROM users WHERE id = ? LIMIT 1',
            [$id]
        );
    }

    /** Like findById but includes the password hash — for verifying a login. */
    public function findAuthById(int $id): ?array
    {
        return $this->one(
            'SELECT id, name, email, password_hash, role FROM users WHERE id = ? LIMIT 1',
            [$id]
        );
    }

    /** Everyone — admins first, then by join date — for the management screen. */
    public function allUsers(): array
    {
        return $this->all(
            'SELECT ' . self::PROFILE_COLUMNS . ' FROM users ORDER BY role ASC, created_at ASC'
        );
    }

    /** True if an account already uses this email (optionally excluding one user, for edits). */
    public function emailExists(string $email, int $excludeId = 0): bool
    {
        if ($excludeId > 0) {
            return $this->one('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1', [$email, $excludeId]) !== null;
        }
        return $this->one('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]) !== null;
    }

    /** How many admins exist — used to stop the last admin being removed. */
    public function adminCount(): int
    {
        $row = $this->one("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'");
        return (int) ($row['c'] ?? 0);
    }

    /**
     * Create a fully onboarded user — every hire-time field, like a company
     * would collect when hiring an employee. $profile keys match the
     * LOCKED_ONCE_SET and ADMIN_ONLY field names (minus name/email/role,
     * passed separately).
     */
    public function create(string $name, string $email, string $plainPassword, string $role, array $profile = []): int
    {
        $role = in_array($role, self::ROLES, true) ? $role : 'manager';
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

        $this->run(
            'INSERT INTO users (
                name, email, password_hash, role,
                phone, alternate_phone, address, aadhar_number, pan_number,
                designation, date_of_joining, date_of_birth,
                emergency_contact_name, emergency_contact_phone
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $name, $email, $hash, $role,
                $profile['phone'] ?? null,
                $profile['alternate_phone'] ?? null,
                $profile['address'] ?? null,
                $profile['aadhar_number'] ?? null,
                $profile['pan_number'] ?? null,
                $profile['designation'] ?? null,
                $profile['date_of_joining'] ?: null,
                $profile['date_of_birth'] ?: null,
                $profile['emergency_contact_name'] ?? null,
                $profile['emergency_contact_phone'] ?? null,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * Admin edit — an admin may update any profile field on any user,
     * including ones that are locked for the account owner.
     */
    public function adminUpdateProfile(int $id, array $profile): void
    {
        $this->run(
            'UPDATE users SET
                name = ?, email = ?, phone = ?, alternate_phone = ?, address = ?,
                aadhar_number = ?, pan_number = ?, designation = ?,
                date_of_joining = ?, date_of_birth = ?,
                emergency_contact_name = ?, emergency_contact_phone = ?
             WHERE id = ?',
            [
                $profile['name'] ?? '',
                $profile['email'] ?? '',
                $profile['phone'] ?? null,
                $profile['alternate_phone'] ?? null,
                $profile['address'] ?? null,
                $profile['aadhar_number'] ?? null,
                $profile['pan_number'] ?? null,
                $profile['designation'] ?? null,
                $profile['date_of_joining'] ?: null,
                $profile['date_of_birth'] ?: null,
                $profile['emergency_contact_name'] ?? null,
                $profile['emergency_contact_phone'] ?? null,
                $id,
            ]
        );
    }

    /**
     * Self-service edit for the signed-in user. Only ever touches
     * SELF_EDITABLE columns (date_of_birth, emergency contact) — locked and
     * admin-only fields are never written here, no matter what was posted.
     */
    public function updateSelfEditable(int $id, array $profile): void
    {
        $this->run(
            'UPDATE users SET date_of_birth = ?, emergency_contact_name = ?, emergency_contact_phone = ? WHERE id = ?',
            [
                $profile['date_of_birth'] ?: null,
                $profile['emergency_contact_name'] ?? null,
                $profile['emergency_contact_phone'] ?? null,
                $id,
            ]
        );
    }

    /** Save one uploaded document path. Caller must have already checked it isn't locked. */
    public function setDocument(int $id, string $field, string $path): void
    {
        if (!in_array($field, self::DOCUMENT_FIELDS, true)) {
            throw new \InvalidArgumentException('Unknown document field: ' . $field);
        }
        $this->run("UPDATE users SET {$field} = ? WHERE id = ?", [$path, $id]);
    }

    /** True once every onboarding document has been uploaded. */
    public function hasCompletedDocuments(array $user): bool
    {
        foreach (self::DOCUMENT_FIELDS as $field) {
            if (empty($user[$field])) {
                return false;
            }
        }
        return true;
    }

    /** Replace a user's password with a new securely hashed one. */
    public function updatePassword(int $id, string $plainPassword): void
    {
        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $this->run('UPDATE users SET password_hash = ? WHERE id = ?', [$hash, $id]);
    }

    /** Remove a user account. */
    public function delete(int $id): void
    {
        $this->run('DELETE FROM users WHERE id = ?', [$id]);
    }
}
