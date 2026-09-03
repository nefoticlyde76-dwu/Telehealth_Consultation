<?php

/**
 * Slot expiration behaviour for unbooked doctor availability.
 *
 * Usage: php bin/test_slot_expiration.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Core\Database;
use App\Helpers\Helper;
use App\Helpers\Status;
use App\Models\DoctorAvailability;
use App\Services\SlotExpirationService;

Environment::load(dirname(__DIR__) . '/.env');

$appDefaultTimezone = trim((string) (Environment::get('APP_TIMEZONE') ?? ''));
if ($appDefaultTimezone === '') {
    $appDefaultTimezone = 'Pacific/Port_Moresby';
}
if (!@date_default_timezone_set($appDefaultTimezone)) {
    date_default_timezone_set('Pacific/Port_Moresby');
}

$failed = 0;
$passed = 0;

function expect_true(bool $condition, string $label): void
{
    global $failed, $passed;
    if ($condition) {
        $passed++;
        echo "  PASS  {$label}\n";
        return;
    }

    $failed++;
    echo "  FAIL  {$label}\n";
}

$db = Database::getInstance();
$now = Helper::now();
$suffix = 'exp' . $now->format('His') . bin2hex(random_bytes(2));
$createdUserIds = [];
$createdSlotIds = [];

$roleStmt = $db->query("SELECT id FROM roles WHERE name = 'doctor' LIMIT 1");
$doctorRoleId = (int) $roleStmt->fetchColumn();
expect_true($doctorRoleId > 0, 'Doctor role exists');

$createDoctor = static function (string $email, string $name) use ($db, $doctorRoleId, &$createdUserIds): int {
    $user = $db->prepare(
        "INSERT INTO users (role_id, full_name, email, password, status)
         VALUES (:role_id, :full_name, :email, :password, 'active')"
    );
    $user->execute([
        ':role_id' => $doctorRoleId,
        ':full_name' => $name,
        ':email' => $email,
        ':password' => password_hash('TestPass123!', PASSWORD_DEFAULT),
    ]);
    $userId = (int) $db->lastInsertId();
    $createdUserIds[] = $userId;

    $profile = $db->prepare(
        "INSERT INTO doctor (user_id, professional_title, specialization)
         VALUES (:user_id, 'Medical Officer', 'General Practice')"
    );
    $profile->execute([':user_id' => $userId]);

    return $userId;
};

$createSlot = static function (int $doctorId, string $date, string $start, string $end, string $status = 'Available') use ($db, &$createdSlotIds): int {
    $slot = new DoctorAvailability();
    $slot->doctor_id = $doctorId;
    $slot->consultation_date = $date;
    $slot->start_time = $start;
    $slot->end_time = $end;
    $slot->notes = 'Expiration test slot';
    $slot->status = $status;
    $slot->save();
    $id = (int) ($slot->id ?? 0);
    $createdSlotIds[] = $id;

    return $id;
};

$doctorA = $createDoctor('slot.exp.a+' . $suffix . '@telehealth.test', 'Expiration Doctor A');
$doctorB = $createDoctor('slot.exp.b+' . $suffix . '@telehealth.test', 'Expiration Doctor B');

$futureDate = $now->modify('+2 days')->format('Y-m-d');
$today = $now->format('Y-m-d');
$yesterday = $now->modify('-1 day')->format('Y-m-d');

$endedAt = $now->modify('-60 minutes');
$exactEndAt = $now;
$exactStartAt = $now->modify('-30 minutes');
$openStart = $now->modify('+30 minutes')->format('H:i:s');
$openEnd = $now->modify('+60 minutes')->format('H:i:s');

$futureOpenId = $createSlot($doctorA, $futureDate, '09:00:00', '09:30:00');
$todayOpenId = $createSlot($doctorA, $today, $openStart, $openEnd);
$todayEndedId = $createSlot(
    $doctorA,
    $endedAt->format('Y-m-d'),
    $endedAt->modify('-30 minutes')->format('H:i:s'),
    $endedAt->format('H:i:s')
);
$exactlyEndedId = $createSlot(
    $doctorA,
    $exactEndAt->format('Y-m-d'),
    $exactStartAt->format('H:i:s'),
    $exactEndAt->format('H:i:s')
);
$yesterdayOpenId = $createSlot($doctorA, $yesterday, '10:00:00', '10:30:00');
$doctorBEndedId = $createSlot($doctorB, $yesterday, '11:00:00', '11:30:00');
$bookedPastId = $createSlot($doctorA, $yesterday, '14:00:00', '14:30:00', 'Booked');

expect_true($futureOpenId > 0 && $todayEndedId > 0 && $bookedPastId > 0, 'Test slots were created');
expect_true(SlotExpirationService::hasEnded($endedAt->format('Y-m-d'), $endedAt->format('H:i:s')), 'Ended slot is detected from application clock');
expect_true(SlotExpirationService::hasEnded($exactEndAt->format('Y-m-d'), $exactEndAt->format('H:i:s')), 'Slot at exact end time is treated as expired');
expect_true(!SlotExpirationService::hasEnded($today, $openEnd), 'Open slot has not ended');
expect_true(!SlotExpirationService::hasEnded($futureDate, '09:30:00'), 'Future slot has not ended');

SlotExpirationService::resetRequestGuard();
$swept = SlotExpirationService::sweep();
expect_true($swept >= 4, 'Sweep marks expired unbooked slots across doctors');

$loadStatus = static function (int $id) use ($db): ?string {
    $stmt = $db->prepare('SELECT status FROM doctor_availability WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? (string) $row['status'] : null;
};

expect_true($loadStatus($futureOpenId) === Status::SLOT_AVAILABLE, 'Future unbooked slot remains Available');
expect_true($loadStatus($todayOpenId) === Status::SLOT_AVAILABLE, 'Today open slot remains Available');
expect_true($loadStatus($bookedPastId) === Status::SLOT_BOOKED, 'Past booked appointment is not deleted');
expect_true($loadStatus($todayEndedId) === null, 'Ended unbooked slot is removed from the table');
expect_true($loadStatus($exactlyEndedId) === null, 'Slot ending exactly now is removed');
expect_true($loadStatus($yesterdayOpenId) === null, 'Yesterday unbooked slot is removed');
expect_true($loadStatus($doctorBEndedId) === null, 'Expired slots for a second doctor are removed');

expect_true(
    DoctorAvailability::findAvailableSlotForPatients($todayEndedId) === null,
    'Patient lookup cannot load an expired slot'
);
expect_true(
    DoctorAvailability::findAvailableSlotForPatients($futureOpenId) !== null,
    'Patient lookup still loads a future Available slot'
);

$lateEndedAt = Helper::now()->modify('-5 minutes');
$lateEndedId = $createSlot(
    $doctorA,
    $lateEndedAt->format('Y-m-d'),
    $lateEndedAt->modify('-30 minutes')->format('H:i:s'),
    $lateEndedAt->format('H:i:s')
);
expect_true(
    DoctorAvailability::findAvailableSlotForPatients($lateEndedId) === null,
    'Patient booking lookup rejects a slot whose end time has already passed'
);
expect_true(
    SlotExpirationService::slotHasEnded([
        'consultation_date' => $lateEndedAt->format('Y-m-d'),
        'end_time' => $lateEndedAt->format('H:i:s'),
    ]),
    'Booking validation treats a just-ended slot as expired'
);

$doctorSlots = DoctorAvailability::findForDoctor($doctorA, ['sort' => 'earliest'], 50, 0);
$doctorSlotIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $doctorSlots);
expect_true(in_array($futureOpenId, $doctorSlotIds, true), 'Doctor table still lists future slots');
expect_true(in_array($bookedPastId, $doctorSlotIds, true), 'Doctor table still lists booked appointments');
expect_true(!in_array($todayEndedId, $doctorSlotIds, true), 'Doctor table hides expired unbooked slots');

$patientSlots = DoctorAvailability::findAvailableForPatients([], 50, 0);
$patientIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $patientSlots);
expect_true(in_array($futureOpenId, $patientIds, true), 'Patient search still returns future slots');
expect_true(!in_array($todayEndedId, $patientIds, true), 'Patient search hides expired slots');
expect_true(!in_array($yesterdayOpenId, $patientIds, true), 'Patient search hides past-date slots');

$cleanup = static function () use ($db, &$createdSlotIds, &$createdUserIds): void {
    foreach ($createdSlotIds as $slotId) {
        if ($slotId > 0) {
            $db->prepare('DELETE FROM doctor_availability WHERE id = :id')->execute([':id' => $slotId]);
        }
    }

    foreach ($createdUserIds as $userId) {
        $db->prepare('DELETE FROM doctor_availability WHERE doctor_id = :id')->execute([':id' => $userId]);
        $db->prepare('DELETE FROM doctor WHERE user_id = :id')->execute([':id' => $userId]);
        $db->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $userId]);
    }
};

try {
    $cleanup();
} catch (Throwable $exception) {
    echo 'Cleanup warning: ' . $exception->getMessage() . "\n";
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
