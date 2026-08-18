<?php

namespace App\Services;

use App\Models\ConsultationRecord;
use App\Models\ConsultationRequest;
use App\Models\Prescription;

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
