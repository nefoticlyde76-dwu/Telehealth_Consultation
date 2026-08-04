<?php

namespace App\Services;

use App\Core\Csrf;
use App\Models\ConsultationRequest;

class AdminConsultationService
{
    public const PER_PAGE = 10;

    public static function getDashboardSummary(): array
    {
        $summary = ConsultationRequest::getAdminStatusSummary();
        $recentRequests = ConsultationRequest::findRecentForAdmin(5);
        $recentActivityCount = ConsultationRequest::countRecentForAdmin(7);
        $weeklyRequests = ConsultationRequest::findDailyRequestCountsForAdmin(7);
        $statusDistribution = ConsultationRequest::getStatusDistributionForAdmin();

        return [
            'summary' => $summary,
            'recentRequests' => $recentRequests,
            'stats' => [
                [
                    'label' => 'Pending Requests',
                    'value' => (string) ($summary['pending_requests'] ?? 0),
                    'icon' => 'bi-hourglass-split',
                    'description' => 'Consultation requests currently waiting for administrator review.',
                ],
                [
                    'label' => 'Approved Appointments',
                    'value' => (string) ($summary['approved_requests'] ?? 0),
                    'icon' => 'bi-check2-circle',
                    'description' => 'Consultation appointments approved and reserved in the schedule.',
                ],
                [
                    'label' => 'Rejected Requests',
                    'value' => (string) ($summary['rejected_requests'] ?? 0),
                    'icon' => 'bi-x-circle',
                    'description' => 'Consultation requests declined during administrator review.',
                ],
                [
                    'label' => 'Recent Activity',
                    'value' => (string) $recentActivityCount,
                    'icon' => 'bi-activity',
                    'description' => 'Consultation requests submitted within the last seven days.',
                ],
            ],
            'recentActivity' => self::buildRecentActivity($recentRequests),
            'charts' => [
                'weekly_requests' => self::buildWeeklyRequestsChart($weeklyRequests),
                'status_distribution' => self::buildStatusDistributionChart($statusDistribution),
            ],
        ];
    }

    public static function getManagementPageData(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $filters = self::normalizeFilters($query);
        $totalItems = ConsultationRequest::countForAdmin($filters);
        $totalPages = max(1, (int) ceil($totalItems / self::PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * self::PER_PAGE;

        return [
            'filters' => $filters,
            'requests' => ConsultationRequest::findForAdmin($filters, self::PER_PAGE, $offset),
            'summary' => ConsultationRequest::getAdminStatusSummary(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => self::PER_PAGE,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
            ],
            'statusOptions' => self::getStatusOptions(),
            'patientOptions' => ConsultationRequest::getPatientOptionsForAdmin(),
            'doctorOptions' => ConsultationRequest::getDoctorOptionsForAdmin(),
        ];
    }

    public static function getConsultationDetail(int $requestId): ?array
    {
        if ($requestId <= 0) {
            return null;
        }

        return ConsultationRequest::findByIdForAdmin($requestId);
    }

    public static function approveRequest(int $requestId, string $csrfToken): array
    {
        return self::updateRequestStatus($requestId, 'Approved', $csrfToken);
    }

    public static function rejectRequest(int $requestId, string $csrfToken): array
    {
        return self::updateRequestStatus($requestId, 'Rejected', $csrfToken);
    }

    public static function cancelRequest(int $requestId, string $csrfToken): array
    {
        return self::updateRequestStatus($requestId, 'Cancelled', $csrfToken);
    }

    public static function getStatusOptions(): array
    {
        return ['Pending', 'Approved', 'Rejected', 'Cancelled', 'Completed'];
    }

    private static function updateRequestStatus(int $requestId, string $targetStatus, string $csrfToken): array
    {
        if (!Csrf::verify($csrfToken)) {
            return [
                'success' => false,
                'message' => 'Unable to verify the request. Please refresh the page and try again.',
                'type' => 'danger',
            ];
        }

        if (!in_array($targetStatus, self::getStatusOptions(), true)) {
            return [
                'success' => false,
                'message' => 'The requested consultation status is invalid.',
                'type' => 'danger',
            ];
        }

        $result = ConsultationRequest::updateStatusForAdmin($requestId, $targetStatus);

        return [
            'success' => (bool) ($result['success'] ?? false),
            'message' => (string) ($result['message'] ?? 'Consultation status updated.'),
            'type' => (string) ($result['type'] ?? (($result['success'] ?? false) ? 'success' : 'danger')),
        ];
    }

    private static function normalizeFilters(array $query): array
    {
        $search = trim((string) ($query['search'] ?? ''));
        $status = trim((string) ($query['status'] ?? ''));
        $patientId = (int) ($query['patient_id'] ?? 0);
        $doctorId = (int) ($query['doctor_id'] ?? 0);
        $consultationDate = trim((string) ($query['consultation_date'] ?? ''));

        if (!in_array($status, self::getStatusOptions(), true)) {
            $status = '';
        }

        if ($patientId <= 0) {
            $patientId = 0;
        }

        if ($doctorId <= 0) {
            $doctorId = 0;
        }

        if ($consultationDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $consultationDate)) {
            $consultationDate = '';
        }

        return [
            'search' => $search,
            'status' => $status,
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
            'consultation_date' => $consultationDate,
        ];
    }

    private static function buildRecentActivity(array $recentRequests): array
    {
        if ($recentRequests === []) {
            return [
                [
                    'title' => 'No consultation workflow activity yet',
                    'description' => 'Consultation requests will appear here once patients begin booking available appointments.',
                    'meta' => 'Week 5 workflow ready',
                ],
            ];
        }

        $activity = [];

        foreach ($recentRequests as $request) {
            $activity[] = [
                'title' => 'Request #' . (int) ($request['id'] ?? 0) . ' is ' . (string) ($request['status'] ?? 'Pending'),
                'description' => (string) ($request['patient_name'] ?? 'Patient') . ' with ' . (string) ($request['doctor_name'] ?? 'Doctor'),
                'meta' => 'Submitted ' . date('d M Y H:i', strtotime((string) ($request['request_date'] ?? 'now'))),
            ];
        }

        return $activity;
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
                        'label' => 'Weekly Requests',
                        'data' => $values,
                        'borderColor' => '#0A6FB6',
                        'backgroundColor' => 'rgba(10, 111, 182, 0.14)',
                        'fill' => true,
                        'tension' => 0.42,
                        'pointRadius' => 3,
                        'pointBackgroundColor' => '#0A6FB6',
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'legend' => ['display' => false],
                    'tooltip' => ['mode' => 'index', 'intersect' => false],
                ],
                'scales' => [
                    'x' => ['grid' => ['display' => false]],
                    'y' => [
                        'beginAtZero' => true,
                        'ticks' => ['precision' => 0],
                    ],
                ],
            ],
        ];
    }

    private static function buildStatusDistributionChart(array $distribution): array
    {
        $labels = [];
        $values = [];
        $colors = [
            'Pending' => 'rgba(245, 158, 11, 0.7)',
            'Approved' => 'rgba(34, 197, 94, 0.7)',
            'Rejected' => 'rgba(239, 68, 68, 0.7)',
            'Cancelled' => 'rgba(239, 68, 68, 0.45)',
            'Completed' => 'rgba(10, 111, 182, 0.7)',
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
                        'labels' => [
                            'usePointStyle' => true,
                            'boxWidth' => 10,
                        ],
                    ],
                ],
            ],
        ];
    }
}
