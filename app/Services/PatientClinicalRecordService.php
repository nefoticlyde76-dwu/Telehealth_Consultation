<?php

namespace App\Services;

use App\Models\ConsultationRecord;
use App\Models\ConsultationRequest;
use App\Models\Prescription;
use App\Models\User;

class PatientClinicalRecordService
{
    /**
     * @return array{
     *   request:array<string,mixed>,
     *   record:?array<string,mixed>,
     *   prescriptions:list<array<string,mixed>>
     * }|null
     */
    public static function getCompletedRecordForPatient(int $patientId, int $consultationRequestId): ?array
    {
        $request = ConsultationRequest::findByIdForPatient($consultationRequestId, $patientId);
        if ($request === null || (int) ($request['patient_id'] ?? 0) !== $patientId) {
            return null;
        }

        $record = ConsultationRecord::findByConsultationForPatient($consultationRequestId, $patientId);
        $prescriptions = [];
        if (is_array($record)) {
            $prescriptions = Prescription::findByRecordForPatient((int) $record['id'], $patientId);
        }

        return [
            'request' => $request,
            'record' => $record,
            'prescriptions' => $prescriptions,
        ];
    }

    /**
     * Historical consultation record for the assigned doctor.
     * Draft notes stay in the live room and are not returned here.
     *
     * @return array{
     *   request:array<string,mixed>,
     *   record:?array<string,mixed>,
     *   prescriptions:list<array<string,mixed>>
     * }|null
     */
    public static function getHistoricalRecordForDoctor(int $doctorId, int $consultationRequestId): ?array
    {
        $request = ConsultationRequest::findByIdForDoctor($consultationRequestId, $doctorId);
        if ($request === null || (int) ($request['doctor_id'] ?? 0) !== $doctorId) {
            return null;
        }

        $record = ConsultationRecord::findByConsultationForDoctor($consultationRequestId, $doctorId);
        if (!is_array($record) || (string) ($record['record_status'] ?? '') !== ConsultationRecord::STATUS_FINAL) {
            $record = null;
        }

        $prescriptions = [];
        if (is_array($record)) {
            $prescriptions = Prescription::findByRecordForDoctor((int) $record['id'], $doctorId);
        }

        return [
            'request' => $request,
            'record' => $record,
            'prescriptions' => $prescriptions,
        ];
    }

