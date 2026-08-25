<?php

namespace App\Services;

use App\Core\Csrf;
use App\Helpers\ListFilter;
use App\Helpers\Status;
use App\Models\ConsultationRequest;

class DoctorConsultationService
{
    public const PER_PAGE = 10;

    public static function getConsultationPageData(int $doctorId, array $query): array
    {
        $filters = self::normalizeFilters($query);
        $perPage = (int) ($filters['per_page'] ?? self::PER_PAGE);
        $page = max(1, (int) ($query['page'] ?? 1));
        $grouped = self::shouldGroup($filters);

        if (!$grouped) {
            $totalItems = ConsultationRequest::countForDoctor($doctorId, $filters);
            $pagination = ListFilter::paginate($page, $totalItems, $perPage);
            $offset = ($pagination['current_page'] - 1) * $perPage;
            $consultations = ConsultationRequest::findForDoctor($doctorId, $filters, $perPage, $offset);

            return [
                'filters' => $filters,
                'consultations' => $consultations,
                'grouped' => false,
                'groups' => self::groupConsultationRows($consultations),
                'filterActive' => ListFilter::isActive($filters, ['sort' => 'date_asc', 'per_page' => self::PER_PAGE]),
                'summary' => ConsultationRequest::getDoctorStatusSummary($doctorId),
                'pagination' => $pagination,
                'statusOptions' => self::getStatusOptions(),
                'dateOptions' => self::getDateOptions(),
                'sortOptions' => self::getSortOptions(),
            ];
        }

        $groupFilters = [
            'search' => (string) ($filters['search'] ?? ''),
        ];
        $approved = ConsultationRequest::findForDoctor($doctorId, $groupFilters + ['status' => 'Approved'], 50, 0);
        $pending = ConsultationRequest::findForDoctor($doctorId, $groupFilters + ['status' => 'Pending'], 20, 0);
        $rejected = ConsultationRequest::findForDoctor($doctorId, $groupFilters + ['status' => 'Rejected'], 20, 0);
        $cancelled = ConsultationRequest::findForDoctor($doctorId, $groupFilters + ['status' => 'Cancelled'], 20, 0);

        $completedFilters = $groupFilters + ['status' => 'Completed', 'order' => 'DESC'];
        $completedTotal = ConsultationRequest::countForDoctor($doctorId, $completedFilters);
        $pagination = ListFilter::paginate($page, $completedTotal, $perPage);
        $offset = ($pagination['current_page'] - 1) * $perPage;
        $completed = ConsultationRequest::findForDoctor($doctorId, $completedFilters, $perPage, $offset);

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
            'filterActive' => ListFilter::isActive($filters, ['sort' => 'date_asc', 'per_page' => self::PER_PAGE]),
            'summary' => ConsultationRequest::getDoctorStatusSummary($doctorId),
            'pagination' => $pagination,
            'statusOptions' => self::getStatusOptions(),
            'dateOptions' => self::getDateOptions(),
            'sortOptions' => self::getSortOptions(),
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
        return Status::consultationKeys();
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    public static function getDateOptions(): array
    {
        return [
            ['value' => 'today', 'label' => 'Today'],
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
            ['value' => 'newest', 'label' => 'Newest first'],
            ['value' => 'oldest', 'label' => 'Oldest first'],
            ['value' => 'date_asc', 'label' => 'Consultation date (soonest)'],
            ['value' => 'date_desc', 'label' => 'Consultation date (latest)'],
        ];
    }

    /**
     * Keep the grouped upcoming/completed layout unless the doctor applies
     * status, date, or a non-default sort.
     */
    private static function shouldGroup(array $filters): bool
    {
        if (trim((string) ($filters['status'] ?? '')) !== '') {
            return false;
        }
        if (trim((string) ($filters['date'] ?? '')) !== '') {
            return false;
        }
        $sort = trim((string) ($filters['sort'] ?? ''));

        return $sort === '';
    }

    private static function normalizeFilters(array $query): array
    {
        $status = trim((string) ($query['status'] ?? ''));
        $search = ListFilter::normalizeSearch((string) ($query['search'] ?? ''));
        $sort = ListFilter::allowedValue(
            trim((string) ($query['sort'] ?? '')),
            ['newest', 'oldest', 'date_asc', 'date_desc'],
            ''
        );
        $range = ListFilter::resolveDateRange(
            (string) ($query['date'] ?? ''),
            (string) ($query['date_from'] ?? ''),
            (string) ($query['date_to'] ?? '')
        );
        $perPage = ListFilter::allowedPerPage($query['per_page'] ?? self::PER_PAGE, self::PER_PAGE);

        if (!in_array($status, self::getStatusOptions(), true)) {
            $status = '';
        }

        return [
            'status' => $status,
            'search' => $search,
            'date' => $range['preset'],
            'date_from' => $range['preset'] === 'custom' ? $range['from'] : '',
            'date_to' => $range['preset'] === 'custom' ? $range['to'] : '',
            'date_range' => ['from' => $range['from'], 'to' => $range['to']],
            'sort' => $sort,
            'per_page' => $perPage,
        ];
    }
}

