<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use PDOException;

class ConsultationRecord
{
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_FINAL = 'Final';

    public const MAX_FIELD_LENGTH = 8000;

    public const REQUIRED_FIELDS = [
        'chief_complaint' => 'Chief complaint / presenting problem',
        'symptoms' => 'History / symptoms',
        'clinical_findings' => 'Clinical findings / assessment',
        'diagnosis' => 'Diagnosis',
        'treatment_plan' => 'Treatment / medical advice',
    ];

    /**
     * @return array<string, mixed>|null
     */
    public static function findByConsultationForDoctor(int $consultationRequestId, int $doctorId): ?array
    {
        if ($consultationRequestId <= 0 || $doctorId <= 0) {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                consultation_records.id,
                consultation_records.consultation_request_id,
                consultation_records.patient_id,
                consultation_records.doctor_id,
                consultation_records.chief_complaint,
                consultation_records.symptoms,
                consultation_records.clinical_findings,
                consultation_records.diagnosis,
                consultation_records.treatment_plan,
                consultation_records.additional_notes,
                consultation_records.record_status,
                consultation_records.finalized_at,
                consultation_records.consultation_date,
                consultation_records.created_at,
                consultation_records.updated_at
             FROM consultation_records
            WHERE consultation_records.consultation_request_id = :consultation_request_id
              AND consultation_records.doctor_id = :doctor_id
            LIMIT 1"
        );
        $stmt->bindValue(':consultation_request_id', $consultationRequestId, PDO::PARAM_INT);
        $stmt->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Insert or update a Draft clinical record for the assigned doctor.
     * Patient id and doctor id are taken from the consultation request row,
     * never from the browser payload.
     *
     * @param array{
     *   chief_complaint?:string,
     *   symptoms?:string,
     *   clinical_findings?:string,
     *   diagnosis?:string,
     *   treatment_plan?:string,
     *   additional_notes?:string
     * } $fields
     * @return array{success:bool,message:string,type:string,record?:array<string,mixed>}
     */
    public static function upsertDraftForDoctor(
        int $consultationRequestId,
        int $doctorId,
        int $patientId,
        string $consultationDateTime,
        array $fields,
        int $expectedRecordId = 0
    ): array {
        if ($consultationRequestId <= 0 || $doctorId <= 0 || $patientId <= 0) {
            return [
                'success' => false,
                'message' => 'The consultation record could not be associated with this appointment.',
                'type' => 'danger',
            ];
        }

        $payload = self::normalizeFields($fields);
        $db = Database::getInstance();
        $ownsTransaction = !$db->inTransaction();

        try {
            if ($ownsTransaction) {
                $db->beginTransaction();
            }

            $lock = $db->prepare(
                "SELECT id, doctor_id, patient_id, record_status,
                        chief_complaint, symptoms, clinical_findings,
                        diagnosis, treatment_plan, additional_notes
                   FROM consultation_records
                  WHERE consultation_request_id = :consultation_request_id
                  LIMIT 1
                  FOR UPDATE"
            );
            $lock->bindValue(':consultation_request_id', $consultationRequestId, PDO::PARAM_INT);
            $lock->execute();
            $existing = $lock->fetch(PDO::FETCH_ASSOC) ?: null;

            if ($expectedRecordId > 0) {
                if (!is_array($existing) || (int) ($existing['id'] ?? 0) !== $expectedRecordId) {
                    if ($ownsTransaction && $db->inTransaction()) {
                        $db->rollBack();
                    }
                    return [
                        'success' => false,
                        'message' => 'The clinical record does not belong to this consultation.',
                        'type' => 'danger',
                    ];
                }
            }

            if (is_array($existing)) {
                if ((int) ($existing['doctor_id'] ?? 0) !== $doctorId
                    || (int) ($existing['patient_id'] ?? 0) !== $patientId
                ) {
                    if ($ownsTransaction && $db->inTransaction()) {
                        $db->rollBack();
                    }
                    return [
                        'success' => false,
                        'message' => 'This clinical record belongs to a different consultation and cannot be changed here.',
                        'type' => 'danger',
                    ];
                }

                if ((string) ($existing['record_status'] ?? '') === self::STATUS_FINAL) {
                    if ($ownsTransaction && $db->inTransaction()) {
                        $db->rollBack();
                    }
                    return [
                        'success' => false,
                        'message' => 'This clinical record has been finalized and can no longer be edited.',
                        'type' => 'warning',
                    ];
                }

                if (!self::fieldsMatch($existing, $payload)) {
                    self::bindAndExecuteDraftUpdate($db, (int) $existing['id'], $doctorId, $patientId, $payload);
                }
            } else {
                try {
                    self::bindAndExecuteDraftInsert(
                        $db,
                        $consultationRequestId,
                        $doctorId,
                        $patientId,
                        $consultationDateTime,
                        $payload
                    );
                } catch (PDOException $e) {
                    if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                        throw $e;
                    }

                    $lock->execute();
                    $duplicate = $lock->fetch(PDO::FETCH_ASSOC) ?: null;
                    if (!is_array($duplicate)
                        || (int) ($duplicate['doctor_id'] ?? 0) !== $doctorId
                        || (int) ($duplicate['patient_id'] ?? 0) !== $patientId
                    ) {
                        throw $e;
                    }
                    if ((string) ($duplicate['record_status'] ?? '') === self::STATUS_FINAL) {
                        if ($ownsTransaction && $db->inTransaction()) {
                            $db->rollBack();
                        }
                        return [
                            'success' => false,
                            'message' => 'This clinical record has been finalized and can no longer be edited.',
                            'type' => 'warning',
                        ];
                    }
                    self::bindAndExecuteDraftUpdate($db, (int) $duplicate['id'], $doctorId, $patientId, $payload);
                }
            }

            if ($ownsTransaction) {
                $db->commit();
            }
        } catch (PDOException $e) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[ConsultationRecord::upsertDraftForDoctor] ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'The clinical draft could not be saved right now.',
                'type' => 'danger',
            ];
        }

        $record = self::findByConsultationForDoctor($consultationRequestId, $doctorId);
        if ($record === null) {
            return [
                'success' => false,
                'message' => 'The clinical draft was written but could not be reloaded.',
                'type' => 'warning',
            ];
        }

        return [
            'success' => true,
            'message' => 'Clinical draft saved.',
            'type' => 'success',
            'record' => $record,
        ];
    }

    /**
     * Create a Draft row when the assigned doctor opens an approved
     * consultation, or return the existing row. Never overwrites notes.
     *
     * @return array<string, mixed>|null
     */
    public static function ensureDraftForDoctor(
        int $consultationRequestId,
        int $doctorId,
        int $patientId,
        string $consultationDateTime,
        string $chiefComplaint
    ): ?array {
        $existing = self::findByConsultationForDoctor($consultationRequestId, $doctorId);
        if (is_array($existing)) {
            return $existing;
        }

        $db = Database::getInstance();
        $payload = self::normalizeFields([
            'chief_complaint' => $chiefComplaint,
        ]);

        try {
            self::bindAndExecuteDraftInsert(
                $db,
                $consultationRequestId,
                $doctorId,
                $patientId,
                $consultationDateTime,
                $payload
            );
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                error_log('[ConsultationRecord::ensureDraftForDoctor] ' . $e->getMessage());
                return self::findByConsultationForDoctor($consultationRequestId, $doctorId);
            }
        }

        return self::findByConsultationForDoctor($consultationRequestId, $doctorId);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, string> $payload
     */
    private static function fieldsMatch(array $row, array $payload): bool
    {
        foreach ($payload as $key => $value) {
            if (trim((string) ($row[$key] ?? '')) !== (string) $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, string> $payload
     */
    private static function bindAndExecuteDraftUpdate(
        PDO $db,
        int $recordId,
        int $doctorId,
        int $patientId,
        array $payload
    ): void {
        $update = $db->prepare(
            "UPDATE consultation_records
                SET chief_complaint = :chief_complaint,
                    symptoms = :symptoms,
                    clinical_findings = :clinical_findings,
                    diagnosis = :diagnosis,
                    treatment_plan = :treatment_plan,
                    additional_notes = :additional_notes,
                    record_status = :record_status
              WHERE id = :id
                AND doctor_id = :doctor_id
                AND patient_id = :patient_id
                AND record_status = :draft_status"
        );
        $update->bindValue(':chief_complaint', $payload['chief_complaint']);
        $update->bindValue(':symptoms', $payload['symptoms']);
        $update->bindValue(':clinical_findings', $payload['clinical_findings']);
        $update->bindValue(':diagnosis', $payload['diagnosis']);
        $update->bindValue(':treatment_plan', $payload['treatment_plan']);
        $update->bindValue(':additional_notes', $payload['additional_notes']);
        $update->bindValue(':record_status', self::STATUS_DRAFT);
        $update->bindValue(':id', $recordId, PDO::PARAM_INT);
        $update->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
        $update->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $update->bindValue(':draft_status', self::STATUS_DRAFT);
        $update->execute();
    }

    /**
     * @param array<string, string> $payload
     */
    private static function bindAndExecuteDraftInsert(
        PDO $db,
        int $consultationRequestId,
        int $doctorId,
        int $patientId,
        string $consultationDateTime,
        array $payload
    ): void {
        $insert = $db->prepare(
            "INSERT INTO consultation_records (
                consultation_request_id,
                patient_id,
                doctor_id,
                chief_complaint,
                symptoms,
                clinical_findings,
                diagnosis,
                treatment_plan,
                additional_notes,
                record_status,
                consultation_date
             ) VALUES (
                :consultation_request_id,
                :patient_id,
                :doctor_id,
                :chief_complaint,
                :symptoms,
                :clinical_findings,
                :diagnosis,
                :treatment_plan,
                :additional_notes,
                :record_status,
                :consultation_date
             )"
        );
        $insert->bindValue(':consultation_request_id', $consultationRequestId, PDO::PARAM_INT);
        $insert->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $insert->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
        $insert->bindValue(':chief_complaint', $payload['chief_complaint']);
        $insert->bindValue(':symptoms', $payload['symptoms']);
        $insert->bindValue(':clinical_findings', $payload['clinical_findings']);
        $insert->bindValue(':diagnosis', $payload['diagnosis']);
        $insert->bindValue(':treatment_plan', $payload['treatment_plan']);
        $insert->bindValue(':additional_notes', $payload['additional_notes']);
        $insert->bindValue(':record_status', self::STATUS_DRAFT);
        $insert->bindValue(':consultation_date', $consultationDateTime);
        $insert->execute();
    }

    /**
     * @param array<string, mixed> $fields
     * @return array{
     *   chief_complaint:string,
     *   symptoms:string,
     *   clinical_findings:string,
     *   diagnosis:string,
     *   treatment_plan:string,
     *   additional_notes:string
     * }
     */
    public static function normalizeFields(array $fields): array
    {
        $keys = [
            'chief_complaint',
            'symptoms',
            'clinical_findings',
            'diagnosis',
            'treatment_plan',
            'additional_notes',
        ];

        $normalized = [];
        foreach ($keys as $key) {
            $value = trim((string) ($fields[$key] ?? ''));
            $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
            if ($length > self::MAX_FIELD_LENGTH) {
                $value = function_exists('mb_substr')
                    ? mb_substr($value, 0, self::MAX_FIELD_LENGTH)
                    : substr($value, 0, self::MAX_FIELD_LENGTH);
            }
            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByConsultationForPatient(int $consultationRequestId, int $patientId): ?array
    {
        if ($consultationRequestId <= 0 || $patientId <= 0) {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                consultation_records.id,
                consultation_records.consultation_request_id,
                consultation_records.patient_id,
                consultation_records.doctor_id,
                consultation_records.chief_complaint,
                consultation_records.symptoms,
                consultation_records.clinical_findings,
                consultation_records.diagnosis,
                consultation_records.treatment_plan,
                consultation_records.additional_notes,
                consultation_records.record_status,
                consultation_records.finalized_at,
                consultation_records.consultation_date,
                consultation_records.created_at,
                consultation_records.updated_at
             FROM consultation_records
            WHERE consultation_records.consultation_request_id = :consultation_request_id
              AND consultation_records.patient_id = :patient_id
              AND consultation_records.record_status = :record_status
            LIMIT 1"
        );
        $stmt->bindValue(':consultation_request_id', $consultationRequestId, PDO::PARAM_INT);
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->bindValue(':record_status', self::STATUS_FINAL);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $fields
     * @return list<string>
     */
    public static function missingRequiredFields(array $fields): array
    {
        $normalized = self::normalizeFields($fields);
        $missing = [];
        foreach (self::REQUIRED_FIELDS as $key => $label) {
            if ($normalized[$key] === '') {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    /**
     * Save the latest draft, finalize the clinical record, and mark the
     * consultation request Completed in one transaction.
     *
     * @param array<string, mixed> $fields
     * @return array{success:bool,message:string,type:string,record?:array<string,mixed>}
     */
    public static function completeConsultationForDoctor(
        int $consultationRequestId,
        int $doctorId,
        int $patientId,
        string $consultationDateTime,
        array $fields
    ): array {
        $payload = self::normalizeFields($fields);
        $existingRecord = self::findByConsultationForDoctor($consultationRequestId, $doctorId);
        $alreadyFinal = is_array($existingRecord)
            && (string) ($existingRecord['record_status'] ?? '') === self::STATUS_FINAL;

        if (!$alreadyFinal) {
            $saved = self::upsertDraftForDoctor(
                $consultationRequestId,
                $doctorId,
                $patientId,
                $consultationDateTime,
                $payload
            );
            if (!($saved['success'] ?? false)) {
                return $saved;
            }
        }

        $db = Database::getInstance();
        $ownsTransaction = !$db->inTransaction();

        try {
            if ($ownsTransaction) {
                $db->beginTransaction();
            }

            $recordLock = $db->prepare(
                "SELECT id, doctor_id, patient_id, record_status
                   FROM consultation_records
                  WHERE consultation_request_id = :consultation_request_id
                  LIMIT 1
                  FOR UPDATE"
            );
            $recordLock->bindValue(':consultation_request_id', $consultationRequestId, PDO::PARAM_INT);
            $recordLock->execute();
            $record = $recordLock->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!is_array($record)
                || (int) ($record['doctor_id'] ?? 0) !== $doctorId
                || (int) ($record['patient_id'] ?? 0) !== $patientId
            ) {
                if ($ownsTransaction && $db->inTransaction()) {
                    $db->rollBack();
                }
                return [
                    'success' => false,
                    'message' => 'The clinical record could not be finalized for this consultation.',
                    'type' => 'danger',
                ];
            }

            if ((string) ($record['record_status'] ?? '') !== self::STATUS_FINAL) {
                $finalize = $db->prepare(
                    "UPDATE consultation_records
                        SET record_status = :final_status,
                            finalized_at = COALESCE(finalized_at, NOW())
                      WHERE id = :id
                        AND doctor_id = :doctor_id
                        AND patient_id = :patient_id"
                );
                $finalize->bindValue(':final_status', self::STATUS_FINAL);
                $finalize->bindValue(':id', (int) $record['id'], PDO::PARAM_INT);
                $finalize->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
                $finalize->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
                $finalize->execute();
            }

            $requestLock = $db->prepare(
                "SELECT id, status
                   FROM consultation_requests
                  WHERE id = :id
                    AND doctor_id = :doctor_id
                  LIMIT 1
                  FOR UPDATE"
            );
            $requestLock->bindValue(':id', $consultationRequestId, PDO::PARAM_INT);
            $requestLock->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
            $requestLock->execute();
            $requestRow = $requestLock->fetch(PDO::FETCH_ASSOC) ?: null;

            if (!is_array($requestRow)) {
                if ($ownsTransaction && $db->inTransaction()) {
                    $db->rollBack();
                }
                return [
                    'success' => false,
                    'message' => 'The consultation could not be found.',
                    'type' => 'warning',
                ];
            }

            $status = (string) ($requestRow['status'] ?? '');
            if ($status === 'Completed') {
                if ($ownsTransaction) {
                    $db->commit();
                }
                $final = self::findByConsultationForDoctor($consultationRequestId, $doctorId);
                return [
                    'success' => true,
                    'message' => 'This consultation is already marked as completed.',
                    'type' => 'info',
                    'record' => is_array($final) ? $final : [],
                ];
            }

            if ($status !== 'Approved') {
                if ($ownsTransaction && $db->inTransaction()) {
                    $db->rollBack();
                }
                return [
                    'success' => false,
                    'message' => 'Only approved consultations can be marked as completed.',
                    'type' => 'danger',
                ];
            }

            $complete = $db->prepare(
                "UPDATE consultation_requests
                    SET status = 'Completed',
                        completed_at = COALESCE(completed_at, NOW())
                  WHERE id = :id
                    AND doctor_id = :doctor_id
                    AND status = 'Approved'"
            );
            $complete->bindValue(':id', $consultationRequestId, PDO::PARAM_INT);
            $complete->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
            $complete->execute();

            if ($ownsTransaction) {
                $db->commit();
            }
        } catch (PDOException $e) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[ConsultationRecord::completeConsultationForDoctor] ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'The consultation could not be completed right now.',
                'type' => 'danger',
            ];
        }

        $final = self::findByConsultationForDoctor($consultationRequestId, $doctorId);

        return [
            'success' => true,
            'message' => 'Consultation completed. The clinical record is now finalized.',
            'type' => 'success',
            'record' => is_array($final) ? $final : [],
        ];
    }
}
