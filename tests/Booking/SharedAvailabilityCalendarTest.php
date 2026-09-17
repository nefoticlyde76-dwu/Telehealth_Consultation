<?php

declare(strict_types=1);

namespace Tests\Booking;

use App\Helpers\DoctorScheduleColor;
use App\Models\DoctorAvailability;
use App\Services\DoctorAvailabilityService;
use App\Services\PatientDirectoryService;
use Tests\Support\DatabaseTestCase;

final class SharedAvailabilityCalendarTest extends DatabaseTestCase
{
    public function testSharedRangeIncludesActiveDoctorsAndExcludesInactive(): void
    {
        $doctorA = $this->createDoctor('Shared Cal Doctor A', 'Cardiology');
        $doctorB = $this->createDoctor('Shared Cal Doctor B', 'Paediatrics');
        $inactive = $this->createDoctor('Shared Cal Inactive', 'Surgery');
        $date = (new \DateTimeImmutable('+5 days'))->format('Y-m-d');

        $slotA = $this->createAvailability($doctorA, '09:00:00', '09:30:00', $date);
        $slotB = $this->createAvailability($doctorB, '10:00:00', '10:30:00', $date);
        $slotInactive = $this->createAvailability($inactive, '11:00:00', '11:30:00', $date);

        $this->db()->prepare("UPDATE users SET status = 'inactive' WHERE id = :id")
            ->execute([':id' => $inactive]);

        $slots = DoctorAvailability::findSharedInDateRange($date, $date);
        $ids = array_map(static fn (array $slot): int => (int) ($slot['id'] ?? 0), $slots);

        $this->assertContains($slotA, $ids);
        $this->assertContains($slotB, $ids);
        $this->assertNotContains($slotInactive, $ids);

        $cardiology = DoctorAvailability::findSharedInDateRange($date, $date, [
            'specialization' => 'Cardiology',
        ]);
        $this->assertCount(1, $cardiology);
        $this->assertSame($slotA, (int) ($cardiology[0]['id'] ?? 0));
        $this->assertSame($doctorA, (int) ($cardiology[0]['doctor_id'] ?? 0));
        $this->assertSame('Cardiology', (string) ($cardiology[0]['specialization'] ?? ''));
    }

    public function testDoctorCannotEditOrDeleteAnotherDoctorsSlot(): void
    {
        $doctorA = $this->createDoctor('Shared Owner A');
        $doctorB = $this->createDoctor('Shared Owner B');
        $slotA = $this->createAvailability($doctorA, '13:00:00', '13:30:00');

        $this->assertNotNull(DoctorAvailability::findByIdForDoctor($slotA, $doctorA));
        $this->assertNull(DoctorAvailability::findByIdForDoctor($slotA, $doctorB));

        $update = DoctorAvailabilityService::updateAvailability($doctorB, $slotA, [
            '_token' => $this->csrfToken(),
            'consultation_date' => (new \DateTimeImmutable('+6 days'))->format('Y-m-d'),
            'start_time' => '14:00',
            'end_time' => '14:30',
            'notes' => 'Should not apply',
        ]);
        $this->assertFalse((bool) ($update['success'] ?? true));

        $delete = DoctorAvailabilityService::deleteAvailability($doctorB, $slotA, $this->csrfToken());
        $this->assertFalse((bool) ($delete['success'] ?? true));
        $this->assertNotNull(DoctorAvailability::findByIdForDoctor($slotA, $doctorA));
    }

