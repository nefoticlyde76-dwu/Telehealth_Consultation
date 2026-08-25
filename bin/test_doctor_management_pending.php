<?php

/**
 * Admin doctor management UI for invitation_pending (Feature 2 Step 9).
 *
 * Usage: php bin/test_doctor_management_pending.php
 *
 * Does not create invitations or send email.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Helpers\Status;
use App\Models\Doctor;
use App\Models\DoctorAvailability;
use App\Models\User;
use App\Services\AdminDoctorService;

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

function password_is_sql_null(PDO $db, int $userId): bool
{
    $stmt = $db->prepare('SELECT password IS NULL FROM users WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    return (int) $stmt->fetchColumn() === 1;
}

function create_established_doctor(int $roleId, string $email, string $status, string $employeeId): int
{
    $user = new User();
    $user->role_id = $roleId;
    $user->full_name = 'Mgmt ' . ucfirst($status) . ' Doctor';
    $user->email = $email;
    $user->password = password_hash('MgmtPass!234', PASSWORD_DEFAULT);
    $user->status = 'active';
    if (!$user->save() || $user->id === null) {
        throw new RuntimeException('Unable to create established doctor user.');
    }

    $doctor = new Doctor();
    $doctor->user_id = (int) $user->id;
    $doctor->phone = '+675 7002000';
    $doctor->gender = 'female';
    $doctor->professional_title = 'Dr.';
    $doctor->specialization = 'General Medicine';
    $doctor->employee_id = $employeeId;
    if (!$doctor->save()) {
        throw new RuntimeException('Unable to create established doctor profile.');
    }

    if ($status !== 'active' && !User::updateStatus((int) $user->id, $status) && $status !== 'deleted') {
        $db = Database::getInstance();
        $stmt = $db->prepare('UPDATE users SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $user->id]);
    }

    if ($status === 'deleted') {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE users SET status = 'deleted' WHERE id = :id");
        $stmt->execute([':id' => $user->id]);
    }

    return (int) $user->id;
}

$db = Database::getInstance();
$suffix = bin2hex(random_bytes(4));
$createdUserIds = [];
$availabilityIds = [];

try {
    $roleId = User::findRoleIdByName('doctor');
    expect_true($roleId !== null && $roleId > 0, 'Doctor role is available');

    $summaryBefore = Doctor::getManagementSummary();

    $pending = User::createInvitedDoctorUser([
        'role_id' => (int) $roleId,
        'full_name' => 'Pending Mgmt Doctor',
        'email' => 'mgmt.pending+' . $suffix . '@telehealth.test',
    ]);
    expect_true($pending !== null && $pending->id !== null, 'Pending doctor user can be created without an invitation email');
    $pendingId = (int) ($pending->id ?? 0);
    $createdUserIds[] = $pendingId;

    $pendingProfile = new Doctor();
    $pendingProfile->user_id = $pendingId;
    $pendingProfile->phone = '+675 7002101';
    $pendingProfile->gender = 'male';
    $pendingProfile->professional_title = 'Consultant';
    $pendingProfile->specialization = 'Paediatrics';
    $pendingProfile->employee_id = 'EMP-P-' . $suffix;
    expect_true($pendingProfile->save(), 'Pending doctor profile can be created');

    $activeId = create_established_doctor((int) $roleId, 'mgmt.active+' . $suffix . '@telehealth.test', 'active', 'EMP-A-' . $suffix);
    $suspendedId = create_established_doctor((int) $roleId, 'mgmt.suspended+' . $suffix . '@telehealth.test', 'suspended', 'EMP-S-' . $suffix);
    $inactiveId = create_established_doctor((int) $roleId, 'mgmt.inactive+' . $suffix . '@telehealth.test', 'inactive', 'EMP-I-' . $suffix);
    $deletedId = create_established_doctor((int) $roleId, 'mgmt.deleted+' . $suffix . '@telehealth.test', 'deleted', 'EMP-D-' . $suffix);
    $createdUserIds = array_merge($createdUserIds, [$activeId, $suspendedId, $inactiveId, $deletedId]);

    $badge = Status::badgeHtml(Status::USER_INVITATION_PENDING, Status::DOMAIN_USER);
    expect_true(str_contains($badge, 'Invitation pending'), '1. Pending doctor renders Invitation pending');
    expect_true(str_contains($badge, 'ux-badge--pending'), '1. Pending badge uses ux-badge--pending');
    expect_true(str_contains($badge, 'bi-envelope'), '1. Pending badge uses the envelope icon');

    $summaryAfter = Doctor::getManagementSummary();
    expect_true(
        (int) $summaryAfter['active_doctors'] === (int) $summaryBefore['active_doctors'] + 1,
        '2. Pending doctor is not counted as active'
    );
    expect_true(
        (int) $summaryAfter['pending_doctors'] === (int) $summaryBefore['pending_doctors'] + 1,
        '2. Pending doctor is counted separately'
    );
    expect_true(
        (int) $summaryAfter['total_doctors'] === (int) $summaryBefore['total_doctors'] + 4,
        'Total includes pending/active/suspended/inactive and excludes deleted'
    );

    $pageData = AdminDoctorService::getDoctorManagementPageData(['status' => Status::USER_INVITATION_PENDING]);
    expect_true(
        in_array(Status::USER_INVITATION_PENDING, $pageData['statusOptions'], true),
        '3. Filter options include invitation_pending'
    );
    expect_true(
        !in_array(Status::USER_INVITATION_PENDING, Status::assignableUserKeys(), true),
        '3. invitation_pending remains excluded from assignableUserKeys'
    );
    $filteredIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $pageData['doctors']);
    expect_true(in_array($pendingId, $filteredIds, true), '3. Pending doctor can be filtered as pending');
    expect_true(!in_array($activeId, $filteredIds, true), '3. Active doctor is excluded from the pending filter');

    $pendingActions = AdminDoctorService::managementActions(['status' => Status::USER_INVITATION_PENDING]);
    expect_true($pendingActions['edit'] === true, '7. Pending doctor remains editable');
    expect_true($pendingActions['reset_password'] === false, '4. Pending doctor does not show Reset Password');
    expect_true($pendingActions['activate'] === false, '5. Pending doctor does not show Activate');
    expect_true($pendingActions['deactivate'] === false, '6. Pending doctor does not show Deactivate');

    $activeActions = AdminDoctorService::managementActions(['status' => Status::USER_ACTIVE]);
    expect_true(
        $activeActions['edit'] && $activeActions['reset_password'] && $activeActions['deactivate'] && !$activeActions['activate'],
        '8. Active doctor behavior unchanged'
    );

    $suspendedActions = AdminDoctorService::managementActions(['status' => Status::USER_SUSPENDED]);
    expect_true(
        $suspendedActions['edit'] && $suspendedActions['reset_password'] && $suspendedActions['activate'] && !$suspendedActions['deactivate'],
        '9. Suspended doctor behavior unchanged'
    );

    $inactiveActions = AdminDoctorService::managementActions(['status' => Status::USER_INACTIVE]);
    expect_true(
        $inactiveActions['edit'] && $inactiveActions['reset_password'] && $inactiveActions['activate'] && !$inactiveActions['deactivate'],
        '10. Inactive doctor behavior unchanged'
    );

    expect_true(AdminDoctorService::getDoctorDetail($deletedId) === null, '11. Deleted doctors remain hidden from management detail');
    $deletedSearch = AdminDoctorService::getDoctorManagementPageData(['search' => 'mgmt.deleted+' . $suffix]);
    $deletedSearchIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $deletedSearch['doctors']);
    expect_true(!in_array($deletedId, $deletedSearchIds, true), '11. Deleted doctors remain excluded from the doctor list');

    $csrf = Csrf::generate();
    $activateAttempt = AdminDoctorService::updateDoctorStatus($pendingId, Status::USER_ACTIVE, $csrf);
    $pendingAfterActivate = User::findById($pendingId);
    expect_true(($activateAttempt['success'] ?? true) === false, '12. Direct activate attempt against pending doctor is refused');
    expect_true(
        $pendingAfterActivate !== null && $pendingAfterActivate->status === Status::USER_INVITATION_PENDING,
        '12. Pending status is unchanged after refused activate'
    );

    $resetAttempt = AdminDoctorService::resetDoctorPassword($pendingId, [
        '_token' => Csrf::generate(),
        'password' => 'ResetPass!234',
        'confirm_password' => 'ResetPass!234',
    ]);
    expect_true(($resetAttempt['success'] ?? true) === false, '13. Direct reset-password attempt against pending doctor is refused');
    expect_true(password_is_sql_null($db, $pendingId), '13. Pending password remains NULL after refused reset');

    $editResult = AdminDoctorService::updateDoctorAccount($pendingId, [
        '_token' => Csrf::generate(),
        'full_name' => 'Pending Mgmt Doctor Updated',
        'email' => 'mgmt.pending+' . $suffix . '@telehealth.test',
        'phone' => '+675 7002199',
        'gender' => 'male',
        'professional_title' => 'Consultant',
        'specialization' => 'Paediatrics',
        'employee_id' => 'EMP-P-' . $suffix,
        'status' => 'active',
    ]);
    $editedPending = User::findById($pendingId);
    $editedProfile = Doctor::findByUserId($pendingId);
    expect_true(($editResult['success'] ?? false) === true, '7. Pending doctor identity can still be edited');
    expect_true(
        $editedPending !== null
        && $editedPending->full_name === 'Pending Mgmt Doctor Updated'
        && $editedPending->status === Status::USER_INVITATION_PENDING,
        '7. Edit cannot manually activate a pending doctor'
    );
    expect_true(
        $editedProfile !== null && $editedProfile->phone === '+675 7002199' && password_is_sql_null($db, $pendingId),
        '7. Pending edit preserves professional fields and NULL password'
    );

    $slot = new DoctorAvailability();
    $slot->doctor_id = $pendingId;
    $slot->consultation_date = date('Y-m-d', strtotime('+3 days'));
    $slot->start_time = '09:00:00';
    $slot->end_time = '09:30:00';
    $slot->status = 'Available';
    expect_true($slot->save() && $slot->id !== null, 'Pending doctor can have an availability slot for booking exclusion');
    $availabilityIds[] = (int) $slot->id;

    $bookable = Doctor::findForPatientDirectory(50, 0);
    $bookableIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $bookable);
    expect_true(!in_array($pendingId, $bookableIds, true), '14. Pending doctors remain excluded from bookable doctors');

    $bookableMatch = $db->prepare(
        "SELECT COUNT(*)
         FROM doctor
         INNER JOIN users ON users.id = doctor.user_id
         INNER JOIN roles ON roles.id = users.role_id
         WHERE doctor.user_id = :id
           AND roles.name = 'doctor'
           AND users.status = 'active'"
    );
    $bookableMatch->execute([':id' => $pendingId]);
    expect_true((int) $bookableMatch->fetchColumn() === 0, '14. Pending doctors fail the active-only bookable query');
} catch (Throwable $exception) {
    expect_true(false, 'Doctor management pending tests completed without an unexpected exception: ' . $exception->getMessage());
} finally {
    foreach ($availabilityIds as $availabilityId) {
        if ($availabilityId <= 0) {
            continue;
        }
        $db->prepare('DELETE FROM doctor_availability WHERE id = :id')->execute([':id' => $availabilityId]);
    }

    foreach (array_unique($createdUserIds) as $userId) {
        if ($userId <= 0) {
            continue;
        }
        $db->prepare('DELETE FROM doctor_password_setup_tokens WHERE user_id = :id')->execute([':id' => $userId]);
        $db->prepare('DELETE FROM doctor_availability WHERE doctor_id = :id')->execute([':id' => $userId]);
        $db->prepare('DELETE FROM doctor WHERE user_id = :id')->execute([':id' => $userId]);
        $db->prepare('DELETE FROM user_sessions WHERE user_id = :id')->execute([':id' => $userId]);
        $db->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $userId]);
    }
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
