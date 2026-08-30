<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Patient
{
    public ?int $user_id = null;
    public ?string $dob = null;
    public ?string $gender = null;
    public ?string $address = null;
    public ?string $phone = null;
    public ?string $medical_history = null;
    public ?string $profile_photo_path = null;

    public static function findByUserId(int $userId): ?self
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM patient WHERE user_id = :user_id LIMIT 1");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return self::fromArray($row);
        }

        return null;
    }

    public static function fromArray(array $row): self
    {
        $patient = new self();
        $patient->user_id = isset($row['user_id']) ? (int) $row['user_id'] : null;
        $patient->dob = $row['dob'] ?? null;
        $patient->gender = $row['gender'] ?? null;
        $patient->address = $row['address'] ?? null;
        $patient->phone = $row['phone'] ?? null;
        $patient->medical_history = $row['medical_history'] ?? null;
        $patient->profile_photo_path = $row['profile_photo_path'] ?? null;

        return $patient;
    }

    public function save(): bool
    {
        $db = Database::getInstance();

        if (self::findByUserId($this->user_id)) {
            $stmt = $db->prepare("UPDATE patient SET dob = :dob, gender = :gender, address = :address, phone = :phone, medical_history = :medical_history, profile_photo_path = :profile_photo_path WHERE user_id = :user_id");
        } else {
            $stmt = $db->prepare("INSERT INTO patient (user_id, dob, gender, address, phone, medical_history, profile_photo_path) VALUES (:user_id, :dob, :gender, :address, :phone, :medical_history, :profile_photo_path)");
        }

        $stmt->bindParam(':user_id', $this->user_id, PDO::PARAM_INT);
        $stmt->bindParam(':dob', $this->dob);
        $stmt->bindParam(':gender', $this->gender);
        $stmt->bindParam(':address', $this->address);
        $stmt->bindParam(':phone', $this->phone);
        $stmt->bindParam(':medical_history', $this->medical_history);
        $stmt->bindParam(':profile_photo_path', $this->profile_photo_path);

        return $stmt->execute();
    }

    public static function findProfileDetailByUserId(int $userId): ?array
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
                patient.dob,
                patient.gender,
                patient.address,
                patient.phone,
                patient.medical_history,
                patient.profile_photo_path
            FROM patient
            INNER JOIN users ON users.id = patient.user_id
            INNER JOIN roles ON roles.id = users.role_id
            WHERE roles.name = 'patient' AND users.id = :user_id
            LIMIT 1"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function getManagementSummary(): array
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT
                COUNT(*) AS total_patients,
                SUM(CASE WHEN users.status = 'active' THEN 1 ELSE 0 END) AS active_patients,
                SUM(CASE WHEN users.status = 'inactive' THEN 1 ELSE 0 END) AS inactive_patients
            FROM patient
            INNER JOIN users ON users.id = patient.user_id
            WHERE users.status <> 'deleted'"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_patients' => (int) ($row['total_patients'] ?? 0),
            'active_patients' => (int) ($row['active_patients'] ?? 0),
            'inactive_patients' => (int) ($row['inactive_patients'] ?? 0),
        ];
    }

    public static function countForManagement(array $filters = []): int
    {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*)
            FROM patient
            INNER JOIN users ON users.id = patient.user_id
            INNER JOIN roles ON roles.id = users.role_id";
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
                patient.dob,
                patient.gender,
                patient.address,
                patient.profile_photo_path
            FROM patient
            INNER JOIN users ON users.id = patient.user_id
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

    public static function findManagementDetailByUserId(int $userId): ?array
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
                patient.dob,
                patient.gender,
                patient.address,
                patient.phone,
                patient.medical_history,
                patient.profile_photo_path
            FROM patient
            INNER JOIN users ON users.id = patient.user_id
            INNER JOIN roles ON roles.id = users.role_id
            WHERE roles.name = 'patient' AND users.id = :user_id
            LIMIT 1"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private static function appendManagementFilters(array $filters, array &$conditions, array &$parameters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        $conditions[] = "roles.name = 'patient'";
        $conditions[] = "users.status <> 'deleted'";

        if ($search !== '') {
            $conditions[] = '(
                users.full_name LIKE :search_name
                OR users.email LIKE :search_email
                OR patient.address LIKE :search_address
            )';
            $searchValue = '%' . $search . '%';
            $parameters[':search_name'] = $searchValue;
            $parameters[':search_email'] = $searchValue;
            $parameters[':search_address'] = $searchValue;
        }

        if ($status !== '' && $status !== \App\Helpers\Status::USER_DELETED) {
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
