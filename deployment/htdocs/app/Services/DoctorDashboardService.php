<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\ConsultationRequest;
use App\Models\DoctorAvailability;
use App\Models\User;
use App\Helpers\Palette;
use App\Helpers\Status;

class DoctorDashboardService
{
    public static function getDashboardData(int $userId): array
    {
        $user = User::findById($userId);
        $doctor = Doctor::findProfileDetailByUserId($userId);
        $availabilitySummary = DoctorAvailability::getSummaryForDoctor($userId);
        $todaySummary = DoctorAvailability::getTodaySummaryForDoctor($userId);
        $upcomingSlots = DoctorAvailability::getUpcomingForDoctor($userId, 4);
        $weeklySchedule = DoctorAvailability::getDailyScheduleCountsForDoctor($userId, 7);
        $recentApprovedAppointments = ConsultationRequest::countApprovedRecentlyForDoctor($userId, 7);
        $upcomingApprovedCount = ConsultationRequest::countUpcomingApprovedForDoctor($userId);
        $upcomingApprovedAppointments = ConsultationRequest::findUpcomingApprovedForDoctor($userId, 5);
        $consultationSummary = ConsultationRequest::getDoctorStatusSummary($userId);
        $weeklyRequestCounts = ConsultationRequest::findDailyRequestCountsForDoctor($userId, 7);
        $statusDistribution = ConsultationRequest::getStatusDistributionForDoctor($userId);
        $recentCompleted = ConsultationRequest::findForDoctor($userId, [
            'status' => Status::COMPLETED,
            'sort' => 'newest',
        ], 5, 0);

        $hasProfilePhoto = !empty($doctor['profile_photo_path'] ?? '');
        $hasSignature = !empty($doctor['signature_path'] ?? '');

        return [
            'user' => $user,
            'doctor' => $doctor,
            'stats' => [
                [
                    'label' => "Today's Consultations",
                    'value' => (string) ($todaySummary['booked_today_slots'] ?? 0),
                    'icon' => 'bi-calendar-date',
                    'description' => 'Approved consultations booked for today.',
                    'tone' => 'pending',
                    'url' => '/doctor/consultations?date=today',
                ],
                [
                    'label' => 'Upcoming Consultations',
                    'value' => (string) ($consultationSummary['upcoming_consultations'] ?? 0),
                    'icon' => 'bi-clock-history',
                    'description' => 'Approved consultations from today onward.',
                    'tone' => 'success',
                    'url' => Status::filteredListUrl('/doctor/consultations', Status::APPROVED) . '&date=upcoming',
                ],
                [
                    'label' => 'Completed Consultations',
                    'value' => (string) ($consultationSummary['completed_consultations'] ?? 0),
                    'icon' => 'bi-clipboard2-check',
                    'description' => 'Consultations you have completed.',
                    'tone' => 'navy',
                    'url' => Status::filteredListUrl('/doctor/consultations', Status::COMPLETED),
                ],
            ],
            'quickActions' => [
                [
                    'title' => 'Manage Availability',
                    'description' => 'Create and update consultation slots patients can book.',
                    'icon' => 'bi-calendar-week',
                    'url' => '/doctor/availability',
                    'action_label' => 'Open',
                    'emphasis' => 'primary',
                ],
                [
                    'title' => 'View Upcoming Consultations',
                    'description' => 'Review approved consultations assigned to you.',
                    'icon' => 'bi-calendar2-check',
                    'url' => Status::filteredListUrl('/doctor/consultations', Status::APPROVED),
                    'action_label' => 'Open',
                ],
                [
                    'title' => 'Consultation History',
                    'description' => 'Open completed records and prescriptions.',
                    'icon' => 'bi-journal-medical',
                    'url' => Status::filteredListUrl('/doctor/consultations', Status::COMPLETED),
                    'action_label' => 'Open',
                ],
            ],
            'recentActivity' => [
                [
                    'title' => $recentApprovedAppointments > 0 ? 'New approved consultations' : 'No new approvals',
                    'description' => $recentApprovedAppointments > 0
                        ? 'Approved consultations are listed on your dashboard and consultations page.'
                        : 'Newly approved consultations will appear here when assigned to you.',
                    'meta' => $recentApprovedAppointments . ' new approval' . ($recentApprovedAppointments === 1 ? '' : 's'),
                ],
                [
                    'title' => $hasProfilePhoto && $hasSignature ? 'Profile documents complete' : 'Profile documents incomplete',
                    'description' => $hasProfilePhoto && $hasSignature
                        ? 'Your profile photo and signature are ready for clinical documentation.'
                        : 'Add your profile photo and signature from Edit Profile.',
                    'meta' => $hasProfilePhoto && $hasSignature ? 'Complete' : 'Action needed',
                ],
            ],
            'emptyState' => [
                'icon' => 'bi-clipboard2-pulse',
                'title' => 'No consultations to show yet',
                'description' => 'Create availability slots so patients can request appointments with you.',
            ],
            'todaySummary' => $todaySummary,
            'upcomingSlots' => $upcomingSlots,
            'weeklySchedule' => $weeklySchedule,
            'availabilitySummary' => $availabilitySummary,
            'upcomingApprovedAppointments' => $upcomingApprovedAppointments,
            'recentApprovedAppointmentCount' => $recentApprovedAppointments,
            'upcomingApprovedAppointmentCount' => $upcomingApprovedCount,
            'recentCompletedConsultations' => $recentCompleted,
            'consultationSummary' => $consultationSummary,
            'charts' => [
                'weekly_requests' => self::buildWeeklyRequestsChart($weeklyRequestCounts),
                'availability' => self::buildAvailabilityChart($weeklySchedule),
                'status_distribution' => self::buildStatusDistributionChart($statusDistribution),
            ],
            'assetReadiness' => [
                'has_profile_photo' => $hasProfilePhoto,
                'has_signature' => $hasSignature,
            ],
        ];
    }