    public function testWeeklyScheduleMarksOwnershipAndColours(): void
    {
        $doctorA = $this->createDoctor('Shared Week Doctor A', 'Cardiology');
        $doctorB = $this->createDoctor('Shared Week Doctor B', 'Paediatrics');
        $date = (new \DateTimeImmutable('+5 days'))->format('Y-m-d');
        $this->createAvailability($doctorA, '09:00:00', '09:30:00', $date);
        $this->createAvailability($doctorB, '09:00:00', '09:30:00', $date);

        $page = DoctorAvailabilityService::getWeeklySchedulePageData($doctorA, ['week' => $date]);
        $dayBlocks = $page['blocks'][$date] ?? [];

        $this->assertCount(2, $dayBlocks);
        $owned = array_values(array_filter(
            $dayBlocks,
            static fn (array $block): bool => !empty($block['owned'])
        ));
        $foreign = array_values(array_filter(
            $dayBlocks,
            static fn (array $block): bool => empty($block['owned'])
        ));

        $this->assertCount(1, $owned);
        $this->assertCount(1, $foreign);
        $this->assertSame($doctorA, (int) ($owned[0]['doctor_id'] ?? 0));
        $this->assertSame($doctorB, (int) ($foreign[0]['doctor_id'] ?? 0));
        $this->assertSame('Cardiology', (string) ($owned[0]['specialization'] ?? ''));
        $this->assertNotSame(
            (string) ($owned[0]['color']['accent'] ?? ''),
            (string) ($foreign[0]['color']['accent'] ?? '')
        );
        $this->assertLessThan(100, (float) ($owned[0]['width_pct'] ?? 100));
        $this->assertNotSame(
            (float) ($owned[0]['left_pct'] ?? 0),
            (float) ($foreign[0]['left_pct'] ?? 0)
        );
    }

    public function testPatientWeeklySlotsKeepBookingDataAndAddColours(): void
    {
        $doctorA = $this->createDoctor('Shared Patient Doctor A', 'Cardiology');
        $doctorB = $this->createDoctor('Shared Patient Doctor B', 'Paediatrics');
        $date = (new \DateTimeImmutable('+5 days'))->format('Y-m-d');
        $slotA = $this->createAvailability($doctorA, '15:00:00', '15:30:00', $date);
        $this->createAvailability($doctorB, '15:30:00', '16:00:00', $date);

        $page = PatientDirectoryService::getWeeklyBookingPageData(['week' => $date]);
        $dayBlocks = $page['blocks'][$date] ?? [];
        $ids = array_map(static fn (array $block): int => (int) ($block['id'] ?? 0), $dayBlocks);

        $this->assertContains($slotA, $ids);
        $this->assertGreaterThanOrEqual(2, count($dayBlocks));
        foreach ($dayBlocks as $block) {
            $this->assertNotEmpty($block['color']['accent'] ?? null);
            $this->assertSame('Available', (string) ($block['status'] ?? ''));
            $this->assertNotSame('', (string) ($block['full_name'] ?? ''));
        }
    }

    public function testThirtyMinuteSlotFillsOneGridRow(): void
    {
        $layout = DoctorAvailabilityService::layoutBlock('15:00', '15:30');

        $this->assertTrue((bool) ($layout['visible'] ?? false));
        $this->assertSame(30, (int) ($layout['duration_minutes'] ?? 0));
        $this->assertSame(14.0, (float) ($layout['top_rows'] ?? -1));
        $this->assertSame(1.0, (float) ($layout['span_rows'] ?? 0));

        $hour = DoctorAvailabilityService::layoutBlock('15:00', '16:00');
        $this->assertSame(2.0, (float) ($hour['span_rows'] ?? 0));
    }

    public function testDoctorColourAssignmentIsStable(): void
    {
        $first = DoctorScheduleColor::forDoctorId(42);
        $second = DoctorScheduleColor::forDoctorId(42);
        $other = DoctorScheduleColor::forDoctorId(43);

        $this->assertSame($first, $second);
        $this->assertNotSame($first['accent'], $other['accent']);

        $assigned = DoctorScheduleColor::assign([12, 7, 12, 3]);
        $this->assertCount(3, $assigned);
        $this->assertSame($assigned[3]['index'], DoctorScheduleColor::assign([3])[3]['index']);
    }
}
