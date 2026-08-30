<?php

namespace App\Services;

use App\Core\Csrf;
use App\Models\ConsultationRecord;
use App\Models\ConsultationRequest;
use App\Models\Prescription;

class DoctorPrescriptionService
{
    /**
     * @return array{
     *   request:array<string,mixed>,
     *   record:array<string,mixed>,
     *   prescriptions:list<array<string,mixed>>,
     *   has_signature:bool,
     *   can_create:bool
     * }|null
     */
    public static function getPrescriptionPageForDoctor(int $doctorId, int $consultationRequestId): ?array
    {
        $request = ConsultationRequest::findByIdForDoctor($consultationRequestId, $doctorId);
        if ($request === null || (int) ($request['doctor_id'] ?? 0) !== $doctorId) {
            return null;
        }

        $record = ConsultationRecord::findByConsultationForDoctor($consultationRequestId, $doctorId);
        if (!is_array($record) || (string) ($record['record_status'] ?? '') !== ConsultationRecord::STATUS_FINAL) {
            return [
                'request' => $request,
                'record' => is_array($record) ? $record : [],
                'prescriptions' => [],
                'has_signature' => trim((string) ($request['doctor_signature_path'] ?? '')) !== '',
                'can_create' => false,
            ];
        }

        $prescriptions = Prescription::findByRecordForDoctor((int) $record['id'], $doctorId);
        $hasSignature = trim((string) ($request['doctor_signature_path'] ?? '')) !== '';

        return [
            'request' => $request,
            'record' => $record,
            'prescriptions' => $prescriptions,
            'has_signature' => $hasSignature,
            'can_create' => (string) ($request['status'] ?? '') === 'Completed'
                && $prescriptions === []
                && $hasSignature,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success:bool,message:string,type:string}
     */
    public static function createPrescription(int $doctorId, int $consultationRequestId, string $csrfToken, array $input): array
    {
        if (!Csrf::verify($csrfToken)) {
            return [
                'success' => false,
                'message' => 'Unable to verify the request. Please refresh the page and try again.',
                'type' => 'danger',
            ];
        }

        $page = self::getPrescriptionPageForDoctor($doctorId, $consultationRequestId);
        if ($page === null) {
            return [
                'success' => false,
                'message' => 'Consultation not found.',
                'type' => 'warning',
            ];
        }

        if ((string) ($page['request']['status'] ?? '') !== 'Completed') {
            return [
                'success' => false,
                'message' => 'A prescription can only be issued after the consultation is completed.',
                'type' => 'warning',
            ];
        }

        $record = $page['record'];
        if ((string) ($record['record_status'] ?? '') !== ConsultationRecord::STATUS_FINAL) {
            return [
                'success' => false,
                'message' => 'Finalize the clinical record before issuing a prescription.',
                'type' => 'warning',
            ];
        }

        if ((int) ($record['patient_id'] ?? 0) !== (int) ($page['request']['patient_id'] ?? 0)
            || (int) ($record['doctor_id'] ?? 0) !== $doctorId
        ) {
            return [
                'success' => false,
                'message' => 'This clinical record does not belong to the assigned consultation.',
                'type' => 'danger',
            ];
        }

        $signature = trim((string) ($page['request']['doctor_signature_path'] ?? ''));
        if ($signature === '') {
            return [
                'success' => false,
                'message' => 'Upload your signature in My Profile before issuing a prescription.',
                'type' => 'warning',
            ];
        }

        $medications = $input['medications'] ?? [];
        if (!is_array($medications)) {
            $medications = [];
        }

        $result = Prescription::createForCompletedConsultation(
            (int) $record['id'],
            $doctorId,
            (int) $record['patient_id'],
            $medications
        );

        if (($result['success'] ?? false) === true) {
            NotificationService::notifyPrescriptionCreated($consultationRequestId);
            AuditLogService::record(
                'prescription_created',
                'Doctor created a prescription for a completed consultation.',
                AuditLogService::ENTITY_PRESCRIPTION,
                $consultationRequestId
            );
        }

        return $result;
    }
}
