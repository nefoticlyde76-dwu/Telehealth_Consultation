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
              AND consultation_requests.status IN ('Pending', 'Assigned', 'Approved')"
        );
        $upcomingStmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $upcomingStmt->execute();

        return [
            'pending_requests' => (int) ($row['pending_requests'] ?? 0),
            'approved_requests' => (int) ($row['approved_requests'] ?? 0),
            'upcoming_appointments' => (int) $upcomingStmt->fetchColumn(),
            'consultation_history' => (int) ($row['total_requests'] ?? 0),
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
                doctor_availability.consultation_date,
                doctor_availability.start_time,
                doctor_availability.end_time,
                doctor.user_id AS doctor_id,
                doctor.professional_title AS doctor_title,
                doctor.specialization,
                users.full_name AS doctor_name
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
                doctor.profile_photo_path,
                users.full_name AS doctor_name
            FROM consultation_requests
            INNER JOIN doctor ON doctor.user_id = consultation_requests.doctor_id
            INNER JOIN users ON users.id = doctor.user_id
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
              AND availability_id = :availability_id"
        );
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->bindValue(':availability_id', $availabilityId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }
}

