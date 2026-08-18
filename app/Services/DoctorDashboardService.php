<?php

namespace App\Services;

use App\Models\Doctor;
use App\Models\ConsultationRequest;
use App\Models\DoctorAvailability;
use App\Models\User;

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

        $hasProfilePhoto = !empty($doctor['profile_photo_path'] ?? '');
        $hasSignature = !empty($doctor['signature_path'] ?? '');

        return [
            'user' => $user,
            'doctor' => $doctor,
            'stats' => [
                [
                    'label' => "Today's Schedule",
                    'value' => (string) ($todaySummary['total_today_slots'] ?? 0),
                    'icon' => 'bi-calendar-date',
                    'description' => 'Consultation slots currently scheduled on your calendar for today.',
                ],
                [
                    'label' => 'Approved Appointments',
                    'value' => (string) ($consultationSummary['approved_appointments'] ?? 0),
                    'icon' => 'bi-check2-circle',
                    'description' => 'Appointments approved by administration and assigned to your consultation workflow.',
                ],
                [
                    'label' => 'Upcoming Consultations',
                    'value' => (string) ($consultationSummary['upcoming_consultations'] ?? 0),
                    'icon' => 'bi-clock-history',
                    'description' => 'Approved consultations scheduled from today onward.',
                ],
                [
                    'label' => 'New Approved Appointments',
                    'value' => (string) $recentApprovedAppointments,
                    'icon' => 'bi-bell',
                    'description' => 'Recently approved consultation requests assigned to your clinician schedule.',
                ],
            ],
            'quickActions' => [
                [
                    'title' => 'View Consultations',
                    'description' => 'Review upcoming, approved, and completed consultations.',
                    'icon' => 'bi-clipboard2-pulse',
                    'status' => 'Active',
                    'url' => '/doctor/consultations',
                    'action_label' => 'Open Consultations',
                ],
                [
                    'title' => 'View My Profile',
                    'description' => 'Review your name, specialization, profile photo, and signature.',
                    'icon' => 'bi-person-vcard',
                    'status' => 'Available',
                    'url' => '/doctor/profile',
                    'action_label' => 'Open Profile',
                ],
                [
                    'title' => 'Edit Profile Settings',
                    'description' => 'Update phone number, specialization, profile photo, signature, and password.',
                    'icon' => 'bi-person-gear',
                    'status' => 'Available',
                    'url' => '/doctor/profile/edit',
                    'action_label' => 'Edit Profile',
                ],
                [
                    'title' => 'Manage Availability',
                    'description' => 'Create, review, update, and delete consultation slots.',
                    'icon' => 'bi-calendar-week',
                    'status' => 'Available',
                    'url' => '/doctor/availability',
                    'action_label' => 'Open Availability',
                ],
                [
                    'title' => 'Profile photo and signature',
                    'description' => $hasProfilePhoto && $hasSignature
                        ? 'Your profile photo and signature are on file.'
                        : 'Add a profile photo and signature for consultation documentation.',
                    'icon' => $hasProfilePhoto && $hasSignature ? 'bi-check2-circle' : 'bi-cloud-arrow-up',
                    'status' => $hasProfilePhoto && $hasSignature ? 'Up to date' : 'Action needed',
                    'url' => '/doctor/profile/edit',
                    'action_label' => 'Update Profile',
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
                        'borderColor' => '#0794E3',
                        'backgroundColor' => 'rgba(7, 148, 227, 0.12)',
                        'fill' => true,
                        'tension' => 0.25,
                        'pointRadius' => 3,
                        'pointBackgroundColor' => '#0794E3',
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
                        'backgroundColor' => '#B8DFF6',
                        'borderRadius' => 6,
                        'stack' => 'slots',
                    ],
                    [
                        'label' => 'Booked',
                        'data' => $booked,
                        'backgroundColor' => '#0794E3',
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
        $labels = [];
        $values = [];
        $colors = [
            'Pending' => '#F59E0B',
            'Approved' => '#08B4C6',
            'Rejected' => '#DC3545',
            'Cancelled' => '#70838A',
            'Completed' => '#455F68',
        ];
        $background = [];

        foreach (['Pending', 'Approved', 'Rejected', 'Cancelled', 'Completed'] as $status) {
            $labels[] = $status;
            $values[] = (int) ($distribution[$status] ?? 0);
            $background[] = $colors[$status] ?? 'rgba(107, 114, 128, 0.6)';
        }

        return [
            'type' => 'doughnut',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'data' => $values,
                        'backgroundColor' => $background,
                        'borderWidth' => 0,
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'cutout' => '68%',
                'plugins' => [
                    'legend' => [
                        'position' => 'bottom',
                        'labels' => ['usePointStyle' => true, 'boxWidth' => 10],
                    ],
                ],
            ],
        ];
    }
}
