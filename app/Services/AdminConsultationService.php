<?php

namespace App\Services;

use App\Core\Csrf;
use App\Models\ConsultationRequest;

class AdminConsultationService
{
    public const PER_PAGE = 20;

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
        $filters = self::normalizeFilters($query);
        $queueIds = ConsultationRequest::findQueueIdsForAdmin($filters);
        $totalItems = count($queueIds);
        $totalPages = max(1, (int) ceil(max($totalItems, 1) / self::PER_PAGE));
        $selectedId = (int) ($query['selected'] ?? 0);
        $page = max(1, (int) ($query['page'] ?? 1));
        $position = 0;

        $queueIndex = $selectedId > 0 ? array_search($selectedId, $queueIds, true) : false;
        if ($queueIndex !== false) {
            $page = (int) ceil(($queueIndex + 1) / self::PER_PAGE);
            $position = $queueIndex + 1;
        } else {
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            if ($selectedId <= 0 && $queueIds !== []) {
                $pageOffset = ($page - 1) * self::PER_PAGE;
                $selectedId = (int) ($queueIds[$pageOffset] ?? $queueIds[0]);
                $queueIndex = array_search($selectedId, $queueIds, true);
                if ($queueIndex !== false) {
                    $page = (int) ceil(($queueIndex + 1) / self::PER_PAGE);
                    $position = $queueIndex + 1;
                }
            }
        }

        $offset = ($page - 1) * self::PER_PAGE;
        $requests = ConsultationRequest::findForAdmin($filters, self::PER_PAGE, $offset);
        $selectedRequest = $selectedId > 0
            ? ConsultationRequest::findByIdForAdmin($selectedId)
            : null;

        return [
            'filters' => $filters,
            'requests' => $requests,
            'selectedId' => $selectedId,
            'selectedRequest' => $selectedRequest,
            'queuePosition' => $position,
            'summary' => self::getWorkspaceSummary(),
            'pagination' => [
                'current_page' => $page,
                'per_page' => self::PER_PAGE,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
            ],
            'statusOptions' => self::getStatusOptions(),
            'doctorOptions' => ConsultationRequest::getDoctorOptionsForAdmin(),
        ];
    }

    public static function getWorkspaceSummary(): array
    {
        $summary = ConsultationRequest::getAdminStatusSummary();
        $summary['today_requests'] = ConsultationRequest::countTodayPendingForAdmin();

        return $summary;
    }

    /**
     * @param list<int> $orderedIds
     */
    public static function nextQueueRequestId(array $orderedIds, int $currentId): ?int
    {
        $orderedIds = array_values(array_map('intval', $orderedIds));
        $index = array_search($currentId, $orderedIds, true);
        if ($index === false) {
            return $orderedIds[0] ?? null;
        }

        $next = $orderedIds[$index + 1] ?? null;

        return $next !== null && $next > 0 ? $next : null;
    }

    public static function workspacePath(array $filters, ?int $selectedId = null, int $page = 1): string
    {
        $query = array_filter([
            'search' => (string) ($filters['search'] ?? ''),
            'status' => (string) ($filters['status'] ?? ''),
            'doctor_id' => (int) ($filters['doctor_id'] ?? 0) > 0 ? (int) $filters['doctor_id'] : '',
            'consultation_date' => (string) ($filters['consultation_date'] ?? ''),
            'page' => $page > 1 ? $page : '',
            'selected' => $selectedId !== null && $selectedId > 0 ? $selectedId : '',
        ], static fn ($value) => $value !== '' && $value !== 0);

        $queryString = http_build_query($query);
        if (!array_key_exists('status', $query)) {
            $query['status'] = (string) ($filters['status'] ?? 'Pending');
            $queryString = http_build_query($query);
        }

        return '/admin/consultation-requests' . ($queryString !== '' ? '?' . $queryString : '');
    }

    public static function pageForQueueIndex(int $positionFromOne): int
    {
        if ($positionFromOne <= 0) {
            return 1;
        }

        return (int) ceil($positionFromOne / self::PER_PAGE);
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

    public static function normalizeFilters(array $query): array
    {
        $search = trim((string) ($query['search'] ?? ''));
        $status = trim((string) ($query['status'] ?? ''));
        $patientId = (int) ($query['patient_id'] ?? 0);
        $doctorId = (int) ($query['doctor_id'] ?? 0);
        $consultationDate = trim((string) ($query['consultation_date'] ?? ''));

        if (!array_key_exists('status', $query)) {
            $status = 'Pending';
        } elseif (!in_array($status, self::getStatusOptions(), true)) {
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
                    'meta' => 'Consultation requests',
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
