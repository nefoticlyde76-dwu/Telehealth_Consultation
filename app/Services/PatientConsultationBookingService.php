<?php

namespace App\Services;

use App\Core\Csrf;
use App\Core\Database;
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
        $page = max(1, (int) ($query['page'] ?? 1));
        $totalItems = ConsultationRequest::countForPatient($patientId);
        $totalPages = max(1, (int) ceil($totalItems / self::PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * self::PER_PAGE;

        return [
            'requests' => ConsultationRequest::findForPatient($patientId, self::PER_PAGE, $offset),
            'summary' => self::getDashboardSummary($patientId),
            'pagination' => [
                'current_page' => $page,
                'per_page' => self::PER_PAGE,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
            ],
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
        $slot = DoctorAvailability::findAvailableSlotForPatients($availabilityId);

        return [
            'slot' => $slot,
            'formData' => [
                'reason' => trim((string) ($input['reason'] ?? '')),
            ],
        ];
    }

    public static function submitBooking(int $patientId, int $availabilityId, array $input): array
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

        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            $slotStmt = $db->prepare(
                "SELECT
                    doctor_availability.id,
                    doctor_availability.doctor_id,
                    doctor_availability.status,
                    doctor_availability.consultation_date
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

            if ((string) ($slotRow['consultation_date'] ?? '') < date('Y-m-d')) {
                return self::failTransaction(
                    $db,
                    'This consultation slot is no longer available.',
                    $availabilityId,
                    $formData
                );
            }

            if (ConsultationRequest::existsForPatientAndAvailability($patientId, $availabilityId)) {
                return self::failTransaction(
                    $db,
                    'You have already submitted a consultation request for this slot.',
                    $availabilityId,
                    $formData
                );
            }

            $doctorId = (int) ($slotRow['doctor_id'] ?? 0);

            if ($doctorId <= 0) {
                throw new \RuntimeException('Doctor id missing for slot.');
            }

            $requestId = ConsultationRequest::createBooking($patientId, $doctorId, $availabilityId, $formData['reason'], 'Pending');

            if ($requestId <= 0) {
                throw new \RuntimeException('Unable to create consultation request.');
            }

            $updateStmt = $db->prepare("UPDATE doctor_availability SET status = 'Booked' WHERE id = :id");
            $updateStmt->bindValue(':id', $availabilityId, \PDO::PARAM_INT);
            $updateStmt->execute();

            $db->commit();

            return [
                'success' => true,
                'requestId' => $requestId,
                'message' => 'Consultation booking submitted successfully.',
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
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
                        'labels' => ['usePointStyle' => true, 'boxWidth' => 10],
                    ],
                ],
            ],
        ];
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
                        'backgroundColor' => 'rgba(10, 111, 182, 0.45)',
                        'borderRadius' => 12,
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
