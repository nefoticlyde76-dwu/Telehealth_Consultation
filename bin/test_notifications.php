<?php

/**
 * In-app notification system checks.
 *
 * Usage: php bin/test_notifications.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Core\Database;
use App\Core\Session;
use App\Helpers\Helper;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;

$root = dirname(__DIR__);
Environment::load($root . '/.env');
date_default_timezone_set('Pacific/Port_Moresby');
Session::start();

$failed = 0;
$passed = 0;

function expect_true(bool $condition, string $label): void
{
    global $failed, $passed;
    if ($condition) {
        $passed++;
        echo "PASS  {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL  {$label}\n";
}

$catalog = NotificationService::catalog();
$expectedTypes = [
    NotificationService::TYPE_REQUEST_CREATED,
    NotificationService::TYPE_APPROVED,
    NotificationService::TYPE_ASSIGNED,
    NotificationService::TYPE_CANCELLED,
    NotificationService::TYPE_RESCHEDULED,
    NotificationService::TYPE_REJECTED,
    NotificationService::TYPE_COMPLETED,
    NotificationService::TYPE_PRESCRIPTION,
    NotificationService::TYPE_UPCOMING,
];

expect_true(
    array_keys($catalog) === $expectedTypes,
    'Catalog contains only implemented notification types'
);

foreach ($expectedTypes as $type) {
    expect_true(NotificationService::typeTitle($type) !== 'Notification', 'Type ' . $type . ' has a display title');
    expect_true(NotificationService::typeIcon($type) !== '', 'Type ' . $type . ' has an icon');
    expect_true(NotificationService::typeAction($type) !== '', 'Type ' . $type . ' has an overlay action label');
    expect_true(
        in_array(NotificationService::typeTone($type), ['ok', 'info', 'warn', 'danger'], true),
        'Type ' . $type . ' has a supported overlay tone'
    );
}

expect_true(NotificationService::typeTone(NotificationService::TYPE_APPROVED) === 'ok', 'Approval overlay uses the success tone');
expect_true(NotificationService::typeTone(NotificationService::TYPE_REJECTED) === 'danger', 'Rejection overlay uses the danger tone');
expect_true(NotificationService::typeTone(NotificationService::TYPE_UPCOMING) === 'warn', 'Upcoming overlay uses the warning tone');
expect_true(NotificationService::typeAction(NotificationService::TYPE_REQUEST_CREATED) === 'Review', 'Admin request overlay action is Review');
expect_true(NotificationService::dayGroup(date('Y-m-d H:i:s')) === 'Today', 'Current timestamps group under Today');
expect_true(NotificationService::dayGroup(date('Y-m-d H:i:s', strtotime('-1 day'))) === 'Yesterday', 'Yesterday timestamps group under Yesterday');
expect_true(NotificationService::dayGroup(date('Y-m-d H:i:s', strtotime('-5 days'))) === 'Earlier', 'Older timestamps group under Earlier');

expect_true(Helper::safeInternalPath('/admin/dashboard') === '/admin/dashboard', 'Safe return path keeps an internal dashboard path');
expect_true(Helper::safeInternalPath('/notifications?read_state=unread') === '/notifications?read_state=unread', 'Safe return path keeps an internal query string');
expect_true(Helper::safeInternalPath('https://evil.example/phish') === '/', 'Safe return path rejects an absolute URL');
expect_true(Helper::safeInternalPath('//evil.example') === '/', 'Safe return path rejects a protocol-relative URL');

$routes = (string) file_get_contents($root . '/routes/web.php');
expect_true(str_contains($routes, "post('/notifications/{id}/read'"), 'Archive/mark-read route is registered');
expect_true(str_contains($routes, "post('/notifications/mark-all-read'"), 'Mark-all-read route remains registered');

$adminNote = [
    'notification_type' => NotificationService::TYPE_REQUEST_CREATED,
    'related_entity_id' => 42,
];
$patientApproved = [
    'notification_type' => NotificationService::TYPE_APPROVED,
    'related_entity_id' => 42,
];
$patientCompleted = [
    'notification_type' => NotificationService::TYPE_COMPLETED,
    'related_entity_id' => 42,
];
$patientPrescription = [
    'notification_type' => NotificationService::TYPE_PRESCRIPTION,
    'related_entity_id' => 42,
];
$patientRejected = [
    'notification_type' => NotificationService::TYPE_REJECTED,
    'related_entity_id' => 42,
];
$doctorAssigned = [
    'notification_type' => NotificationService::TYPE_ASSIGNED,
    'related_entity_id' => 42,
];
$doctorUpcoming = [
    'notification_type' => NotificationService::TYPE_UPCOMING,
    'related_entity_id' => 42,
];

expect_true(
    NotificationService::resolveTarget($adminNote, 'admin') === '/admin/consultation-requests?selected=42',
    'Admin new-request target opens the consultation queue'
);
expect_true(
    NotificationService::resolveTarget($adminNote, 'patient') === '/notifications',
    'Patient cannot follow an admin notification target'
);
expect_true(
    NotificationService::resolveTarget($patientApproved, 'patient') === '/patient/consultation-requests/42',
    'Patient approval target opens consultation details'
);
expect_true(
    !str_contains(NotificationService::resolveTarget($patientCompleted, 'patient'), '/room'),
    'Completed consultation target does not open the Daily room'
);
expect_true(
    NotificationService::resolveTarget($patientCompleted, 'patient') === '/patient/consultation-requests/42',
    'Completed consultation target opens the patient record page'
);
expect_true(
    NotificationService::resolveTarget($patientPrescription, 'patient') === '/patient/consultation-requests/42',
    'Prescription target opens the patient consultation record'
);
expect_true(
    NotificationService::resolveTarget($patientRejected, 'patient') === '/patient/consultation-requests/42',
    'Rejected request target opens patient consultation details'
);
expect_true(
    NotificationService::resolveTarget($doctorAssigned, 'doctor') === '/doctor/consultations/42',
    'Doctor assignment target opens the consultation page'
);
expect_true(
    NotificationService::resolveTarget($doctorUpcoming, 'doctor') === '/doctor/consultations/42',
    'Doctor upcoming target opens the consultation page'
);
expect_true(
    NotificationService::resolveTarget($doctorAssigned, 'patient') === '/notifications',
    'Patient cannot follow a doctor notification target'
);

expect_true(
    NotificationService::clinicianName('John Smith') === 'Dr. Smith',
    'Clinician names are formatted as Dr. Lastname'
);
expect_true(
    NotificationService::clinicianName('Dr. Jane Doe') === 'Dr. Jane Doe',
    'Existing Dr. prefix is not duplicated'
);
expect_true(
    NotificationService::appointmentPhrase('2026-08-20', '14:00:00') === '20 August 2026 at 2:00 PM',
    'Appointment phrase uses a clear date and time'
);
expect_true(
    NotificationService::relativeTime((new DateTimeImmutable('now'))->format('Y-m-d H:i:s')) === 'Just now',
    'Relative time for a current timestamp is Just now'
);

$patientTypes = array_column(NotificationService::typeFilterOptions('patient'), 'value');
$doctorTypes = array_column(NotificationService::typeFilterOptions('doctor'), 'value');
$adminTypes = array_column(NotificationService::typeFilterOptions('admin'), 'value');
expect_true(
    !in_array(NotificationService::TYPE_REQUEST_CREATED, $patientTypes, true),
    'Patient type filter does not include admin request notifications'
);
expect_true(
    in_array(NotificationService::TYPE_APPROVED, $patientTypes, true)
        && in_array(NotificationService::TYPE_PRESCRIPTION, $patientTypes, true),
    'Patient type filter includes approval and prescription notifications'
);
expect_true(
    in_array(NotificationService::TYPE_ASSIGNED, $doctorTypes, true)
        && in_array(NotificationService::TYPE_CANCELLED, $doctorTypes, true)
        && in_array(NotificationService::TYPE_RESCHEDULED, $doctorTypes, true)
        && in_array(NotificationService::TYPE_UPCOMING, $doctorTypes, true),
    'Doctor type filter includes assigned, cancelled, rescheduled, and upcoming notifications'
);
expect_true(
    $adminTypes === [NotificationService::TYPE_REQUEST_CREATED],
    'Admin type filter only includes new consultation requests'
);

$db = Database::getInstance();
$tableReady = false;
try {
    $db->query('SELECT 1 FROM notifications LIMIT 1');
    $tableReady = true;
} catch (Throwable $exception) {
    echo "SKIP  notifications table is not installed: " . $exception->getMessage() . "\n";
}

if ($tableReady) {
    $users = $db->query(
        "SELECT users.id, roles.name AS role_name
        FROM users
        INNER JOIN roles ON roles.id = users.role_id
        WHERE users.status = 'active'
        ORDER BY users.id ASC
        LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $userA = (int) ($users[0]['id'] ?? 0);
    $userB = (int) ($users[1]['id'] ?? $userA);
    expect_true($userA > 0, 'At least one active user exists for scoped notification tests');

    $testEntityId = 900000001;
    $cleanup = $db->prepare(
        "DELETE FROM notifications
        WHERE related_entity_type = 'consultation_request'
          AND related_entity_id = :related_entity_id"
    );
    $cleanup->bindValue(':related_entity_id', $testEntityId, PDO::PARAM_INT);
    $cleanup->execute();

    $first = Notification::createOnce([
        'user_id' => $userA,
        'notification_type' => NotificationService::TYPE_APPROVED,
        'title' => 'Consultation Approved',
        'message' => 'Your consultation with Dr. Smith has been approved.',
        'related_entity_type' => Notification::ENTITY_CONSULTATION_REQUEST,
        'related_entity_id' => $testEntityId,
    ]);
    $duplicate = Notification::createOnce([
        'user_id' => $userA,
        'notification_type' => NotificationService::TYPE_APPROVED,
        'title' => 'Consultation Approved',
        'message' => 'Your consultation with Dr. Smith has been approved.',
        'related_entity_type' => Notification::ENTITY_CONSULTATION_REQUEST,
        'related_entity_id' => $testEntityId,
    ]);
    expect_true($first > 0, 'First notification insert returns an id');
    expect_true($duplicate === 0, 'Duplicate notification insert is ignored at the database layer');

    $presented = NotificationService::present(
        Notification::findByIdForUser($first, $userA) ?? [],
        'patient'
    );
    expect_true(($presented['tone'] ?? '') === 'ok', 'Presented approval notification includes overlay tone');
    expect_true(($presented['action_label'] ?? '') === 'View', 'Presented approval notification includes overlay action');
    expect_true(($presented['day_group'] ?? '') === 'Today', 'Newly created notification groups under Today');
    expect_true(NotificationService::belongsToUser($userA, $first), 'Owner is recognised for overlay archive');
    expect_true(
        $userB === $userA || NotificationService::belongsToUser($userB, $first) === false,
        'Other users cannot archive a foreign notification'
    );
    expect_true(
        Notification::existsForUser($userA, NotificationService::TYPE_APPROVED, $testEntityId),
        'Created notification exists for the recipient'
    );

    if ($userB > 0 && $userB !== $userA) {
        Notification::createOnce([
            'user_id' => $userB,
            'notification_type' => NotificationService::TYPE_APPROVED,
            'title' => 'Consultation Approved',
            'message' => 'Your consultation with Dr. Smith has been approved.',
            'related_entity_type' => Notification::ENTITY_CONSULTATION_REQUEST,
            'related_entity_id' => $testEntityId,
        ]);
        expect_true(
            Notification::findByIdForUser($first, $userB) === null,
            'User B cannot load User A notification by id'
        );
        expect_true(
            Notification::markReadForUser($first, $userB) === false,
            'User B cannot mark User A notification as read'
        );
        $ownedByA = Notification::findForUser($userA, [], 20, 0);
        $leaked = false;
        foreach ($ownedByA as $row) {
            if ((int) ($row['user_id'] ?? 0) !== $userA) {
                $leaked = true;
                break;
            }
        }
        expect_true(!$leaked, 'User A listing contains only User A notifications');
    } else {
        echo "SKIP  Second active user not available for isolation check\n";
    }

    $beforeRead = Notification::findByIdForUser($first, $userA);
    expect_true((int) ($beforeRead['is_read'] ?? 1) === 0, 'New notification starts unread');
    expect_true(Notification::markReadForUser($first, $userA), 'Owner can mark their notification as read');
    $afterRead = Notification::findByIdForUser($first, $userA);
    expect_true((int) ($afterRead['is_read'] ?? 0) === 1, 'Read flag is stored');
    expect_true(trim((string) ($afterRead['read_at'] ?? '')) !== '', 'Read timestamp is stored');
    expect_true(Notification::markReadForUser($first, $userA) === false, 'Marking an already-read notification is a no-op');

    Notification::createOnce([
        'user_id' => $userA,
        'notification_type' => NotificationService::TYPE_COMPLETED,
        'title' => 'Consultation Completed',
        'message' => 'Your consultation record is now available.',
        'related_entity_type' => Notification::ENTITY_CONSULTATION_REQUEST,
        'related_entity_id' => $testEntityId,
    ]);
    $marked = Notification::markAllReadForUser($userA);
    expect_true($marked >= 1, 'Mark all as read updates the owner unread rows');
    expect_true(Notification::countUnreadForUser($userA) === 0, 'Owner unread count is zero after mark all as read');

    $header = NotificationService::getHeaderData($userA, 'patient');
    expect_true(count($header['recent']) <= NotificationService::HEADER_LIMIT, 'Header recent list is capped');
    expect_true(isset($header['unread_count']), 'Header payload includes unread count');
    expect_true(isset($header['total_count']), 'Header payload includes total count');

    $opened = NotificationService::openForUser($userA, 'patient', $first);
    expect_true(is_array($opened), 'Opening a owned notification succeeds');
    expect_true(
        ($opened['target'] ?? '') === '/patient/consultation-requests/' . $testEntityId,
        'Opening resolves to the patient consultation page'
    );
    expect_true(NotificationService::openForUser($userB > 0 ? $userB : 0, 'patient', $first) === null || $userB === $userA, 'Cross-user open is denied when a second user exists');

    $page = NotificationService::getPageData($userA, 'patient', ['read_state' => 'unread']);
    expect_true(isset($page['pagination']['total_items']), 'Notifications page reports pagination totals');

    expect_true(Notification::markUnreadForUser($first, $userA), 'Owner can mark a notification unread');
    $unreadAgain = Notification::findByIdForUser($first, $userA);
    expect_true((int) ($unreadAgain['is_read'] ?? 1) === 0, 'Unread flag is restored after mark unread');

    if ($userB > 0 && $userB !== $userA) {
        expect_true(
            Notification::deleteForUser($first, $userB) === false,
            'User B cannot delete User A notification'
        );
        expect_true(
            Notification::findByIdForUser($first, $userA) !== null,
            'Owner notification remains after a foreign delete attempt'
        );
        expect_true(
            Notification::deleteManyForUser($userB, [$first]) === 0,
            'Bulk delete cannot remove another user notification'
        );
    }

    expect_true(Notification::deleteForUser($first, $userA), 'Owner can delete their own notification');
    expect_true(Notification::findByIdForUser($first, $userA) === null, 'Deleted notification is no longer listed for the owner');

    $clearEntity = $testEntityId + 900000;
    $inboxBefore = Notification::countForUser($userA);
    $extraId = Notification::createOnce([
        'user_id' => $userA,
        'notification_type' => NotificationService::TYPE_REJECTED,
        'title' => 'Consultation Rejected',
        'message' => 'A test notice used for scoped delete.',
        'related_entity_type' => Notification::ENTITY_CONSULTATION_REQUEST,
        'related_entity_id' => $clearEntity,
    ]);
    $auditBeforeClear = (int) $db->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
    expect_true($extraId > 0, 'An extra owner notification can be created for scoped delete');
    expect_true(Notification::deleteManyForUser($userA, [$extraId]) >= 1, 'Owner bulk delete removes selected inbox rows');
    expect_true(Notification::countForUser($userA) === $inboxBefore, 'Bulk delete does not remove unrelated owner notifications');
    expect_true(Notification::deleteAllForUser(2147483000) === 0, 'Clear all for an unknown user deletes nothing');
    $auditAfterClear = (int) $db->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
    expect_true($auditAfterClear >= $auditBeforeClear, 'Deleting notifications does not delete audit logs');

    $adminIds = User::findActiveAdminIds();
    expect_true(is_array($adminIds), 'Active administrator ids can be resolved');

    $sample = $db->query(
        "SELECT id, patient_id, doctor_id, status
        FROM consultation_requests
        ORDER BY id DESC
        LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC) ?: null;

    if (is_array($sample) && (int) ($sample['id'] ?? 0) > 0) {
        $sampleId = (int) $sample['id'];
        $patientId = (int) ($sample['patient_id'] ?? 0);
        $doctorId = (int) ($sample['doctor_id'] ?? 0);

        $maxIdBefore = (int) $db->query('SELECT COALESCE(MAX(id), 0) FROM notifications')->fetchColumn();

        $countType = static function (string $type, int $entityId) use ($db): int {
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM notifications
                WHERE notification_type = :type
                  AND related_entity_id = :id"
            );
            $stmt->execute([':type' => $type, ':id' => $entityId]);

            return (int) $stmt->fetchColumn();
        };

        $createdBefore = $countType(NotificationService::TYPE_REQUEST_CREATED, $sampleId);
        NotificationService::notifyConsultationRequestCreated($sampleId);
        NotificationService::notifyConsultationRequestCreated($sampleId);
        $createdAfter = $countType(NotificationService::TYPE_REQUEST_CREATED, $sampleId);
        if ($adminIds !== []) {
            $expectedAdmins = max($createdBefore, count($adminIds));
            expect_true($createdAfter === $expectedAdmins, 'New request notifies each active administrator once');
        } else {
            expect_true($createdAfter === $createdBefore, 'No admin notifications are created when no active administrators exist');
        }

        $approvedBefore = $countType(NotificationService::TYPE_APPROVED, $sampleId);
        $assignedBefore = $countType(NotificationService::TYPE_ASSIGNED, $sampleId);
        NotificationService::notifyConsultationApproved($sampleId);
        NotificationService::notifyConsultationApproved($sampleId);
        expect_true(
            $countType(NotificationService::TYPE_APPROVED, $sampleId) === max($approvedBefore, $patientId > 0 ? 1 : 0),
            'Approval notifies the patient once'
        );
        expect_true(
            $countType(NotificationService::TYPE_ASSIGNED, $sampleId) === max($assignedBefore, $doctorId > 0 ? 1 : 0),
            'Approval notifies the assigned doctor once'
        );

        $rejectedBefore = $countType(NotificationService::TYPE_REJECTED, $sampleId);
        NotificationService::notifyConsultationRejected($sampleId);
        NotificationService::notifyConsultationRejected($sampleId);
        expect_true(
            $countType(NotificationService::TYPE_REJECTED, $sampleId) === max($rejectedBefore, $patientId > 0 ? 1 : 0),
            'Rejection notifies the patient once'
        );

        $completedBefore = $countType(NotificationService::TYPE_COMPLETED, $sampleId);
        NotificationService::notifyConsultationCompleted($sampleId);
        NotificationService::notifyConsultationCompleted($sampleId);
        expect_true(
            $countType(NotificationService::TYPE_COMPLETED, $sampleId) === max($completedBefore, $patientId > 0 ? 1 : 0),
            'Completion notifies the patient once'
        );

        $prescriptionBefore = $countType(NotificationService::TYPE_PRESCRIPTION, $sampleId);
        NotificationService::notifyPrescriptionCreated($sampleId);
        NotificationService::notifyPrescriptionCreated($sampleId);
        expect_true(
            $countType(NotificationService::TYPE_PRESCRIPTION, $sampleId) === max($prescriptionBefore, $patientId > 0 ? 1 : 0),
            'Prescription notifies the patient once'
        );

        if ($patientId > 0) {
            $foreign = Notification::findForUser($patientId, [], 50, 0);
            $leaked = false;
            foreach ($foreign as $row) {
                if ((int) ($row['user_id'] ?? 0) !== $patientId) {
                    $leaked = true;
                    break;
                }
            }
            expect_true(!$leaked, 'Patient notification listing stays scoped to that patient');
        }

        $db->prepare(
            'DELETE FROM notifications
             WHERE id > :max_id
               AND related_entity_id = :entity_id'
        )->execute([
            ':max_id' => $maxIdBefore,
            ':entity_id' => $sampleId,
        ]);
    } else {
        echo "SKIP  No consultation request available for trigger tests\n";
    }

    $cleanup->execute();
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
