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

    public static function getAvailableSlotsPageData(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $filters = self::normalizeSlotFilters($query);
        $totalItems = DoctorAvailability::countAvailableForPatients($filters);
        $totalPages = max(1, (int) ceil($totalItems / self::SLOT_PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * self::SLOT_PER_PAGE;

        return [
            'filters' => $filters,
            'slots' => DoctorAvailability::findAvailableForPatients($filters, self::SLOT_PER_PAGE, $offset),
            'summary' => self::getBrowseSummary(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => self::SLOT_PER_PAGE,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
            ],
            'doctorOptions' => DoctorAvailability::getAvailableDoctorOptionsForPatients(),
            'specializationOptions' => Doctor::getSpecializationOptionsForPatients(),
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

    private static function isValidDate(string $date): bool
    {
        $parsed = \DateTime::createFromFormat('Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
