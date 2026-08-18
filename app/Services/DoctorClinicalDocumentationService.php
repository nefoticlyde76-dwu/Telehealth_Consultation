<?php

namespace App\Services;

use App\Core\Csrf;
use App\Models\ConsultationRecord;
use App\Models\ConsultationRequest;

class DoctorClinicalDocumentationService
{
    /**
     * Load the assigned doctor's draft (if any) for the consultation room.
     *
     * @return array<string, mixed>|null
     */
    public static function getDraftForDoctor(int $consultationRequestId, int $doctorId): ?array
    {
        return ConsultationRecord::findByConsultationForDoctor($consultationRequestId, $doctorId);
    }

    /**
     * Guarantee a Draft row exists when the assigned doctor opens an
     * approved consultation. Existing notes are never overwritten.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>|null
     */
    public static function ensureDraftForRoom(array $request, int $doctorId): ?array
    {
        $consultationRequestId = (int) ($request['id'] ?? 0);
        if ($consultationRequestId <= 0 || $doctorId <= 0) {
            return null;
        }

        $existing = ConsultationRecord::findByConsultationForDoctor($consultationRequestId, $doctorId);
        if ((string) ($request['status'] ?? '') !== 'Approved') {
            return $existing;
        }

        $patientId = (int) ($request['patient_id'] ?? 0);
        if ($patientId <= 0 || (int) ($request['doctor_id'] ?? 0) !== $doctorId) {
            return $existing;
        }

        return ConsultationRecord::ensureDraftForDoctor(
            $consultationRequestId,
            $doctorId,
            $patientId,
            self::resolveConsultationDateTime($request),
            trim((string) ($request['reason'] ?? ''))
        );
    }

    /**
     * Save a Draft clinical record during a live consultation.
     * Does not finalize the record and does not complete the consultation.
     *
     * @param array<string, mixed> $fields
     * @return array{ok:bool,http_code:int,code:string,message:string,record?:array<string,mixed>}
     */
    public static function saveDraft(int $doctorId, int $consultationRequestId, string $csrfToken, array $fields): array
    {
        if (!Csrf::verify($csrfToken)) {
            return [
                'ok' => false,
                'http_code' => 419,
                'code' => 'invalid_csrf',
                'message' => 'Security token expired or is invalid. Please refresh the page and try again.',
            ];
        }

        if ($doctorId <= 0 || $consultationRequestId <= 0) {
            return [
                'ok' => false,
                'http_code' => 400,
                'code' => 'invalid_consultation',
                'message' => 'The consultation could not be identified.',
            ];
        }

        $request = ConsultationRequest::findByIdForDoctor($consultationRequestId, $doctorId);
        if ($request === null) {
            return [
                'ok' => false,
                'http_code' => 404,
                'code' => 'not_found',
                'message' => 'Consultation not found.',
            ];
        }

        if ((int) ($request['doctor_id'] ?? 0) !== $doctorId) {
            return [
                'ok' => false,
                'http_code' => 403,
                'code' => 'forbidden_ownership',
                'message' => 'You are not assigned to this consultation.',
            ];
        }

        $status = (string) ($request['status'] ?? '');
        if ($status !== 'Approved') {
            return [
                'ok' => false,
                'http_code' => 403,
                'code' => 'not_editable',
                'message' => $status === 'Completed'
                    ? 'This consultation is completed. The clinical record can no longer be edited here.'
                    : 'Clinical notes can be recorded once the consultation is approved.',
            ];
        }

        $patientId = (int) ($request['patient_id'] ?? 0);
        if ($patientId <= 0) {
            return [
                'ok' => false,
                'http_code' => 400,
                'code' => 'missing_patient',
                'message' => 'This consultation is missing a patient assignment.',
            ];
        }

        $consultationDateTime = self::resolveConsultationDateTime($request);
        $expectedRecordId = (int) ($fields['record_id'] ?? 0);
        $result = ConsultationRecord::upsertDraftForDoctor(
            $consultationRequestId,
            $doctorId,
            $patientId,
            $consultationDateTime,
            $fields,
            $expectedRecordId
        );

        if (!($result['success'] ?? false)) {
            $message = (string) ($result['message'] ?? 'The clinical draft could not be saved.');
            $wrongRecord = str_contains($message, 'does not belong')
                || str_contains($message, 'different consultation');
            $locked = str_contains($message, 'finalized');
            return [
                'ok' => false,
                'http_code' => $wrongRecord || $locked ? 403 : 500,
                'code' => $wrongRecord ? 'record_mismatch' : ($locked ? 'record_final' : 'save_failed'),
                'message' => $message,
            ];
        }

        return [
            'ok' => true,
            'http_code' => 200,
            'code' => 'saved',
            'message' => 'Draft saved.',
            'record' => is_array($result['record'] ?? null) ? $result['record'] : [],
        ];
    }

    /**
     * Save the latest clinical notes, finalize the record, and mark the
     * consultation Completed. Requires an explicit confirmation flag.
     *
     * @param array<string, mixed> $fields
     * @return array{success:bool,message:string,type:string}
     */
    public static function completeConsultation(
        int $doctorId,
        int $consultationRequestId,
        string $csrfToken,
        array $fields,
        bool $confirmed
    ): array {
        if (!Csrf::verify($csrfToken)) {
            return [
                'success' => false,
                'message' => 'Unable to verify the request. Please refresh the page and try again.',
                'type' => 'danger',
            ];
        }

        if (!$confirmed) {
            return [
                'success' => false,
                'message' => 'Please confirm that you want to complete this consultation.',
                'type' => 'warning',
            ];
        }

        $request = ConsultationRequest::findByIdForDoctor($consultationRequestId, $doctorId);
        if ($request === null || (int) ($request['doctor_id'] ?? 0) !== $doctorId) {
            return [
                'success' => false,
                'message' => 'Consultation not found.',
                'type' => 'warning',
            ];
        }

        $patientId = (int) ($request['patient_id'] ?? 0);
        if ($patientId <= 0) {
            return [
                'success' => false,
                'message' => 'This consultation is missing a patient assignment.',
                'type' => 'danger',
            ];
        }

        return ConsultationRecord::completeConsultationForDoctor(
            $consultationRequestId,
            $doctorId,
            $patientId,
            self::resolveConsultationDateTime($request),
            $fields
        );
    }

    /**
     * @param array<string, mixed> $request
     */
    private static function resolveConsultationDateTime(array $request): string
    {
        $date = trim((string) ($request['consultation_date'] ?? ''));
        $start = trim((string) ($request['start_time'] ?? ''));

        if ($date !== '' && $start !== '') {
            $start = strlen($start) === 5 ? $start . ':00' : $start;
            return $date . ' ' . $start;
        }

        return (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    }
}
