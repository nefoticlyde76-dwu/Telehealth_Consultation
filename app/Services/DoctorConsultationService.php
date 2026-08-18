<?php

namespace App\Services;

use App\Core\Csrf;
use App\Models\ConsultationRequest;

class DoctorConsultationService
{
    public const PER_PAGE = 10;

    public static function getConsultationPageData(int $doctorId, array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $filters = self::normalizeFilters($query);
        $grouped = ($filters['status'] ?? '') === '';

        if (!$grouped) {
            $totalItems = ConsultationRequest::countForDoctor($doctorId, $filters);
            $totalPages = max(1, (int) ceil($totalItems / self::PER_PAGE));

            if ($page > $totalPages) {
                $page = $totalPages;
            }

            $offset = ($page - 1) * self::PER_PAGE;
            $consultations = ConsultationRequest::findForDoctor($doctorId, $filters, self::PER_PAGE, $offset);

            return [
                'filters' => $filters,
                'consultations' => $consultations,
                'grouped' => false,
                'groups' => self::groupConsultationRows($consultations),
                'summary' => ConsultationRequest::getDoctorStatusSummary($doctorId),
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => self::PER_PAGE,
                    'total_items' => $totalItems,
                    'total_pages' => $totalPages,
                ],
                'statusOptions' => self::getStatusOptions(),
            ];
        }

        $searchFilter = ['search' => (string) ($filters['search'] ?? '')];
        $approved = ConsultationRequest::findForDoctor($doctorId, $searchFilter + ['status' => 'Approved'], 50, 0);
        $pending = ConsultationRequest::findForDoctor($doctorId, $searchFilter + ['status' => 'Pending'], 20, 0);
        $rejected = ConsultationRequest::findForDoctor($doctorId, $searchFilter + ['status' => 'Rejected'], 20, 0);
        $cancelled = ConsultationRequest::findForDoctor($doctorId, $searchFilter + ['status' => 'Cancelled'], 20, 0);

        $completedTotal = ConsultationRequest::countForDoctor($doctorId, $searchFilter + ['status' => 'Completed']);
        $totalPages = max(1, (int) ceil($completedTotal / self::PER_PAGE));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * self::PER_PAGE;
        $completed = ConsultationRequest::findForDoctor(
            $doctorId,
            $searchFilter + ['status' => 'Completed', 'order' => 'DESC'],
            self::PER_PAGE,
            $offset
        );

        $consultations = array_merge($approved, $pending, $completed, $rejected, $cancelled);

        return [
            'filters' => $filters,
            'consultations' => $consultations,
            'grouped' => true,
            'groups' => [
                'active' => array_merge($approved, $pending),
                'completed' => $completed,
                'closed' => array_merge($rejected, $cancelled),
            ],
            'summary' => ConsultationRequest::getDoctorStatusSummary($doctorId),
            'pagination' => [
                'current_page' => $page,
                'per_page' => self::PER_PAGE,
                'total_items' => $completedTotal,
                'total_pages' => $totalPages,
            ],
            'statusOptions' => self::getStatusOptions(),
        ];
    }

    /**
     * @param list<array<string,mixed>> $consultations
     * @return array{
     *   active:list<array<string,mixed>>,
     *   completed:list<array<string,mixed>>,
     *   closed:list<array<string,mixed>>
     * }
     */
    public static function groupConsultationRows(array $consultations): array
    {
        $active = [];
        $completed = [];
        $closed = [];

        foreach ($consultations as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'Completed') {
                $completed[] = $row;
            } elseif (in_array($status, ['Rejected', 'Cancelled'], true)) {
                $closed[] = $row;
            } else {
                $active[] = $row;
            }
        }

        return [
            'active' => $active,
            'completed' => $completed,
            'closed' => $closed,
        ];
    }

    public static function completeConsultation(int $doctorId, int $requestId, string $csrfToken): array
    {
        if (!Csrf::verify($csrfToken)) {
            return [
                'success' => false,
                'message' => 'Unable to verify the request. Please refresh the page and try again.',
                'type' => 'danger',
            ];
        }

        return [
            'success' => false,
            'message' => 'Complete the consultation from the consultation room after reviewing the clinical record.',
            'type' => 'warning',
        ];
    }

    public static function getStatusOptions(): array
    {
        return ['Approved', 'Completed', 'Rejected', 'Cancelled', 'Pending'];
    }

    private static function normalizeFilters(array $query): array
    {
        $status = trim((string) ($query['status'] ?? ''));
        $search = trim((string) ($query['search'] ?? ''));

        if (!in_array($status, self::getStatusOptions(), true)) {
            $status = '';
        }

        return [
            'status' => $status,
            'search' => $search,
        ];
    }
}