    private static function buildWeeklyRequestsChart(array $rows): array
    {
        $labels = [];
        $values = [];
        $map = [];

        foreach ($rows as $row) {
            $day = (string) ($row['request_day'] ?? '');

            if ($day !== '') {
                $map[$day] = (int) ($row['total'] ?? 0);
            }
        }

        $today = new \DateTimeImmutable('today');

        for ($i = 6; $i >= 0; $i--) {
            $date = $today->sub(new \DateInterval('P' . $i . 'D'));
            $key = $date->format('Y-m-d');
            $labels[] = $date->format('D');
            $values[] = (int) ($map[$key] ?? 0);
        }

        return [
            'type' => 'line',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Requests',
                        'data' => $values,
                        'borderColor' => Palette::MEDICAL_BLUE,
                        'backgroundColor' => 'rgba(' . Palette::MEDICAL_BLUE_RGB . ', 0.12)',
                        'fill' => true,
                        'tension' => 0.25,
                        'pointRadius' => 3,
                        'pointBackgroundColor' => Palette::MEDICAL_BLUE,
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'legend' => ['display' => false],
                ],
                'scales' => [
                    'x' => ['grid' => ['display' => false]],
                    'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
                ],
            ],
        ];
    }

    private static function buildAvailabilityChart(array $rows): array
    {
        $labels = [];
        $available = [];
        $booked = [];

        foreach ($rows as $row) {
            $date = (string) ($row['consultation_date'] ?? '');

            if ($date === '') {
                continue;
            }

            $labels[] = date('D', strtotime($date));
            $available[] = (int) ($row['available_slots'] ?? 0);
            $booked[] = (int) ($row['booked_slots'] ?? 0);
        }

        return [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Available',
                        'data' => $available,
                        'backgroundColor' => Palette::MEDIUM_GRAY,
                        'borderRadius' => 6,
                        'stack' => 'slots',
                    ],
                    [
                        'label' => 'Booked',
                        'data' => $booked,
                        'backgroundColor' => Palette::MEDICAL_BLUE,
                        'borderRadius' => 6,
                        'stack' => 'slots',
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'legend' => [
                        'position' => 'bottom',
                        'labels' => ['usePointStyle' => true, 'boxWidth' => 10],
                    ],
                ],
                'scales' => [
                    'x' => ['stacked' => true, 'grid' => ['display' => false]],
                    'y' => ['stacked' => true, 'beginAtZero' => true, 'ticks' => ['precision' => 0]],
                ],
            ],
        ];
    }

    private static function buildStatusDistributionChart(array $distribution): array
    {
        return Status::consultationDistributionChart($distribution);
    }
}