    /**
     * Prior finalized records from other doctors for a patient this doctor
     * has already treated. Default deny when there is no consultation_requests
     * relationship. The viewing doctor's own records are not included.
     *
     * @return list<array{
     *   request:array<string,mixed>,
     *   record:array<string,mixed>,
     *   prescriptions:list<array<string,mixed>>
     * }>|null
     */
    public static function getPriorFinalizedRecordsForDoctor(int $viewerDoctorId, int $patientId): ?array
    {
        if ($viewerDoctorId <= 0 || $patientId <= 0) {
            return null;
        }

        if (!ConsultationRequest::doctorHasRelationshipWithPatient($viewerDoctorId, $patientId)) {
            return null;
        }

        $viewer = User::findById($viewerDoctorId);
        $prior = [];

        foreach (ConsultationRecord::findFinalizedHistoryForPatient($patientId) as $row) {
            if ((int) ($row['patient_id'] ?? 0) !== $patientId) {
                continue;
            }
            if ((string) ($row['record_status'] ?? '') !== ConsultationRecord::STATUS_FINAL) {
                continue;
            }

            $authorDoctorId = (int) ($row['doctor_id'] ?? 0);
            if ($authorDoctorId <= 0 || $authorDoctorId === $viewerDoctorId) {
                continue;
            }

            $recordId = (int) ($row['id'] ?? 0);
            if ($recordId <= 0) {
                continue;
            }

            $prescriptions = Prescription::findByRecordForPatient($recordId, $patientId);
            $request = self::priorRecordRequestContext($row);
            $prior[] = [
                'request' => $request,
                'record' => $row,
                'prescriptions' => $prescriptions,
            ];

            AuditLogService::record(
                'clinical_record_viewed_cross_doctor',
                'Doctor viewed another clinician\'s finalized clinical record.',
                AuditLogService::ENTITY_CONSULTATION_RECORD,
                $recordId,
                'success',
                [
                    'actor_name' => trim((string) ($viewer?->full_name ?? '')) !== ''
                        ? (string) $viewer->full_name
                        : 'Doctor',
                    'actor_role' => 'doctor',
                    'subject_name' => (string) ($row['patient_name'] ?? 'Patient'),
                    'subject_role' => 'patient',
                ]
            );
        }

        return $prior;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function priorRecordRequestContext(array $row): array
    {
        return [
            'id' => (int) ($row['consultation_request_id'] ?? 0),
            'patient_id' => (int) ($row['patient_id'] ?? 0),
            'doctor_id' => (int) ($row['doctor_id'] ?? 0),
            'status' => 'Completed',
            'patient_name' => (string) ($row['patient_name'] ?? ''),
            'patient_address' => (string) ($row['patient_address'] ?? ''),
            'doctor_name' => (string) ($row['doctor_name'] ?? ''),
            'doctor_title' => (string) ($row['doctor_title'] ?? ''),
            'specialization' => (string) ($row['specialization'] ?? ''),
            'doctor_signature_path' => (string) ($row['doctor_signature_path'] ?? ''),
            'doctor_clinic_address' => (string) ($row['doctor_clinic_address'] ?? ''),
            'doctor_photo_path' => $row['doctor_photo_path'] ?? null,
            'consultation_date' => (string) ($row['consultation_date'] ?? ''),
        ];
    }

    /**
     * Authorized printable document for the signed-in patient.
     * Returns null when the consultation is not theirs, is not completed,
     * or the requested document does not exist yet.
     *
     * @param 'record'|'prescription' $documentType
     * @return array{
     *   request:array<string,mixed>,
     *   record:?array<string,mixed>,
     *   prescriptions:list<array<string,mixed>>
     * }|null
     */
    public static function getPrintableDocumentForPatient(int $patientId, int $consultationRequestId, string $documentType): ?array
    {
        $page = self::getCompletedRecordForPatient($patientId, $consultationRequestId);
        if (!self::isDownloadableDocument($page, $documentType)) {
            return null;
        }

        return $page;
    }

    /**
     * Authorized printable document for the assigned doctor.
     *
     * @param 'record'|'prescription' $documentType
     * @return array{
     *   request:array<string,mixed>,
     *   record:?array<string,mixed>,
     *   prescriptions:list<array<string,mixed>>
     * }|null
     */
    public static function getPrintableDocumentForDoctor(int $doctorId, int $consultationRequestId, string $documentType): ?array
    {
        $page = self::getHistoricalRecordForDoctor($doctorId, $consultationRequestId);
        if (!self::isDownloadableDocument($page, $documentType)) {
            return null;
        }

        return $page;
    }

    /**
     * @param array{
     *   request:array<string,mixed>,
     *   record:?array<string,mixed>,
     *   prescriptions:list<array<string,mixed>>
     * }|null $page
     * @param 'record'|'prescription' $documentType
     */
    public static function isDownloadableDocument(?array $page, string $documentType): bool
    {
        if ($page === null) {
            return false;
        }

        $request = $page['request'] ?? [];
        if ((string) ($request['status'] ?? '') !== 'Completed') {
            return false;
        }

        $record = $page['record'] ?? null;
        $hasFinalRecord = is_array($record)
            && (string) ($record['record_status'] ?? '') === ConsultationRecord::STATUS_FINAL;

        if ($documentType === 'record') {
            return $hasFinalRecord;
        }

        if ($documentType === 'prescription') {
            return $hasFinalRecord && ($page['prescriptions'] ?? []) !== [];
        }

        return false;
    }
}
