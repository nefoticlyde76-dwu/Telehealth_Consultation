<?php

declare(strict_types=1);

namespace Tests\Booking;

use App\Models\ConsultationRequest;
use App\Services\PatientConsultationBookingService;
use Tests\Support\DatabaseTestCase;

/**
 * Converted from the booking portions of bin/test_complaint_image.php
 * and bin/test_week8_day4.php.
 */
final class PatientBookingTest extends DatabaseTestCase
{
    public function testPatientCanBookAnAvailableSlot(): void
    {
        $doctorId = $this->createDoctor('Booking Doctor');
        $patientId = $this->createPatient('Booking Patient');
        $slotId = $this->createAvailability($doctorId);

        $result = PatientConsultationBookingService::submitBooking($patientId, $slotId, [
            '_token' => $this->csrfToken(),
            'reason' => 'PHPUNIT booking for a scheduled slot',
        ]);

        $this->assertTrue(($result['success'] ?? false) === true);
        $requestId = (int) ($result['requestId'] ?? 0);
        $this->trackRequest($requestId);
        $this->assertGreaterThan(0, $requestId);

        $owned = PatientConsultationBookingService::getRequestDetail($patientId, $requestId);
        $this->assertIsArray($owned);
        $this->assertSame($patientId, (int) ($owned['patient_id'] ?? 0));
        $this->assertSame($doctorId, (int) ($owned['doctor_id'] ?? 0));
    }

    public function testAnotherPatientCannotReadTheBookingById(): void
    {
        $doctorId = $this->createDoctor('Booking Doctor B');
        $patientA = $this->createPatient('Booking Patient A');
        $patientB = $this->createPatient('Booking Patient B');
        $slotId = $this->createAvailability($doctorId, '10:00:00', '10:30:00');

        $result = PatientConsultationBookingService::submitBooking($patientA, $slotId, [
            '_token' => $this->csrfToken(),
            'reason' => 'PHPUNIT patient A booking',
        ]);
        $requestId = (int) ($result['requestId'] ?? 0);
        $this->trackRequest($requestId);

        $this->assertNull(PatientConsultationBookingService::getRequestDetail($patientB, $requestId));
        $this->assertNull(ConsultationRequest::findByIdForPatient($requestId, $patientB));
        $this->assertNull(ConsultationRequest::findByIdForDoctor($requestId, $patientB));
    }

    public function testUnassignedDoctorCannotOpenTheBooking(): void
    {
        $doctorA = $this->createDoctor('Assigned Booking Doctor');
        $doctorB = $this->createDoctor('Unassigned Booking Doctor');
        $patientId = $this->createPatient('Booking Patient C');
        $slotId = $this->createAvailability($doctorA, '11:00:00', '11:30:00');

        $result = PatientConsultationBookingService::submitBooking($patientId, $slotId, [
            '_token' => $this->csrfToken(),
            'reason' => 'PHPUNIT assigned-doctor booking',
        ]);
        $requestId = (int) ($result['requestId'] ?? 0);
        $this->trackRequest($requestId);

        $this->assertIsArray(ConsultationRequest::findByIdForDoctor($requestId, $doctorA));
        $this->assertNull(ConsultationRequest::findByIdForDoctor($requestId, $doctorB));
    }

    public function testInvalidCsrfDoesNotCreateABooking(): void
    {
        $doctorId = $this->createDoctor('CSRF Booking Doctor');
        $patientId = $this->createPatient('CSRF Booking Patient');
        $slotId = $this->createAvailability($doctorId, '13:00:00', '13:30:00');

        $result = PatientConsultationBookingService::submitBooking($patientId, $slotId, [
            '_token' => 'not-a-real-token',
            'reason' => 'PHPUNIT csrf rejected booking',
        ]);

        $this->assertFalse(($result['success'] ?? true) === true);
        $this->assertSame(0, (int) ($result['requestId'] ?? 0));
    }
}
