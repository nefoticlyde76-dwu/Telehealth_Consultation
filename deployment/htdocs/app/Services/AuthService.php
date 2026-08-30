<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use App\Helpers\Status;
use App\Models\Patient;
use App\Models\User;

class AuthService
{
    /**
     * Test-only hook used by bin/test_register_google_patient.php to prove
     * user+patient creation rolls back together. Production callers must leave
     * this false.
     */
    public static bool $testFailGooglePatientInsert = false;

    public static function isAuthenticated(): bool
    {
        return Session::has('user_id');
    }

    public static function getUserId(): ?int
    {
        return Session::get('user_id');
    }

    public static function getUserRole(): ?string
    {
        return Session::get('user_role');
    }

    public static function getUser(): ?User
    {
        $userId = self::getUserId();
        if ($userId) {
            return User::findById($userId);
        }
        return null;
    }

    public static function login(int $userId, string $role): void
    {
        Session::regenerate();
        Session::set('user_id', $userId);
        Session::set('user_role', $role);
        User::touchLastLogin($userId);
        SessionService::registerCurrent($userId);
    }

    public static function logout(): void
    {
        $userId = self::getUserId();
        if ($userId !== null) {
            SessionService::destroyCurrent($userId);
        }
        Session::destroy();
    }

    public static function register(array $data): ?User
    {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT id FROM roles WHERE name = 'patient' LIMIT 1");
        $patientRoleId = $stmt->fetchColumn();

        if (!$patientRoleId || User::findByEmail($data['email'])) {
            return null;
        }

        try {
            $db->beginTransaction();

            $user = new User();
            $user->role_id = (int) $patientRoleId;
            $user->full_name = $data['full_name'];
            $user->email = $data['email'];
            $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
            $user->status = 'active';

            if (!$user->save()) {
                $db->rollBack();
                return null;
            }

            $patient = new Patient();
            $patient->user_id = $user->id;
            $patient->dob = $data['dob'] ?: null;
            $patient->gender = $data['gender'] ?: null;
            $patient->address = $data['address'] ?: null;

            if (!$patient->save()) {
                $db->rollBack();
                return null;
            }

            $db->commit();

            return $user;
        } catch (\Throwable) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            return null;
        }
    }

    /**
     * Create a brand-new Google patient from an already-validated identity.
     * Does not validate JWTs, create sessions, or link existing accounts.
     *
     * @param array{sub?:mixed,email?:mixed,email_verified?:mixed,name?:mixed} $identity
     */
    public static function registerGooglePatient(array $identity): ?User
    {
        $sub = trim((string) ($identity['sub'] ?? ''));
        $email = strtolower(trim((string) ($identity['email'] ?? '')));
        $emailVerified = $identity['email_verified'] ?? false;
        $name = trim((string) ($identity['name'] ?? ''));

        if ($sub === '' || $email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || $emailVerified !== true) {
            return null;
        }

        if ($name === '') {
            $name = 'Patient';
        } elseif (mb_strlen($name) > 255) {
            $name = mb_substr($name, 0, 255);
        }

        $db = Database::getInstance();
        $stmt = $db->query("SELECT id FROM roles WHERE name = 'patient' LIMIT 1");
        $patientRoleId = $stmt->fetchColumn();

        if (!$patientRoleId) {
            error_log('[AuthService::registerGooglePatient] Patient role is not configured.');
            return null;
        }

        if (User::findByGoogleSub($sub) !== null || User::findByEmail($email) !== null) {
            return null;
        }

        try {
            $db->beginTransaction();

            $user = User::createGoogleUser([
                'role_id' => (int) $patientRoleId,
                'full_name' => $name,
                'email' => $email,
                'google_sub' => $sub,
                'google_email' => $email,
                'status' => 'active',
            ]);

            if ($user === null || $user->id === null) {
                $db->rollBack();
                return null;
            }

            if (self::$testFailGooglePatientInsert) {
                throw new \RuntimeException('Simulated patient profile insert failure.');
            }

            $patient = new Patient();
            $patient->user_id = $user->id;
            $patient->dob = null;
            $patient->gender = null;
            $patient->address = null;

            if (!$patient->save()) {
                $db->rollBack();
                error_log('[AuthService::registerGooglePatient] Patient profile insert failed for user id ' . $user->id);
                return null;
            }

            $db->commit();

            return $user;
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log('[AuthService::registerGooglePatient] ' . $exception->getMessage());

            return null;
        }
    }

    public static function authenticate(string $email, string $password): ?User
    {
        $user = User::findByEmail($email);

        if (
            $user &&
            Status::canAuthenticateUserStatus((string) $user->status) &&
            is_string($user->password) &&
            $user->password !== '' &&
            password_verify($password, $user->password)
        ) {
            return $user;
        }

        return null;
    }

    public static function getRoleRedirectUrl(string $role): string
    {
        $urls = [
            'admin' => '/admin/dashboard',
            'doctor' => '/doctor/dashboard',
            'patient' => '/patient/dashboard',
        ];

        return $urls[$role] ?? '/';
    }
}
