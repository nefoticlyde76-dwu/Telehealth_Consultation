<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConsultationRequest
{
    public ?int $id = null;
    public ?int $patient_id = null;
    public ?int $doctor_id = null;
    public ?int $availability_id = null;
    public ?string $reason = null;
    public ?string $specialization = null;
    public ?string $attachment_path = null;
    public ?string $attachment_original_name = null;
    public ?string $attachment_mime = null;
    public ?int $attachment_size = null;
    public ?string $status = null;

    public static function fromArray(array $row): self
    {
        $request = new self();
        $request->id = isset($row['id']) ? (int) $row['id'] : null;
        $request->patient_id = isset($row['patient_id']) ? (int) $row['patient_id'] : null;
        $request->doctor_id = isset($row['doctor_id']) ? (int) $row['doctor_id'] : null;
        $request->availability_id = isset($row['availability_id']) ? (int) $row['availability_id'] : null;
        $request->reason = $row['reason'] ?? null;
        $request->specialization = $row['specialization'] ?? null;
        $request->attachment_path = $row['attachment_path'] ?? null;
        $request->attachment_original_name = $row['attachment_original_name'] ?? null;
        $request->attachment_mime = $row['attachment_mime'] ?? null;
        $request->attachment_size = isset($row['attachment_size']) ? (int) $row['attachment_size'] : null;
        $request->status = $row['status'] ?? null;

        return $request;
    }

    public function save(): bool
    {
        $db = Database::getInstance();

        if ($this->patient_id === null || $this->doctor_id === null || $this->specialization === null) {
            return false;
        }

        if ($this->id !== null) {
            $stmt = $db->prepare(
                "UPDATE consultation_requests
                SET
                    reason = :reason,
                    specialization = :specialization,
                    attachment_path = :attachment_path,
                    attachment_original_name = :attachment_original_name,
                    attachment_mime = :attachment_mime,
                    attachment_size = :attachment_size,
                    status = :status
                WHERE id = :id AND patient_id = :patient_id"
            );
            $stmt->bindValue(':id', $this->id, PDO::PARAM_INT);
        } else {
            $stmt = $db->prepare(
                "INSERT INTO consultation_requests (
                    patient_id,
                    doctor_id,
                    availability_id,
                    reason,
                    specialization,
                    attachment_path,
                    attachment_original_name,
                    attachment_mime,
                    attachment_size,
                    status
                ) VALUES (
                    :patient_id,
                    :doctor_id,
                    :availability_id,
                    :reason,
                    :specialization,
                    :attachment_path,
                    :attachment_original_name,
                    :attachment_mime,
                    :attachment_size,
                    :status
                )"
            );
        }

        $stmt->bindValue(':patient_id', $this->patient_id, PDO::PARAM_INT);
        $stmt->bindValue(':doctor_id', $this->doctor_id, PDO::PARAM_INT);

        if ($this->availability_id !== null) {
            $stmt->bindValue(':availability_id', $this->availability_id, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':availability_id', null, PDO::PARAM_NULL);
        }

        $stmt->bindValue(':reason', $this->reason);
        $stmt->bindValue(':specialization', $this->specialization);
        $stmt->bindValue(':attachment_path', $this->attachment_path);
        $stmt->bindValue(':attachment_original_name', $this->attachment_original_name);
        $stmt->bindValue(':attachment_mime', $this->attachment_mime);

        if ($this->attachment_size !== null) {
            $stmt->bindValue(':attachment_size', $this->attachment_size, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':attachment_size', null, PDO::PARAM_NULL);
        }

        $stmt->bindValue(':status', $this->status ?? 'Pending');

        if (!$stmt->execute()) {
            return false;
        }

        if ($this->id === null) {
            $this->id = (int) $db->lastInsertId();
        }

        return true;
    }

    public static function getSummaryForPatient(int $patientId): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                COUNT(*) AS total_requests,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending_requests,
                SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS approved_requests
            FROM consultation_requests
            WHERE patient_id = :patient_id"
        );
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_requests' => (int) ($row['total_requests'] ?? 0),
            'pending_requests' => (int) ($row['pending_requests'] ?? 0),
            'approved_requests' => (int) ($row['approved_requests'] ?? 0),
        ];
    }

    public static function countForPatient(int $patientId): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT COUNT(*) FROM consultation_requests WHERE patient_id = :patient_id");
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function findForPatient(int $patientId, int $limit = 10, int $offset = 0): array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                consultation_requests.id,
                consultation_requests.request_date,
                consultation_requests.specialization,
                consultation_requests.status,
                users.full_name AS doctor_name,
                doctor.professional_title AS doctor_title
            FROM consultation_requests
            INNER JOIN doctor ON doctor.user_id = consultation_requests.doctor_id
            INNER JOIN users ON users.id = doctor.user_id
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
                users.full_name AS doctor_name,
                users.email AS doctor_email,
                doctor.professional_title AS doctor_title,
                doctor.specialization AS doctor_profile_specialization
            FROM consultation_requests
            INNER JOIN doctor ON doctor.user_id = consultation_requests.doctor_id
            INNER JOIN users ON users.id = doctor.user_id
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
}

