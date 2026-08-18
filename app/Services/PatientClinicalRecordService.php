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
}
