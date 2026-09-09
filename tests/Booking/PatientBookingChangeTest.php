<?php

declare(strict_types=1);

namespace Tests\Booking;

use App\Helpers\Helper;
use App\Helpers\Status;
use App\Services\NotificationService;
use App\Services\PatientConsultationBookingService;
use Tests\Support\DatabaseTestCase;

final class PatientBookingChangeTest extends DatabaseTestCase
{
    public function testPatientCanCancelOwnPendingBookingOutsideCutoff(): void
    {
        $doctorId = $this->createDoctor('Cancel Doctor');
        $patientId = $this->createPatient('Cancel Patient');
        $slotId = $this->createAvailability($doctorId, '09:00:00', '09:30:00');

        $booked = PatientConsultationBookingService::submitBooking($patientId, $slotId, [
            '_token' => $this->csrfToken(),
            'reason' => 'PHPUNIT cancel own pending booking',
        ]);
        $requestId = (int) ($booked['requestId'] ?? 0);
        $this->trackRequest($requestId);
        $this->assertTrue(($booked['success'] ?? false) === true);

        $result = PatientConsultationBookingService::cancelBooking($patientId, $requestId, $this->csrfToken());

        $this->assertTrue(($result['success'] ?? false) === true);
        $this->assertSame(Status::CANCELLED, $this->requestStatus($requestId));
        $this->assertSame(Status::SLOT_AVAILABLE, $this->slotStatus($slotId));
        $this->assertNotNull($this->notificationFor($doctorId, $requestId, NotificationService::TYPE_CANCELLED));
    }

    public function testPatientCanCancelOwnApprovedBookingOutsideCutoff(): void
    {
        $doctorId = $this->createDoctor('Approved Cancel Doctor');
        $patientId = $this->createPatient('Approved Cancel Patient');
        $slotId = $this->createAvailability($doctorId, '10:00:00', '10:30:00');

        $booked = PatientConsultationBookingService::submitBooking($patientId, $slotId, [
            '_token' => $this->csrfToken(),
            'reason' => 'PHPUNIT cancel own approved booking',
        ]);
        $requestId = (int) ($booked['requestId'] ?? 0);
        $this->trackRequest($requestId);
        $this->markRequestApproved($requestId);

        $result = PatientConsultationBookingService::cancelBooking($patientId, $requestId, $this->csrfToken());

        $this->assertTrue(($result['success'] ?? false) === true);
        $this->assertSame(Status::CANCELLED, $this->requestStatus($requestId));
        $this->assertSame(Status::SLOT_AVAILABLE, $this->slotStatus($slotId));
        $this->assertNotNull($this->notificationFor($doctorId, $requestId, NotificationService::TYPE_CANCELLED));
    }

    public function testAnotherPatientCannotCancelTheBooking(): void
    {
        $doctorId = $this->createDoctor('Ownership Cancel Doctor');
        $patientA = $this->createPatient('Ownership Patient A');
        $patientB = $this->createPatient('Ownership Patient B');
        $slotId = $this->createAvailability($doctorId, '11:00:00', '11:30:00');

        $booked = PatientConsultationBookingService::submitBooking($patientA, $slotId, [
            '_token' => $this->csrfToken(),
            'reason' => 'PHPUNIT patient A owned booking',
        ]);
        $requestId = (int) ($booked['requestId'] ?? 0);
        $this->trackRequest($requestId);

        $result = PatientConsultationBookingService::cancelBooking($patientB, $requestId, $this->csrfToken());

        $this->assertFalse(($result['success'] ?? true) === true);
        $this->assertSame(Status::PENDING, $this->requestStatus($requestId));
        $this->assertSame(Status::SLOT_BOOKED, $this->slotStatus($slotId));
        $this->assertNull($this->notificationFor($doctorId, $requestId, NotificationService::TYPE_CANCELLED));
    }

