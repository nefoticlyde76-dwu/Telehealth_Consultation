<?php

namespace App\Services;

use App\Core\Csrf;
use App\Helpers\Helper;
use App\Models\ConsultationRecord;
use App\Models\ConsultationRequest;
use App\Models\Doctor;

class PatientConsultationRequestService
{
    public const PER_PAGE = 10;
    public const MAX_ATTACHMENT_BYTES = 15728640;

    public static function getDashboardSummary(int $patientId): array
    {
        $requestSummary = ConsultationRequest::getSummaryForPatient($patientId);
        $completedConsultations = ConsultationRecord::countForPatient($patientId);

        return [
            'total_requests' => (int) ($requestSummary['total_requests'] ?? 0),
            'pending_requests' => (int) ($requestSummary['pending_requests'] ?? 0),
            'approved_requests' => (int) ($requestSummary['approved_requests'] ?? 0),
            'completed_consultations' => $completedConsultations,
        ];
    }

    public static function getRequestListPageData(int $patientId, array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $totalItems = ConsultationRequest::countForPatient($patientId);
        $totalPages = max(1, (int) ceil($totalItems / self::PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * self::PER_PAGE;

        return [
            'requests' => ConsultationRequest::findForPatient($patientId, self::PER_PAGE, $offset),
            'summary' => self::getDashboardSummary($patientId),
            'pagination' => [
                'current_page' => $page,
                'per_page' => self::PER_PAGE,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public static function getRequestDetail(int $patientId, int $requestId): ?array
    {
        if ($requestId <= 0) {
            return null;
        }

        return ConsultationRequest::findByIdForPatient($requestId, $patientId);
    }

    public static function getSpecializationOptions(): array
    {
        return Doctor::getSpecializationOptionsForPatients();
    }

    public static function getRequestFormData(array $input = []): array
    {
        return [
            'reason' => trim((string) ($input['reason'] ?? '')),
            'specialization' => trim((string) ($input['specialization'] ?? '')),
        ];
    }

    public static function submitRequest(int $patientId, array $input, array $files): array
    {
        $formData = self::getRequestFormData($input);
        $errors = [];
        $fieldErrors = [];

        if (!Csrf::verify((string) ($input['_token'] ?? ''))) {
            $fieldErrors['_token'] = 'Unable to verify the request. Please refresh the page and try again.';
        }

        if ($formData['reason'] === '') {
            $fieldErrors['reason'] = 'Consultation reason is required.';
        } elseif (mb_strlen($formData['reason']) > 2000) {
            $fieldErrors['reason'] = 'Consultation reason must be 2000 characters or fewer.';
        }

        $specializationOptions = self::getSpecializationOptions();

        if ($formData['specialization'] === '') {
            $fieldErrors['specialization'] = 'Medical specialization is required.';
        } elseif (!in_array($formData['specialization'], $specializationOptions, true)) {
            $fieldErrors['specialization'] = 'Please select a valid medical specialization.';
        }

        $attachment = $files['attachment'] ?? null;
        $attachmentData = null;

        if (is_array($attachment) && ($attachment['name'] ?? '') !== '') {
            $attachmentResult = self::validateAndStoreAttachment($patientId, $attachment);

            if (!($attachmentResult['success'] ?? false)) {
                $fieldErrors['attachment'] = (string) ($attachmentResult['message'] ?? 'Attachment upload is invalid.');
            } else {
                $attachmentData = $attachmentResult['data'] ?? null;
            }
        }

        if ($fieldErrors !== []) {
            if (isset($fieldErrors['_token'])) {
                $errors[] = $fieldErrors['_token'];
            }

            $errors[] = 'Please correct the highlighted consultation request fields.';

            return [
                'success' => false,
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
                'formData' => $formData,
                'specializationOptions' => $specializationOptions,
            ];
        }

        $doctorId = Doctor::findBestDoctorIdForSpecialization($formData['specialization']);

        if ($doctorId === null) {
            return [
                'success' => false,
                'errors' => ['No doctor is currently available for the selected specialization. Please try again later.'],
                'fieldErrors' => [],
                'formData' => $formData,
                'specializationOptions' => $specializationOptions,
            ];
        }

        try {
            $request = new ConsultationRequest();
            $request->patient_id = $patientId;
            $request->doctor_id = $doctorId;
            $request->availability_id = null;
            $request->reason = $formData['reason'];
            $request->specialization = $formData['specialization'];
            $request->status = 'Pending';

            if ($attachmentData !== null) {
                $request->attachment_path = $attachmentData['attachment_path'] ?? null;
                $request->attachment_original_name = $attachmentData['attachment_original_name'] ?? null;
                $request->attachment_mime = $attachmentData['attachment_mime'] ?? null;
                $request->attachment_size = isset($attachmentData['attachment_size']) ? (int) $attachmentData['attachment_size'] : null;
            }

            if (!$request->save()) {
                throw new \RuntimeException('Consultation request insert failed.');
            }

            return [
                'success' => true,
                'requestId' => $request->id,
                'message' => 'Consultation request submitted successfully.',
            ];
        } catch (\Throwable $exception) {
            error_log('Consultation request creation failed: ' . $exception->getMessage());

            if ($attachmentData !== null && !empty($attachmentData['attachment_path'])) {
                self::deleteStoredAttachment((string) $attachmentData['attachment_path']);
            }

            return [
                'success' => false,
                'errors' => ['Consultation request submission is temporarily unavailable. Please try again later.'],
                'fieldErrors' => [],
                'formData' => $formData,
                'specializationOptions' => $specializationOptions,
            ];
        }
    }

    private static function validateAndStoreAttachment(int $patientId, array $file): array
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'message' => 'Unable to upload the attachment. Please try a different file.',
            ];
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return [
                'success' => false,
                'message' => 'Unable to verify the uploaded attachment. Please try again.',
            ];
        }

        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0) {
            return [
                'success' => false,
                'message' => 'The uploaded attachment appears to be empty.',
            ];
        }

        if ($size > self::MAX_ATTACHMENT_BYTES) {
            return [
                'success' => false,
                'message' => 'Attachment must be 15 MB or smaller.',
            ];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $tmpName) : null;

        if ($finfo) {
            finfo_close($finfo);
        }

        $allowedMimes = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if ($mime === null || !isset($allowedMimes[$mime])) {
            return [
                'success' => false,
                'message' => 'Only PDF, JPG, PNG, or WEBP files are allowed.',
            ];
        }

        $baseDir = 'uploads/consultation_requests/' . $patientId;
        $absoluteBaseDir = dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'public'
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $baseDir);

        if (!is_dir($absoluteBaseDir) && !mkdir($absoluteBaseDir, 0775, true) && !is_dir($absoluteBaseDir)) {
            return [
                'success' => false,
                'message' => 'Unable to store the attachment at this time.',
            ];
        }

        $extension = $allowedMimes[$mime];
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $absolutePath = rtrim($absoluteBaseDir, '\\/') . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpName, $absolutePath)) {
            return [
                'success' => false,
                'message' => 'Unable to store the uploaded attachment. Please try again.',
            ];
        }

        return [
            'success' => true,
            'data' => [
                'attachment_path' => $baseDir . '/' . $filename,
                'attachment_original_name' => (string) ($file['name'] ?? ''),
                'attachment_mime' => $mime,
                'attachment_size' => $size,
            ],
        ];
    }

    private static function deleteStoredAttachment(string $relativePath): void
    {
        $relativePath = str_replace(['\\', "\0"], ['/', ''], ltrim($relativePath, '/'));

        if ($relativePath === '') {
            return;
        }

        $absolutePath = dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'public'
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}
