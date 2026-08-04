<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class DoctorAvailability
{
    public ?int $id = null;
    public ?int $doctor_id = null;
    public ?string $consultation_date = null;
    public ?string $start_time = null;
    public ?string $end_time = null;
    public ?string $notes = null;
    public ?string $status = null;

    public static function fromArray(array $row): self
    {
        $availability = new self();
        $availability->id = isset($row['id']) ? (int) $row['id'] : null;
        $availability->doctor_id = isset($row['doctor_id']) ? (int) $row['doctor_id'] : null;
        $availability->consultation_date = $row['consultation_date'] ?? null;
        $availability->start_time = $row['start_time'] ?? null;
        $availability->end_time = $row['end_time'] ?? null;
        $availability->notes = $row['notes'] ?? null;
        $availability->status = $row['status'] ?? null;

        return $availability;
    }

    public static function findByIdForDoctor(int $availabilityId, int $doctorId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT *
            FROM doctor_availability
            WHERE id = :id AND doctor_id = :doctor_id
            LIMIT 1"
        );
        $stmt->bindValue(':id', $availabilityId, PDO::PARAM_INT);
        $stmt->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function save(): bool
    {
        $db = Database::getInstance();

        if ($this->doctor_id === null) {
            return false;
        }

        if ($this->id === null) {
            $stmt = $db->prepare(
                "INSERT INTO doctor_availability (
                    doctor_id,
                    consultation_date,
                    start_time,
                    end_time,
                    notes,
                    status
                ) VALUES (
                    :doctor_id,
                    :consultation_date,
                    :start_time,
                    :end_time,
                    :notes,
                    :status
                )"
            );
        } else {
            $stmt = $db->prepare(
                "UPDATE doctor_availability
                SET consultation_date = :consultation_date,
                    start_time = :start_time,
                    end_time = :end_time,
                    notes = :notes,
                    status = :status
                WHERE id = :id AND doctor_id = :doctor_id"
            );
        }

        $parameters = [
            ':doctor_id' => $this->doctor_id,
            ':consultation_date' => $this->consultation_date,
            ':start_time' => $this->start_time,
            ':end_time' => $this->end_time,
            ':notes' => $this->notes,
            ':status' => $this->status,
        ];

        if ($this->id !== null) {
            $parameters[':id'] = $this->id;
        }

        $result = $stmt->execute($parameters);

        if ($result && $this->id === null) {
            $this->id = (int) $db->lastInsertId();
        }

        return $result;
    }

    public static function deleteForDoctor(int $availabilityId, int $doctorId): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "DELETE FROM doctor_availability
            WHERE id = :id AND doctor_id = :doctor_id"
        );

        return $stmt->execute([
            ':id' => $availabilityId,
            ':doctor_id' => $doctorId,
        ]);
    }

    public static function countForDoctor(int $doctorId, array $filters = []): int
    {
        $db = Database::getInstance();
        $sql = 'SELECT COUNT(*) FROM doctor_availability';
        $conditions = ['doctor_id = :doctor_id'];
        $parameters = [':doctor_id' => $doctorId];

        self::appendFilters($filters, $conditions, $parameters);

        $sql .= ' WHERE ' . implode(' AND ', $conditions);
        $stmt = $db->prepare($sql);
        self::bindParameters($stmt, $parameters);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function findForDoctor(int $doctorId, array $filters = [], int $limit = 10, int $offset = 0): array
    {
        $db = Database::getInstance();
        $sql = 'SELECT * FROM doctor_availability';
        $conditions = ['doctor_id = :doctor_id'];
        $parameters = [':doctor_id' => $doctorId];

        self::appendFilters($filters, $conditions, $parameters);

        $sql .= ' WHERE ' . implode(' AND ', $conditions);
        $sql .= ' ORDER BY consultation_date ASC, start_time ASC, id ASC LIMIT :limit OFFSET :offset';

        $stmt = $db->prepare($sql);
        self::bindParameters($stmt, $parameters);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function getSummaryForDoctor(int $doctorId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                COUNT(*) AS total_slots,
                SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) AS available_slots,
                SUM(CASE WHEN status = 'Booked' THEN 1 ELSE 0 END) AS booked_slots,
                SUM(CASE WHEN consultation_date >= CURDATE() THEN 1 ELSE 0 END) AS upcoming_slots,
                SUM(CASE WHEN status = 'Booked' AND consultation_date >= CURDATE() THEN 1 ELSE 0 END) AS upcoming_consultations,
                SUM(CASE WHEN status = 'Booked' AND consultation_date < CURDATE() THEN 1 ELSE 0 END) AS completed_consultations
            FROM doctor_availability
            WHERE doctor_id = :doctor_id"
        );
        $stmt->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_slots' => (int) ($row['total_slots'] ?? 0),
            'available_slots' => (int) ($row['available_slots'] ?? 0),
            'booked_slots' => (int) ($row['booked_slots'] ?? 0),
            'upcoming_slots' => (int) ($row['upcoming_slots'] ?? 0),
            'upcoming_consultations' => (int) ($row['upcoming_consultations'] ?? 0),
            'completed_consultations' => (int) ($row['completed_consultations'] ?? 0),
        ];
    }

    public static function getTodaySummaryForDoctor(int $doctorId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                COUNT(*) AS total_today_slots,
                SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) AS available_today_slots,
                SUM(CASE WHEN status = 'Booked' THEN 1 ELSE 0 END) AS booked_today_slots
            FROM doctor_availability
            WHERE doctor_id = :doctor_id
              AND consultation_date = CURDATE()"
        );
        $stmt->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_today_slots' => (int) ($row['total_today_slots'] ?? 0),
            'available_today_slots' => (int) ($row['available_today_slots'] ?? 0),
            'booked_today_slots' => (int) ($row['booked_today_slots'] ?? 0),
        ];
    }

    public static function getUpcomingForDoctor(int $doctorId, int $limit = 5): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                id,
                consultation_date,
                start_time,
                end_time,
                notes,
                status
            FROM doctor_availability
            WHERE doctor_id = :doctor_id
              AND consultation_date >= CURDATE()
            ORDER BY consultation_date ASC, start_time ASC, id ASC
            LIMIT :limit"
        );
        $stmt->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function getDailyScheduleCountsForDoctor(int $doctorId, int $days = 7): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                consultation_date,
                COUNT(*) AS total_slots,
                SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) AS available_slots,
                SUM(CASE WHEN status = 'Booked' THEN 1 ELSE 0 END) AS booked_slots
            FROM doctor_availability
            WHERE doctor_id = :doctor_id
              AND consultation_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)
            GROUP BY consultation_date
            ORDER BY consultation_date ASC"
        );
        $stmt->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days - 1, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function hasDuplicateSlot(
        int $doctorId,
        string $consultationDate,
        string $startTime,
        string $endTime,
        ?int $excludeId = null
    ): bool {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*)
            FROM doctor_availability
            WHERE doctor_id = :doctor_id
              AND consultation_date = :consultation_date
              AND start_time = :start_time
              AND end_time = :end_time";

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
        }

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
        $stmt->bindValue(':consultation_date', $consultationDate);
        $stmt->bindValue(':start_time', $startTime);
        $stmt->bindValue(':end_time', $endTime);

        if ($excludeId !== null) {
            $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        }

        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function hasOverlappingSlot(
        int $doctorId,
        string $consultationDate,
        string $startTime,
        string $endTime,
        ?int $excludeId = null
    ): bool {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*)
            FROM doctor_availability
            WHERE doctor_id = :doctor_id
              AND consultation_date = :consultation_date
              AND start_time < :end_time
              AND end_time > :start_time";

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
        }

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
        $stmt->bindValue(':consultation_date', $consultationDate);
        $stmt->bindValue(':start_time', $startTime);
        $stmt->bindValue(':end_time', $endTime);

        if ($excludeId !== null) {
            $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        }

        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function countAvailableForPatients(array $filters = []): int
    {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*)
            FROM doctor_availability
            INNER JOIN doctor ON doctor.user_id = doctor_availability.doctor_id
            INNER JOIN users ON users.id = doctor.user_id
            INNER JOIN roles ON roles.id = users.role_id";
        $conditions = [
            "roles.name = 'doctor'",
            "users.status = 'active'",
            "doctor_availability.status = 'Available'",
            'doctor_availability.consultation_date >= CURDATE()',
        ];
        $parameters = [];

        self::appendPatientFilters($filters, $conditions, $parameters);

        $sql .= ' WHERE ' . implode(' AND ', $conditions);
        $stmt = $db->prepare($sql);
        self::bindPatientParameters($stmt, $parameters);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function findAvailableForPatients(array $filters = [], int $limit = 10, int $offset = 0): array
    {
        $db = Database::getInstance();
        $sql = "SELECT
                doctor_availability.id,
                doctor_availability.consultation_date,
                doctor_availability.start_time,
                doctor_availability.end_time,
                doctor_availability.notes,
                doctor_availability.status,
                doctor.user_id AS doctor_id,
                doctor.professional_title,
                doctor.specialization,
                doctor.profile_photo_path,
                users.full_name
            FROM doctor_availability
            INNER JOIN doctor ON doctor.user_id = doctor_availability.doctor_id
            INNER JOIN users ON users.id = doctor.user_id
            INNER JOIN roles ON roles.id = users.role_id";
        $conditions = [
            "roles.name = 'doctor'",
            "users.status = 'active'",
            "doctor_availability.status = 'Available'",
            'doctor_availability.consultation_date >= CURDATE()',
        ];
        $parameters = [];

        self::appendPatientFilters($filters, $conditions, $parameters);

        $sql .= ' WHERE ' . implode(' AND ', $conditions);
        $sql .= ' ORDER BY doctor_availability.consultation_date ASC, doctor_availability.start_time ASC, users.full_name ASC LIMIT :limit OFFSET :offset';

        $stmt = $db->prepare($sql);
        self::bindPatientParameters($stmt, $parameters);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function getAvailableDoctorOptionsForPatients(): array
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT DISTINCT
                doctor.user_id AS doctor_id,
                users.full_name
            FROM doctor_availability
            INNER JOIN doctor ON doctor.user_id = doctor_availability.doctor_id
            INNER JOIN users ON users.id = doctor.user_id
            INNER JOIN roles ON roles.id = users.role_id
            WHERE roles.name = 'doctor'
              AND users.status = 'active'
              AND doctor_availability.status = 'Available'
              AND doctor_availability.consultation_date >= CURDATE()
            ORDER BY users.full_name ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function getAvailableDaysAndTimesForDoctor(int $doctorId, int $limit = 3): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                id,
                consultation_date,
                start_time,
                end_time
            FROM doctor_availability
            WHERE doctor_id = :doctor_id
              AND status = 'Available'
              AND consultation_date >= CURDATE()
            ORDER BY consultation_date ASC, start_time ASC
            LIMIT :limit"
        );
        $stmt->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function findAvailableSlotForPatients(int $availabilityId): ?array
    {
        if ($availabilityId <= 0) {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                doctor_availability.id,
                doctor_availability.consultation_date,
                doctor_availability.start_time,
                doctor_availability.end_time,
                doctor_availability.status,
                doctor.user_id AS doctor_id,
                doctor.professional_title,
                doctor.specialization,
                doctor.profile_photo_path,
                users.full_name
            FROM doctor_availability
            INNER JOIN doctor ON doctor.user_id = doctor_availability.doctor_id
            INNER JOIN users ON users.id = doctor.user_id
            INNER JOIN roles ON roles.id = users.role_id
            WHERE doctor_availability.id = :id
              AND roles.name = 'doctor'
              AND users.status = 'active'
              AND doctor_availability.status = 'Available'
              AND doctor_availability.consultation_date >= CURDATE()
            LIMIT 1"
        );
        $stmt->bindValue(':id', $availabilityId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private static function appendFilters(array $filters, array &$conditions, array &$parameters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $filterDate = trim((string) ($filters['filter_date'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        if ($search !== '') {
            $conditions[] = '(
                DATE_FORMAT(consultation_date, "%Y-%m-%d") LIKE :search_date
                OR TIME_FORMAT(start_time, "%H:%i") LIKE :search_start
                OR TIME_FORMAT(end_time, "%H:%i") LIKE :search_end
                OR notes LIKE :search_notes
            )';
            $searchValue = '%' . $search . '%';
            $parameters[':search_date'] = $searchValue;
            $parameters[':search_start'] = $searchValue;
            $parameters[':search_end'] = $searchValue;
            $parameters[':search_notes'] = $searchValue;
        }

        if ($filterDate !== '') {
            $conditions[] = 'consultation_date = :filter_date';
            $parameters[':filter_date'] = $filterDate;
        }

        if ($status !== '') {
            $conditions[] = 'status = :status';
            $parameters[':status'] = $status;
        }
    }

    private static function bindParameters(\PDOStatement $stmt, array $parameters): void
    {
        foreach ($parameters as $name => $value) {
            $stmt->bindValue($name, $value, $name === ':doctor_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }

    private static function appendPatientFilters(array $filters, array &$conditions, array &$parameters): void
    {
        $doctorId = (int) ($filters['doctor_id'] ?? 0);
        $specialization = trim((string) ($filters['specialization'] ?? ''));
        $consultationDate = trim((string) ($filters['consultation_date'] ?? ''));

        if ($doctorId > 0) {
            $conditions[] = 'doctor.user_id = :patient_filter_doctor_id';
            $parameters[':patient_filter_doctor_id'] = $doctorId;
        }

        if ($specialization !== '') {
            $conditions[] = 'doctor.specialization = :patient_filter_specialization';
            $parameters[':patient_filter_specialization'] = $specialization;
        }

        if ($consultationDate !== '') {
            $conditions[] = 'doctor_availability.consultation_date = :patient_filter_consultation_date';
            $parameters[':patient_filter_consultation_date'] = $consultationDate;
        }
    }

    private static function bindPatientParameters(\PDOStatement $stmt, array $parameters): void
    {
        foreach ($parameters as $name => $value) {
            $stmt->bindValue(
                $name,
                $value,
                $name === ':patient_filter_doctor_id' ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }
    }
}
