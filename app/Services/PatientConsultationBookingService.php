<?php

namespace App\Services;

use App\Core\Csrf;
use App\Core\Database;
use App\Helpers\ListFilter;
use App\Helpers\Palette;
use App\Helpers\Status;
use App\Models\ConsultationRequest;
use App\Models\DoctorAvailability;

class PatientConsultationBookingService
{
    public const PER_PAGE = 10;
    public const MAX_REASON_LENGTH = 500;

    public static function getDashboardSummary(int $patientId): array
    {
        return ConsultationRequest::getDashboardSummaryForPatient($patientId);
    }

    public static function getDashboardInsights(int $patientId): array
    {
        $summary = self::getDashboardSummary($patientId);
        $nextAppointment = ConsultationRequest::findNextApprovedForPatient($patientId);
        $statusDistribution = ConsultationRequest::getStatusDistributionForPatient($patientId);
        $monthlyCounts = ConsultationRequest::findMonthlyRequestCountsForPatient($patientId, 6);

        return [
            'summary' => $summary,
            'nextAppointment' => $nextAppointment,
            'charts' => [
                'status_distribution' => self::buildStatusDistributionChart($statusDistribution),
                'monthly_requests' => self::buildMonthlyRequestsChart($monthlyCounts),
            ],
        ];
    }

    public static function getHistoryPageData(int $patientId, array $query): array
    {
        $filters = self::normalizeFilters($query);
        $perPage = (int) ($filters['per_page'] ?? self::PER_PAGE);
        $page = max(1, (int) ($query['page'] ?? 1));
        $grouped = self::shouldGroup($filters);

        if (!$grouped) {
            $totalItems = ConsultationRequest::countForPatient($patientId, $filters);
            $pagination = ListFilter::paginate($page, $totalItems, $perPage);
            $offset = ($pagination['current_page'] - 1) * $perPage;
            $requests = ConsultationRequest::findForPatient($patientId, $perPage, $offset, $filters);

            return [
                'filters' => $filters,
                'requests' => $requests,
                'groups' => self::groupHistoryRows($requests),
                'grouped' => false,
                'filterActive' => ListFilter::isActive($filters, ['sort' => '', 'per_page' => self::PER_PAGE]),
                'summary' => self::getDashboardSummary($patientId),
                'pagination' => $pagination,
                'statusOptions' => self::getStatusOptions(),
                'dateOptions' => self::getDateOptions(),
                'sortOptions' => self::getSortOptions(),
                'documentOptions' => self::getDocumentOptions(),
            ];
        }

        $groupFilters = [
            'search' => (string) ($filters['search'] ?? ''),
            'documents' => (string) ($filters['documents'] ?? ''),
        ];
        $pending = ConsultationRequest::findForPatient($patientId, 20, 0, $groupFilters + ['status' => 'Pending', 'sort' => 'upcoming']);
        $approved = ConsultationRequest::findForPatient($patientId, 50, 0, $groupFilters + ['status' => 'Approved', 'sort' => 'upcoming']);
        $rejected = ConsultationRequest::findForPatient($patientId, 20, 0, $groupFilters + ['status' => 'Rejected', 'sort' => 'newest']);
        $cancelled = ConsultationRequest::findForPatient($patientId, 20, 0, $groupFilters + ['status' => 'Cancelled', 'sort' => 'newest']);

        $completedFilters = $groupFilters + ['status' => 'Completed', 'sort' => 'newest'];
        $completedTotal = ConsultationRequest::countForPatient($patientId, $completedFilters);
        $pagination = ListFilter::paginate($page, $completedTotal, $perPage);
        $offset = ($pagination['current_page'] - 1) * $perPage;
        $completed = ConsultationRequest::findForPatient($patientId, $perPage, $offset, $completedFilters);

        $requests = array_merge($pending, $approved, $completed, $rejected, $cancelled);

        return [
            'filters' => $filters,
            'requests' => $requests,
            'groups' => [
                'active' => array_merge($pending, $approved),
                'completed' => $completed,
                'closed' => array_merge($rejected, $cancelled),
            ],
            'grouped' => true,
            'filterActive' => ListFilter::isActive($filters, ['sort' => '', 'per_page' => self::PER_PAGE]),
            'summary' => self::getDashboardSummary($patientId),
            'pagination' => $pagination,
            'statusOptions' => self::getStatusOptions(),
            'dateOptions' => self::getDateOptions(),
            'sortOptions' => self::getSortOptions(),
            'documentOptions' => self::getDocumentOptions(),
        ];
    }

