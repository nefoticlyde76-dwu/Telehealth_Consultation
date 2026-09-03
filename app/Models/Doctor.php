<?php

namespace App\Models;

use App\Core\Database;
use App\Helpers\Helper;
use App\Helpers\ListFilter;
use PDO;

class Doctor
{
    public ?int $user_id = null;
    public ?string $phone = null;
    public ?string $gender = null;
    public ?string $professional_title = null;
    public ?string $specialization = null;
    public ?string $employee_id = null;
    public ?string $license_number = null;
    public ?string $clinic_address = null;
    public ?float $consultation_fee = null;
    public ?string $signature_path = null;
    public ?string $consent_doc_path = null;
    public ?string $profile_photo_path = null;

    public static function findByUserId(int $userId): ?self
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM doctor WHERE user_id = :user_id LIMIT 1");
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
        $doctor = new self();
        $doctor->user_id = isset($row['user_id']) ? (int) $row['user_id'] : null;
        $doctor->phone = $row['phone'] ?? null;
        $doctor->gender = $row['gender'] ?? null;
        $doctor->professional_title = $row['professional_title'] ?? null;
        $doctor->specialization = $row['specialization'] ?? null;
        $doctor->employee_id = $row['employee_id'] ?? null;
        $doctor->license_number = $row['license_number'] ?? null;
        $doctor->clinic_address = $row['clinic_address'] ?? null;
        $doctor->consultation_fee = isset($row['consultation_fee']) ? (float) $row['consultation_fee'] : null;
        $doctor->signature_path = $row['signature_path'] ?? null;
        $doctor->consent_doc_path = $row['consent_doc_path'] ?? null;
        $doctor->profile_photo_path = $row['profile_photo_path'] ?? null;

