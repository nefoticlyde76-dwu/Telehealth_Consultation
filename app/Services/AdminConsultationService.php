<?php

namespace App\Services;

use App\Core\Csrf;
use App\Helpers\ListFilter;
use App\Helpers\Palette;
use App\Helpers\Status;
use App\Models\ConsultationRequest;

class AdminConsultationService
{
    public const PER_PAGE = 20;

    public static function getDashboardSummary(int $unreadNotifications = 0): array
    {
        $summary = ConsultationRequest::getAdminStatusSummary();
        $recentRequests = ConsultationRequest::findRecentForAdmin(5, Status::PENDING);
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
                    'description' => 'Requests waiting for administrator review.',
                    'tone' => 'pending',
                    'url' => Status::filteredListUrl('/admin/consultation-requests', Status::PENDING),
                ],
                [
                    'label' => 'Approved Consultations',
                    'value' => (string) ($summary['approved_requests'] ?? 0),
                    'icon' => 'bi-check2-circle',
                    'description' => 'Approved consultations currently on the schedule.',
                    'tone' => 'success',
                    'url' => Status::filteredListUrl('/admin/consultation-requests', Status::APPROVED),
                ],
                [
                    'label' => 'Completed Consultations',
                    'value' => (string) ($summary['completed_requests'] ?? 0),
                    'icon' => 'bi-clipboard2-check',
                    'description' => 'Consultations marked complete.',
                    'tone' => 'navy',
                    'url' => Status::filteredListUrl('/admin/consultation-requests', Status::COMPLETED),
                ],
                [
                    'label' => 'Unread Notifications',
                    'value' => (string) max(0, $unreadNotifications),
                    'icon' => 'bi-bell',
                    'description' => 'Notifications that still require attention.',
                    'tone' => 'info',
                    'url' => '/notifications?read_state=unread',
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
        $pagination = ListFilter::paginate(
            max(1, (int) ($query['page'] ?? 1)),
            $totalItems,
            self::PER_PAGE
        );
        $selectedId = (int) ($query['selected'] ?? 0);
        $page = $pagination['current_page'];
        $position = 0;

        $queueIndex = $selectedId > 0 ? array_search($selectedId, $queueIds, true) : false;
        if ($queueIndex !== false) {
            $page = (int) ceil(($queueIndex + 1) / self::PER_PAGE);
            $position = $queueIndex + 1;
            $pagination = ListFilter::paginate($page, $totalItems, self::PER_PAGE);
        } else {
            if ($selectedId <= 0 && $queueIds !== []) {
                $pageOffset = ($page - 1) * self::PER_PAGE;
                $selectedId = (int) ($queueIds[$pageOffset] ?? $queueIds[0]);
                $queueIndex = array_search($selectedId, $queueIds, true);
                if ($queueIndex !== false) {
                    $page = (int) ceil(($queueIndex + 1) / self::PER_PAGE);
                    $position = $queueIndex + 1;
                    $pagination = ListFilter::paginate($page, $totalItems, self::PER_PAGE);
                }
            }
        }

        $offset = ($pagination['current_page'] - 1) * self::PER_PAGE;
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
            'filterActive' => ListFilter::isActive($filters, ['status' => 'Pending', 'sort' => 'date_asc']),
            'summary' => self::getWorkspaceSummary(),
            'pagination' => $pagination,
            'statusOptions' => self::getStatusOptions(),
            'doctorOptions' => ConsultationRequest::getDoctorOptionsForAdmin(),
            'dateOptions' => self::getDateOptions(),
            'sortOptions' => self::getSortOptions(),
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
        $sort = (string) ($filters['sort'] ?? 'date_asc');
        $query = [
            'search' => (string) ($filters['search'] ?? ''),
            'status' => (string) ($filters['status'] ?? ''),
            'doctor_id' => (int) ($filters['doctor_id'] ?? 0) > 0 ? (int) $filters['doctor_id'] : '',
            'date' => (string) ($filters['date'] ?? ''),
            'date_from' => (string) ($filters['date'] ?? '') === 'custom' ? (string) ($filters['date_from'] ?? '') : '',
            'date_to' => (string) ($filters['date'] ?? '') === 'custom' ? (string) ($filters['date_to'] ?? '') : '',
            'sort' => $sort !== 'date_asc' ? $sort : '',
            'page' => $page > 1 ? $page : '',
            'selected' => $selectedId !== null && $selectedId > 0 ? $selectedId : '',
        ];

        $query = array_filter($query, static fn ($value) => $value !== '' && $value !== 0);
        if (!array_key_exists('status', $query)) {
            $query['status'] = (string) ($filters['status'] ?? 'Pending');
        }

        $queryString = http_build_query($query);

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
        return self::updateRequestStatus($requestId, Status::APPROVED, $csrfToken);
    }

