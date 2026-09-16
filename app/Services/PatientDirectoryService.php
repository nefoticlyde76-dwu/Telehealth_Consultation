<?php

namespace App\Services;

use App\Helpers\Helper;
use App\Models\Doctor;
use App\Models\DoctorAvailability;

class PatientDirectoryService
{
    public const DIRECTORY_PER_PAGE = 6;
    public const SLOT_PER_PAGE = 10;

    public static function getBrowseSummary(): array
    {
        SlotExpirationService::sweep();
        $doctorOptions = DoctorAvailability::getAvailableDoctorOptionsForPatients();
        $specializationOptions = Doctor::getSpecializationOptionsForPatients();

        return [
            'available_doctors' => count($doctorOptions),
            'available_slots' => DoctorAvailability::countAvailableForPatients(),
            'specializations' => count($specializationOptions),
        ];
    }

    public static function getDoctorDirectoryPageData(array $query): array
    {
        SlotExpirationService::sweep();
        $page = max(1, (int) ($query['page'] ?? 1));
        $totalItems = Doctor::countForPatientDirectory();
        $totalPages = max(1, (int) ceil($totalItems / self::DIRECTORY_PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * self::DIRECTORY_PER_PAGE;
        $doctors = Doctor::findForPatientDirectory(self::DIRECTORY_PER_PAGE, $offset);

        foreach ($doctors as &$doctor) {
            $previewSlots = DoctorAvailability::getAvailableDaysAndTimesForDoctor((int) ($doctor['id'] ?? 0));
            $doctor['available_days'] = [];
            $doctor['available_times'] = [];
            $doctor['booking_slots'] = $previewSlots;

            foreach ($previewSlots as $slot) {
                $dayLabel = Helper::formatDate((string) ($slot['consultation_date'] ?? ''), 'D, d M Y', '');
                $timeLabel = substr((string) ($slot['start_time'] ?? ''), 0, 5) . ' - ' . substr((string) ($slot['end_time'] ?? ''), 0, 5);

                if ($dayLabel !== '' && !in_array($dayLabel, $doctor['available_days'], true) && count($doctor['available_days']) < 3) {
                    $doctor['available_days'][] = $dayLabel;
                }

                if (!in_array($timeLabel, $doctor['available_times'], true) && count($doctor['available_times']) < 3) {
                    $doctor['available_times'][] = $timeLabel;
                }
            }
        }
        unset($doctor);

        return [
            'doctors' => $doctors,
            'summary' => self::getBrowseSummary(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => self::DIRECTORY_PER_PAGE,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
            ],
        ];
    }

    public static function getFeaturedDoctors(int $limit = 3): array
    {
        SlotExpirationService::sweep();
        return Doctor::findForPatientDirectory($limit, 0);
    }

    public static function getUpcomingSlotPreview(int $limit = 4): array
    {
        SlotExpirationService::sweep();
        return DoctorAvailability::findAvailableForPatients([], $limit, 0);
    }

    public static function getAvailableSlotsPageData(array $query): array
    {
        SlotExpirationService::sweep();
        $page = max(1, (int) ($query['page'] ?? 1));
        $filters = self::normalizeSlotFilters($query);
        $totalItems = DoctorAvailability::countAvailableForPatients($filters);
        $totalPages = max(1, (int) ceil($totalItems / self::SLOT_PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * self::SLOT_PER_PAGE;
        $colors = self::scheduleColors();
        $slots = self::decorateSlotsWithColors(
            DoctorAvailability::findAvailableForPatients($filters, self::SLOT_PER_PAGE, $offset),
            $colors
        );

        return [
            'filters' => $filters,
            'slots' => $slots,
            'summary' => self::getBrowseSummary(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => self::SLOT_PER_PAGE,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
            ],
            'doctorOptions' => DoctorAvailability::getAvailableDoctorOptionsForPatients(),
            'specializationOptions' => Doctor::getSpecializationOptionsForPatients(),
            'colors' => $colors,
        ];
    }

    private static function normalizeSlotFilters(array $query): array
    {
        $doctorId = max(0, (int) ($query['doctor_id'] ?? 0));
        $specialization = trim((string) ($query['specialization'] ?? ''));
        $consultationDate = trim((string) ($query['consultation_date'] ?? ''));

        $doctorIds = array_map(
            static fn (array $doctor): int => (int) ($doctor['doctor_id'] ?? 0),
            DoctorAvailability::getAvailableDoctorOptionsForPatients()
        );

        if ($doctorId > 0 && !in_array($doctorId, $doctorIds, true)) {
            $doctorId = 0;
        }

        $specializations = Doctor::getSpecializationOptionsForPatients();

        if ($specialization !== '' && !in_array($specialization, $specializations, true)) {
            $specialization = '';
        }

        if ($consultationDate !== '' && !self::isValidDate($consultationDate)) {
            $consultationDate = '';
        }

        return [
            'doctor_id' => $doctorId,
            'specialization' => $specialization,
            'consultation_date' => $consultationDate,
        ];
    }

    /**
     * Weekly timetable of bookable slots for the patient booking page.
     *
     * @return array<string, mixed>
     */
    public static function getWeeklyBookingPageData(array $query): array
    {
        SlotExpirationService::sweep();
        $filters = self::normalizeSlotFilters($query);
        $weekHint = trim((string) ($query['week'] ?? ''));
        if ($weekHint === '' && $filters['consultation_date'] !== '') {
            $weekHint = $filters['consultation_date'];
        }

        $anchor = DoctorAvailabilityService::getWeekGridScaffold($weekHint);
        $rangeFilters = $filters;
        unset($rangeFilters['consultation_date']);
        $slots = DoctorAvailability::findAvailableInDateRangeForPatients(
            $rangeFilters,
            (string) $anchor['weekStart'],
            (string) $anchor['weekEnd']
        );
        $colors = self::scheduleColors();
        $slots = self::decorateSlotsWithColors($slots, $colors);
        $grid = DoctorAvailabilityService::getWeekGridScaffold($weekHint, $slots);
        $blocks = DoctorAvailabilityService::buildDayBlocks(
            $grid['days'],
            $slots,
            (string) $grid['todayDate'],
            (string) $grid['nowHm'],
            ['colors' => $colors]
        );

        $openCells = 0;
        foreach ($blocks as $dayBlocks) {
            $openCells += count($dayBlocks);
        }

        return [
            'filters' => $filters,
            'weekStart' => $grid['weekStart'],
            'weekEnd' => $grid['weekEnd'],
            'weekLabel' => $grid['weekLabel'],
            'isCurrentWeek' => $grid['isCurrentWeek'],
            'prevWeek' => $grid['prevWeek'],
            'nextWeek' => $grid['nextWeek'],
            'thisWeek' => $grid['thisWeek'],
            'days' => $grid['days'],
            'intervals' => $grid['intervals'],
            'blocks' => $blocks,
            'gridStart' => $grid['gridStart'],
            'gridEnd' => $grid['gridEnd'],
            'openCells' => $openCells,
            'weekSlotCount' => count($slots),
            'summary' => self::getBrowseSummary(),
            'doctorOptions' => DoctorAvailability::getAvailableDoctorOptionsForPatients(),
            'specializationOptions' => Doctor::getSpecializationOptionsForPatients(),
            'colors' => $colors,
            'timezoneLabel' => $grid['timezoneLabel'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $days
     * @param list<array<string, mixed>> $intervals
     * @param list<array<string, mixed>> $slots
     * @return array<string, array<string, mixed>>
     */
    private static function buildBookingCells(
        array $days,
        array $intervals,
        array $slots,
        string $todayDate,
        string $nowHm
    ): array {
        $cells = [];

        foreach ($days as $day) {
            $date = (string) ($day['date'] ?? '');
            foreach ($intervals as $interval) {
                $start = (string) ($interval['start'] ?? '');
                $end = (string) ($interval['end'] ?? '');
                $key = $date . '|' . $start;
                $isPast = $date < $todayDate || ($date === $todayDate && $end !== '' && $end <= $nowHm);
                $options = [];

                if (!$isPast) {
                    foreach ($slots as $slot) {
                        if (!self::slotCoversInterval($slot, $date, $start, $end)) {
                            continue;
                        }

                        $slotId = (int) ($slot['id'] ?? 0);
                        if ($slotId <= 0) {
                            continue;
                        }

                        $options[] = [
                            'id' => $slotId,
                            'doctor_id' => (int) ($slot['doctor_id'] ?? 0),
                            'full_name' => (string) ($slot['full_name'] ?? 'Doctor'),
                            'specialization' => (string) ($slot['specialization'] ?? 'General Practice'),
                            'start_label' => self::formatClock((string) ($slot['start_time'] ?? $start)),
                            'end_label' => self::formatClock((string) ($slot['end_time'] ?? $end)),
                        ];
                    }
                }

                $cells[$key] = [
                    'date' => $date,
                    'start' => $start,
                    'end' => $end,
                    'past' => $isPast,
                    'options' => $options,
                ];
            }
        }

        return $cells;
    }

    /**
     * @param array<string, mixed> $slot
     */
    private static function slotCoversInterval(array $slot, string $date, string $startHm, string $endHm): bool
    {
        if ((string) ($slot['consultation_date'] ?? '') !== $date) {
            return false;
        }

        $slotStart = self::clockKey((string) ($slot['start_time'] ?? ''));
        $slotEnd = self::clockKey((string) ($slot['end_time'] ?? ''));

        return $slotStart !== '' && $slotEnd !== '' && $slotStart < $endHm && $slotEnd > $startHm;
    }

    private static function clockKey(string $time): string
    {
        $time = trim($time);
        if ($time === '') {
            return '';
        }

        return substr($time, 0, 5);
    }

    private static function formatClock(string $time): string
    {
        $time = trim($time);
        if ($time === '') {
            return '';
        }

        $parsed = \DateTimeImmutable::createFromFormat('H:i:s', $time);
        if ($parsed === false) {
            $parsed = \DateTimeImmutable::createFromFormat('H:i', substr($time, 0, 5));
        }

        return $parsed instanceof \DateTimeImmutable ? $parsed->format('g:i A') : $time;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function scheduleColors(): array
    {
        return DoctorAvailabilityService::scheduleColorsForDoctors(array_map(
            static fn (array $doctor): int => (int) ($doctor['doctor_id'] ?? 0),
            Doctor::getActiveDoctorsForSchedule()
        ));
    }

    /**
     * @param list<array<string, mixed>> $slots
     * @param array<int, array<string, mixed>> $colors
     * @return list<array<string, mixed>>
     */
    private static function decorateSlotsWithColors(array $slots, array $colors): array
    {
        foreach ($slots as &$slot) {
            $doctorId = (int) ($slot['doctor_id'] ?? 0);
            $slot['color'] = is_array($colors[$doctorId] ?? null)
                ? $colors[$doctorId]
                : \App\Helpers\DoctorScheduleColor::forDoctorId($doctorId);
        }
        unset($slot);

        return $slots;
    }

    private static function isValidDate(string $date): bool
    {
        $parsed = \DateTime::createFromFormat('Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
