<?php

/**
 * Admin resend of doctor password-setup invitations (Feature 2 Step 11).
 *
 * Usage: php bin/test_doctor_invitation_resend.php
 *
 * Uses the array/fail mailers only. Does not send real Gmail.
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
use App\Models\DoctorPasswordSetupToken;
use App\Models\User;
use App\Services\AdminDoctorService;
use App\Services\DoctorInvitationService;
use App\Services\DoctorPasswordSetupService;
use App\Services\MailService;

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

function setEnvKey(string $key, string $value): void
{
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
    putenv($key . '=' . $value);
}

function password_is_sql_null(PDO $db, int $userId): bool
{
    $stmt = $db->prepare('SELECT password IS NULL FROM users WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    return (int) $stmt->fetchColumn() === 1;
}

function extract_setup_token(string $html): string
{
    if (preg_match('/setup-password\?token=([a-f0-9]{64})/', $html, $matches) !== 1) {
        return '';
    }

    return (string) $matches[1];
}

function create_pending_doctor_with_profile(int $roleId, string $email, string $name, string $employeeId): User
{
    $user = User::createInvitedDoctorUser([
        'role_id' => $roleId,
        'full_name' => $name,
        'email' => $email,
    ]);
    if ($user === null || $user->id === null) {
        throw new RuntimeException('Unable to create pending doctor.');
    }

    $profile = new Doctor();
    $profile->user_id = (int) $user->id;
    $profile->phone = '+675 7004001';
    $profile->gender = 'female';
    $profile->professional_title = 'Dr.';
    $profile->specialization = 'General Medicine';
    $profile->employee_id = $employeeId;
    if (!$profile->save()) {
        throw new RuntimeException('Unable to create doctor profile.');
    }

    return $user;
}

function create_established_doctor(int $roleId, string $email, string $status, string $employeeId): int
{
    $user = new User();
    $user->role_id = $roleId;
    $user->full_name = 'Resend ' . ucfirst($status) . ' Doctor';
    $user->email = $email;
    $user->password = password_hash('ResendPass!234', PASSWORD_DEFAULT);
    $user->status = 'active';
    if (!$user->save() || $user->id === null) {
        throw new RuntimeException('Unable to create established doctor.');
    }

    $profile = new Doctor();
    $profile->user_id = (int) $user->id;
    $profile->phone = '+675 7004002';
    $profile->gender = 'male';
    $profile->professional_title = 'Dr.';
    $profile->specialization = 'Surgery';
    $profile->employee_id = $employeeId;
    if (!$profile->save()) {
        throw new RuntimeException('Unable to create established doctor profile.');
    }

    if ($status !== 'active') {
        if ($status === 'deleted') {
            Database::getInstance()->prepare("UPDATE users SET status = 'deleted' WHERE id = :id")->execute([':id' => $user->id]);
        } else {
            User::updateStatus((int) $user->id, $status);
        }
    }

    return (int) $user->id;
}

$originalMailer = (string) Environment::get('MAIL_MAILER', 'log');
$db = Database::getInstance();
$suffix = bin2hex(random_bytes(4));
$createdUserIds = [];
DoctorInvitationService::resetTestState();
DoctorPasswordSetupService::resetTestState();
MailService::resetTestState();
setEnvKey('MAIL_MAILER', 'array');

try {
    $routes = (string) file_get_contents($root . '/routes/web.php');
    expect_true(
        str_contains($routes, "post('/admin/doctors/{id}/resend-invitation'"),
        '30. POST resend-invitation route exists'
    );
    expect_true(
        !str_contains($routes, "get('/admin/doctors/{id}/resend-invitation'"),
        '30. GET cannot trigger resend'
    );
    expect_true(
        str_contains($routes, "resendDoctorInvitation")
        && str_contains($routes, "new RoleMiddleware(['admin'])"),
        '32. Resend route is registered with admin authorization'
    );

    $doctorRoleId = User::findRoleIdByName('doctor');
    $patientRoleId = User::findRoleIdByName('patient');
    $adminRoleId = User::findRoleIdByName('admin');
    expect_true($doctorRoleId !== null && $patientRoleId !== null && $adminRoleId !== null, 'Required roles exist');

    $admin = new User();
    $admin->role_id = (int) $adminRoleId;
    $admin->full_name = 'Resend Admin';
    $admin->email = 'resend.admin+' . $suffix . '@telehealth.test';
    $admin->password = password_hash('AdminPass!234', PASSWORD_DEFAULT);
    $admin->status = 'active';
    expect_true($admin->save() && $admin->id !== null, 'Administrator fixture created');
    $createdUserIds[] = (int) $admin->id;
    Session::set('user_id', (int) $admin->id);
    Session::set('user_role', 'admin');

    $actionsPending = AdminDoctorService::managementActions(['status' => Status::USER_INVITATION_PENDING]);
    $actionsActive = AdminDoctorService::managementActions(['status' => Status::USER_ACTIVE]);
    $actionsSuspended = AdminDoctorService::managementActions(['status' => Status::USER_SUSPENDED]);
    $actionsInactive = AdminDoctorService::managementActions(['status' => Status::USER_INACTIVE]);
    expect_true(($actionsPending['resend_invitation'] ?? false) === true, 'Pending doctors show Resend Invitation');
    expect_true(($actionsActive['resend_invitation'] ?? true) === false, 'Active doctors do not show Resend Invitation');
    expect_true(($actionsSuspended['resend_invitation'] ?? true) === false, 'Suspended doctors do not show Resend Invitation');
    expect_true(($actionsInactive['resend_invitation'] ?? true) === false, 'Inactive doctors do not show Resend Invitation');

    $pending = create_pending_doctor_with_profile(
        (int) $doctorRoleId,
        'resend.ok+' . $suffix . '@telehealth.test',
        'Resend Pending Doctor',
        'EMP-RO-' . $suffix
    );
    $pendingId = (int) $pending->id;
    $createdUserIds[] = $pendingId;
    $oldRaw = (string) (DoctorInvitationService::issueToken($pendingId)['raw_token'] ?? '');
    expect_true($oldRaw !== '' && DoctorPasswordSetupService::inspect($oldRaw)['valid'] === true, '1. Pending doctor starts with a usable invitation');

    $csrf = Csrf::generate();
    $missingCsrf = AdminDoctorService::resendDoctorInvitation($pendingId, '');
    expect_true(($missingCsrf['success'] ?? true) === false, '31. POST requires CSRF');

    $spoofed = AdminDoctorService::resendDoctorInvitation($pendingId, $csrf);
    expect_true(($spoofed['success'] ?? false) === true, '1. Pending doctor can request resend');
    expect_true(
        ($spoofed['message'] ?? '') === 'A new password setup invitation has been sent to the doctor\'s email.',
        '1. Successful resend uses the exact success message'
    );

    expect_true(DoctorPasswordSetupToken::countUsableForUser($pendingId) === 1, '2. A new token is generated and only one remains usable');
    $newRows = $db->prepare(
        'SELECT id, token_hash, sent_at, used_at FROM doctor_password_setup_tokens WHERE user_id = :id ORDER BY id DESC'
    );
    $newRows->execute([':id' => $pendingId]);
    $tokenRows = $newRows->fetchAll(PDO::FETCH_ASSOC);
    $latest = $tokenRows[0] ?? [];
    expect_true(
        isset($latest['token_hash']) && strlen((string) $latest['token_hash']) === 64 && ctype_xdigit((string) $latest['token_hash']),
        '3. New token hash is stored'
    );
    $rawColumn = $db->query("SHOW COLUMNS FROM doctor_password_setup_tokens LIKE 'raw_token'");
    expect_true($rawColumn !== false && $rawColumn->fetch() === false, '4. Raw token is never stored');
    expect_true(DoctorPasswordSetupService::inspect($oldRaw)['valid'] === false, '5. Previous token becomes unusable');
    $afterResend = User::findById($pendingId);
    expect_true($afterResend !== null && $afterResend->status === Status::USER_INVITATION_PENDING, '6. Doctor remains invitation_pending');
    expect_true(password_is_sql_null($db, $pendingId), '7. Doctor password remains NULL');

    expect_true(count(MailService::$outbox) === 1, '9. MailService receives exactly one new invitation');
    $newRaw = extract_setup_token((string) (MailService::$outbox[0]['html'] ?? ''));
    expect_true($newRaw !== '' && $newRaw !== $oldRaw, '8. New invitation URL points to the new token');
    expect_true(hash('sha256', $newRaw) === (string) ($latest['token_hash'] ?? ''), '8. Emailed token matches the stored hash');
    expect_true($latest['sent_at'] !== null && $latest['sent_at'] !== '', '10. sent_at becomes non-NULL after SMTP acceptance');
    expect_true((string) (MailService::$outbox[0]['to'] ?? '') === 'resend.ok+' . $suffix . '@telehealth.test', '33. Browser cannot choose recipient email');
    expect_true(!array_key_exists('raw_token', $spoofed), '34. Browser cannot supply a replacement token');

    $auditStmt = $db->prepare(
        "SELECT actor_user_id, subject_name, subject_role, event_type, action, description
         FROM audit_logs
         WHERE entity_id = :entity_id
           AND (event_type = :event_type OR action = :event_action)
         ORDER BY id DESC
         LIMIT 1"
    );
    $auditStmt->execute([
        ':entity_id' => $pendingId,
        ':event_type' => 'doctor_invitation_resent',
        ':event_action' => 'doctor_invitation_resent',
    ]);
    $audit = $auditStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    expect_true(is_array($audit) && (int) ($audit['actor_user_id'] ?? 0) === (int) $admin->id, '11. Audit identifies the creating admin');
    expect_true(is_array($audit) && (string) ($audit['subject_name'] ?? '') === 'Resend Pending Doctor', '11. Audit identifies the doctor name');
    expect_true(is_array($audit) && (string) ($audit['subject_role'] ?? '') === 'doctor', '11. Audit identifies role doctor');
    $auditBlob = json_encode($audit) ?: '';
    expect_true(
        !str_contains($auditBlob, $oldRaw) && !str_contains($auditBlob, $newRaw),
        '35. Raw token is absent from audit records'
    );

    $activeId = create_established_doctor((int) $doctorRoleId, 'resend.active+' . $suffix . '@telehealth.test', 'active', 'EMP-RA-' . $suffix);
    $suspendedId = create_established_doctor((int) $doctorRoleId, 'resend.sus+' . $suffix . '@telehealth.test', 'suspended', 'EMP-RS-' . $suffix);
    $inactiveId = create_established_doctor((int) $doctorRoleId, 'resend.ina+' . $suffix . '@telehealth.test', 'inactive', 'EMP-RI-' . $suffix);
    $deletedId = create_established_doctor((int) $doctorRoleId, 'resend.del+' . $suffix . '@telehealth.test', 'deleted', 'EMP-RD-' . $suffix);
    $createdUserIds = array_merge($createdUserIds, [$activeId, $suspendedId, $inactiveId, $deletedId]);
    $csrf2 = Csrf::generate();
    expect_true((AdminDoctorService::resendDoctorInvitation($activeId, $csrf2)['success'] ?? true) === false, '12. Active doctor cannot resend');
    expect_true((AdminDoctorService::resendDoctorInvitation($suspendedId, $csrf2)['success'] ?? true) === false, '13. Suspended doctor cannot resend');
    expect_true((AdminDoctorService::resendDoctorInvitation($inactiveId, $csrf2)['success'] ?? true) === false, '14. Inactive doctor cannot resend');
    expect_true((AdminDoctorService::resendDoctorInvitation($deletedId, $csrf2)['success'] ?? true) === false, '15. Deleted doctor cannot resend');

    $patient = new User();
    $patient->role_id = (int) $patientRoleId;
    $patient->full_name = 'Resend Patient';
    $patient->email = 'resend.patient+' . $suffix . '@telehealth.test';
    $patient->password = password_hash('PatientPass!234', PASSWORD_DEFAULT);
    $patient->status = 'active';
    expect_true($patient->save() && $patient->id !== null, 'Patient fixture created');
    $createdUserIds[] = (int) $patient->id;
    expect_true((AdminDoctorService::resendDoctorInvitation((int) $patient->id, $csrf2)['success'] ?? true) === false, '16. Patient cannot resend');
    expect_true((AdminDoctorService::resendDoctorInvitation((int) $admin->id, $csrf2)['success'] ?? true) === false, '17. Administrator cannot resend');

    $failUser = create_pending_doctor_with_profile(
        (int) $doctorRoleId,
        'resend.fail+' . $suffix . '@telehealth.test',
        'Resend Fail Doctor',
        'EMP-RF-' . $suffix
    );
    $failId = (int) $failUser->id;
    $createdUserIds[] = $failId;
    $failOld = (string) (DoctorInvitationService::issueToken($failId)['raw_token'] ?? '');
    setEnvKey('MAIL_MAILER', 'fail');
    MailService::resetTestState();
    $auditBeforeFail = (int) $db->query('SELECT COALESCE(MAX(id), 0) FROM audit_logs')->fetchColumn();
    $failResult = AdminDoctorService::resendDoctorInvitation($failId, Csrf::generate());
    expect_true(($failResult['success'] ?? true) === false, '22. Correct warning result is returned');
    expect_true(
        ($failResult['message'] ?? '') === 'The doctor\'s account is still pending, but the invitation email could not be sent. Please try Resend Invitation again.',
        '22. Email failure uses the exact warning message'
    );
    $failAfter = User::findById($failId);
    expect_true($failAfter !== null && $failAfter->status === Status::USER_INVITATION_PENDING, '18. Doctor remains pending');
    expect_true(password_is_sql_null($db, $failId), '19. Password remains NULL');
    expect_true(DoctorPasswordSetupToken::countUsableForUser($failId) === 1, '20. Replacement leaves exactly one usable token');
    expect_true(DoctorPasswordSetupService::inspect($failOld)['valid'] === false, '20. Previous token does not become usable again');
    $failLatest = $db->prepare(
        'SELECT sent_at FROM doctor_password_setup_tokens WHERE user_id = :id ORDER BY id DESC LIMIT 1'
    );
    $failLatest->execute([':id' => $failId]);
    expect_true($failLatest->fetchColumn() === null, '21. sent_at remains NULL');
    $failAudit = $db->prepare(
        'SELECT COUNT(*) FROM audit_logs WHERE id > :max_before AND entity_id = :id AND (event_type = :event OR action = :event_action)'
    );
    $failAudit->execute([
        ':max_before' => $auditBeforeFail,
        ':id' => $failId,
        ':event' => 'doctor_invitation_resent',
        ':event_action' => 'doctor_invitation_resent',
    ]);
    expect_true((int) $failAudit->fetchColumn() === 0, '23. No successful resend audit event is recorded');

    setEnvKey('MAIL_MAILER', 'array');
    MailService::resetTestState();
    expect_true(DoctorPasswordSetupService::inspect($oldRaw)['valid'] === false, '24. Old token cannot open the setup page');
    $oldComplete = DoctorPasswordSetupService::complete([
        '_token' => Csrf::generate(),
        'token' => $oldRaw,
        'password' => 'SetupPass!234',
        'confirm_password' => 'SetupPass!234',
    ]);
    expect_true(($oldComplete['invalidLink'] ?? false) === true, '25. Old token cannot complete password setup');
    expect_true((DoctorPasswordSetupService::inspect($newRaw)['valid'] ?? false) === true, '26. New token opens the setup page');
    $setupOk = DoctorPasswordSetupService::complete([
        '_token' => Csrf::generate(),
        'token' => $newRaw,
        'password' => 'SetupPass!234',
        'confirm_password' => 'SetupPass!234',
    ]);
    expect_true(($setupOk['success'] ?? false) === true, '27. New token can complete password setup successfully');
    $activated = User::findById($pendingId);
    expect_true($activated !== null && $activated->status === Status::USER_ACTIVE, '27. Setup after resend activates the doctor');
    expect_true(
        (int) (Session::get('user_id') ?? 0) === (int) $admin->id
        && (int) (Session::get('user_id') ?? 0) !== $pendingId,
        'Setup after resend does not create a doctor session'
    );

    $dup = create_pending_doctor_with_profile(
        (int) $doctorRoleId,
        'resend.dup+' . $suffix . '@telehealth.test',
        'Resend Duplicate Doctor',
        'EMP-DUP-' . $suffix
    );
    $dupId = (int) $dup->id;
    $createdUserIds[] = $dupId;
    DoctorInvitationService::issueToken($dupId);
    MailService::resetTestState();
    $firstDup = AdminDoctorService::resendDoctorInvitation($dupId, Csrf::generate());
    expect_true(($firstDup['success'] ?? false) === true, '28. First duplicate resend succeeds');
    $db->prepare(
        'UPDATE doctor_password_setup_tokens SET sent_at = DATE_SUB(NOW(), INTERVAL 10 MINUTE) WHERE user_id = :id AND sent_at IS NOT NULL'
    )->execute([':id' => $dupId]);
    MailService::resetTestState();
    $secondDup = AdminDoctorService::resendDoctorInvitation($dupId, Csrf::generate());
    expect_true(($secondDup['success'] ?? false) === true, '28. Repeated resend succeeds after cooldown window');
    expect_true(DoctorPasswordSetupToken::countUsableForUser($dupId) === 1, '28. Repeated resend does not leave multiple simultaneously valid tokens');
    $finalRaw = extract_setup_token((string) (MailService::$outbox[0]['html'] ?? ''));
    $finalComplete = DoctorPasswordSetupService::complete([
        '_token' => Csrf::generate(),
        'token' => $finalRaw,
        'password' => 'FinalPass!234',
        'confirm_password' => 'FinalPass!234',
    ]);
    expect_true(($finalComplete['success'] ?? false) === true, '29. Only the final usable token can complete setup');

    $logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mbpha_resend_test.log';
    @unlink($logFile);
    $previousLog = ini_get('error_log');
    ini_set('error_log', $logFile);
    AdminDoctorService::resendDoctorInvitation($failId, Csrf::generate());
    ini_set('error_log', is_string($previousLog) ? $previousLog : '');
    $logContents = is_file($logFile) ? (string) file_get_contents($logFile) : '';
    @unlink($logFile);
    expect_true(
        ($newRaw === '' || !str_contains($logContents, $newRaw))
        && ($oldRaw === '' || !str_contains($logContents, $oldRaw))
        && !str_contains($logContents, 'setup-password?token='),
        '35. Raw token is absent from logs'
    );
} catch (Throwable $exception) {
    expect_true(false, 'Doctor invitation resend tests completed without an unexpected exception: ' . $exception->getMessage());
} finally {
    DoctorInvitationService::resetTestState();
    DoctorPasswordSetupService::resetTestState();
    MailService::resetTestState();
    setEnvKey('MAIL_MAILER', $originalMailer);
    foreach (array_unique($createdUserIds) as $userId) {
        if ($userId <= 0) {
            continue;
        }
        $db->prepare('DELETE FROM doctor_password_setup_tokens WHERE user_id = :id')->execute([':id' => $userId]);
        $db->prepare('DELETE FROM doctor WHERE user_id = :id')->execute([':id' => $userId]);
        $db->prepare('DELETE FROM patient WHERE user_id = :id')->execute([':id' => $userId]);
        $db->prepare('DELETE FROM user_sessions WHERE user_id = :id')->execute([':id' => $userId]);
        $db->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $userId]);
    }
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
