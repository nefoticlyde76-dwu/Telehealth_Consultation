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
}
