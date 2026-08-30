<?php

namespace App\Models;

use App\Core\Database;
use App\Helpers\Status;
use PDO;

class User
{
    public ?int $id = null;
    public ?int $role_id = null;
    public ?string $full_name = null;
    public ?string $email = null;
    public ?string $password = null;
    public ?string $status = null;
    public ?string $profile_photo_path = null;
    public ?string $last_login_at = null;
    public int $force_password_reset = 0;
    public ?string $password_changed_at = null;
    public ?string $deleted_at = null;
    public ?string $anonymized_at = null;
    public ?string $google_sub = null;
    public ?string $google_email = null;
    public string $auth_provider = 'local';

    public const AUTH_PROVIDER_LOCAL = 'local';
    public const AUTH_PROVIDER_GOOGLE = 'google';
    public const AUTH_PROVIDER_BOTH = 'both';

    public function __construct()
    {
    }

    public static function findById(int $id): ?self
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                users.*,
                COALESCE(admin.profile_photo_path, doctor.profile_photo_path, patient.profile_photo_path) AS profile_photo_path
            FROM users
            LEFT JOIN admin ON admin.user_id = users.id
            LEFT JOIN doctor ON doctor.user_id = users.id
            LEFT JOIN patient ON patient.user_id = users.id
            WHERE users.id = :id
            LIMIT 1"
        );
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return self::fromArray($row);
        }

        return null;
    }

    public static function findByEmail(string $email): ?self
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                users.*,
                COALESCE(admin.profile_photo_path, doctor.profile_photo_path, patient.profile_photo_path) AS profile_photo_path
            FROM users
            LEFT JOIN admin ON admin.user_id = users.id
            LEFT JOIN doctor ON doctor.user_id = users.id
            LEFT JOIN patient ON patient.user_id = users.id
            WHERE users.email = :email
            LIMIT 1"
        );
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return self::fromArray($row);
        }

        return null;
    }

    public static function findByEmailExcludingId(string $email, int $excludeId): ?self
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email AND id != :exclude_id LIMIT 1");
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromArray($row) : null;
    }

    public static function findByGoogleSub(string $sub): ?self
    {
        $sub = trim($sub);
        if ($sub === '') {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                users.*,
                COALESCE(admin.profile_photo_path, doctor.profile_photo_path, patient.profile_photo_path) AS profile_photo_path
            FROM users
            LEFT JOIN admin ON admin.user_id = users.id
            LEFT JOIN doctor ON doctor.user_id = users.id
            LEFT JOIN patient ON patient.user_id = users.id
            WHERE users.google_sub = :google_sub
            LIMIT 1"
        );
        $stmt->bindValue(':google_sub', $sub);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromArray($row) : null;
    }

    public static function fromArray(array $data): self
    {
        $user = new self();
        $user->id = $data['id'] ?? null;
        $user->role_id = $data['role_id'] ?? null;
        $user->full_name = $data['full_name'] ?? null;
        $user->email = $data['email'] ?? null;
        $user->password = $data['password'] ?? null;
        $user->status = $data['status'] ?? null;
        $user->profile_photo_path = $data['profile_photo_path'] ?? null;
        $user->last_login_at = $data['last_login_at'] ?? null;
        $user->force_password_reset = (int) ($data['force_password_reset'] ?? 0);
        $user->password_changed_at = $data['password_changed_at'] ?? null;
        $user->deleted_at = $data['deleted_at'] ?? null;
        $user->anonymized_at = $data['anonymized_at'] ?? null;
        $user->google_sub = isset($data['google_sub']) && $data['google_sub'] !== ''
            ? (string) $data['google_sub']
            : null;
        $user->google_email = isset($data['google_email']) && $data['google_email'] !== ''
            ? (string) $data['google_email']
            : null;
        $authProvider = (string) ($data['auth_provider'] ?? self::AUTH_PROVIDER_LOCAL);
        $user->auth_provider = in_array($authProvider, [
            self::AUTH_PROVIDER_LOCAL,
            self::AUTH_PROVIDER_GOOGLE,
            self::AUTH_PROVIDER_BOTH,
        ], true) ? $authProvider : self::AUTH_PROVIDER_LOCAL;
        return $user;
    }

    public function save(): bool
    {
        $db = Database::getInstance();

        if ($this->id === null) {
            $stmt = $db->prepare("INSERT INTO users (role_id, full_name, email, password, status) VALUES (:role_id, :full_name, :email, :password, :status)");
            $stmt->bindValue(':role_id', $this->role_id, PDO::PARAM_INT);
            $stmt->bindValue(':full_name', $this->full_name);
            $stmt->bindValue(':email', $this->email);
            $stmt->bindValue(':password', $this->password);
            $stmt->bindValue(':status', $this->status);

            if ($stmt->execute()) {
                $this->id = (int)$db->lastInsertId();
                return true;
            }
        } else {
            $stmt = $db->prepare("UPDATE users SET role_id = :role_id, full_name = :full_name, email = :email, password = :password, status = :status WHERE id = :id");
            $stmt->bindValue(':role_id', $this->role_id, PDO::PARAM_INT);
            $stmt->bindValue(':full_name', $this->full_name);
            $stmt->bindValue(':email', $this->email);
            $stmt->bindValue(':password', $this->password);
            $stmt->bindValue(':status', $this->status);
            $stmt->bindValue(':id', $this->id, PDO::PARAM_INT);

            return $stmt->execute();
        }

        return false;
    }

    /**
     * Insert a Google-authenticated users row. The caller supplies the verified
     * role_id. Password is always stored as NULL.
     *
     * @param array{
     *   role_id:int,
     *   full_name:string,
     *   email:string,
     *   google_sub:string,
     *   google_email?:string,
     *   status?:string
     * } $data
     */
    public static function createGoogleUser(array $data): ?self
    {
        $roleId = (int) ($data['role_id'] ?? 0);
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $googleSub = trim((string) ($data['google_sub'] ?? ''));
        $googleEmail = strtolower(trim((string) ($data['google_email'] ?? $email)));
        $status = trim((string) ($data['status'] ?? 'active'));

        if ($roleId <= 0 || $fullName === '' || $email === '' || $googleSub === '' || $googleEmail === '') {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT INTO users (
                role_id,
                full_name,
                email,
                password,
                google_sub,
                google_email,
                auth_provider,
                status
            ) VALUES (
                :role_id,
                :full_name,
                :email,
                :password,
                :google_sub,
                :google_email,
                :auth_provider,
                :status
            )"
        );
        $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->bindValue(':full_name', $fullName);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':password', null, PDO::PARAM_NULL);
        $stmt->bindValue(':google_sub', $googleSub);
        $stmt->bindValue(':google_email', $googleEmail);
        $stmt->bindValue(':auth_provider', self::AUTH_PROVIDER_GOOGLE);
        $stmt->bindValue(':status', $status);

        if (!$stmt->execute()) {
            return null;
        }

        return self::findById((int) $db->lastInsertId());
    }

    /**
     * Insert a local doctor users row waiting for password setup.
     * Password is stored as SQL NULL. Status is always invitation_pending.
     *
     * @param array{role_id:int,full_name:string,email:string} $data
     */
    public static function createInvitedDoctorUser(array $data): ?self
    {
        $roleId = (int) ($data['role_id'] ?? 0);
        $fullName = trim((string) ($data['full_name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        if ($roleId <= 0 || $fullName === '' || $email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT INTO users (
                role_id,
                full_name,
                email,
                password,
                auth_provider,
                status
            ) VALUES (
                :role_id,
                :full_name,
                :email,
                :password,
                :auth_provider,
                :status
            )"
        );
        $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->bindValue(':full_name', $fullName);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':password', null, PDO::PARAM_NULL);
        $stmt->bindValue(':auth_provider', self::AUTH_PROVIDER_LOCAL);
        $stmt->bindValue(':status', Status::USER_INVITATION_PENDING);

        if (!$stmt->execute()) {
            return null;
        }

        return self::findById((int) $db->lastInsertId());
    }

    public static function linkGoogleIdentity(int $userId, string $googleSub, string $googleEmail): bool
    {
        $googleSub = trim($googleSub);
        $googleEmail = strtolower(trim($googleEmail));
        if ($userId <= 0 || $googleSub === '' || $googleEmail === '') {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE users
            SET google_sub = :google_sub,
                google_email = :google_email,
                auth_provider = :auth_provider
            WHERE id = :id
              AND status <> 'deleted'
              AND google_sub IS NULL
              AND auth_provider = :required_provider"
        );

        return $stmt->execute([
            ':google_sub' => $googleSub,
            ':google_email' => $googleEmail,
            ':auth_provider' => self::AUTH_PROVIDER_BOTH,
            ':required_provider' => self::AUTH_PROVIDER_LOCAL,
            ':id' => $userId,
        ]) && $stmt->rowCount() > 0;
    }

    public static function updateGoogleEmail(int $userId, string $googleEmail): bool
    {
        $googleEmail = strtolower(trim($googleEmail));
        if ($userId <= 0 || $googleEmail === '') {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE users
            SET google_email = :google_email
            WHERE id = :id
              AND status <> 'deleted'"
        );

        return $stmt->execute([
            ':google_email' => $googleEmail,
            ':id' => $userId,
        ]) && $stmt->rowCount() > 0;
    }

    /**
     * Drop Google identity keys from a permanently deleted row so the same
     * Google account can register as a new patient. Never touches live users.
     */
    public static function releaseGoogleIdentityIfDeleted(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE users
            SET google_sub = NULL,
                google_email = NULL,
                auth_provider = :auth_provider
            WHERE id = :id
              AND status = 'deleted'"
        );

        return $stmt->execute([
            ':auth_provider' => self::AUTH_PROVIDER_LOCAL,
            ':id' => $userId,
        ]) && $stmt->rowCount() > 0;
    }

    public function getRole(): ?string
    {
        if ($this->role_id === null) {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT name FROM roles WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $this->role_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() ?: null;
    }

    public static function getRoleMap(): array
    {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT id, name FROM roles ORDER BY id ASC");
        $roleMap = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $role) {
            $roleMap[(int) $role['id']] = $role['name'];
        }

        return $roleMap;
    }

    public static function findRoleIdByName(string $roleName): ?int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM roles WHERE name = :name LIMIT 1");
        $stmt->bindValue(':name', $roleName);
        $stmt->execute();
        $roleId = $stmt->fetchColumn();

        return $roleId !== false ? (int) $roleId : null;
    }

    public static function updateIdentity(int $id, string $fullName, string $email): bool
    {
        $fullName = trim($fullName);
        $email = strtolower(trim($email));
        if ($id <= 0 || $fullName === '' || $email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE users
            SET full_name = :full_name,
                email = :email
            WHERE id = :id
              AND status <> 'deleted'"
        );

        return $stmt->execute([
            ':full_name' => $fullName,
            ':email' => $email,
            ':id' => $id,
        ]);
    }

    public static function updateStatus(int $id, string $status): bool
    {
        if (!\App\Helpers\Status::canAssignUserStatus($status)) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE users
            SET status = :status
            WHERE id = :id
              AND status <> 'deleted'"
        );

        return $stmt->execute([
            ':status' => \App\Helpers\Status::normalizeKey(\App\Helpers\Status::DOMAIN_USER, $status),
            ':id' => $id,
        ]) && $stmt->rowCount() > 0;
    }

    public static function updatePasswordHash(int $id, string $passwordHash): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE users
            SET password = :password,
                password_changed_at = NOW(),
                force_password_reset = 0
            WHERE id = :id
              AND status <> 'deleted'"
        );

        return $stmt->execute([
            ':password' => $passwordHash,
            ':id' => $id,
        ]);
    }

    /**
     * Invitation completion only. Activates a pending doctor whose password is still NULL.
     */
    public static function completeInvitationPassword(int $id, string $passwordHash): bool
    {
        if ($id <= 0 || $passwordHash === '') {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE users
            SET password = :password,
                password_changed_at = NOW(),
                force_password_reset = 0,
                status = :active_status
            WHERE id = :id
              AND status = :pending_status
              AND password IS NULL"
        );
        $stmt->bindValue(':password', $passwordHash);
        $stmt->bindValue(':active_status', Status::USER_ACTIVE);
        $stmt->bindValue(':pending_status', Status::USER_INVITATION_PENDING);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() === 1;
    }

    public static function setForcePasswordReset(int $id, bool $required = true): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE users
            SET force_password_reset = :flag
            WHERE id = :id
              AND status <> 'deleted'"
        );

        return $stmt->execute([
            ':flag' => $required ? 1 : 0,
            ':id' => $id,
        ]);
    }

    public static function touchLastLogin(int $id): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE users
            SET last_login_at = NOW()
            WHERE id = :id
              AND status = 'active'"
        );

        return $stmt->execute([':id' => $id]);
    }

    public static function requiresPasswordReset(int $id): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT force_password_reset
            FROM users
            WHERE id = :id
            LIMIT 1"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn() === 1;
    }

    public static function getUserManagementSummary(): array
    {
        $db = Database::getInstance();

        $summary = [
            'total_users' => 0,
            'active_users' => 0,
            'inactive_users' => 0,
            'suspended_users' => 0,
            'deleted_users' => 0,
            'admin_users' => 0,
            'doctor_users' => 0,
            'patient_users' => 0,
        ];

        $stmt = $db->query(
            "SELECT
                COUNT(*) AS total_users,
                SUM(CASE WHEN users.status = 'active' THEN 1 ELSE 0 END) AS active_users,
                SUM(CASE WHEN users.status = 'inactive' THEN 1 ELSE 0 END) AS inactive_users,
                SUM(CASE WHEN users.status = 'suspended' THEN 1 ELSE 0 END) AS suspended_users,
                SUM(CASE WHEN users.status = 'deleted' THEN 1 ELSE 0 END) AS deleted_users,
                SUM(CASE WHEN roles.name = 'admin' THEN 1 ELSE 0 END) AS admin_users,
                SUM(CASE WHEN roles.name = 'doctor' THEN 1 ELSE 0 END) AS doctor_users,
                SUM(CASE WHEN roles.name = 'patient' THEN 1 ELSE 0 END) AS patient_users
            FROM users
            INNER JOIN roles ON roles.id = users.role_id
            WHERE users.status <> 'deleted'"
        );

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            foreach ($summary as $key => $value) {
                $summary[$key] = (int) ($row[$key] ?? 0);
            }
        }

        return $summary;
    }

    public static function getLatestUsers(int $limit = 5): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                users.id,
                users.full_name,
                users.email,
                users.status,
                users.created_at,
                roles.name AS role_name,
                COALESCE(admin.profile_photo_path, doctor.profile_photo_path, patient.profile_photo_path) AS profile_photo_path
            FROM users
            INNER JOIN roles ON roles.id = users.role_id
            LEFT JOIN admin ON admin.user_id = users.id
            LEFT JOIN doctor ON doctor.user_id = users.id
            LEFT JOIN patient ON patient.user_id = users.id
            WHERE users.status <> 'deleted'
            ORDER BY users.created_at DESC, users.id DESC
            LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function countForManagement(array $filters = []): int
    {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) FROM users INNER JOIN roles ON roles.id = users.role_id";
        $conditions = [];
        $parameters = [];

        self::appendManagementFilters($filters, $conditions, $parameters);

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $stmt = $db->prepare($sql);
        self::bindManagementParameters($stmt, $parameters);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function findForManagement(array $filters = [], int $limit = 10, int $offset = 0): array
    {
        $db = Database::getInstance();
        $sql = "SELECT
                users.id,
                users.full_name,
                users.email,
                users.status,
                users.created_at,
                users.last_login_at,
                users.force_password_reset,
                roles.name AS role_name,
                COALESCE(admin.profile_photo_path, doctor.profile_photo_path, patient.profile_photo_path) AS profile_photo_path
            FROM users
            INNER JOIN roles ON roles.id = users.role_id
            LEFT JOIN admin ON admin.user_id = users.id
            LEFT JOIN doctor ON doctor.user_id = users.id
            LEFT JOIN patient ON patient.user_id = users.id";
        $conditions = [];
        $parameters = [];

        self::appendManagementFilters($filters, $conditions, $parameters);

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= self::managementOrderBy($filters);
        $sql .= ' LIMIT :limit OFFSET :offset';

        $stmt = $db->prepare($sql);
        self::bindManagementParameters($stmt, $parameters);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function findManagementDetailById(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                users.id,
                users.full_name,
                users.email,
                users.status,
                users.created_at,
                users.updated_at,
                users.last_login_at,
                users.force_password_reset,
                users.deleted_at,
                users.anonymized_at,
                roles.name AS role_name,
                COALESCE(admin.profile_photo_path, doctor.profile_photo_path, patient.profile_photo_path) AS profile_photo_path,
                admin.employee_id,
                doctor.phone,
                doctor.gender AS doctor_gender,
                doctor.professional_title,
                doctor.specialization,
                doctor.employee_id AS doctor_employee_id,
                doctor.license_number,
                doctor.clinic_address,
                patient.dob,
                patient.gender,
                patient.address
            FROM users
            INNER JOIN roles ON roles.id = users.role_id
            LEFT JOIN admin ON admin.user_id = users.id
            LEFT JOIN doctor ON doctor.user_id = users.id
            LEFT JOIN patient ON patient.user_id = users.id
            WHERE users.id = :id
            LIMIT 1"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function findDeletionContextById(int $id): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                users.id,
                users.full_name,
                users.email,
                users.status,
                roles.name AS role_name,
                admin.profile_photo_path AS admin_profile_photo_path,
                doctor.profile_photo_path AS doctor_profile_photo_path,
                doctor.signature_path,
                patient.profile_photo_path AS patient_profile_photo_path
            FROM users
            INNER JOIN roles ON roles.id = users.role_id
            LEFT JOIN admin ON admin.user_id = users.id
            LEFT JOIN doctor ON doctor.user_id = users.id
            LEFT JOIN patient ON patient.user_id = users.id
            WHERE users.id = :id
            LIMIT 1"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function countAdministrators(): int
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT COUNT(*)
            FROM users
            INNER JOIN roles ON roles.id = users.role_id
            WHERE roles.name = 'admin'
              AND users.status <> 'deleted'"
        );

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<int>
     */
    public static function findActiveAdminIds(): array
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT users.id
            FROM users
            INNER JOIN roles ON roles.id = users.role_id
            INNER JOIN admin ON admin.user_id = users.id
            WHERE roles.name = 'admin'
              AND users.status = 'active'
            ORDER BY users.id ASC"
        );
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return array_values(array_map('intval', $ids));
    }

    /**
     * @return list<array{id:int,full_name:string,email:string,role_name:string}>
     */
    public static function findAdminAuditUserOptions(): array
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT users.id, users.full_name, users.email, roles.name AS role_name
            FROM users
            INNER JOIN roles ON roles.id = users.role_id
            WHERE users.status = 'active'
            ORDER BY users.full_name ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function countActiveAdministrators(): int
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT COUNT(*)
            FROM users
            INNER JOIN roles ON roles.id = users.role_id
            WHERE roles.name = 'admin'
              AND users.status = 'active'"
        );

        return (int) $stmt->fetchColumn();
    }

    /**
     * Direct DELETE FROM users is forbidden. Use UserDeletionService so
     * clinical records, prescriptions, and audit logs are preserved.
     */
    public static function deleteById(int $id): bool
    {
        throw new \LogicException(
            'Direct user row deletion is not permitted. Use the anonymizing UserDeletionService.'
        );
    }

    private static function appendManagementFilters(array $filters, array &$conditions, array &$parameters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $role = trim((string) ($filters['role'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        $conditions[] = "users.status <> 'deleted'";

        if ($search !== '') {
            $conditions[] = '(users.full_name LIKE :search_name OR users.email LIKE :search_email)';
            $parameters[':search_name'] = '%' . $search . '%';
            $parameters[':search_email'] = '%' . $search . '%';
        }

        if ($role !== '') {
            $conditions[] = 'roles.name = :role';
            $parameters[':role'] = $role;
        }

        if ($status !== '' && $status !== \App\Helpers\Status::USER_DELETED) {
            $conditions[] = 'users.status = :status';
            $parameters[':status'] = $status;
        }
    }

    /**
     * @param array<string, mixed> $filters
     */
    private static function managementOrderBy(array $filters): string
    {
        $sort = trim((string) ($filters['sort'] ?? 'newest'));

        return match ($sort) {
            'name' => ' ORDER BY users.full_name ASC, users.id ASC',
            'email' => ' ORDER BY users.email ASC, users.id ASC',
            'last_login' => ' ORDER BY users.last_login_at IS NULL ASC, users.last_login_at DESC, users.id DESC',
            'oldest' => ' ORDER BY users.created_at ASC, users.id ASC',
            default => ' ORDER BY users.created_at DESC, users.id DESC',
        };
    }

    private static function bindManagementParameters(\PDOStatement $stmt, array $parameters): void
    {
        foreach ($parameters as $name => $value) {
            $stmt->bindValue($name, $value, PDO::PARAM_STR);
        }
    }
}
