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
        $totalItems = ConsultationRequest::countForDoctor($doctorId, $filters);
        $totalPages = max(1, (int) ceil($totalItems / self::PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * self::PER_PAGE;

        return [
            'filters' => $filters,
            'consultations' => ConsultationRequest::findForDoctor($doctorId, $filters, self::PER_PAGE, $offset),
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