    public static function rejectRequest(int $requestId, string $csrfToken): array
    {
        return self::updateRequestStatus($requestId, Status::REJECTED, $csrfToken);
    }

    public static function cancelRequest(int $requestId, string $csrfToken): array
    {
        return self::updateRequestStatus($requestId, Status::CANCELLED, $csrfToken);
    }

    public static function getStatusOptions(): array
    {
        return Status::consultationKeys();
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    public static function getDateOptions(): array
    {
        return [
            ['value' => 'today', 'label' => 'Today'],
            ['value' => 'tomorrow', 'label' => 'Tomorrow'],
            ['value' => 'this_week', 'label' => 'This week'],
            ['value' => 'this_month', 'label' => 'This month'],
            ['value' => 'custom', 'label' => 'Custom range'],
        ];
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    public static function getSortOptions(): array
    {
        return [
            ['value' => 'date_asc', 'label' => 'Consultation date (soonest)'],
            ['value' => 'date_desc', 'label' => 'Consultation date (latest)'],
            ['value' => 'newest', 'label' => 'Newest first'],
            ['value' => 'oldest', 'label' => 'Oldest first'],
        ];
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

        if (!in_array($targetStatus, Status::adminActionableStatuses(), true)) {
            return [
                'success' => false,
                'message' => 'The requested consultation status is invalid.',
                'type' => 'danger',
            ];
        }

        $result = ConsultationRequest::updateStatusForAdmin($requestId, $targetStatus);
        $success = (bool) ($result['success'] ?? false);
        $type = (string) ($result['type'] ?? ($success ? 'success' : 'danger'));

        if ($success && $type === 'success') {
            if ($targetStatus === Status::APPROVED) {
                NotificationService::notifyConsultationApproved($requestId);
                AuditLogService::record(
                    'consultation_approved',
                    'Consultation request approved by administrator.',
                    AuditLogService::ENTITY_CONSULTATION_REQUEST,
                    $requestId
                );
            } elseif ($targetStatus === Status::REJECTED) {
                NotificationService::notifyConsultationRejected($requestId);
                AuditLogService::record(
                    'consultation_rejected',
                    'Consultation request rejected by administrator.',
                    AuditLogService::ENTITY_CONSULTATION_REQUEST,
                    $requestId
                );
            } elseif ($targetStatus === Status::CANCELLED) {
                AuditLogService::record(
                    'consultation_cancelled',
                    'Consultation request cancelled by administrator.',
                    AuditLogService::ENTITY_CONSULTATION_REQUEST,
                    $requestId
                );
            }
        }

        return [
            'success' => $success,
            'message' => (string) ($result['message'] ?? 'Consultation status updated.'),
            'type' => $type,
        ];
    }

    public static function normalizeFilters(array $query): array
    {
        $search = ListFilter::normalizeSearch((string) ($query['search'] ?? ''));
        $status = trim((string) ($query['status'] ?? ''));
        $patientId = (int) ($query['patient_id'] ?? 0);
        $doctorId = (int) ($query['doctor_id'] ?? 0);
        $sort = ListFilter::allowedValue(
            trim((string) ($query['sort'] ?? 'date_asc')),
            ['date_asc', 'date_desc', 'newest', 'oldest'],
            'date_asc'
        );
        $range = ListFilter::resolveDateRange(
            (string) ($query['date'] ?? ''),
            (string) ($query['date_from'] ?? ''),
            (string) ($query['date_to'] ?? ''),
            (string) ($query['consultation_date'] ?? '')
        );

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

        return [
            'search' => $search,
            'status' => $status,
            'patient_id' => $patientId,
            'doctor_id' => $doctorId,
            'date' => $range['preset'],
            'date_from' => $range['preset'] === 'custom' ? $range['from'] : '',
            'date_to' => $range['preset'] === 'custom' ? $range['to'] : '',
            'date_range' => ['from' => $range['from'], 'to' => $range['to']],
            'sort' => $sort,
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
        return Status::consultationDistributionChart($distribution);
    }
}
