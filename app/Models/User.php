<?php

namespace App\Models;

use App\Core\Database;
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
        return $user;
    }

    public function save(): bool
    {
        $db = Database::getInstance();

        if ($this->id === null) {
            $stmt = $db->prepare("INSERT INTO users (role_id, full_name, email, password, status) VALUES (:role_id, :full_name, :email, :password, :status)");
            $stmt->bindParam(':role_id', $this->role_id, PDO::PARAM_INT);
            $stmt->bindParam(':full_name', $this->full_name);
            $stmt->bindParam(':email', $this->email);
            $stmt->bindParam(':password', $this->password);
            $stmt->bindParam(':status', $this->status);

            if ($stmt->execute()) {
                $this->id = (int)$db->lastInsertId();
                return true;
            }
        } else {
            $stmt = $db->prepare("UPDATE users SET role_id = :role_id, full_name = :full_name, email = :email, password = :password, status = :status WHERE id = :id");
            $stmt->bindParam(':role_id', $this->role_id, PDO::PARAM_INT);
            $stmt->bindParam(':full_name', $this->full_name);
            $stmt->bindParam(':email', $this->email);
            $stmt->bindParam(':password', $this->password);
            $stmt->bindParam(':status', $this->status);
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);

            return $stmt->execute();
        }

        return false;
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

    public static function updateStatus(int $id, string $status): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE users SET status = :status WHERE id = :id");

        return $stmt->execute([
            ':status' => $status,
            ':id' => $id,
        ]);
    }

    public static function updatePasswordHash(int $id, string $passwordHash): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");

        return $stmt->execute([
            ':password' => $passwordHash,
            ':id' => $id,
        ]);
    }

    public static function getUserManagementSummary(): array
    {
        $db = Database::getInstance();

        $summary = [
            'total_users' => 0,
            'active_users' => 0,
            'inactive_users' => 0,
            'admin_users' => 0,
            'doctor_users' => 0,
            'patient_users' => 0,
        ];

        $stmt = $db->query(
            "SELECT
                COUNT(*) AS total_users,
                SUM(CASE WHEN users.status = 'active' THEN 1 ELSE 0 END) AS active_users,
                SUM(CASE WHEN users.status = 'inactive' THEN 1 ELSE 0 END) AS inactive_users,
                SUM(CASE WHEN roles.name = 'admin' THEN 1 ELSE 0 END) AS admin_users,
                SUM(CASE WHEN roles.name = 'doctor' THEN 1 ELSE 0 END) AS doctor_users,
                SUM(CASE WHEN roles.name = 'patient' THEN 1 ELSE 0 END) AS patient_users
            FROM users
            INNER JOIN roles ON roles.id = users.role_id"
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
                roles.name AS role_name
            FROM users
            INNER JOIN roles ON roles.id = users.role_id
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
                roles.name AS role_name
            FROM users
            INNER JOIN roles ON roles.id = users.role_id";
        $conditions = [];
        $parameters = [];

        self::appendManagementFilters($filters, $conditions, $parameters);

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= ' ORDER BY users.created_at DESC, users.id DESC LIMIT :limit OFFSET :offset';

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
                roles.name AS role_name,
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
            WHERE roles.name = 'admin'"
        );

        return (int) $stmt->fetchColumn();
    }

    public static function deleteById(int $id): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id");

        return $stmt->execute([
            ':id' => $id,
        ]);
    }

    private static function appendManagementFilters(array $filters, array &$conditions, array &$parameters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $role = trim((string) ($filters['role'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        if ($search !== '') {
            $conditions[] = '(users.full_name LIKE :search_name OR users.email LIKE :search_email)';
            $parameters[':search_name'] = '%' . $search . '%';
            $parameters[':search_email'] = '%' . $search . '%';
        }

        if ($role !== '') {
            $conditions[] = 'roles.name = :role';
            $parameters[':role'] = $role;
        }

        if ($status !== '') {
            $conditions[] = 'users.status = :status';
            $parameters[':status'] = $status;
        }
    }

    private static function bindManagementParameters(\PDOStatement $stmt, array $parameters): void
    {
        foreach ($parameters as $name => $value) {
            $stmt->bindValue($name, $value, PDO::PARAM_STR);
        }
    }
}
