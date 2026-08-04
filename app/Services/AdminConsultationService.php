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
}