        return $doctor;
    }

    public function save(): bool
    {
        if ($this->user_id === null) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT INTO doctor (
                user_id,
                phone,
                gender,
                professional_title,
                specialization,
                employee_id,
                license_number,
                clinic_address,
                consultation_fee,
                signature_path,
                consent_doc_path,
                profile_photo_path
            ) VALUES (
                :user_id,
                :phone,
                :gender,
                :professional_title,
                :specialization,
                :employee_id,
                :license_number,
                :clinic_address,
                :consultation_fee,
                :signature_path,
                :consent_doc_path,
                :profile_photo_path
            )"
        );

        return $stmt->execute([
            ':user_id' => $this->user_id,
            ':phone' => $this->phone,
            ':gender' => $this->gender,
            ':professional_title' => $this->professional_title,
            ':specialization' => $this->specialization,
            ':employee_id' => $this->employee_id,
            ':license_number' => $this->license_number,
            ':clinic_address' => $this->clinic_address,
            ':consultation_fee' => $this->consultation_fee,
            ':signature_path' => $this->signature_path,
            ':consent_doc_path' => $this->consent_doc_path,
            ':profile_photo_path' => $this->profile_photo_path,
        ]);
    }

    public function update(): bool
    {
        if ($this->user_id === null) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE doctor
            SET
                phone = :phone,
                gender = :gender,
                professional_title = :professional_title,
                specialization = :specialization,
                employee_id = :employee_id,
                signature_path = :signature_path,
                profile_photo_path = :profile_photo_path
            WHERE user_id = :user_id"
        );

        return $stmt->execute([
            ':phone' => $this->phone,
            ':gender' => $this->gender,
            ':professional_title' => $this->professional_title,
            ':specialization' => $this->specialization,
            ':employee_id' => $this->employee_id,
            ':signature_path' => $this->signature_path,
            ':profile_photo_path' => $this->profile_photo_path,
            ':user_id' => $this->user_id,
        ]);
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
                doctor.phone,
                doctor.gender,
                doctor.professional_title,
                doctor.specialization,
                doctor.license_number,
                doctor.employee_id,
                doctor.signature_path,
                doctor.profile_photo_path
            FROM doctor
            INNER JOIN users ON users.id = doctor.user_id
            INNER JOIN roles ON roles.id = users.role_id
            WHERE roles.name = 'doctor' AND users.id = :user_id
            LIMIT 1"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function findByEmployeeId(string $employeeId, ?int $excludeUserId = null): ?self
    {
        $employeeId = trim($employeeId);

        if ($employeeId === '') {
            return null;
        }

        $db = Database::getInstance();
        $sql = "SELECT * FROM doctor WHERE employee_id = :employee_id";

        if ($excludeUserId !== null) {
            $sql .= " AND user_id != :exclude_user_id";
        }

        $sql .= " LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':employee_id', $employeeId);

        if ($excludeUserId !== null) {
            $stmt->bindValue(':exclude_user_id', $excludeUserId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromArray($row) : null;
    }

    public static function getManagementSummary(): array
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT
                COUNT(*) AS total_doctors,
                SUM(CASE WHEN users.status = 'active' THEN 1 ELSE 0 END) AS active_doctors,
                SUM(CASE WHEN users.status = 'invitation_pending' THEN 1 ELSE 0 END) AS pending_doctors,
                SUM(CASE WHEN users.status = 'inactive' THEN 1 ELSE 0 END) AS inactive_doctors,
                SUM(CASE WHEN users.status IN ('inactive', 'suspended') THEN 1 ELSE 0 END) AS inactive_suspended_doctors
            FROM doctor
            INNER JOIN users ON users.id = doctor.user_id
            WHERE users.status <> 'deleted'"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_doctors' => (int) ($row['total_doctors'] ?? 0),
            'active_doctors' => (int) ($row['active_doctors'] ?? 0),
            'pending_doctors' => (int) ($row['pending_doctors'] ?? 0),
            'inactive_doctors' => (int) ($row['inactive_doctors'] ?? 0),
            'inactive_suspended_doctors' => (int) ($row['inactive_suspended_doctors'] ?? 0),
        ];
    }

    public static function countForManagement(array $filters = []): int
    {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*)
            FROM doctor
            INNER JOIN users ON users.id = doctor.user_id
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
                doctor.phone,
                doctor.gender,
                doctor.professional_title,
                doctor.specialization,
                doctor.employee_id,
                doctor.profile_photo_path
            FROM doctor
            INNER JOIN users ON users.id = doctor.user_id
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
                doctor.phone,
                doctor.gender,
                doctor.professional_title,
                doctor.specialization,
                doctor.employee_id,
                doctor.profile_photo_path
            FROM doctor
            INNER JOIN users ON users.id = doctor.user_id
            INNER JOIN roles ON roles.id = users.role_id
            WHERE roles.name = 'doctor' AND users.id = :user_id
            LIMIT 1"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function countForPatientDirectory(): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT COUNT(*)
            FROM doctor
            INNER JOIN users ON users.id = doctor.user_id
            INNER JOIN roles ON roles.id = users.role_id
            CROSS JOIN (SELECT CAST(:slot_now AS DATETIME) AS slot_now) AS slot_clock
            WHERE roles.name = 'doctor'
              AND users.status = 'active'
              AND EXISTS (
                  SELECT 1
                  FROM doctor_availability
                  WHERE doctor_availability.doctor_id = doctor.user_id
                    AND " . DoctorAvailability::stillBookableSql('doctor_availability', 'slot_clock.slot_now') . "
              )"
        );
        $stmt->bindValue(':slot_now', Helper::nowDatetime());
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function findForPatientDirectory(int $limit = 6, int $offset = 0): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                users.id,
                users.full_name,
                users.email,
                doctor.professional_title,
                doctor.specialization,
                doctor.profile_photo_path,
                (
                    SELECT COUNT(*)
                    FROM doctor_availability
                    WHERE doctor_availability.doctor_id = doctor.user_id
                      AND " . DoctorAvailability::stillBookableSql('doctor_availability', 'slot_clock.slot_now') . "
                ) AS available_slot_count,
                (
                    SELECT MIN(doctor_availability.consultation_date)
                    FROM doctor_availability
                    WHERE doctor_availability.doctor_id = doctor.user_id
                      AND " . DoctorAvailability::stillBookableSql('doctor_availability', 'slot_clock.slot_now') . "
                ) AS next_available_date
            FROM doctor
            INNER JOIN users ON users.id = doctor.user_id
            INNER JOIN roles ON roles.id = users.role_id
            CROSS JOIN (SELECT CAST(:slot_now AS DATETIME) AS slot_now) AS slot_clock
            WHERE roles.name = 'doctor'
              AND users.status = 'active'
              AND EXISTS (
                  SELECT 1
                  FROM doctor_availability
                  WHERE doctor_availability.doctor_id = doctor.user_id
                    AND " . DoctorAvailability::stillBookableSql('doctor_availability', 'slot_clock.slot_now') . "
              )
            ORDER BY next_available_date ASC, users.full_name ASC
            LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':slot_now', Helper::nowDatetime());
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function getSpecializationOptionsForPatients(): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT DISTINCT doctor.specialization
            FROM doctor
            INNER JOIN users ON users.id = doctor.user_id
            INNER JOIN roles ON roles.id = users.role_id
            INNER JOIN doctor_availability ON doctor_availability.doctor_id = doctor.user_id
            WHERE roles.name = 'doctor'
              AND users.status = 'active'
              AND doctor.specialization IS NOT NULL
              AND doctor.specialization != ''
              AND " . DoctorAvailability::stillBookableSql('doctor_availability') . "
            ORDER BY doctor.specialization ASC"
        );
        $stmt->bindValue(':slot_now', Helper::nowDatetime());
        $stmt->execute();

        return array_map(
            static fn (array $row): string => (string) $row['specialization'],
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );
    }

    private static function appendManagementFilters(array $filters, array &$conditions, array &$parameters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        $conditions[] = "roles.name = 'doctor'";
        $conditions[] = "users.status <> 'deleted'";

        if ($search !== '') {
            $conditions[] = '(
                users.full_name LIKE :search_name
                OR users.email LIKE :search_email
                OR doctor.specialization LIKE :search_specialization
                OR doctor.professional_title LIKE :search_professional_title
                OR doctor.employee_id LIKE :search_employee_id
            )';
            $searchValue = '%' . $search . '%';
            $parameters[':search_name'] = $searchValue;
            $parameters[':search_email'] = $searchValue;
            $parameters[':search_specialization'] = $searchValue;
            $parameters[':search_professional_title'] = $searchValue;
            $parameters[':search_employee_id'] = $searchValue;
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