    public function testCancelWithinCutoffIsRejected(): void
    {
        $doctorId = $this->createDoctor('Cutoff Cancel Doctor');
        $patientId = $this->createPatient('Cutoff Cancel Patient');
        [$date, $start, $end] = $this->soonSlotTimes();
        $slotId = $this->createAvailability($doctorId, $start, $end, $date);

        $booked = PatientConsultationBookingService::submitBooking($patientId, $slotId, [
            '_token' => $this->csrfToken(),
            'reason' => 'PHPUNIT cutoff cancel',
        ]);
        $requestId = (int) ($booked['requestId'] ?? 0);
        $this->trackRequest($requestId);
        $this->assertTrue(($booked['success'] ?? false) === true, 'Soon slot must still be bookable');

        $result = PatientConsultationBookingService::cancelBooking($patientId, $requestId, $this->csrfToken());

        $this->assertFalse(($result['success'] ?? true) === true);
        $this->assertStringContainsString('within', strtolower((string) ($result['message'] ?? '')));
        $this->assertSame(Status::PENDING, $this->requestStatus($requestId));
        $this->assertSame(Status::SLOT_BOOKED, $this->slotStatus($slotId));
    }

    public function testInvalidCsrfDoesNotCancel(): void
    {
        $doctorId = $this->createDoctor('CSRF Cancel Doctor');
        $patientId = $this->createPatient('CSRF Cancel Patient');
        $slotId = $this->createAvailability($doctorId, '12:00:00', '12:30:00');

        $booked = PatientConsultationBookingService::submitBooking($patientId, $slotId, [
            '_token' => $this->csrfToken(),
            'reason' => 'PHPUNIT csrf cancel',
        ]);
        $requestId = (int) ($booked['requestId'] ?? 0);
        $this->trackRequest($requestId);

        $result = PatientConsultationBookingService::cancelBooking($patientId, $requestId, 'not-a-real-token');

        $this->assertFalse(($result['success'] ?? true) === true);
        $this->assertSame(Status::PENDING, $this->requestStatus($requestId));
    }

    public function testPatientCanRescheduleToAnotherSlotWithTheSameDoctor(): void
    {
        $doctorId = $this->createDoctor('Reschedule Doctor');
        $patientId = $this->createPatient('Reschedule Patient');
        $oldSlotId = $this->createAvailability($doctorId, '09:00:00', '09:30:00');
        $newSlotId = $this->createAvailability($doctorId, '14:00:00', '14:30:00', (new \DateTimeImmutable('+6 days'))->format('Y-m-d'));

        $booked = PatientConsultationBookingService::submitBooking($patientId, $oldSlotId, [
            '_token' => $this->csrfToken(),
            'reason' => 'PHPUNIT reschedule same doctor',
        ]);
        $requestId = (int) ($booked['requestId'] ?? 0);
        $this->trackRequest($requestId);

        $result = PatientConsultationBookingService::rescheduleBooking(
            $patientId,
            $requestId,
            $newSlotId,
            $this->csrfToken()
        );

        $this->assertTrue(($result['success'] ?? false) === true, (string) ($result['message'] ?? 'reschedule failed'));
        $this->assertSame($newSlotId, $this->requestAvailabilityId($requestId));
        $this->assertSame(Status::PENDING, $this->requestStatus($requestId));
        $this->assertSame(Status::SLOT_AVAILABLE, $this->slotStatus($oldSlotId));
        $this->assertSame(Status::SLOT_BOOKED, $this->slotStatus($newSlotId));
        $this->assertNotNull($this->notificationFor($doctorId, $requestId, NotificationService::TYPE_RESCHEDULED));
    }

    public function testRescheduleToAnotherDoctorsSlotIsDenied(): void
    {
        $doctorA = $this->createDoctor('Reschedule Doctor A');
        $doctorB = $this->createDoctor('Reschedule Doctor B');
        $patientId = $this->createPatient('Cross Doctor Patient');
        $oldSlotId = $this->createAvailability($doctorA, '09:00:00', '09:30:00');
        $otherSlotId = $this->createAvailability($doctorB, '10:00:00', '10:30:00');

        $booked = PatientConsultationBookingService::submitBooking($patientId, $oldSlotId, [
            '_token' => $this->csrfToken(),
            'reason' => 'PHPUNIT reschedule other doctor',
        ]);
        $requestId = (int) ($booked['requestId'] ?? 0);
        $this->trackRequest($requestId);

        $result = PatientConsultationBookingService::rescheduleBooking(
            $patientId,
            $requestId,
            $otherSlotId,
            $this->csrfToken()
        );

        $this->assertFalse(($result['success'] ?? true) === true);
        $this->assertSame($oldSlotId, $this->requestAvailabilityId($requestId));
        $this->assertSame(Status::SLOT_BOOKED, $this->slotStatus($oldSlotId));
        $this->assertSame(Status::SLOT_AVAILABLE, $this->slotStatus($otherSlotId));
    }