    /**
     * Split history rows so upcoming/active consultations stay separate from
     * completed records. Completed rows are newest consultation date first.
     *
     * @param list<array<string,mixed>> $requests
     * @return array{
     *   active:list<array<string,mixed>>,
     *   completed:list<array<string,mixed>>,
     *   closed:list<array<string,mixed>>
     * }
     */
    public static function groupHistoryRows(array $requests): array
    {
        $active = [];
        $completed = [];
        $closed = [];

        foreach ($requests as $row) {
            $status = (string) ($row['status'] ?? '');
            if ($status === 'Completed') {
                $completed[] = $row;
            } elseif (in_array($status, ['Rejected', 'Cancelled'], true)) {
                $closed[] = $row;
            } else {
                $active[] = $row;
            }
        }

        $sortBySchedule = static function (array $left, array $right, bool $newestFirst): int {
            $leftKey = (string) ($left['consultation_date'] ?? '') . ' ' . (string) ($left['start_time'] ?? '');
            $rightKey = (string) ($right['consultation_date'] ?? '') . ' ' . (string) ($right['start_time'] ?? '');
            $comparison = strcmp($leftKey, $rightKey);

            return $newestFirst ? -$comparison : $comparison;
        };

        usort($active, static fn (array $left, array $right): int => $sortBySchedule($left, $right, false));
        usort($completed, static fn (array $left, array $right): int => $sortBySchedule($left, $right, true));
        usort($closed, static fn (array $left, array $right): int => $sortBySchedule($left, $right, true));

        return [
            'active' => $active,
            'completed' => $completed,
            'closed' => $closed,
        ];
    }

