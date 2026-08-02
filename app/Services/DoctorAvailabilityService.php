<?php

namespace App\Services;

use App\Core\Csrf;
use App\Models\DoctorAvailability;

class DoctorAvailabilityService
{
    public const PER_PAGE = 10;

    public static function getAvailabilityPageData(int $doctorId, array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $filters = self::normalizeFilters($query);
        $totalItems = DoctorAvailability::countForDoctor($doctorId, $filters);
        $totalPages = max(1, (int) ceil($totalItems / self::PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * self::PER_PAGE;

        return [
            'filters' => $filters,
            'availability' => DoctorAvailability::findForDoctor($doctorId, $filters, self::PER_PAGE, $offset),
            'summary' => DoctorAvailability::getSummaryForDoctor($doctorId),
            'pagination' => [
                'current_page' => $page,
                'per_page' => self::PER_PAGE,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
            ],
            'statusOptions' => self::getStatusOptions(),
        ];
    }

    public static function getAvailabilityDetail(int $doctorId, int $availabilityId): ?array
    {
        if ($doctorId <= 0 || $availabilityId <= 0) {
            return null;
        }

        return DoctorAvailability::findByIdForDoctor($availabilityId, $doctorId);
    }

    public static function getAvailabilityFormData(?array $availability = null): array
    {
        return [
            '_token' => '',
            'consultation_date' => (string) ($availability['consultation_date'] ?? ''),
            'start_time' => self::normalizeTimeForForm((string) ($availability['start_time'] ?? '')),
            'end_time' => self::normalizeTimeForForm((string) ($availability['end_time'] ?? '')),
            'notes' => (string) ($availability['notes'] ?? ''),
            'status' => (string) ($availability['status'] ?? 'Available'),
        ];
    }

    public static function createAvailability(int $doctorId, array $input): array
    {
        $formData = self::normalizeFormData($input);
        [$errors, $fieldErrors] = self::validateAvailabilityForm($doctorId, $formData);

        if ($errors !== []) {
            return [
                'success' => false,
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
                'formData' => $formData,
            ];
        }

        $availability = new DoctorAvailability();
        $availability->doctor_id = $doctorId;
        $availability->consultation_date = $formData['consultation_date'];
        $availability->start_time = $formData['start_time'];
        $availability->end_time = $formData['end_time'];
        $availability->notes = $formData['notes'] !== '' ? $formData['notes'] : null;
        $availability->status = $formData['status'];

        try {
            if (!$availability->save()) {
                throw new \RuntimeException('The availability slot could not be created.');
            }

            return [
                'success' => true,
                'message' => 'Availability slot created successfully.',
                'availabilityId' => $availability->id,
            ];
        } catch (\Throwable $exception) {
            error_log('Doctor availability creation failed: ' . $exception->getMessage());

            return [
                'success' => false,
                'errors' => ['Availability scheduling is temporarily unavailable. Please try again later.'],
                'fieldErrors' => [],
                'formData' => $formData,
            ];
        }
    }

    public static function updateAvailability(int $doctorId, int $availabilityId, array $input): array
    {
        $existingAvailability = self::getAvailabilityDetail($doctorId, $availabilityId);

        if ($existingAvailability === null) {
            return [
                'success' => false,
                'errors' => ['The requested availability slot could not be found.'],
                'fieldErrors' => [],
                'formData' => self::normalizeFormData($input),
            ];
        }

        $formData = self::normalizeFormData($input);
        [$errors, $fieldErrors] = self::validateAvailabilityForm($doctorId, $formData, $availabilityId);

        if ($errors !== []) {
            return [
                'success' => false,
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
                'formData' => $formData,
            ];
        }

        $availability = DoctorAvailability::fromArray($existingAvailability);
        $availability->consultation_date = $formData['consultation_date'];
        $availability->start_time = $formData['start_time'];
        $availability->end_time = $formData['end_time'];
        $availability->notes = $formData['notes'] !== '' ? $formData['notes'] : null;
        $availability->status = $formData['status'];

        try {
            if (!$availability->save()) {
                throw new \RuntimeException('The availability slot could not be updated.');
            }

            return [
                'success' => true,
                'message' => 'Availability slot updated successfully.',
                'availabilityId' => $availabilityId,
            ];
        } catch (\Throwable $exception) {
            error_log('Doctor availability update failed: ' . $exception->getMessage());

            return [
                'success' => false,
                'errors' => ['Availability updates are temporarily unavailable. Please try again later.'],
                'fieldErrors' => [],
                'formData' => $formData,
            ];
        }
    }

    public static function deleteAvailability(int $doctorId, int $availabilityId, string $csrfToken): array
    {
        if (!Csrf::verify($csrfToken)) {
            return [
                'success' => false,
                'message' => 'Unable to verify the request. Please refresh the page and try again.',
                'type' => 'danger',
            ];
        }

        $availability = self::getAvailabilityDetail($doctorId, $availabilityId);

        if ($availability === null) {
            return [
                'success' => false,
                'message' => 'The requested availability slot could not be found.',
                'type' => 'warning',
            ];
        }

        try {
            if (!DoctorAvailability::deleteForDoctor($availabilityId, $doctorId)) {
                throw new \RuntimeException('The availability slot could not be deleted.');
            }

            return [
                'success' => true,
                'message' => 'Availability slot deleted successfully.',
                'type' => 'success',
            ];
        } catch (\Throwable $exception) {
            error_log('Doctor availability deletion failed: ' . $exception->getMessage());

            return [
                'success' => false,
                'message' => 'Availability slot deletion is temporarily unavailable. Please try again later.',
                'type' => 'danger',
            ];
        }
    }

    public static function getStatusOptions(): array
    {
        return ['Available', 'Booked'];
    }

    private static function normalizeFilters(array $query): array
    {
        $search = trim((string) ($query['search'] ?? ''));
        $filterDate = trim((string) ($query['filter_date'] ?? ''));
        $status = trim((string) ($query['status'] ?? ''));

        if (!in_array($status, self::getStatusOptions(), true)) {
            $status = '';
        }

        if ($filterDate !== '' && !self::isValidDate($filterDate)) {
            $filterDate = '';
        }

        return [
            'search' => $search,
            'filter_date' => $filterDate,
            'status' => $status,
        ];
    }

    private static function normalizeFormData(array $input): array
    {
        $status = trim((string) ($input['status'] ?? 'Available'));

        if (!in_array($status, self::getStatusOptions(), true)) {
            $status = 'Available';
        }

        return [
            '_token' => (string) ($input['_token'] ?? ''),
            'consultation_date' => trim((string) ($input['consultation_date'] ?? '')),
            'start_time' => self::normalizeTimeForStorage((string) ($input['start_time'] ?? '')),
            'end_time' => self::normalizeTimeForStorage((string) ($input['end_time'] ?? '')),
            'notes' => trim((string) ($input['notes'] ?? '')),
            'status' => $status,
        ];
    }

    private static function validateAvailabilityForm(int $doctorId, array $formData, ?int $excludeId = null): array
    {
        $errors = [];
        $fieldErrors = [];

        if (!Csrf::verify((string) ($formData['_token'] ?? ''))) {
            $fieldErrors['_token'] = 'Unable to verify the request. Please refresh the page and try again.';
        }

        if ($formData['consultation_date'] === '') {
            $fieldErrors['consultation_date'] = 'Consultation date is required.';
        } elseif (!self::isValidDate($formData['consultation_date'])) {
            $fieldErrors['consultation_date'] = 'Please provide a valid consultation date.';
        } elseif ($formData['consultation_date'] < date('Y-m-d')) {
            $fieldErrors['consultation_date'] = 'Past dates are not allowed for availability slots.';
        }

        if ($formData['start_time'] === '') {
            $fieldErrors['start_time'] = 'Start time is required.';
        }

        if ($formData['end_time'] === '') {
            $fieldErrors['end_time'] = 'End time is required.';
        }

        if ($formData['start_time'] !== '' && $formData['end_time'] !== '' && $formData['end_time'] <= $formData['start_time']) {
            $fieldErrors['end_time'] = 'End time must be greater than start time.';
        }

        if (mb_strlen($formData['notes']) > 1000) {
            $fieldErrors['notes'] = 'Optional notes must be 1000 characters or fewer.';
        }

        if (
            !isset($fieldErrors['consultation_date'], $fieldErrors['start_time'], $fieldErrors['end_time'])
            && $formData['consultation_date'] !== ''
            && $formData['start_time'] !== ''
            && $formData['end_time'] !== ''
        ) {
            if (
                DoctorAvailability::hasDuplicateSlot(
                    $doctorId,
                    $formData['consultation_date'],
                    $formData['start_time'],
                    $formData['end_time'],
                    $excludeId
                )
            ) {
                $fieldErrors['start_time'] = 'An identical availability slot already exists for this date and time.';
                $fieldErrors['end_time'] = 'An identical availability slot already exists for this date and time.';
            } elseif (
                DoctorAvailability::hasOverlappingSlot(
                    $doctorId,
                    $formData['consultation_date'],
                    $formData['start_time'],
                    $formData['end_time'],
                    $excludeId
                )
            ) {
                $fieldErrors['start_time'] = 'This availability slot overlaps with an existing slot.';
                $fieldErrors['end_time'] = 'This availability slot overlaps with an existing slot.';
            }
        }

        if ($fieldErrors !== []) {
            if (isset($fieldErrors['_token'])) {
                $errors[] = $fieldErrors['_token'];
            }

            $errors[] = 'Please correct the highlighted availability fields.';
        }

        return [$errors, $fieldErrors];
    }

    private static function isValidDate(string $date): bool
    {
        $parsed = \DateTime::createFromFormat('Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private static function normalizeTimeForStorage(string $time): string
    {
        $time = trim($time);

        if ($time === '') {
            return '';
        }

        $parsed = \DateTime::createFromFormat('H:i', $time);

        if ($parsed === false) {
            $parsed = \DateTime::createFromFormat('H:i:s', $time);
        }

        return $parsed !== false ? $parsed->format('H:i:s') : '';
    }

    private static function normalizeTimeForForm(string $time): string
    {
        $time = trim($time);

        if ($time === '') {
            return '';
        }

        $parsed = \DateTime::createFromFormat('H:i:s', $time);

        if ($parsed === false) {
            $parsed = \DateTime::createFromFormat('H:i', $time);
        }

        return $parsed !== false ? $parsed->format('H:i') : $time;
    }
}
