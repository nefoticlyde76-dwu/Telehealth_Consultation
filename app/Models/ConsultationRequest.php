<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConsultationRequest
{
    public static function countForPatient(int $patientId): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT COUNT(*) FROM consultation_requests WHERE patient_id = :patient_id");
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function getDashboardSummaryForPatient(int $patientId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                COUNT(*) AS total_requests,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_requests,
                SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS approved_requests,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed_requests
            FROM consultation_requests
            WHERE patient_id = :patient_id"
        );
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $upcomingStmt = $db->prepare(
            "SELECT COUNT(*)
            FROM consultation_requests
            INNER JOIN doctor_availability ON doctor_availability.id = consultation_requests.availability_id
            WHERE consultation_requests.patient_id = :patient_id
              AND consultation_requests.availability_id IS NOT NULL
              AND doctor_availability.consultation_date >= CURDATE()
              AND consultation_requests.status = 'Approved'"
        );
        $upcomingStmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $upcomingStmt->execute();

        $latestRequest = self::findLatestForPatient($patientId);
        $latestStatus = (string) ($latestRequest['status'] ?? '');

        return [
            'pending_requests' => (int) ($row['pending_requests'] ?? 0),
            'approved_requests' => (int) ($row['approved_requests'] ?? 0),
            'upcoming_appointments' => (int) $upcomingStmt->fetchColumn(),
            'consultation_history' => (int) ($row['total_requests'] ?? 0),
            'completed_requests' => (int) ($row['completed_requests'] ?? 0),
            'latest_status' => $latestStatus,
            'latest_status_display' => $latestStatus !== '' ? $latestStatus : 'No requests yet',
            'latest_request' => $latestRequest,
        ];
    }

    public static function findForPatient(int $patientId, int $limit = 10, int $offset = 0): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                consultation_requests.id,
                consultation_requests.request_date,
                consultation_requests.reason,
                consultation_requests.status,
                consultation_requests.completed_at,
                doctor_availability.consultation_date,
                doctor_availability.start_time,
                doctor_availability.end_time,
                doctor.user_id AS doctor_id,
                doctor.professional_title AS doctor_title,
                doctor.specialization,
                doctor.profile_photo_path AS doctor_photo_path,
                users.full_name AS doctor_name,
                EXISTS(
                    SELECT 1
                      FROM consultation_records
                     WHERE consultation_records.consultation_request_id = consultation_requests.id
                       AND consultation_records.patient_id = consultation_requests.patient_id
                       AND consultation_records.record_status = 'Final'
                ) AS has_final_record,
                EXISTS(
                    SELECT 1
                      FROM prescriptions
                     INNER JOIN consultation_records
                        ON consultation_records.id = prescriptions.consultation_record_id
                     WHERE consultation_records.consultation_request_id = consultation_requests.id
                       AND prescriptions.patient_id = consultation_requests.patient_id
                ) AS has_prescription
            FROM consultation_requests
            INNER JOIN doctor ON doctor.user_id = consultation_requests.doctor_id
            INNER JOIN users ON users.id = doctor.user_id
            LEFT JOIN doctor_availability ON doctor_availability.id = consultation_requests.availability_id
            WHERE consultation_requests.patient_id = :patient_id
            ORDER BY consultation_requests.request_date DESC, consultation_requests.id DESC
            LIMIT :limit OFFSET :offset"
        );
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function findByIdForPatient(int $requestId, int $patientId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                consultation_requests.*,
                doctor_availability.consultation_date,
                doctor_availability.start_time,
                doctor_availability.end_time,
                doctor.professional_title AS doctor_title,
                doctor.specialization,
                doctor.signature_path AS doctor_signature_path,
                doctor.clinic_address AS doctor_clinic_address,
                doctor.profile_photo_path AS doctor_photo_path,
                users.full_name AS doctor_name,
                patient.address AS patient_address,
                patient.dob AS patient_dob,
                patient.gender AS patient_gender,
                patient_user.full_name AS patient_name
            FROM consultation_requests
            INNER JOIN doctor ON doctor.user_id = consultation_requests.doctor_id
            INNER JOIN users ON users.id = doctor.user_id
            INNER JOIN patient ON patient.user_id = consultation_requests.patient_id
            INNER JOIN users AS patient_user ON patient_user.id = patient.user_id
            LEFT JOIN doctor_availability ON doctor_availability.id = consultation_requests.availability_id
            WHERE consultation_requests.id = :id
              AND consultation_requests.patient_id = :patient_id
            LIMIT 1"
        );
        $stmt->bindValue(':id', $requestId, PDO::PARAM_INT);
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function findByIdForDoctor(int $requestId, int $doctorUserId): ?array
    {
        if ($requestId <= 0 || $doctorUserId <= 0) {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                consultation_requests.*,
                doctor_availability.consultation_date,
                doctor_availability.start_time,
                doctor_availability.end_time,
                doctor_availability.status AS slot_status,
                doctor.professional_title AS doctor_title,
                doctor.specialization,
                doctor.signature_path AS doctor_signature_path,
                doctor.clinic_address AS doctor_clinic_address,
                doctor_user.full_name AS doctor_name,
                patient_user.id AS patient_user_id,
                patient_user.full_name AS patient_name,
                patient_user.email AS patient_email,
                patient.phone AS patient_phone,
                patient.dob AS patient_dob,
                patient.gender AS patient_gender,
                patient.address AS patient_address,
                patient.profile_photo_path AS patient_photo_path,
                doctor.profile_photo_path AS doctor_photo_path
            FROM consultation_requests
            INNER JOIN doctor ON doctor.user_id = consultation_requests.doctor_id
            INNER JOIN users AS doctor_user ON doctor_user.id = doctor.user_id
            INNER JOIN patient ON patient.user_id = consultation_requests.patient_id
            INNER JOIN users AS patient_user ON patient_user.id = patient.user_id
            LEFT JOIN doctor_availability ON doctor_availability.id = consultation_requests.availability_id
            WHERE consultation_requests.id = :id
              AND consultation_requests.doctor_id = :doctor_id
            LIMIT 1"
        );
        $stmt->bindValue(':id', $requestId, PDO::PARAM_INT);
        $stmt->bindValue(':doctor_id', $doctorUserId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function findLatestForPatient(int $patientId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                consultation_requests.id,
                consultation_requests.request_date,
                consultation_requests.reason,
                consultation_requests.status,
                doctor_availability.consultation_date,
                doctor_availability.start_time,
                doctor_availability.end_time,
                doctor.professional_title AS doctor_title,
                doctor.specialization,
                doctor.profile_photo_path AS doctor_photo_path,
                users.full_name AS doctor_name
            FROM consultation_requests
            INNER JOIN doctor ON doctor.user_id = consultation_requests.doctor_id
            INNER JOIN users ON users.id = doctor.user_id
            LEFT JOIN doctor_availability ON doctor_availability.id = consultation_requests.availability_id
            WHERE consultation_requests.patient_id = :patient_id
            ORDER BY consultation_requests.request_date DESC, consultation_requests.id DESC
            LIMIT 1"
        );
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function findNextApprovedForPatient(int $patientId): ?array
    {
        if ($patientId <= 0) {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                consultation_requests.id,
                consultation_requests.status,
                doctor_availability.consultation_date,
                doctor_availability.start_time,
                doctor_availability.end_time,
                doctor.professional_title AS doctor_title,
                doctor.specialization,
                doctor.profile_photo_path AS doctor_photo_path,
                users.full_name AS doctor_name
            FROM consultation_requests
            INNER JOIN doctor_availability ON doctor_availability.id = consultation_requests.availability_id
            INNER JOIN doctor ON doctor.user_id = consultation_requests.doctor_id
            INNER JOIN users ON users.id = doctor.user_id
            WHERE consultation_requests.patient_id = :patient_id
              AND consultation_requests.status = 'Approved'
              AND doctor_availability.consultation_date >= CURDATE()
            ORDER BY doctor_availability.consultation_date ASC, doctor_availability.start_time ASC, consultation_requests.id ASC
            LIMIT 1"
        );
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function getStatusDistributionForPatient(int $patientId): array
    {
        if ($patientId <= 0) {
            return [];
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT status, COUNT(*) AS total
            FROM consultation_requests
            WHERE patient_id = :patient_id
            GROUP BY status"
        );
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $distribution = [];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');

            if ($status === '') {
                continue;
            }

            $distribution[$status] = (int) ($row['total'] ?? 0);
        }

        return $distribution;
    }

    public static function findMonthlyRequestCountsForPatient(int $patientId, int $months = 6): array
    {
        if ($patientId <= 0) {
            return [];
        }

        $months = max(1, $months);
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT DATE_FORMAT(request_date, '%Y-%m-01') AS request_month, COUNT(*) AS total
            FROM consultation_requests
            WHERE patient_id = :patient_id
              AND request_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL :months MONTH)
            GROUP BY request_month
            ORDER BY request_month ASC"
        );
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->bindValue(':months', $months - 1, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function createBooking(int $patientId, int $doctorId, int $availabilityId, string $reason, string $status = 'Pending'): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT INTO consultation_requests (
                patient_id,
                doctor_id,
                availability_id,
                reason,
                status
            ) VALUES (
                :patient_id,
                :doctor_id,
                :availability_id,
                :reason,
                :status
            )"
        );
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
        $stmt->bindValue(':availability_id', $availabilityId, PDO::PARAM_INT);
        $stmt->bindValue(':reason', $reason);
        $stmt->bindValue(':status', $status);
        $stmt->execute();

        return (int) $db->lastInsertId();
    }

    public static function existsForPatientAndAvailability(int $patientId, int $availabilityId): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT COUNT(*)
            FROM consultation_requests
            WHERE patient_id = :patient_id
              AND availability_id = :availability_id
              AND status IN ('Pending', 'Approved')"
        );
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->bindValue(':availability_id', $availabilityId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function hasActiveRequestForAvailability(int $availabilityId, ?int $exceptRequestId = null): bool
    {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*)
            FROM consultation_requests
            WHERE availability_id = :availability_id
              AND status IN ('Pending', 'Approved')";

        if ($exceptRequestId !== null && $exceptRequestId > 0) {
            $sql .= ' AND id <> :except_id';
        }

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':availability_id', $availabilityId, PDO::PARAM_INT);
        if ($exceptRequestId !== null && $exceptRequestId > 0) {
            $stmt->bindValue(':except_id', $exceptRequestId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }

    public static function getAdminStatusSummary(): array
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT
                COUNT(*) AS total_requests,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_requests,
                SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS approved_requests,
                SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) AS rejected_requests
            FROM consultation_requests"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_requests' => (int) ($row['total_requests'] ?? 0),
            'pending_requests' => (int) ($row['pending_requests'] ?? 0),
            'approved_requests' => (int) ($row['approved_requests'] ?? 0),
            'rejected_requests' => (int) ($row['rejected_requests'] ?? 0),
        ];
    }

    public static function getStatusDistributionForAdmin(): array
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT status, COUNT(*) AS total
            FROM consultation_requests
            GROUP BY status"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $distribution = [];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');

            if ($status === '') {
                continue;
            }

            $distribution[$status] = (int) ($row['total'] ?? 0);
        }

        return $distribution;
    }

    public static function findDailyRequestCountsForAdmin(int $days = 7): array
    {
        $days = max(1, $days);
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT DATE(request_date) AS request_day, COUNT(*) AS total
            FROM consultation_requests
            WHERE request_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY request_day
            ORDER BY request_day ASC"
        );
        $stmt->bindValue(':days', $days - 1, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function countRecentForAdmin(int $days = 7): int
    {
        $days = max(1, $days);
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT COUNT(*)
            FROM consultation_requests
            WHERE request_date >= DATE_SUB(NOW(), INTERVAL :days DAY)"
        );
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function findRecentForAdmin(int $limit = 5): array
    {
        $limit = max(1, $limit);
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                consultation_requests.id,
                consultation_requests.request_date,
                consultation_requests.reason,
                consultation_requests.status,
                patient_user.full_name AS patient_name,
                doctor_user.full_name AS doctor_name,
                doctor_availability.consultation_date,
                doctor_availability.start_time,
                doctor_availability.end_time,
                patient.profile_photo_path AS patient_photo_path,
                doctor.profile_photo_path AS doctor_photo_path
            FROM consultation_requests
            INNER JOIN users AS patient_user ON patient_user.id = consultation_requests.patient_id
            INNER JOIN users AS doctor_user ON doctor_user.id = consultation_requests.doctor_id
            LEFT JOIN patient ON patient.user_id = consultation_requests.patient_id
            LEFT JOIN doctor ON doctor.user_id = consultation_requests.doctor_id
            LEFT JOIN doctor_availability ON doctor_availability.id = consultation_requests.availability_id
            ORDER BY consultation_requests.request_date DESC, consultation_requests.id DESC
            LIMIT :limit"
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function getPatientOptionsForAdmin(): array
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT users.id, users.full_name
            FROM users
            INNER JOIN roles ON roles.id = users.role_id
            WHERE roles.name = 'patient'
            ORDER BY users.full_name ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function getDoctorOptionsForAdmin(): array
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            "SELECT users.id, users.full_name
            FROM users
            INNER JOIN roles ON roles.id = users.role_id
            WHERE roles.name = 'doctor'
            ORDER BY users.full_name ASC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function countForAdmin(array $filters = []): int
    {
        $db = Database::getInstance();
        $sql = 'SELECT COUNT(*) ' . self::adminQueueFromSql();
        $conditions = [];
        $parameters = [];

        self::appendAdminFilters($filters, $conditions, $parameters);

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $stmt = $db->prepare($sql);
        self::bindAdminParameters($stmt, $parameters);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function findForAdmin(array $filters = [], int $limit = 10, int $offset = 0): array
    {
        $db = Database::getInstance();
        $sql = 'SELECT
                consultation_requests.id,
                consultation_requests.request_date,
                consultation_requests.reason,
                consultation_requests.status,
                consultation_requests.patient_id,
                consultation_requests.doctor_id,
                patient_user.full_name AS patient_name,
                doctor_user.full_name AS doctor_name,
                doctor.specialization,
                doctor.profile_photo_path AS doctor_photo_path,
                patient.profile_photo_path AS patient_photo_path,
                doctor_availability.consultation_date,
                doctor_availability.start_time,
                doctor_availability.end_time
            ' . self::adminQueueFromSql();
        $conditions = [];
        $parameters = [];

        self::appendAdminFilters($filters, $conditions, $parameters);

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= self::adminQueueOrderSql() . ' LIMIT :limit OFFSET :offset';

        $stmt = $db->prepare($sql);
        self::bindAdminParameters($stmt, $parameters);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Ordered request IDs for the current admin queue filters (no pagination).
     *
     * @return list<int>
     */
    public static function findQueueIdsForAdmin(array $filters = []): array
    {
        $db = Database::getInstance();
        $sql = 'SELECT consultation_requests.id ' . self::adminQueueFromSql();
        $conditions = [];
        $parameters = [];

        self::appendAdminFilters($filters, $conditions, $parameters);

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $sql .= self::adminQueueOrderSql();

        $stmt = $db->prepare($sql);
        self::bindAdminParameters($stmt, $parameters);
        $stmt->execute();

        $ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [] as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public static function countTodayPendingForAdmin(): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT COUNT(*)
               FROM consultation_requests
               LEFT JOIN doctor_availability ON doctor_availability.id = consultation_requests.availability_id
              WHERE consultation_requests.status = 'Pending'
                AND doctor_availability.consultation_date = :today"
        );
        $stmt->bindValue(':today', (new \DateTimeImmutable('today'))->format('Y-m-d'));
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    private static function adminQueueFromSql(): string
    {
        return "FROM consultation_requests
            INNER JOIN users AS patient_user ON patient_user.id = consultation_requests.patient_id
            INNER JOIN users AS doctor_user ON doctor_user.id = consultation_requests.doctor_id
            INNER JOIN doctor ON doctor.user_id = consultation_requests.doctor_id
            LEFT JOIN patient ON patient.user_id = consultation_requests.patient_id
            LEFT JOIN doctor_availability ON doctor_availability.id = consultation_requests.availability_id";
    }

    private static function adminQueueOrderSql(): string
    {
        return " ORDER BY
            CASE WHEN doctor_availability.consultation_date IS NULL THEN 1 ELSE 0 END ASC,
            doctor_availability.consultation_date ASC,
            doctor_availability.start_time ASC,
            consultation_requests.request_date ASC,
            consultation_requests.id ASC";
    }

    public static function findByIdForAdmin(int $requestId): ?array
    {
        if ($requestId <= 0) {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                consultation_requests.*,
                patient_user.full_name AS patient_name,
                doctor_user.full_name AS doctor_name,
                doctor.specialization,
                doctor.professional_title AS doctor_title,
                doctor.profile_photo_path AS doctor_photo_path,
                patient.profile_photo_path AS patient_photo_path,
                doctor_availability.consultation_date,
                doctor_availability.start_time,
                doctor_availability.end_time,
                doctor_availability.status AS availability_status
            FROM consultation_requests
            INNER JOIN users AS patient_user ON patient_user.id = consultation_requests.patient_id
            INNER JOIN doctor ON doctor.user_id = consultation_requests.doctor_id
            INNER JOIN users AS doctor_user ON doctor_user.id = doctor.user_id
            LEFT JOIN patient ON patient.user_id = consultation_requests.patient_id
            LEFT JOIN doctor_availability ON doctor_availability.id = consultation_requests.availability_id
            WHERE consultation_requests.id = :id
            LIMIT 1"
        );
        $stmt->bindValue(':id', $requestId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function updateStatusForAdmin(int $requestId, string $targetStatus): array
    {
        if ($requestId <= 0) {
            return [
                'success' => false,
                'message' => 'The consultation request is invalid.',
                'type' => 'danger',
            ];
        }

        if (!in_array($targetStatus, ['Approved', 'Rejected', 'Cancelled'], true)) {
            return [
                'success' => false,
                'message' => 'The requested consultation status change is not supported.',
                'type' => 'danger',
            ];
        }

        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            $requestStmt = $db->prepare(
                "SELECT id, status, availability_id
                FROM consultation_requests
                WHERE id = :id
                FOR UPDATE"
            );
            $requestStmt->bindValue(':id', $requestId, PDO::PARAM_INT);
            $requestStmt->execute();
            $requestRow = $requestStmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($requestRow === null) {
                $db->rollBack();
                return [
                    'success' => false,
                    'message' => 'The requested consultation request could not be found.',
                    'type' => 'warning',
                ];
            }

            $currentStatus = (string) ($requestRow['status'] ?? '');

            $availabilityId = isset($requestRow['availability_id']) ? (int) $requestRow['availability_id'] : 0;

            // Idempotency: if the target status is already set and the linked
            // Daily room has already been persisted for Approved requests,
            // return a clean success without allocating a new room or firing
            // slot state transitions twice.
            if ($currentStatus === $targetStatus) {
                if ($targetStatus === 'Approved' && $availabilityId > 0) {
                    $existingRoom = ConsultationRoom::findByConsultationRequestId($requestId);
                    if ($existingRoom === null) {
                        // Already Approved but somehow missing the room (edge
                        // case due to partial failure in a previous run).
                        // Re-create it safely here since the DB status already
                        // matches what the admin requested.
                        ConsultationRoom::upsertForApprovedConsultation($requestId);
                    }
                }
                $db->commit();
                return [
                    'success' => true,
                    'message' => 'This consultation request is already marked as ' . $targetStatus . '.',
                    'type' => 'info',
                ];
            }

            $allowedTransitions = [
                'Pending' => ['Approved', 'Rejected', 'Cancelled'],
                'Approved' => ['Cancelled'],
            ];

            if (
                !isset($allowedTransitions[$currentStatus])
                || !in_array($targetStatus, $allowedTransitions[$currentStatus], true)
            ) {
                $db->rollBack();
                return [
                    'success' => false,
                    'message' => 'The requested consultation status transition is not allowed.',
                    'type' => 'danger',
                ];
            }

            if ($availabilityId > 0) {
                $slotStmt = $db->prepare(
                    "SELECT id, status
                    FROM doctor_availability
                    WHERE id = :id
                    FOR UPDATE"
                );
                $slotStmt->bindValue(':id', $availabilityId, PDO::PARAM_INT);
                $slotStmt->execute();
                $slotRow = $slotStmt->fetch(PDO::FETCH_ASSOC) ?: null;

                if ($slotRow === null) {
                    $db->rollBack();
                    return [
                        'success' => false,
                        'message' => 'The consultation slot associated with this request could not be found.',
                        'type' => 'danger',
                    ];
                }

                $slotStatus = (string) ($slotRow['status'] ?? '');

                if ($targetStatus === 'Approved' && ConsultationRequest::hasActiveRequestForAvailability($availabilityId, $requestId)) {
                    $db->rollBack();
                    return [
                        'success' => false,
                        'message' => 'This consultation slot is already assigned to another request. Choose a different request or free the slot first.',
                        'type' => 'warning',
                    ];
                }

                if ($targetStatus === 'Approved' && $slotStatus !== 'Booked') {
                    $updateSlot = $db->prepare("UPDATE doctor_availability SET status = 'Booked' WHERE id = :id");
                    $updateSlot->bindValue(':id', $availabilityId, PDO::PARAM_INT);
                    $updateSlot->execute();
                }

                if (in_array($targetStatus, ['Rejected', 'Cancelled'], true) && $slotStatus === 'Booked') {
                    $updateSlot = $db->prepare("UPDATE doctor_availability SET status = 'Available' WHERE id = :id");
                    $updateSlot->bindValue(':id', $availabilityId, PDO::PARAM_INT);
                    $updateSlot->execute();
                }
            }

            $updateRequest = $db->prepare(
                "UPDATE consultation_requests
                SET status = :status
                WHERE id = :id"
            );
            $updateRequest->bindValue(':status', $targetStatus);
            $updateRequest->bindValue(':id', $requestId, PDO::PARAM_INT);
            $updateRequest->execute();

            // ──────────────────────────────────────────────────────────────
            // Week 6 — Daily video room creation on Admin approval.
            //
            // Only executed when:
            //   • status is Approved (not Rejected / Cancelled).
            //   • the consultation is attached to a real doctor_availability
            //     slot (so start/end times exist).
            //
            // ConsultationRoom::upsertForApprovedConsultation():
            //   • short-circuits if the row already exists (idempotent re-approve).
            //   • computes Daily exp = appointment END + 2 HOURS.
            //   • calls the Daily REST API via DailyService.
            //   • throws RuntimeException on any failure → we ROLLBACK the
            //     Approved status update + Booked slot change atomically so
            //     the system never ends up with an approved appointment
            //     without a working video room.
            //
            // Rollback also restores the slot status transactionally because
            // the slot UPDATE earlier in this function is still inside this
            // same PDO transaction.
            // ──────────────────────────────────────────────────────────────
            if ($targetStatus === 'Approved' && $availabilityId > 0) {
                try {
                    ConsultationRoom::upsertForApprovedConsultation($requestId);
                } catch (\Throwable $roomError) {
                    error_log(
                        sprintf(
                            '[ConsultationRequest::updateStatusForAdmin] Daily room creation FAILED for request %d: %s',
                            $requestId,
                            $roomError->getMessage()
                        )
                    );

                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }

                    $safeMessage = $roomError->getMessage() !== ''
                        ? $roomError->getMessage()
                        : 'The video consultation room could not be created.';

                    return [
                        'success' => false,
                        'message' => 'Consultation could not be approved. ' . $safeMessage . ' No changes were saved.',
                        'type' => 'danger',
                    ];
                }
            }

            $db->commit();

            return [
                'success' => true,
                'message' => 'Consultation request updated to ' . $targetStatus . '.',
                'type' => 'success',
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log('Admin consultation status update failed: ' . $exception->getMessage());

            return [
                'success' => false,
                'message' => 'Consultation request status could not be updated right now.',
                'type' => 'danger',
            ];
        }
    }

    public static function countApprovedRecentlyForDoctor(int $doctorId, int $days = 7): int
    {
        if ($doctorId <= 0) {
            return 0;
        }

        $days = max(1, $days);
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT COUNT(*)
            FROM consultation_requests
            WHERE doctor_id = :doctor_id
              AND status = 'Approved'
              AND updated_at >= DATE_SUB(NOW(), INTERVAL :days DAY)"
        );
        $stmt->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function countUpcomingApprovedForDoctor(int $doctorId): int
    {
        if ($doctorId <= 0) {
            return 0;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT COUNT(*)
            FROM consultation_requests
            INNER JOIN doctor_availability ON doctor_availability.id = consultation_requests.availability_id
            WHERE consultation_requests.doctor_id = :doctor_id
              AND consultation_requests.status = 'Approved'
              AND doctor_availability.consultation_date >= CURDATE()"
        );
        $stmt->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function findUpcomingApprovedForDoctor(int $doctorId, int $limit = 5): array
    {
        if ($doctorId <= 0) {
            return [];
        }

        $limit = max(1, $limit);
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                consultation_requests.id,
                consultation_requests.request_date,
                consultation_requests.status,
                patient_user.full_name AS patient_name,
                patient.profile_photo_path AS patient_photo_path,
                doctor_availability.consultation_date,
                doctor_availability.start_time,
                doctor_availability.end_time
            FROM consultation_requests
            INNER JOIN users AS patient_user ON patient_user.id = consultation_requests.patient_id
            LEFT JOIN patient ON patient.user_id = consultation_requests.patient_id
            INNER JOIN doctor_availability ON doctor_availability.id = consultation_requests.availability_id
            WHERE consultation_requests.doctor_id = :doctor_id
              AND consultation_requests.status = 'Approved'
              AND doctor_availability.consultation_date >= CURDATE()
            ORDER BY doctor_availability.consultation_date ASC, doctor_availability.start_time ASC, consultation_requests.id ASC
            LIMIT :limit"
        );
        $stmt->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function getDoctorStatusSummary(int $doctorId): array
    {
        if ($doctorId <= 0) {
            return [
                'approved_appointments' => 0,
                'upcoming_consultations' => 0,
                'completed_consultations' => 0,
            ];
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                SUM(CASE WHEN consultation_requests.status = 'Approved' THEN 1 ELSE 0 END) AS approved_appointments,
                SUM(
                    CASE
                        WHEN consultation_requests.status = 'Approved'
                         AND doctor_availability.consultation_date >= CURDATE()
                        THEN 1
                        ELSE 0
                    END
                ) AS upcoming_consultations,
                SUM(CASE WHEN consultation_requests.status = 'Completed' THEN 1 ELSE 0 END) AS completed_consultations
            FROM consultation_requests
            LEFT JOIN doctor_availability ON doctor_availability.id = consultation_requests.availability_id
            WHERE consultation_requests.doctor_id = :doctor_id"
        );
        $stmt->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'approved_appointments' => (int) ($row['approved_appointments'] ?? 0),
            'upcoming_consultations' => (int) ($row['upcoming_consultations'] ?? 0),
            'completed_consultations' => (int) ($row['completed_consultations'] ?? 0),
        ];
    }

    public static function getStatusDistributionForDoctor(int $doctorId): array
    {
        if ($doctorId <= 0) {
            return [];
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT status, COUNT(*) AS total
            FROM consultation_requests
            WHERE doctor_id = :doctor_id
            GROUP BY status"
        );
        $stmt->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $distribution = [];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');

            if ($status === '') {
                continue;
            }

            $distribution[$status] = (int) ($row['total'] ?? 0);
        }

        return $distribution;
    }

    public static function findDailyRequestCountsForDoctor(int $doctorId, int $days = 7): array
    {
        if ($doctorId <= 0) {
            return [];
        }

        $days = max(1, $days);
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT DATE(request_date) AS request_day, COUNT(*) AS total
            FROM consultation_requests
            WHERE doctor_id = :doctor_id
              AND request_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
            GROUP BY request_day
            ORDER BY request_day ASC"
        );
        $stmt->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days - 1, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function findForDoctor(int $doctorId, array $filters = [], int $limit = 10, int $offset = 0): array
    {
        if ($doctorId <= 0) {
            return [];
        }

        $db = Database::getInstance();
        $sql = "SELECT
                consultation_requests.id,
                consultation_requests.request_date,
                consultation_requests.reason,
                consultation_requests.status,
                consultation_requests.completed_at,
                patient_user.full_name AS patient_name,
                patient.profile_photo_path AS patient_photo_path,
                doctor_availability.consultation_date,
                doctor_availability.start_time,
                doctor_availability.end_time,
                EXISTS(
                    SELECT 1
                      FROM consultation_records
                     WHERE consultation_records.consultation_request_id = consultation_requests.id
                       AND consultation_records.doctor_id = consultation_requests.doctor_id
                       AND consultation_records.record_status = 'Final'
                ) AS has_final_record,
                EXISTS(
                    SELECT 1
                      FROM prescriptions
                     INNER JOIN consultation_records
                        ON consultation_records.id = prescriptions.consultation_record_id
                     WHERE consultation_records.consultation_request_id = consultation_requests.id
                       AND prescriptions.doctor_id = consultation_requests.doctor_id
                ) AS has_prescription
            FROM consultation_requests
            INNER JOIN users AS patient_user ON patient_user.id = consultation_requests.patient_id
            LEFT JOIN patient ON patient.user_id = consultation_requests.patient_id
            LEFT JOIN doctor_availability ON doctor_availability.id = consultation_requests.availability_id
            WHERE consultation_requests.doctor_id = :doctor_id";
        $parameters = [':doctor_id' => $doctorId];

        $status = trim((string) ($filters['status'] ?? ''));

        if ($status !== '') {
            $sql .= ' AND consultation_requests.status = :status';
            $parameters[':status'] = $status;
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $sql .= ' AND (patient_user.full_name LIKE :search OR consultation_requests.reason LIKE :search)';
            $parameters[':search'] = '%' . $search . '%';
        }

        $order = strtoupper(trim((string) ($filters['order'] ?? 'ASC')));
        if ($order !== 'DESC') {
            $order = 'ASC';
        }

        $sql .= " ORDER BY doctor_availability.consultation_date {$order}, doctor_availability.start_time {$order}, consultation_requests.id DESC LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        foreach ($parameters as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function countForDoctor(int $doctorId, array $filters = []): int
    {
        if ($doctorId <= 0) {
            return 0;
        }

        $db = Database::getInstance();
        $sql = "SELECT COUNT(*)
            FROM consultation_requests
            INNER JOIN users AS patient_user ON patient_user.id = consultation_requests.patient_id
            LEFT JOIN doctor_availability ON doctor_availability.id = consultation_requests.availability_id
            WHERE consultation_requests.doctor_id = :doctor_id";
        $parameters = [':doctor_id' => $doctorId];

        $status = trim((string) ($filters['status'] ?? ''));

        if ($status !== '') {
            $sql .= ' AND consultation_requests.status = :status';
            $parameters[':status'] = $status;
        }

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $sql .= ' AND (patient_user.full_name LIKE :search OR consultation_requests.reason LIKE :search)';
            $parameters[':search'] = '%' . $search . '%';
        }

        $stmt = $db->prepare($sql);
        foreach ($parameters as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function markCompletedForDoctor(int $requestId, int $doctorId): array
    {
        if ($requestId <= 0 || $doctorId <= 0) {
            return [
                'success' => false,
                'message' => 'The consultation request is invalid.',
                'type' => 'danger',
            ];
        }

        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            $stmt = $db->prepare(
                "SELECT
                    consultation_requests.id,
                    consultation_requests.status,
                    doctor_availability.consultation_date,
                    CURDATE() AS workflow_current_date
                FROM consultation_requests
                LEFT JOIN doctor_availability ON doctor_availability.id = consultation_requests.availability_id
                WHERE consultation_requests.id = :id
                  AND consultation_requests.doctor_id = :doctor_id
                FOR UPDATE"
            );
            $stmt->bindValue(':id', $requestId, PDO::PARAM_INT);
            $stmt->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($row === null) {
                $db->rollBack();
                return [
                    'success' => false,
                    'message' => 'The requested consultation could not be found.',
                    'type' => 'warning',
                ];
            }

            $currentStatus = (string) ($row['status'] ?? '');

            if ($currentStatus === 'Completed') {
                $db->rollBack();
                return [
                    'success' => true,
                    'message' => 'This consultation is already marked as completed.',
                    'type' => 'info',
                ];
            }

            if ($currentStatus !== 'Approved') {
                $db->rollBack();
                return [
                    'success' => false,
                    'message' => 'Only approved consultations can be marked as completed.',
                    'type' => 'danger',
                ];
            }

            $consultationDate = (string) ($row['consultation_date'] ?? '');
            $currentDate = (string) ($row['workflow_current_date'] ?? '');

            if ($consultationDate !== '' && $currentDate !== '' && $consultationDate > $currentDate) {
                $db->rollBack();
                return [
                    'success' => false,
                    'message' => 'A future consultation cannot be marked as completed yet.',
                    'type' => 'danger',
                ];
            }

            $updateStmt = $db->prepare(
                "UPDATE consultation_requests
                SET status = 'Completed',
                    completed_at = COALESCE(completed_at, NOW())
                WHERE id = :id
                  AND doctor_id = :doctor_id
                  AND status = 'Approved'"
            );
            $updateStmt->bindValue(':id', $requestId, PDO::PARAM_INT);
            $updateStmt->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
            $updateStmt->execute();

            $db->commit();

            return [
                'success' => true,
                'message' => 'Consultation marked as completed successfully.',
                'type' => 'success',
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log('Doctor consultation completion failed: ' . $exception->getMessage());

            return [
                'success' => false,
                'message' => 'Consultation completion could not be processed right now.',
                'type' => 'danger',
            ];
        }
    }

    private static function appendAdminFilters(array $filters, array &$conditions, array &$parameters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $conditions[] = '(patient_user.full_name LIKE :search_patient OR doctor_user.full_name LIKE :search_doctor OR consultation_requests.reason LIKE :search_reason OR consultation_requests.id = :search_exact)';
            $like = '%' . $search . '%';
            $parameters[':search_patient'] = $like;
            $parameters[':search_doctor'] = $like;
            $parameters[':search_reason'] = $like;
            $parameters[':search_exact'] = ctype_digit($search) ? (int) $search : 0;
        }

        $status = trim((string) ($filters['status'] ?? ''));

        if ($status !== '') {
            $conditions[] = 'consultation_requests.status = :status';
            $parameters[':status'] = $status;
        }

        $patientId = (int) ($filters['patient_id'] ?? 0);

        if ($patientId > 0) {
            $conditions[] = 'consultation_requests.patient_id = :patient_id';
            $parameters[':patient_id'] = $patientId;
        }

        $doctorId = (int) ($filters['doctor_id'] ?? 0);

        if ($doctorId > 0) {
            $conditions[] = 'consultation_requests.doctor_id = :doctor_id';
            $parameters[':doctor_id'] = $doctorId;
        }

        $consultationDate = trim((string) ($filters['consultation_date'] ?? ''));

        if ($consultationDate !== '') {
            $conditions[] = 'doctor_availability.consultation_date = :consultation_date';
            $parameters[':consultation_date'] = $consultationDate;
        }
    }

    private static function bindAdminParameters(\PDOStatement $stmt, array $parameters): void
    {
        foreach ($parameters as $key => $value) {
            $paramType = PDO::PARAM_STR;

            if (is_int($value)) {
                $paramType = PDO::PARAM_INT;
            }

            $stmt->bindValue($key, $value, $paramType);
        }
    }
}