    public function testRescheduleToMissingSlotIsDenied(): void
    {
        $doctorId = $this->createDoctor('Missing Slot Doctor');
        $patientId = $this->createPatient('Missing Slot Patient');
        $slotId = $this->createAvailability($doctorId, '13:00:00', '13:30:00');

        $booked = PatientConsultationBookingService::submitBooking($patientId, $slotId, [
            '_token' => $this->csrfToken(),
            'reason' => 'PHPUNIT missing slot reschedule',
        ]);
        $requestId = (int) ($booked['requestId'] ?? 0);
        $this->trackRequest($requestId);

        $result = PatientConsultationBookingService::rescheduleBooking(
            $patientId,
            $requestId,
            999999001,
            $this->csrfToken()
        );

        $this->assertFalse(($result['success'] ?? true) === true);
        $this->assertSame($slotId, $this->requestAvailabilityId($requestId));
    }

    public function testRescheduleWithinCutoffIsRejected(): void
    {
        $doctorId = $this->createDoctor('Cutoff Reschedule Doctor');
        $patientId = $this->createPatient('Cutoff Reschedule Patient');
        [$date, $start, $end] = $this->soonSlotTimes();
        $oldSlotId = $this->createAvailability($doctorId, $start, $end, $date);
        $newSlotId = $this->createAvailability($doctorId, '15:00:00', '15:30:00', (new \DateTimeImmutable('+6 days'))->format('Y-m-d'));

        $booked = PatientConsultationBookingService::submitBooking($patientId, $oldSlotId, [
            '_token' => $this->csrfToken(),
            'reason' => 'PHPUNIT cutoff reschedule',
        ]);
        $requestId = (int) ($booked['requestId'] ?? 0);
        $this->trackRequest($requestId);
        $this->assertTrue(($booked['success'] ?? false) === true);

        $result = PatientConsultationBookingService::rescheduleBooking(
            $patientId,
            $requestId,
            $newSlotId,
            $this->csrfToken()
        );

        $this->assertFalse(($result['success'] ?? true) === true);
        $this->assertStringContainsString('within', strtolower((string) ($result['message'] ?? '')));
        $this->assertSame($oldSlotId, $this->requestAvailabilityId($requestId));
        $this->assertSame(Status::SLOT_BOOKED, $this->slotStatus($oldSlotId));
        $this->assertSame(Status::SLOT_AVAILABLE, $this->slotStatus($newSlotId));
    }

    public function testChangeCutoffHoursComesFromConfig(): void
    {
        $hours = PatientConsultationBookingService::changeCutoffHours();
        $this->assertGreaterThanOrEqual(1, $hours);
        $this->assertLessThanOrEqual(168, $hours);
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function soonSlotTimes(): array
    {
        $start = Helper::now()->modify('+45 minutes');
        $end = $start->modify('+30 minutes');
        if ($end->format('Y-m-d') !== $start->format('Y-m-d')) {
            $start = $end->setTime(0, 15, 0);
            $end = $start->modify('+30 minutes');
        }

        return [$start->format('Y-m-d'), $start->format('H:i:s'), $end->format('H:i:s')];
    }

    private function markRequestApproved(int $requestId): void
    {
        $stmt = $this->db()->prepare("UPDATE consultation_requests SET status = 'Approved' WHERE id = :id");
        $stmt->execute([':id' => $requestId]);
    }

    private function requestStatus(int $requestId): string
    {
        $stmt = $this->db()->prepare('SELECT status FROM consultation_requests WHERE id = :id');
        $stmt->execute([':id' => $requestId]);

        return (string) $stmt->fetchColumn();
    }

    private function requestAvailabilityId(int $requestId): int
    {
        $stmt = $this->db()->prepare('SELECT availability_id FROM consultation_requests WHERE id = :id');
        $stmt->execute([':id' => $requestId]);

        return (int) $stmt->fetchColumn();
    }

    private function slotStatus(int $slotId): string
    {
        $stmt = $this->db()->prepare('SELECT status FROM doctor_availability WHERE id = :id');
        $stmt->execute([':id' => $slotId]);

        return (string) $stmt->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function notificationFor(int $userId, int $requestId, string $type): ?array
    {
        $stmt = $this->db()->prepare(
            'SELECT * FROM notifications
             WHERE user_id = :user_id
               AND notification_type = :type
               AND related_entity_id = :request_id
             LIMIT 1'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':type' => $type,
            ':request_id' => $requestId,
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