    /**
     * @return list<string>
     */
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
            ['value' => 'upcoming', 'label' => 'Upcoming'],
            ['value' => 'past', 'label' => 'Past'],
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
            ['value' => 'upcoming', 'label' => 'Upcoming first'],
        ];
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    public static function getDocumentOptions(): array
    {
        return [
            ['value' => 'record', 'label' => 'With clinical record'],
            ['value' => 'prescription', 'label' => 'With prescription'],
        ];
    }

    private static function shouldGroup(array $filters): bool
    {
        if (trim((string) ($filters['status'] ?? '')) !== '') {
            return false;
        }
        if (trim((string) ($filters['date'] ?? '')) !== '') {
            return false;
        }
        if (trim((string) ($filters['documents'] ?? '')) !== '') {
            return false;
        }

        $sort = trim((string) ($filters['sort'] ?? ''));

        return $sort === '';
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private static function normalizeFilters(array $query): array
    {
        $status = trim((string) ($query['status'] ?? ''));
        $search = ListFilter::normalizeSearch((string) ($query['search'] ?? ''));
        $sort = ListFilter::allowedValue(
            trim((string) ($query['sort'] ?? '')),
            ['newest', 'oldest', 'upcoming'],
            ''
        );
        $documents = ListFilter::allowedValue(
            trim((string) ($query['documents'] ?? '')),
            ['record', 'prescription'],
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
            'documents' => $documents,
            'per_page' => $perPage,
        ];
    }

    public static function getRequestDetail(int $patientId, int $requestId): ?array
    {
        if ($requestId <= 0) {
            return null;
        }

        return ConsultationRequest::findByIdForPatient($requestId, $patientId);
    }

    public static function getLatestRequestSummary(int $patientId): ?array
    {
        if ($patientId <= 0) {
            return null;
        }

        return ConsultationRequest::findLatestForPatient($patientId);
    }

    public static function getRecentRequests(int $patientId, int $limit = 5): array
    {
        if ($patientId <= 0) {
            return [];
        }

        $limit = max(1, $limit);

        return ConsultationRequest::findForPatient($patientId, $limit, 0);
    }

    public static function getBookingPageData(int $availabilityId, array $input = []): array
    {
        SlotExpirationService::sweep();
        $slot = DoctorAvailability::findAvailableSlotForPatients($availabilityId);

        return [
            'slot' => $slot,
            'formData' => [
                'reason' => trim((string) ($input['reason'] ?? '')),
            ],
        ];
    }

    public static function submitBooking(int $patientId, int $availabilityId, array $input, array $files = []): array
    {
        $formData = [
            'reason' => trim((string) ($input['reason'] ?? '')),
        ];
        $errors = [];
        $fieldErrors = [];

        if (!Csrf::verify((string) ($input['_token'] ?? ''))) {
            $fieldErrors['_token'] = 'Unable to verify the request. Please refresh the page and try again.';
        }

        if ($formData['reason'] === '') {
            $fieldErrors['reason'] = 'Consultation reason is required.';
        } elseif (mb_strlen($formData['reason']) > self::MAX_REASON_LENGTH) {
            $fieldErrors['reason'] = 'Consultation reason must be ' . self::MAX_REASON_LENGTH . ' characters or fewer.';
        }

        if ($availabilityId <= 0) {
            $errors[] = 'The selected consultation slot is invalid.';
        }

        if ($fieldErrors !== []) {
            if (isset($fieldErrors['_token'])) {
                $errors[] = $fieldErrors['_token'];
            }

            $errors[] = 'Please correct the highlighted booking fields.';

            return [
                'success' => false,
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
                'formData' => $formData,
                'slot' => DoctorAvailability::findAvailableSlotForPatients($availabilityId),
            ];
        }

        SlotExpirationService::sweep();

        $db = Database::getInstance();
        $storedImagePath = null;
        $committed = false;

        try {
            $db->beginTransaction();

            $slotStmt = $db->prepare(
                "SELECT
                    doctor_availability.id,
                    doctor_availability.doctor_id,
                    doctor_availability.status,
                    doctor_availability.consultation_date,
                    doctor_availability.end_time
                FROM doctor_availability
                WHERE id = :id
                FOR UPDATE"
            );
            $slotStmt->bindValue(':id', $availabilityId, \PDO::PARAM_INT);
            $slotStmt->execute();
            $slotRow = $slotStmt->fetch(\PDO::FETCH_ASSOC) ?: null;

            if ($slotRow === null) {
                throw new \RuntimeException('Slot not found.');
            }

            if (($slotRow['status'] ?? '') !== 'Available') {
                return self::failTransaction(
                    $db,
                    'This consultation slot is no longer available.',
                    $availabilityId,
                    $formData
                );
            }

            if (SlotExpirationService::slotHasEnded($slotRow)) {
                $failed = self::failTransaction(
                    $db,
                    'This consultation slot has ended and can no longer be booked.',
                    $availabilityId,
                    $formData
                );
                SlotExpirationService::resetRequestGuard();
                SlotExpirationService::sweep();

                return $failed;
            }

            if (ConsultationRequest::existsForPatientAndAvailability($patientId, $availabilityId)) {
                return self::failTransaction(
                    $db,
                    'You have already submitted a consultation request for this slot.',
                    $availabilityId,
                    $formData
                );
            }

            if (ConsultationRequest::hasActiveRequestForAvailability($availabilityId)) {
                return self::failTransaction(
                    $db,
                    'This consultation slot is already reserved. Please choose another time.',
                    $availabilityId,
                    $formData
                );
            }

            $doctorId = (int) ($slotRow['doctor_id'] ?? 0);

            if ($doctorId <= 0) {
                throw new \RuntimeException('Doctor id missing for slot.');
            }

            try {
                $stored = ComplaintImageService::storeForPatient($patientId, $files);
                $storedImagePath = is_array($stored) ? (string) ($stored['path'] ?? '') : null;
                if ($storedImagePath === '') {
                    $storedImagePath = null;
                }
            } catch (\RuntimeException $uploadException) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }

                return [
                    'success' => false,
                    'errors' => ['Please correct the highlighted booking fields.'],
                    'fieldErrors' => [
                        'complaint_image' => $uploadException->getMessage(),
                    ],
                    'formData' => $formData,
                    'slot' => DoctorAvailability::findAvailableSlotForPatients($availabilityId),
                ];
            }

            $requestId = ConsultationRequest::createBooking(
                $patientId,
                $doctorId,
                $availabilityId,
                $formData['reason'],
                'Pending',
                $storedImagePath
            );

            if ($requestId <= 0) {
                throw new \RuntimeException('Unable to create consultation request.');
            }

            $updateStmt = $db->prepare("UPDATE doctor_availability SET status = 'Booked' WHERE id = :id");
            $updateStmt->bindValue(':id', $availabilityId, \PDO::PARAM_INT);
            $updateStmt->execute();

            $db->commit();
            $committed = true;

            NotificationService::notifyConsultationRequestCreated($requestId);
            AuditLogService::record(
                'consultation_request_created',
                'Patient submitted a new consultation request.',
                AuditLogService::ENTITY_CONSULTATION_REQUEST,
                $requestId
            );

            return [
                'success' => true,
                'requestId' => $requestId,
                'message' => 'Consultation booking submitted successfully.',
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            if (!$committed && $storedImagePath !== null) {
                ComplaintImageService::deleteStoredPath($storedImagePath);
            }

            error_log('Patient booking submission failed: ' . $exception->getMessage());

            return [
                'success' => false,
                'errors' => ['Consultation booking is temporarily unavailable. Please try again later.'],
                'fieldErrors' => [],
                'formData' => $formData,
                'slot' => DoctorAvailability::findAvailableSlotForPatients($availabilityId),
            ];
        }
    }

    private static function failTransaction(
        \PDO $db,
        string $message,
        int $availabilityId,
        array $formData
    ): array {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        return [
            'success' => false,
            'errors' => [$message],
            'fieldErrors' => [],
            'formData' => $formData,
            'slot' => DoctorAvailability::findAvailableSlotForPatients($availabilityId),
        ];
    }

    private static function buildStatusDistributionChart(array $distribution): array
    {
        return Status::consultationDistributionChart($distribution);
    }

    private static function buildMonthlyRequestsChart(array $rows): array
    {
        $labels = [];
        $values = [];

        foreach ($rows as $row) {
            $month = (string) ($row['request_month'] ?? '');

            if ($month === '') {
                continue;
            }

            $labels[] = date('M', strtotime($month));
            $values[] = (int) ($row['total'] ?? 0);
        }

        return [
            'type' => 'bar',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'label' => 'Requests',
                        'data' => $values,
                        'backgroundColor' => Palette::MEDICAL_BLUE,
                        'borderRadius' => 8,
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
}
