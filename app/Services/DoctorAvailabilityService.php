<?php

namespace App\Services;

use App\Core\Csrf;
use App\Core\Database;
use App\Helpers\Helper;
use App\Helpers\ListFilter;
use App\Helpers\Status;
use App\Models\DoctorAvailability;
use DateTimeImmutable;
use DateTimeZone;

class DoctorAvailabilityService
{
    public const PER_PAGE = 10;
    public const SLOT_MINUTES = 30;
    public const GRID_START = '08:00';
    public const GRID_END = '16:00';
    public const MAX_APPLY_WEEKS = 8;

    public static function getAvailabilityPageData(int $doctorId, array $query): array
    {
        $filters = self::normalizeFilters($query);
        $perPage = (int) ($filters['per_page'] ?? self::PER_PAGE);
        $totalItems = DoctorAvailability::countForDoctor($doctorId, $filters);
        $pagination = ListFilter::paginate(max(1, (int) ($query['page'] ?? 1)), $totalItems, $perPage);
        $offset = ($pagination['current_page'] - 1) * $perPage;

        return [
            'filters' => $filters,
            'availability' => DoctorAvailability::findForDoctor($doctorId, $filters, $perPage, $offset),
            'summary' => DoctorAvailability::getSummaryForDoctor($doctorId),
            'filterActive' => ListFilter::isActive($filters, ['sort' => 'earliest', 'per_page' => self::PER_PAGE]),
            'pagination' => $pagination,
            'statusOptions' => self::getStatusOptions(),
            'dateOptions' => self::getDateOptions(),
            'sortOptions' => self::getSortOptions(),
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
        $formData['status'] = 'Available';
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
        $availability->status = 'Available';

        try {
            if (!$availability->save()) {
                throw new \RuntimeException('The availability slot could not be created.');
            }

            AuditLogService::record(
                'availability_created',
                'Doctor created an availability slot.',
                AuditLogService::ENTITY_AVAILABILITY,
                (int) ($availability->id ?? 0)
            );

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
        $formData['status'] = (string) ($existingAvailability['status'] ?? 'Available');

        if ($formData['status'] !== 'Available') {
            return [
                'success' => false,
                'errors' => ['Only unbooked availability slots can be edited.'],
                'fieldErrors' => [],
                'formData' => self::getAvailabilityFormData($existingAvailability),
            ];
        }
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
        $availability->status = (string) ($existingAvailability['status'] ?? 'Available');

        try {
            if (!$availability->save()) {
                throw new \RuntimeException('The availability slot could not be updated.');
            }

            AuditLogService::record(
                'availability_updated',
                'Doctor updated an availability slot.',
                AuditLogService::ENTITY_AVAILABILITY,
                $availabilityId
            );

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

        if (($availability['status'] ?? 'Available') !== 'Available') {
            return [
                'success' => false,
                'message' => 'Only unbooked availability slots can be deleted.',
                'type' => 'warning',
            ];
        }

        try {
            if (!DoctorAvailability::deleteForDoctor($availabilityId, $doctorId)) {
                throw new \RuntimeException('The availability slot could not be deleted.');
            }

            AuditLogService::record(
                'availability_deleted',
                'Doctor deleted an availability slot.',
                AuditLogService::ENTITY_AVAILABILITY,
                $availabilityId
            );

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
        return Status::slotKeys();
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    public static function getDateOptions(): array
    {
        return [
            ['value' => 'today', 'label' => 'Today'],
            ['value' => 'this_week', 'label' => 'This week'],
            ['value' => 'future', 'label' => 'Future'],
            ['value' => 'past', 'label' => 'Past'],
            ['value' => 'custom', 'label' => 'Custom range'],
        ];
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    public static function getSortOptions(): array
    {
        return [
            ['value' => 'earliest', 'label' => 'Earliest first'],
            ['value' => 'latest', 'label' => 'Latest first'],
        ];
    }

    private static function normalizeFilters(array $query): array
    {
        $search = ListFilter::normalizeSearch((string) ($query['search'] ?? ''));
        $status = trim((string) ($query['status'] ?? ''));
        $sort = ListFilter::allowedValue(
            trim((string) ($query['sort'] ?? 'earliest')),
            ['earliest', 'latest'],
            'earliest'
        );
        $range = ListFilter::resolveDateRange(
            (string) ($query['date'] ?? ''),
            (string) ($query['date_from'] ?? ''),
            (string) ($query['date_to'] ?? ''),
            (string) ($query['filter_date'] ?? '')
        );
        $perPage = ListFilter::allowedPerPage($query['per_page'] ?? self::PER_PAGE, self::PER_PAGE);

        if (!in_array($status, self::getStatusOptions(), true)) {
            $status = '';
        }

        return [
            'search' => $search,
            'status' => $status,
            'date' => $range['preset'],
            'date_from' => $range['preset'] === 'custom' ? $range['from'] : '',
            'date_to' => $range['preset'] === 'custom' ? $range['to'] : '',
            'date_range' => ['from' => $range['from'], 'to' => $range['to']],
            'sort' => $sort,
            'per_page' => $perPage,
        ];
    }

    private static function normalizeFormData(array $input): array
    {
        return [
            '_token' => (string) ($input['_token'] ?? ''),
            'consultation_date' => trim((string) ($input['consultation_date'] ?? '')),
            'start_time' => self::normalizeTimeForStorage((string) ($input['start_time'] ?? '')),
            'end_time' => self::normalizeTimeForStorage((string) ($input['end_time'] ?? '')),
            'notes' => trim((string) ($input['notes'] ?? '')),
            'status' => 'Available',
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

    /**
     * Shared Monday–Sunday grid used by doctor scheduling and patient booking.
     *
     * @param list<array<string, mixed>> $slots
     * @return array<string, mixed>
     */
    public static function getWeekGridScaffold(string $week = '', array $slots = []): array
    {
        $timezone = self::appTimezone();
        $today = new DateTimeImmutable('now', $timezone);
        $weekStart = self::resolveWeekMonday($week, $timezone);
        $weekEnd = $weekStart->modify('+6 days');
        [$gridStart, $gridEnd] = self::resolveGridBounds($slots);
        $thisWeek = self::resolveWeekMonday('', $timezone)->format('Y-m-d');

        return [
            'weekStart' => $weekStart->format('Y-m-d'),
            'weekEnd' => $weekEnd->format('Y-m-d'),
            'weekLabel' => $weekStart->format('j M') . ' – ' . $weekEnd->format('j M Y'),
            'isCurrentWeek' => $weekStart->format('Y-m-d') === $thisWeek,
            'prevWeek' => $weekStart->modify('-7 days')->format('Y-m-d'),
            'nextWeek' => $weekStart->modify('+7 days')->format('Y-m-d'),
            'thisWeek' => $thisWeek,
            'days' => self::buildWeekDays($weekStart, $today),
            'intervals' => self::buildTimeIntervals($gridStart, $gridEnd, $timezone),
            'gridStart' => $gridStart,
            'gridEnd' => $gridEnd,
            'todayDate' => $today->format('Y-m-d'),
            'nowHm' => $today->format('H:i'),
            'timezoneLabel' => Helper::appTimezoneLabel(),
        ];
    }

    /**
     * Build the weekly timetable payload for the doctor availability page.
     *
     * @return array<string, mixed>
     */
    public static function getWeeklySchedulePageData(int $doctorId, array $query): array
    {
        $timezone = self::appTimezone();
        $today = new DateTimeImmutable('now', $timezone);
        $weekHint = (string) ($query['week'] ?? '');
        $anchor = self::getWeekGridScaffold($weekHint);
        $slots = DoctorAvailability::findInDateRangeForDoctor(
            $doctorId,
            (string) $anchor['weekStart'],
            (string) $anchor['weekEnd']
        );
        $grid = self::getWeekGridScaffold($weekHint, $slots);
        $cells = self::buildWeekCells($grid['days'], $grid['intervals'], $slots, $today);

        $availableCount = 0;
        $bookedCount = 0;
        $customCount = 0;
        foreach ($cells as $cell) {
            if (($cell['state'] ?? '') === 'available') {
                $availableCount++;
            } elseif (($cell['state'] ?? '') === 'booked') {
                $bookedCount++;
            } elseif (($cell['state'] ?? '') === 'custom') {
                $customCount++;
            }
        }

        return [
            'weekStart' => $grid['weekStart'],
            'weekEnd' => $grid['weekEnd'],
            'weekLabel' => $grid['weekLabel'],
            'isCurrentWeek' => $grid['isCurrentWeek'],
            'prevWeek' => $grid['prevWeek'],
            'nextWeek' => $grid['nextWeek'],
            'thisWeek' => $grid['thisWeek'],
            'days' => $grid['days'],
            'intervals' => $grid['intervals'],
            'cells' => $cells,
            'slots' => $slots,
            'gridStart' => $grid['gridStart'],
            'gridEnd' => $grid['gridEnd'],
            'timeOptions' => self::buildTimeSelectOptions($grid['intervals']),
            'applyWeekOptions' => self::getApplyWeekOptions(),
            'counts' => [
                'available' => $availableCount,
                'booked' => $bookedCount,
                'custom' => $customCount,
            ],
            'summary' => DoctorAvailability::getSummaryForDoctor($doctorId),
            'timezoneLabel' => $grid['timezoneLabel'],
        ];
    }

    /**
     * Persist one week's 30-minute selections onto existing date-specific rows.
     *
     * Booked and non-30-minute slots are never deleted. Past dates are left untouched.
     * Optional apply-forward only creates matching future-week cells.
     *
     * @param array<string, mixed> $input
     * @return array{success:bool,message:string,type:string,weekStart:string}
     */
    public static function saveWeeklySchedule(int $doctorId, array $input): array
    {
        $timezone = self::appTimezone();
        $weekStart = self::resolveWeekMonday((string) ($input['week_start'] ?? ''), $timezone);
        $weekKey = $weekStart->format('Y-m-d');

        if (!Csrf::verify((string) ($input['_token'] ?? ''))) {
            return [
                'success' => false,
                'message' => 'Unable to verify the request. Please refresh the page and try again.',
                'type' => 'danger',
                'weekStart' => $weekKey,
            ];
        }

        $applyWeeks = (int) ($input['apply_weeks'] ?? 1);
        if ($applyWeeks < 1) {
            $applyWeeks = 1;
        }
        if ($applyWeeks > self::MAX_APPLY_WEEKS) {
            $applyWeeks = self::MAX_APPLY_WEEKS;
        }

        $desired = self::parseDesiredSlots($input['slots'] ?? [], $weekStart, $timezone);
        if (($desired['error'] ?? '') !== '') {
            return [
                'success' => false,
                'message' => $desired['error'],
                'type' => 'warning',
                'weekStart' => $weekKey,
            ];
        }

        $today = new DateTimeImmutable('now', $timezone);
        $todayDate = $today->format('Y-m-d');
        $nowHm = $today->format('H:i');
        $weekEnd = $weekStart->modify('+6 days');
        $existing = DoctorAvailability::findInDateRangeForDoctor(
            $doctorId,
            $weekStart->format('Y-m-d'),
            $weekEnd->format('Y-m-d')
        );

        $desiredKeys = $desired['keys'];
        $created = 0;
        $removed = 0;
        $skippedBooked = 0;
        $forwardCreated = 0;

        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            foreach ($existing as $slot) {
                $slotDate = (string) ($slot['consultation_date'] ?? '');
                $status = (string) ($slot['status'] ?? 'Available');
                $slotId = (int) ($slot['id'] ?? 0);

                if ($slotId <= 0 || $status !== 'Available') {
                    continue;
                }

                if ($slotDate < $todayDate) {
                    continue;
                }

                if (!self::isExactThirtyMinuteSlot($slot)) {
                    continue;
                }

                $startHm = self::normalizeTimeForForm((string) ($slot['start_time'] ?? ''));
                $cellKey = $slotDate . '|' . $startHm;

                if ($slotDate === $todayDate && $startHm < $nowHm) {
                    continue;
                }

                if (!isset($desiredKeys[$cellKey])) {
                    if (!DoctorAvailability::deleteForDoctor($slotId, $doctorId)) {
                        throw new \RuntimeException('Unable to remove an unselected availability slot.');
                    }
                    $removed++;
                }
            }

            $existingAfterDelete = DoctorAvailability::findInDateRangeForDoctor(
                $doctorId,
                $weekStart->format('Y-m-d'),
                $weekEnd->format('Y-m-d')
            );

            foreach ($desired['slots'] as $cell) {
                $date = $cell['date'];
                $start = $cell['start'];
                $end = $cell['end'];

                if ($date < $todayDate || ($date === $todayDate && $start < $nowHm)) {
                    continue;
                }

                $cover = self::coveringSlots($existingAfterDelete, $date, $start, $end);
                if (self::hasBookedCover($cover)) {
                    $skippedBooked++;
                    continue;
                }
                if (self::hasNonThirtyCover($cover)) {
                    continue;
                }
                if (self::hasExactAvailableCover($cover, $start, $end)) {
                    continue;
                }

                if (!self::insertThirtyMinuteSlot($doctorId, $date, $start, $end)) {
                    throw new \RuntimeException('Unable to create an availability slot.');
                }
                $created++;
                $existingAfterDelete[] = [
                    'consultation_date' => $date,
                    'start_time' => $start . ':00',
                    'end_time' => $end . ':00',
                    'status' => 'Available',
                ];
            }

            if ($applyWeeks > 1) {
                $forwardCreated = self::applyPatternForward(
                    $doctorId,
                    $desired['slots'],
                    $weekStart,
                    $applyWeeks,
                    $todayDate,
                    $timezone
                );
            }

            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Doctor weekly availability save failed: ' . $exception->getMessage());

            return [
                'success' => false,
                'message' => 'Availability could not be saved. Please try again later.',
                'type' => 'danger',
                'weekStart' => $weekKey,
            ];
        }

        $parts = [];
        if ($created > 0) {
            $parts[] = $created . ' slot' . ($created === 1 ? '' : 's') . ' added';
        }
        if ($removed > 0) {
            $parts[] = $removed . ' slot' . ($removed === 1 ? '' : 's') . ' removed';
        }
        if ($forwardCreated > 0) {
            $parts[] = $forwardCreated . ' copied to later weeks';
        }
        if ($skippedBooked > 0) {
            $parts[] = $skippedBooked . ' booked time' . ($skippedBooked === 1 ? '' : 's') . ' left unchanged';
        }

        $message = $parts === []
            ? 'Weekly availability is already up to date.'
            : 'Weekly availability saved: ' . implode(', ', $parts) . '.';

        AuditLogService::record(
            'availability_week_saved',
            $message,
            AuditLogService::ENTITY_AVAILABILITY,
            $doctorId
        );

        return [
            'success' => true,
            'message' => $message,
            'type' => 'success',
            'weekStart' => $weekKey,
        ];
    }

    /**
     * @return list<array{value:int,label:string}>
     */
    public static function getApplyWeekOptions(): array
    {
        $options = [
            ['value' => 1, 'label' => 'This week only'],
        ];

        for ($weeks = 2; $weeks <= self::MAX_APPLY_WEEKS; $weeks++) {
            $ahead = $weeks - 1;
            $options[] = [
                'value' => $weeks,
                'label' => 'This week + next ' . $ahead . ' week' . ($ahead === 1 ? '' : 's'),
            ];
        }

        return $options;
    }

    private static function appTimezone(): DateTimeZone
    {
        try {
            return new DateTimeZone(Helper::appTimezone());
        } catch (\Throwable) {
            return new DateTimeZone('Pacific/Port_Moresby');
        }
    }

    private static function resolveWeekMonday(string $week, DateTimeZone $timezone): DateTimeImmutable
    {
        $anchor = new DateTimeImmutable('today', $timezone);
        $week = trim($week);

        if (self::isValidDate($week)) {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $week, $timezone);
            if ($parsed instanceof DateTimeImmutable) {
                $anchor = $parsed->setTime(0, 0);
            }
        }

        $weekday = (int) $anchor->format('N');

        return $anchor->modify('-' . ($weekday - 1) . ' days')->setTime(0, 0);
    }

    /**
     * @param list<array<string, mixed>> $slots
     * @return array{0:string,1:string}
     */
    private static function resolveGridBounds(array $slots): array
    {
        $start = self::GRID_START;
        $end = self::GRID_END;

        foreach ($slots as $slot) {
            $slotStart = self::normalizeTimeForForm((string) ($slot['start_time'] ?? ''));
            $slotEnd = self::normalizeTimeForForm((string) ($slot['end_time'] ?? ''));
            if ($slotStart !== '' && $slotStart < $start) {
                $start = $slotStart;
            }
            if ($slotEnd !== '' && $slotEnd > $end) {
                $end = $slotEnd;
            }
        }

        return [self::snapTimeDown($start), self::snapTimeUp($end)];
    }

    /**
     * @return list<array{start:string,end:string,label:string,end_label:string}>
     */
    private static function buildTimeIntervals(string $startHm, string $endHm, DateTimeZone $timezone): array
    {
        $cursor = DateTimeImmutable::createFromFormat('H:i', $startHm, $timezone);
        $end = DateTimeImmutable::createFromFormat('H:i', $endHm, $timezone);
        if (!$cursor instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable || $cursor >= $end) {
            $cursor = DateTimeImmutable::createFromFormat('H:i', self::GRID_START, $timezone);
            $end = DateTimeImmutable::createFromFormat('H:i', self::GRID_END, $timezone);
        }

        $intervals = [];
        while ($cursor < $end) {
            $next = $cursor->modify('+' . self::SLOT_MINUTES . ' minutes');
            if ($next > $end) {
                break;
            }
            $intervals[] = [
                'start' => $cursor->format('H:i'),
                'end' => $next->format('H:i'),
                'label' => $cursor->format('g:i A'),
                'end_label' => $next->format('g:i A'),
            ];
            $cursor = $next;
        }

        return $intervals;
    }

    /**
     * @return list<array{date:string,name:string,short:string,day_num:string,is_today:bool,is_past:bool}>
     */
    private static function buildWeekDays(DateTimeImmutable $weekStart, DateTimeImmutable $today): array
    {
        $todayDate = $today->format('Y-m-d');
        $days = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $day = $weekStart->modify('+' . $offset . ' days');
            $date = $day->format('Y-m-d');
            $days[] = [
                'date' => $date,
                'name' => $day->format('l'),
                'short' => $day->format('D'),
                'day_num' => $day->format('j'),
                'month_short' => $day->format('M'),
                'is_today' => $date === $todayDate,
                'is_past' => $date < $todayDate,
            ];
        }

        return $days;
    }

    /**
     * @param list<array<string, mixed>> $days
     * @param list<array<string, mixed>> $intervals
     * @param list<array<string, mixed>> $slots
     * @return array<string, array<string, mixed>>
     */
    private static function buildWeekCells(array $days, array $intervals, array $slots, DateTimeImmutable $today): array
    {
        $todayDate = $today->format('Y-m-d');
        $nowHm = $today->format('H:i');
        $cells = [];

        foreach ($days as $day) {
            $date = (string) $day['date'];
            foreach ($intervals as $interval) {
                $start = (string) $interval['start'];
                $end = (string) $interval['end'];
                $key = $date . '|' . $start;
                $cover = self::coveringSlots($slots, $date, $start, $end);
                $isPast = $date < $todayDate || ($date === $todayDate && $start < $nowHm);
                $state = 'empty';
                $locked = $isPast;
                $slotId = null;
                $label = 'unavailable';

                if (self::hasBookedCover($cover)) {
                    $state = 'booked';
                    $locked = true;
                    $label = 'booked';
                    $slotId = (int) ($cover[0]['id'] ?? 0) ?: null;
                } elseif (self::hasNonThirtyCover($cover)) {
                    $state = 'custom';
                    $locked = true;
                    $label = 'existing custom slot';
                    foreach ($cover as $slot) {
                        if ((string) ($slot['status'] ?? '') === 'Available' && !self::isExactThirtyMinuteSlot($slot)) {
                            $slotId = isset($slot['id']) ? (int) $slot['id'] : null;
                            break;
                        }
                    }
                } elseif (self::hasExactAvailableCover($cover, $start, $end) || $cover !== []) {
                    $state = 'available';
                    $label = 'available';
                    foreach ($cover as $slot) {
                        if ((string) ($slot['status'] ?? '') === 'Available') {
                            $slotId = isset($slot['id']) ? (int) $slot['id'] : null;
                            break;
                        }
                    }
                }

                $cells[$key] = [
                    'key' => $key,
                    'date' => $date,
                    'start' => $start,
                    'end' => $end,
                    'state' => $state,
                    'locked' => $locked,
                    'past' => $isPast,
                    'slot_id' => $slotId,
                    'label' => $label,
                ];
            }
        }

        return $cells;
    }

    /**
     * @param list<array<string, mixed>> $intervals
     * @return list<array{value:string,label:string}>
     */
    private static function buildTimeSelectOptions(array $intervals): array
    {
        $options = [];
        $seen = [];

        foreach ($intervals as $interval) {
            $start = (string) ($interval['start'] ?? '');
            $end = (string) ($interval['end'] ?? '');
            if ($start !== '' && !isset($seen[$start])) {
                $options[] = [
                    'value' => $start,
                    'label' => (string) ($interval['label'] ?? $start),
                ];
                $seen[$start] = true;
            }
            if ($end !== '' && !isset($seen[$end])) {
                $options[] = [
                    'value' => $end,
                    'label' => (string) ($interval['end_label'] ?? $end),
                ];
                $seen[$end] = true;
            }
        }

        return $options;
    }

    /**
     * @param mixed $rawSlots
     * @return array{keys:array<string,true>,slots:list<array{date:string,start:string,end:string}>,error:string}
     */
    private static function parseDesiredSlots(mixed $rawSlots, DateTimeImmutable $weekStart, DateTimeZone $timezone): array
    {
        $items = is_array($rawSlots) ? $rawSlots : [];
        $weekDates = [];
        for ($offset = 0; $offset < 7; $offset++) {
            $weekDates[$weekStart->modify('+' . $offset . ' days')->format('Y-m-d')] = true;
        }

        $keys = [];
        $slots = [];

        foreach ($items as $item) {
            $token = trim((string) $item);
            if ($token === '' || !str_contains($token, '|')) {
                continue;
            }

            [$date, $start] = array_pad(explode('|', $token, 2), 2, '');
            $date = trim($date);
            $start = self::normalizeTimeForForm(trim($start));

            if (!isset($weekDates[$date]) || $start === '') {
                return [
                    'keys' => [],
                    'slots' => [],
                    'error' => 'One or more selected times are invalid for this week.',
                ];
            }

            $startAt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $date . ' ' . $start, $timezone);
            if (!$startAt instanceof DateTimeImmutable) {
                return [
                    'keys' => [],
                    'slots' => [],
                    'error' => 'One or more selected times are invalid.',
                ];
            }

            $end = $startAt->modify('+' . self::SLOT_MINUTES . ' minutes')->format('H:i');
            $key = $date . '|' . $start;
            if (isset($keys[$key])) {
                continue;
            }

            $keys[$key] = true;
            $slots[] = [
                'date' => $date,
                'start' => $start,
                'end' => $end,
            ];
        }

        return [
            'keys' => $keys,
            'slots' => $slots,
            'error' => '',
        ];
    }

    /**
     * @param list<array{date:string,start:string,end:string}> $desiredSlots
     */
    private static function applyPatternForward(
        int $doctorId,
        array $desiredSlots,
        DateTimeImmutable $weekStart,
        int $applyWeeks,
        string $todayDate,
        DateTimeZone $timezone
    ): int {
        $created = 0;
        $lastWeekStart = $weekStart->modify('+' . (($applyWeeks - 1) * 7) . ' days');
        $rangeEnd = $lastWeekStart->modify('+6 days')->format('Y-m-d');
        $existing = DoctorAvailability::findInDateRangeForDoctor(
            $doctorId,
            $weekStart->modify('+7 days')->format('Y-m-d'),
            $rangeEnd
        );

        for ($week = 1; $week < $applyWeeks; $week++) {
            $shiftDays = $week * 7;
            foreach ($desiredSlots as $cell) {
                $sourceDate = DateTimeImmutable::createFromFormat('Y-m-d', $cell['date'], $timezone);
                if (!$sourceDate instanceof DateTimeImmutable) {
                    continue;
                }

                $date = $sourceDate->modify('+' . $shiftDays . ' days')->format('Y-m-d');
                if ($date < $todayDate) {
                    continue;
                }

                $start = $cell['start'];
                $end = $cell['end'];
                $cover = self::coveringSlots($existing, $date, $start, $end);
                if ($cover !== []) {
                    continue;
                }

                if (!self::insertThirtyMinuteSlot($doctorId, $date, $start, $end)) {
                    throw new \RuntimeException('Unable to copy availability to a later week.');
                }

                $created++;
                $existing[] = [
                    'consultation_date' => $date,
                    'start_time' => $start . ':00',
                    'end_time' => $end . ':00',
                    'status' => 'Available',
                ];
            }
        }

        return $created;
    }

    /**
     * @param list<array<string, mixed>> $slots
     * @return list<array<string, mixed>>
     */
    private static function coveringSlots(array $slots, string $date, string $startHm, string $endHm): array
    {
        $start = self::normalizeTimeForStorage($startHm);
        $end = self::normalizeTimeForStorage($endHm);
        $matches = [];

        foreach ($slots as $slot) {
            if ((string) ($slot['consultation_date'] ?? '') !== $date) {
                continue;
            }

            $slotStart = self::normalizeTimeForStorage((string) ($slot['start_time'] ?? ''));
            $slotEnd = self::normalizeTimeForStorage((string) ($slot['end_time'] ?? ''));
            if ($slotStart === '' || $slotEnd === '') {
                continue;
            }

            if ($slotStart < $end && $slotEnd > $start) {
                $matches[] = $slot;
            }
        }

        return $matches;
    }

    /**
     * @param list<array<string, mixed>> $slots
     */
    private static function hasBookedCover(array $slots): bool
    {
        foreach ($slots as $slot) {
            if ((string) ($slot['status'] ?? '') === 'Booked') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $slots
     */
    private static function hasNonThirtyCover(array $slots): bool
    {
        foreach ($slots as $slot) {
            if ((string) ($slot['status'] ?? '') === 'Available' && !self::isExactThirtyMinuteSlot($slot)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $slots
     */
    private static function hasExactAvailableCover(array $slots, string $startHm, string $endHm): bool
    {
        $start = self::normalizeTimeForStorage($startHm);
        $end = self::normalizeTimeForStorage($endHm);

        foreach ($slots as $slot) {
            if ((string) ($slot['status'] ?? '') !== 'Available') {
                continue;
            }
            if (
                self::normalizeTimeForStorage((string) ($slot['start_time'] ?? '')) === $start
                && self::normalizeTimeForStorage((string) ($slot['end_time'] ?? '')) === $end
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $slot
     */
    private static function isExactThirtyMinuteSlot(array $slot): bool
    {
        $start = self::normalizeTimeForForm((string) ($slot['start_time'] ?? ''));
        $end = self::normalizeTimeForForm((string) ($slot['end_time'] ?? ''));
        if ($start === '' || $end === '') {
            return false;
        }

        $timezone = self::appTimezone();
        $startAt = DateTimeImmutable::createFromFormat('H:i', $start, $timezone);
        $endAt = DateTimeImmutable::createFromFormat('H:i', $end, $timezone);
        if (!$startAt instanceof DateTimeImmutable || !$endAt instanceof DateTimeImmutable) {
            return false;
        }

        return $startAt->modify('+' . self::SLOT_MINUTES . ' minutes')->format('H:i') === $endAt->format('H:i');
    }

    private static function insertThirtyMinuteSlot(int $doctorId, string $date, string $startHm, string $endHm): bool
    {
        $availability = new DoctorAvailability();
        $availability->doctor_id = $doctorId;
        $availability->consultation_date = $date;
        $availability->start_time = self::normalizeTimeForStorage($startHm);
        $availability->end_time = self::normalizeTimeForStorage($endHm);
        $availability->notes = null;
        $availability->status = 'Available';

        return $availability->save();
    }

    private static function snapTimeDown(string $timeHm): string
    {
        $parsed = DateTimeImmutable::createFromFormat('H:i', $timeHm, self::appTimezone());
        if (!$parsed instanceof DateTimeImmutable) {
            return self::GRID_START;
        }

        $minutes = (int) $parsed->format('i');
        $snapped = $minutes >= 30 ? 30 : 0;

        return $parsed->setTime((int) $parsed->format('H'), $snapped)->format('H:i');
    }

    private static function snapTimeUp(string $timeHm): string
    {
        $parsed = DateTimeImmutable::createFromFormat('H:i', $timeHm, self::appTimezone());
        if (!$parsed instanceof DateTimeImmutable) {
            return self::GRID_END;
        }

        $minutes = (int) $parsed->format('i');
        if ($minutes === 0) {
            return $parsed->format('H:i');
        }
        if ($minutes <= 30) {
            return $parsed->setTime((int) $parsed->format('H'), 30)->format('H:i');
        }

        $nextHour = $parsed->modify('+1 hour');

        return $nextHour->setTime((int) $nextHour->format('H'), 0)->format('H:i');
    }
}
