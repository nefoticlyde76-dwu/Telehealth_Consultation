<?php

declare(strict_types=1);

namespace Tests\Booking;

use App\Helpers\Helper;
use App\Helpers\Status;
use App\Models\ConsultationRequest;
use App\Services\AdminConsultationService;
use App\Services\DoctorConsultationService;
use App\Services\MailService;
use App\Services\NotificationService;
use App\Services\PatientConsultationBookingService;
use Tests\Support\DatabaseTestCase;

final class ConsultationNoShowTest extends DatabaseTestCase
{
    private mixed $previousMailer = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousMailer = $_ENV['MAIL_MAILER'] ?? $_SERVER['MAIL_MAILER'] ?? getenv('MAIL_MAILER') ?: '';
        $_ENV['MAIL_MAILER'] = 'array';
        $_SERVER['MAIL_MAILER'] = 'array';
        putenv('MAIL_MAILER=array');
        MailService::resetTestState();
    }

    protected function tearDown(): void
    {
        if (is_string($this->previousMailer) && $this->previousMailer !== '') {
            $_ENV['MAIL_MAILER'] = $this->previousMailer;
            $_SERVER['MAIL_MAILER'] = $this->previousMailer;
            putenv('MAIL_MAILER=' . $this->previousMailer);
        } else {
            unset($_ENV['MAIL_MAILER'], $_SERVER['MAIL_MAILER']);
            putenv('MAIL_MAILER');
        }
        MailService::resetTestState();

        parent::tearDown();
    }
    public function testDoctorCanMarkOwnPastStartApprovedConsultationAsNoShow(): void
    {
        [$doctorId, $patientId, $slotId, $requestId] = $this->bookApprovedConsultation('NoShow Doctor', 'NoShow Patient');
        $this->moveSlotIntoThePast($slotId);

        $before = ConsultationRequest::getDoctorStatusSummary($doctorId);
        $result = DoctorConsultationService::markNoShow($doctorId, $requestId, $this->csrfToken());

        $this->assertTrue(($result['success'] ?? false) === true);
        $this->assertSame(Status::NO_SHOW, $this->requestStatus($requestId));
        $this->assertNotSame(Status::SLOT_BOOKED, $this->slotStatus($slotId));
        $this->assertNotNull($this->notificationFor($patientId, $requestId, NotificationService::TYPE_NO_SHOW));
        $this->assertNotNull($this->notificationFor($doctorId, $requestId, NotificationService::TYPE_NO_SHOW));

        $after = ConsultationRequest::getDoctorStatusSummary($doctorId);
        $this->assertSame((int) $before['completed_consultations'], (int) $after['completed_consultations']);
        $this->assertSame((int) $before['no_show_consultations'] + 1, (int) $after['no_show_consultations']);

        $page = DoctorConsultationService::getConsultationPageData($doctorId, []);
        $closedIds = array_column($page['groups']['closed'] ?? [], 'id');
        $completedIds = array_column($page['groups']['completed'] ?? [], 'id');
        $this->assertContains($requestId, $closedIds);
        $this->assertNotContains($requestId, $completedIds);

        $history = PatientConsultationBookingService::getHistoryPageData($patientId, []);
        $patientClosed = array_column($history['groups']['closed'] ?? [], 'id');
        $patientCompleted = array_column($history['groups']['completed'] ?? [], 'id');
        $this->assertContains($requestId, $patientClosed);
        $this->assertNotContains($requestId, $patientCompleted);
    }

    public function testFutureApprovedConsultationCannotBeMarkedNoShow(): void
    {
        [$doctorId, , , $requestId] = $this->bookApprovedConsultation('Future NoShow Doctor', 'Future NoShow Patient');

        $result = DoctorConsultationService::markNoShow($doctorId, $requestId, $this->csrfToken());

        $this->assertFalse(($result['success'] ?? true) === true);
        $this->assertSame(Status::APPROVED, $this->requestStatus($requestId));
        $this->assertStringContainsString('start time', strtolower((string) ($result['message'] ?? '')));
    }

    public function testAnotherDoctorCannotMarkNoShow(): void
    {
        [$ownerId, , $slotId, $requestId] = $this->bookApprovedConsultation('Owner NoShow Doctor', 'Owned NoShow Patient');
        $this->moveSlotIntoThePast($slotId);
        $otherDoctorId = $this->createDoctor('Other NoShow Doctor');

        $result = DoctorConsultationService::markNoShow($otherDoctorId, $requestId, $this->csrfToken());

        $this->assertFalse(($result['success'] ?? true) === true);
        $this->assertSame(Status::APPROVED, $this->requestStatus($requestId));
        $this->assertNull($this->notificationFor($ownerId, $requestId, NotificationService::TYPE_NO_SHOW));
    }

    public function testInvalidCsrfDoesNotMarkNoShow(): void
    {
        [$doctorId, , $slotId, $requestId] = $this->bookApprovedConsultation('Csrf NoShow Doctor', 'Csrf NoShow Patient');
        $this->moveSlotIntoThePast($slotId);

        $result = DoctorConsultationService::markNoShow($doctorId, $requestId, 'not-a-real-token');

        $this->assertFalse(($result['success'] ?? true) === true);
        $this->assertSame(Status::APPROVED, $this->requestStatus($requestId));
    }

    public function testAdminCanMarkPastStartApprovedConsultationAsNoShow(): void
    {
        [, $patientId, $slotId, $requestId] = $this->bookApprovedConsultation('Admin NoShow Doctor', 'Admin NoShow Patient');
        $this->moveSlotIntoThePast($slotId);

        $result = AdminConsultationService::markNoShow($requestId, $this->csrfToken());

        $this->assertTrue(($result['success'] ?? false) === true);
        $this->assertSame(Status::NO_SHOW, $this->requestStatus($requestId));
        $this->assertNotNull($this->notificationFor($patientId, $requestId, NotificationService::TYPE_NO_SHOW));
    }

    public function testGenericAdminStatusUpdateDoesNotAcceptNoShow(): void
    {
        [, , $slotId, $requestId] = $this->bookApprovedConsultation('Generic NoShow Doctor', 'Generic NoShow Patient');
        $this->moveSlotIntoThePast($slotId);

        $result = ConsultationRequest::updateStatusForAdmin($requestId, Status::NO_SHOW);

        $this->assertFalse(($result['success'] ?? true) === true);
        $this->assertSame(Status::APPROVED, $this->requestStatus($requestId));
    }

    public function testAppointmentRemindersAreSentOnceOnHeaderLoad(): void
    {
        $doctorId = $this->createDoctor('Reminder Doctor');
        $patientId = $this->createPatient('Reminder Patient');
        [$date, $start, $end] = $this->soonSlotTimes();
        $slotId = $this->createAvailability($doctorId, $start, $end, $date);

        $booked = PatientConsultationBookingService::submitBooking($patientId, $slotId, [
            '_token' => $this->csrfToken(),
            'reason' => 'PHPUNIT appointment reminder',
        ]);
        $requestId = (int) ($booked['requestId'] ?? 0);
        $this->trackRequest($requestId);
        $this->assertTrue(($booked['success'] ?? false) === true, 'Soon slot must still be bookable');
        $this->markRequestApproved($requestId);

        NotificationService::resetTestState();
        NotificationService::maybeDispatchAppointmentReminders();
        NotificationService::resetTestState();
        NotificationService::maybeDispatchAppointmentReminders();

        $this->assertCount(1, $this->notificationsFor($patientId, $requestId, NotificationService::TYPE_REMINDER_24H));
        $this->assertCount(1, $this->notificationsFor($patientId, $requestId, NotificationService::TYPE_UPCOMING));
        $this->assertCount(1, $this->notificationsFor($doctorId, $requestId, NotificationService::TYPE_REMINDER_24H));
        $this->assertCount(1, $this->notificationsFor($doctorId, $requestId, NotificationService::TYPE_UPCOMING));
        $this->assertNotEmpty(MailService::$outbox);
    }

    /**
     * @return array{0:int,1:int,2:int,3:int}
     */
    private function bookApprovedConsultation(string $doctorName, string $patientName): array
    {
        $doctorId = $this->createDoctor($doctorName);
        $patientId = $this->createPatient($patientName);
        $slotId = $this->createAvailability($doctorId, '09:00:00', '09:30:00');

        $booked = PatientConsultationBookingService::submitBooking($patientId, $slotId, [
            '_token' => $this->csrfToken(),
            'reason' => 'PHPUNIT no-show ' . $doctorName,
        ]);
        $requestId = (int) ($booked['requestId'] ?? 0);
        $this->trackRequest($requestId);
        $this->assertTrue(($booked['success'] ?? false) === true);
        $this->markRequestApproved($requestId);

        return [$doctorId, $patientId, $slotId, $requestId];
    }

    private function moveSlotIntoThePast(int $slotId): void
    {
        $start = Helper::now()->modify('-2 hours');
        $end = $start->modify('+30 minutes');
        if ($end->format('Y-m-d') !== $start->format('Y-m-d')) {
            $end = $start->setTime(23, 59, 0);
        }

        $stmt = $this->db()->prepare(
            'UPDATE doctor_availability
             SET consultation_date = :date, start_time = :start, end_time = :end
             WHERE id = :id'
        );
        $stmt->execute([
            ':date' => $start->format('Y-m-d'),
            ':start' => $start->format('H:i:s'),
            ':end' => $end->format('H:i:s'),
            ':id' => $slotId,
        ]);
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
        $rows = $this->notificationsFor($userId, $requestId, $type);

        return $rows[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function notificationsFor(int $userId, int $requestId, string $type): array
    {
        $stmt = $this->db()->prepare(
            'SELECT * FROM notifications
             WHERE user_id = :user_id
               AND notification_type = :type
               AND related_entity_id = :request_id'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':type' => $type,
            ':request_id' => $requestId,
        ]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
