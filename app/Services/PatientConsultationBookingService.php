<?php

namespace App\Services;

use App\Config\App;
use App\Core\Csrf;
use App\Core\Database;
use App\Helpers\Helper;
use App\Helpers\ListFilter;
use App\Helpers\Palette;
use App\Helpers\Status;
use App\Models\ConsultationRequest;
use App\Models\ConsultationRoom;
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
        $stored = null;
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

            if ($storedImagePath !== null && is_array($stored)) {
                ComplaintImageService::persistStoredUpload($requestId, $stored);
            }

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

    public static function changeCutoffHours(): int
    {
        return max(1, (int) (App::getConfig()['booking']['change_cutoff_hours'] ?? 24));
    }

    /**
     * @param array<string, mixed> $request
     * @return array{
     *   cutoff_hours:int,
     *   can_cancel:bool,
     *   can_reschedule:bool,
     *   blocked_reason:string,
     *   hours_until_start:?float
     * }
     */
    public static function changeEligibility(array $request): array
    {
        $cutoffHours = self::changeCutoffHours();
        $status = (string) ($request['status'] ?? '');
        $availabilityId = (int) ($request['availability_id'] ?? 0);
        $result = [
            'cutoff_hours' => $cutoffHours,
            'can_cancel' => false,
            'can_reschedule' => false,
            'blocked_reason' => '',
            'hours_until_start' => null,
        ];

        if (!in_array($status, [Status::PENDING, Status::APPROVED], true)) {
            $result['blocked_reason'] = 'This consultation can no longer be cancelled or rescheduled.';

            return $result;
        }

        $startAt = self::appointmentStart(
            (string) ($request['consultation_date'] ?? ''),
            (string) ($request['start_time'] ?? '')
        );

        if ($startAt === null) {
            $result['can_cancel'] = true;
            if ($availabilityId <= 0) {
                $result['blocked_reason'] = 'This booking has no scheduled slot to reschedule.';
            }

            return $result;
        }

        $remainingSeconds = $startAt->getTimestamp() - Helper::now()->getTimestamp();
        $result['hours_until_start'] = $remainingSeconds / 3600;

        if ($remainingSeconds <= 0) {
            $result['blocked_reason'] = 'This appointment has already started and can no longer be cancelled or rescheduled.';

            return $result;
        }

        $cutoffSeconds = $cutoffHours * 3600;
        if ($remainingSeconds < $cutoffSeconds) {
            $result['blocked_reason'] = self::cutoffErrorMessage($cutoffHours);

            return $result;
        }

        $result['can_cancel'] = true;
        $result['can_reschedule'] = $availabilityId > 0;

        return $result;
    }

    /**
     * @return array{success:bool,message:string,type:string}
     */
    public static function cancelBooking(int $patientId, int $requestId, string $csrfToken): array
    {
        if (!Csrf::verify($csrfToken)) {
            return [
                'success' => false,
                'message' => 'Unable to verify the request. Please refresh the page and try again.',
                'type' => 'danger',
            ];
        }

        $request = self::getRequestDetail($patientId, $requestId);
        if ($request === null) {
            return [
                'success' => false,
                'message' => 'The requested consultation record could not be found.',
                'type' => 'warning',
            ];
        }

        $eligibility = self::changeEligibility($request);
        if (!$eligibility['can_cancel']) {
            return [
                'success' => false,
                'message' => $eligibility['blocked_reason'] !== ''
                    ? $eligibility['blocked_reason']
                    : 'This consultation cannot be cancelled.',
                'type' => 'warning',
            ];
        }

        $result = ConsultationRequest::updateStatusForAdmin($requestId, Status::CANCELLED);
        $success = (bool) ($result['success'] ?? false);
        $type = (string) ($result['type'] ?? ($success ? 'success' : 'danger'));

        if ($success && $type === 'success') {
            NotificationService::notifyPatientCancelledConsultation($requestId);
            AuditLogService::record(
                'consultation_cancelled',
                'Consultation request cancelled by the patient.',
                AuditLogService::ENTITY_CONSULTATION_REQUEST,
                $requestId
            );

            return [
                'success' => true,
                'message' => 'Your consultation booking has been cancelled.',
                'type' => 'success',
            ];
        }

        return [
            'success' => $success,
            'message' => (string) ($result['message'] ?? 'Unable to cancel this consultation booking.'),
            'type' => $type !== '' ? $type : 'danger',
        ];
    }

    /**
     * @return array{
     *   request:?array<string,mixed>,
     *   eligibility:array<string,mixed>,
     *   slots:list<array<string,mixed>>,
     *   errors:list<string>
     * }
     */
    public static function getReschedulePageData(int $patientId, int $requestId): array
    {
        SlotExpirationService::sweep();

        $request = self::getRequestDetail($patientId, $requestId);
        $eligibility = $request !== null
            ? self::changeEligibility($request)
            : [
                'cutoff_hours' => self::changeCutoffHours(),
                'can_cancel' => false,
                'can_reschedule' => false,
                'blocked_reason' => 'The requested consultation record could not be found.',
                'hours_until_start' => null,
            ];

        $slots = [];
        if ($request !== null && !empty($eligibility['can_reschedule'])) {
            $slots = self::availableSlotsForReschedule(
                (int) ($request['doctor_id'] ?? 0),
                (int) ($request['availability_id'] ?? 0)
            );
        }

        return [
            'request' => $request,
            'eligibility' => $eligibility,
            'slots' => $slots,
            'errors' => [],
        ];
    }

    /**
     * @return array{success:bool,message:string,type:string,errors?:list<string>}
     */
    public static function rescheduleBooking(
        int $patientId,
        int $requestId,
        int $newAvailabilityId,
        string $csrfToken
    ): array {
        if (!Csrf::verify($csrfToken)) {
            return [
                'success' => false,
                'message' => 'Unable to verify the request. Please refresh the page and try again.',
                'type' => 'danger',
                'errors' => ['Unable to verify the request. Please refresh the page and try again.'],
            ];
        }

        $request = self::getRequestDetail($patientId, $requestId);
        if ($request === null) {
            return [
                'success' => false,
                'message' => 'The requested consultation record could not be found.',
                'type' => 'warning',
                'errors' => ['The requested consultation record could not be found.'],
            ];
        }

        $eligibility = self::changeEligibility($request);
        if (!$eligibility['can_reschedule']) {
            $message = $eligibility['blocked_reason'] !== ''
                ? $eligibility['blocked_reason']
                : 'This consultation cannot be rescheduled.';

            return [
                'success' => false,
                'message' => $message,
                'type' => 'warning',
                'errors' => [$message],
            ];
        }

        if ($newAvailabilityId <= 0) {
            return [
                'success' => false,
                'message' => 'Choose a new consultation slot to continue.',
                'type' => 'warning',
                'errors' => ['Choose a new consultation slot to continue.'],
            ];
        }

        $oldAvailabilityId = (int) ($request['availability_id'] ?? 0);
        if ($newAvailabilityId === $oldAvailabilityId) {
            return [
                'success' => false,
                'message' => 'Choose a different consultation slot from the one already booked.',
                'type' => 'warning',
                'errors' => ['Choose a different consultation slot from the one already booked.'],
            ];
        }

        SlotExpirationService::sweep();

        $db = Database::getInstance();
        $previousRoomName = '';
        $previousDate = (string) ($request['consultation_date'] ?? '');
        $previousTime = (string) ($request['start_time'] ?? '');
        $isApproved = (string) ($request['status'] ?? '') === Status::APPROVED;

        try {
            $db->beginTransaction();

            $requestStmt = $db->prepare(
                "SELECT id, patient_id, doctor_id, status, availability_id
                FROM consultation_requests
                WHERE id = :id
                FOR UPDATE"
            );
            $requestStmt->bindValue(':id', $requestId, \PDO::PARAM_INT);
            $requestStmt->execute();
            $lockedRequest = $requestStmt->fetch(\PDO::FETCH_ASSOC) ?: null;

            if ($lockedRequest === null || (int) ($lockedRequest['patient_id'] ?? 0) !== $patientId) {
                $db->rollBack();

                return [
                    'success' => false,
                    'message' => 'The requested consultation record could not be found.',
                    'type' => 'warning',
                    'errors' => ['The requested consultation record could not be found.'],
                ];
            }

            $currentStatus = (string) ($lockedRequest['status'] ?? '');
            if (!in_array($currentStatus, [Status::PENDING, Status::APPROVED], true)) {
                $db->rollBack();

                return [
                    'success' => false,
                    'message' => 'This consultation can no longer be cancelled or rescheduled.',
                    'type' => 'warning',
                    'errors' => ['This consultation can no longer be cancelled or rescheduled.'],
                ];
            }

            $lockedOldId = (int) ($lockedRequest['availability_id'] ?? 0);
            $doctorId = (int) ($lockedRequest['doctor_id'] ?? 0);
            $slotRows = self::lockSlotsForUpdate($db, $lockedOldId, $newAvailabilityId);

            $oldSlot = $lockedOldId > 0 ? ($slotRows[$lockedOldId] ?? null) : null;
            $newSlot = $slotRows[$newAvailabilityId] ?? null;

            if ($oldSlot === null || $newSlot === null) {
                $db->rollBack();

                return [
                    'success' => false,
                    'message' => 'The selected consultation slot could not be found.',
                    'type' => 'danger',
                    'errors' => ['The selected consultation slot could not be found.'],
                ];
            }

            $startAt = self::appointmentStart(
                (string) ($oldSlot['consultation_date'] ?? ''),
                (string) ($oldSlot['start_time'] ?? '')
            );
            if ($startAt !== null) {
                $remainingSeconds = $startAt->getTimestamp() - Helper::now()->getTimestamp();
                if ($remainingSeconds < (self::changeCutoffHours() * 3600)) {
                    $db->rollBack();
                    $message = $remainingSeconds <= 0
                        ? 'This appointment has already started and can no longer be cancelled or rescheduled.'
                        : self::cutoffErrorMessage(self::changeCutoffHours());

                    return [
                        'success' => false,
                        'message' => $message,
                        'type' => 'warning',
                        'errors' => [$message],
                    ];
                }
            }

            if ((int) ($newSlot['doctor_id'] ?? 0) !== $doctorId) {
                $db->rollBack();

                return [
                    'success' => false,
                    'message' => 'You can only reschedule to another available slot with the same doctor.',
                    'type' => 'warning',
                    'errors' => ['You can only reschedule to another available slot with the same doctor.'],
                ];
            }

            if ((string) ($newSlot['status'] ?? '') !== Status::SLOT_AVAILABLE) {
                $db->rollBack();

                return [
                    'success' => false,
                    'message' => 'This consultation slot is no longer available.',
                    'type' => 'warning',
                    'errors' => ['This consultation slot is no longer available.'],
                ];
            }

            if (SlotExpirationService::slotHasEnded($newSlot)) {
                $db->rollBack();
                SlotExpirationService::resetRequestGuard();
                SlotExpirationService::sweep();

                return [
                    'success' => false,
                    'message' => 'This consultation slot has ended and can no longer be booked.',
                    'type' => 'warning',
                    'errors' => ['This consultation slot has ended and can no longer be booked.'],
                ];
            }

            if (!self::slotIsOutsideCutoff($newSlot)) {
                $db->rollBack();
                $message = self::cutoffErrorMessage(self::changeCutoffHours());

                return [
                    'success' => false,
                    'message' => $message,
                    'type' => 'warning',
                    'errors' => [$message],
                ];
            }

            if (ConsultationRequest::hasActiveRequestForAvailability($newAvailabilityId, $requestId)) {
                $db->rollBack();

                return [
                    'success' => false,
                    'message' => 'This consultation slot is already reserved. Please choose another time.',
                    'type' => 'warning',
                    'errors' => ['This consultation slot is already reserved. Please choose another time.'],
                ];
            }

            $releasedStatus = SlotExpirationService::hasEnded(
                (string) ($oldSlot['consultation_date'] ?? ''),
                (string) ($oldSlot['end_time'] ?? '')
            ) ? Status::SLOT_EXPIRED : Status::SLOT_AVAILABLE;

            $releaseOld = $db->prepare('UPDATE doctor_availability SET status = :status WHERE id = :id');
            $releaseOld->bindValue(':status', $releasedStatus);
            $releaseOld->bindValue(':id', $lockedOldId, \PDO::PARAM_INT);
            $releaseOld->execute();

            $bookNew = $db->prepare("UPDATE doctor_availability SET status = 'Booked' WHERE id = :id");
            $bookNew->bindValue(':id', $newAvailabilityId, \PDO::PARAM_INT);
            $bookNew->execute();

            $updateRequest = $db->prepare(
                'UPDATE consultation_requests SET availability_id = :availability_id WHERE id = :id'
            );
            $updateRequest->bindValue(':availability_id', $newAvailabilityId, \PDO::PARAM_INT);
            $updateRequest->bindValue(':id', $requestId, \PDO::PARAM_INT);
            $updateRequest->execute();

            if ($isApproved) {
                try {
                    $previousRoomName = ConsultationRoom::replaceForRescheduledConsultation($requestId);
                } catch (\Throwable $roomError) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }

                    $safeMessage = $roomError->getMessage() !== ''
                        ? $roomError->getMessage()
                        : 'The video consultation room could not be updated.';

                    return [
                        'success' => false,
                        'message' => 'The consultation could not be rescheduled. ' . $safeMessage . ' No changes were saved.',
                        'type' => 'danger',
                        'errors' => ['The consultation could not be rescheduled. ' . $safeMessage . ' No changes were saved.'],
                    ];
                }
            }

            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log('Patient booking reschedule failed: ' . $exception->getMessage());

            return [
                'success' => false,
                'message' => 'Consultation reschedule is temporarily unavailable. Please try again later.',
                'type' => 'danger',
                'errors' => ['Consultation reschedule is temporarily unavailable. Please try again later.'],
            ];
        }

        SlotExpirationService::sweep();

        if ($previousRoomName !== '') {
            try {
                DailyService::deleteRoom($previousRoomName);
            } catch (\Throwable $deleteError) {
                error_log(
                    'Failed to delete previous Daily room after reschedule: ' . $deleteError->getMessage()
                );
            }
        }

        NotificationService::notifyPatientRescheduledConsultation($requestId, $previousDate, $previousTime);
        AuditLogService::record(
            'consultation_rescheduled',
            'Patient rescheduled a consultation booking to a different slot.',
            AuditLogService::ENTITY_CONSULTATION_REQUEST,
            $requestId
        );

        return [
            'success' => true,
            'message' => 'Your consultation has been rescheduled.',
            'type' => 'success',
        ];
    }

    /**
     * @param list<array<string,mixed>> $requests
     * @return list<array<string,mixed>>
     */
    public static function decorateRequestsForPatient(array $requests): array
    {
        $decorated = [];
        foreach ($requests as $request) {
            if (!is_array($request)) {
                continue;
            }
            $request['bookingChange'] = self::changeEligibility($request);
            $decorated[] = $request;
        }

        return $decorated;
    }

    private static function cutoffErrorMessage(int $cutoffHours): string
    {
        return sprintf(
            'Bookings cannot be cancelled or rescheduled within %d hour%s of the appointment.',
            $cutoffHours,
            $cutoffHours === 1 ? '' : 's'
        );
    }

    private static function appointmentStart(string $date, string $time): ?\DateTimeImmutable
    {
        $date = trim($date);
        $time = trim($time);
        if ($date === '' || $time === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            $time .= ':00';
        }

        $parsed = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $date . ' ' . $time,
            Helper::now()->getTimezone()
        );

        return $parsed instanceof \DateTimeImmutable ? $parsed : null;
    }

    /**
     * @param array<string, mixed> $slot
     */
    private static function slotIsOutsideCutoff(array $slot): bool
    {
        $startAt = self::appointmentStart(
            (string) ($slot['consultation_date'] ?? ''),
            (string) ($slot['start_time'] ?? '')
        );
        if ($startAt === null) {
            return false;
        }

        $remainingSeconds = $startAt->getTimestamp() - Helper::now()->getTimestamp();

        return $remainingSeconds >= (self::changeCutoffHours() * 3600);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function availableSlotsForReschedule(int $doctorId, int $currentAvailabilityId): array
    {
        if ($doctorId <= 0) {
            return [];
        }

        $rows = DoctorAvailability::findAvailableForPatients(['doctor_id' => $doctorId], 50, 0);
        $slots = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ((int) ($row['id'] ?? 0) === $currentAvailabilityId) {
                continue;
            }
            if (!self::slotIsOutsideCutoff($row)) {
                continue;
            }
            $slots[] = $row;
        }

        return $slots;
    }

    /**
     * Lock availability rows in id order to avoid deadlocks during a slot swap.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function lockSlotsForUpdate(\PDO $db, int $oldAvailabilityId, int $newAvailabilityId): array
    {
        $ids = [];
        foreach ([$oldAvailabilityId, $newAvailabilityId] as $id) {
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $ids = array_values($ids);
        sort($ids, SORT_NUMERIC);

        $locked = [];
        foreach ($ids as $id) {
            $slotStmt = $db->prepare(
                "SELECT
                    id,
                    doctor_id,
                    status,
                    consultation_date,
                    start_time,
                    end_time
                FROM doctor_availability
                WHERE id = :id
                FOR UPDATE"
            );
            $slotStmt->bindValue(':id', $id, \PDO::PARAM_INT);
            $slotStmt->execute();
            $row = $slotStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            if (is_array($row)) {
                $locked[$id] = $row;
            }
        }

        return $locked;
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
