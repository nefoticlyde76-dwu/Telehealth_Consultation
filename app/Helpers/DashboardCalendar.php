<?php

namespace App\Helpers;

use App\Models\ConsultationRequest;

/**
 * Maps role-scoped consultation rows into dashboard calendar events.
 */
class DashboardCalendar
{
    /**
     * @return list<array<string, string>>
     */
    public static function eventsForDashboard(string $role, int $userId): array
    {
        $from = (new \DateTimeImmutable('first day of this month'))->modify('-2 months')->format('Y-m-d');
        $to = (new \DateTimeImmutable('last day of this month'))->modify('+6 months')->format('Y-m-d');
        $rows = ConsultationRequest::findForCalendar($role, $userId, $from, $to);

        $detailBase = match ($role) {
            'doctor' => '/doctor/consultations/',
            'patient' => '/patient/consultation-requests/',
            'admin' => '/admin/consultation-requests/',
            default => '',
        };

        $events = [];

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $date = (string) ($row['consultation_date'] ?? '');
            $status = (string) ($row['status'] ?? '');

            if ($date === '' || $id <= 0) {
                continue;
            }

            $name = match ($role) {
                'patient' => (string) ($row['doctor_name'] ?? 'Doctor'),
                'admin' => trim((string) ($row['patient_name'] ?? 'Patient') . ' · ' . (string) ($row['doctor_name'] ?? 'Doctor'), ' ·'),
                default => (string) ($row['patient_name'] ?? 'Patient'),
            };

            $events[] = [
                'date' => $date,
                'time' => substr((string) ($row['start_time'] ?? ''), 0, 5),
                'end' => substr((string) ($row['end_time'] ?? ''), 0, 5),
                'name' => $name,
                'status' => $status,
                'color' => Status::chartColor($status),
                'url' => $detailBase !== '' ? Helper::url($detailBase . $id) : '',
            ];
        }

        return $events;
    }
}
