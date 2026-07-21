<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Session;
use App\Models\Patient;
use App\Models\User;

class AuthService
{
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
    }

    public static function logout(): void
    {
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

    public static function authenticate(string $email, string $password): ?User
    {
        $user = User::findByEmail($email);

        if (
            $user &&
            $user->status === 'active' &&
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
