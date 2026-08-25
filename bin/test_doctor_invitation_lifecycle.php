<?php

/**
 * Feature 2 Step 12 — invitation lifecycle integration and security audit.
 *
 * Usage: php bin/test_doctor_invitation_lifecycle.php
 *
 * Uses the array mailer only. Does not send real Gmail.
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
use App\Services\AdminUserService;
use App\Services\AuditLogService;
use App\Services\AuthService;
use App\Services\DoctorInvitationService;
use App\Services\DoctorPasswordSetupService;
use App\Services\GoogleAuthService;
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

function count_audit_events(PDO $db, int $userId, string $event): int
{
    $stmt = $db->prepare(
        "SELECT COUNT(*)
         FROM audit_logs
         WHERE entity_id = :entity_id
           AND (event_type = :event_type OR action = :event_action)"
    );
    $stmt->execute([
        ':entity_id' => $userId,
        ':event_type' => $event,
        ':event_action' => $event,
    ]);

    return (int) $stmt->fetchColumn();
}

function latest_audit_event(PDO $db, int $userId, string $event): ?array
{
    $stmt = $db->prepare(
        "SELECT actor_user_id, actor_name, actor_role, subject_name, subject_role,
                event_type, action, description, outcome, created_at, entity_type, entity_id
         FROM audit_logs
         WHERE entity_id = :entity_id
           AND (event_type = :event_type OR action = :event_action)
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([
        ':entity_id' => $userId,
        ':event_type' => $event,
        ':event_action' => $event,
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
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
    $catalog = AuditLogService::catalog();
    expect_true(isset($catalog['doctor_password_setup_completed']), 'Catalog includes doctor_password_setup_completed');

    $setupSource = (string) file_get_contents($root . '/app/Services/DoctorPasswordSetupService.php');
    $controllerSource = (string) file_get_contents($root . '/app/Controllers/DoctorPasswordSetupController.php');
    $routes = (string) file_get_contents($root . '/routes/web.php');
    expect_true(!str_contains($setupSource, 'AuthService::login'), 'Setup service does not call AuthService::login()');
    expect_true(!str_contains($controllerSource, 'AuthService::login'), 'Setup controller does not call AuthService::login()');
    expect_true(!str_contains($setupSource, "Session::set('user_id'") && !str_contains($controllerSource, "Session::set('user_id'"), 'Setup does not set user_id');
    expect_true(!str_contains($setupSource, "Session::set('user_role'") && !str_contains($controllerSource, "Session::set('user_role'"), 'Setup does not set user_role');
    expect_true(str_contains($setupSource, 'Csrf::verify'), 'POST /doctor/setup-password still requires CSRF');
    expect_true(str_contains($setupSource, 'password_hash($password, PASSWORD_DEFAULT)'), 'Setup continues to use password_hash(..., PASSWORD_DEFAULT)');
    expect_true(
        str_contains($routes, "post('/admin/doctors/{id}/resend-invitation'")
        && !str_contains($routes, "get('/admin/doctors/{id}/resend-invitation'"),
        'Resend remains POST-only'
    );
    expect_true(
        str_contains($routes, "post('/doctor/setup-password'")
        && str_contains($routes, "get('/doctor/setup-password'"),
        'Setup-password GET and POST routes remain'
    );

    $doctorRoleId = User::findRoleIdByName('doctor');
    $patientRoleId = User::findRoleIdByName('patient');
    $adminRoleId = User::findRoleIdByName('admin');
    expect_true($doctorRoleId !== null && $patientRoleId !== null && $adminRoleId !== null, 'Required roles exist');

    $admin = new User();
    $admin->role_id = (int) $adminRoleId;
    $admin->full_name = 'Lifecycle Admin';
    $admin->email = 'lifecycle.admin+' . $suffix . '@telehealth.test';
    $admin->password = password_hash('AdminPass!234', PASSWORD_DEFAULT);
    $admin->status = 'active';
    expect_true($admin->save() && $admin->id !== null, 'Administrator fixture created');
    $createdUserIds[] = (int) $admin->id;
    Session::set('user_id', (int) $admin->id);
    Session::set('user_role', 'admin');

    $createEmail = 'lifecycle.doctor+' . $suffix . '@telehealth.test';
    $createResult = AdminDoctorService::createDoctorAccount([
        '_token' => Csrf::generate(),
        'full_name' => 'Lifecycle Invitation Doctor',
        'email' => $createEmail,
        'phone' => '+675 7005001',
        'gender' => 'female',
        'professional_title' => 'Dr.',
        'specialization' => 'General Medicine',
        'employee_id' => 'EMP-LC-' . $suffix,
        'status' => 'active',
    ]);
    $doctorId = (int) ($createResult['doctorId'] ?? 0);
    if ($doctorId > 0) {
        $createdUserIds[] = $doctorId;
    }

    expect_true(($createResult['accountCreated'] ?? false) === true, 'A. Admin creates doctor');
    expect_true(($createResult['invitationSent'] ?? false) === true, 'B. Initial invitation is accepted by the test mailer');
    $created = User::findById($doctorId);
    expect_true(password_is_sql_null($db, $doctorId), 'A. State A password IS NULL');
    expect_true($created !== null && $created->status === Status::USER_INVITATION_PENDING, 'A. State A status is invitation_pending');
    expect_true(count_audit_events($db, $doctorId, 'doctor_account_created') === 1, 'A. doctor_account_created exists once');

    $tokenCount = $db->prepare('SELECT COUNT(*) FROM doctor_password_setup_tokens WHERE user_id = :id');
    $tokenCount->execute([':id' => $doctorId]);
    expect_true((int) $tokenCount->fetchColumn() === 1, 'B. Initial invitation token exists');
    expect_true(count(MailService::$outbox) === 1, 'B. Test mailer received the initial invitation');
    $oldRaw = extract_setup_token((string) (MailService::$outbox[0]['html'] ?? ''));
    expect_true($oldRaw !== '' && DoctorPasswordSetupService::inspect($oldRaw)['valid'] === true, 'B. Initial setup token is usable');

    $bookableAfterCreate = Doctor::findForPatientDirectory(50, 0);
    $bookableIds = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $bookableAfterCreate);
    expect_true(!in_array($doctorId, $bookableIds, true), 'Pending doctor remains excluded from patient booking');

    $db->prepare(
        'UPDATE doctor_password_setup_tokens SET sent_at = DATE_SUB(NOW(), INTERVAL 10 MINUTE) WHERE user_id = :id AND sent_at IS NOT NULL'
    )->execute([':id' => $doctorId]);
    MailService::resetTestState();
    $resend = AdminDoctorService::resendDoctorInvitation($doctorId, Csrf::generate());
    expect_true(($resend['success'] ?? false) === true, 'C. Admin resend succeeds');
    expect_true(count_audit_events($db, $doctorId, 'doctor_invitation_resent') === 1, 'C. doctor_invitation_resent exists once');
    expect_true(count_audit_events($db, $doctorId, 'doctor_account_created') === 1, 'C. Resend does not duplicate doctor_account_created');
    expect_true(DoctorPasswordSetupToken::countUsableForUser($doctorId) === 1, 'C. Only one invitation token remains usable');
    expect_true(DoctorPasswordSetupService::inspect($oldRaw)['valid'] === false, 'C. Previous token becomes unusable');
    $afterResend = User::findById($doctorId);
    expect_true(password_is_sql_null($db, $doctorId), 'State B password remains NULL after resend');
    expect_true($afterResend !== null && $afterResend->status === Status::USER_INVITATION_PENDING, 'State B status remains invitation_pending');
    $newRaw = extract_setup_token((string) (MailService::$outbox[0]['html'] ?? ''));
    expect_true($newRaw !== '' && $newRaw !== $oldRaw, 'C. Resend emails a replacement token');
    expect_true((string) (MailService::$outbox[0]['to'] ?? '') === $createEmail, 'Resend uses trusted database recipient email');

    Session::remove('user_id');
    Session::remove('user_role');
    expect_true(!AuthService::isAuthenticated(), 'F. Setup begins without an authenticated session');

    $inspect = DoctorPasswordSetupService::inspect($newRaw);
    expect_true(($inspect['valid'] ?? false) === true, 'D. New invitation opens the setup page');
    $setup = DoctorPasswordSetupService::complete([
        '_token' => Csrf::generate(),
        'token' => $newRaw,
        'password' => 'LifecyclePass!234',
        'confirm_password' => 'LifecyclePass!234',
    ]);
    expect_true(($setup['success'] ?? false) === true, 'D. Password setup succeeds');
    expect_true(!AuthService::isAuthenticated(), 'F. Setup itself did not authenticate the doctor');
    expect_true((int) (Session::get('user_id') ?? 0) !== $doctorId, 'F. Setup did not set the doctor user_id');

    $activated = User::findById($doctorId);
    $stateStmt = $db->prepare(
        'SELECT password IS NULL AS password_null, status, force_password_reset, password_changed_at FROM users WHERE id = :id'
    );
    $stateStmt->execute([':id' => $doctorId]);
    $stateC = $stateStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $usedToken = DoctorInvitationService::findByTokenHash(hash('sha256', $newRaw));
    expect_true((int) ($stateC['password_null'] ?? 1) === 0, 'State C password is not NULL');
    expect_true(
        $activated !== null
        && is_string($activated->password)
        && password_verify('LifecyclePass!234', $activated->password),
        'D. Password hash is stored'
    );
    expect_true((string) ($stateC['status'] ?? '') === Status::USER_ACTIVE, 'State C status is active');
    expect_true((int) ($stateC['force_password_reset'] ?? 1) === 0, 'State C force_password_reset is 0');
    expect_true(!empty($stateC['password_changed_at']), 'State C password_changed_at is set');
    expect_true(is_array($usedToken) && $usedToken['used_at'] !== null && $usedToken['used_at'] !== '', 'State C token.used_at is set');

    $completion = latest_audit_event($db, $doctorId, 'doctor_password_setup_completed');
    expect_true(count_audit_events($db, $doctorId, 'doctor_password_setup_completed') === 1, 'E. doctor_password_setup_completed exists exactly once');
    expect_true(is_array($completion) && (int) ($completion['entity_id'] ?? 0) === $doctorId, 'E. Completion audit identifies the same doctor');
    expect_true(is_array($completion) && (string) ($completion['entity_type'] ?? '') === AuditLogService::ENTITY_USER, 'E. Completion audit entity_type is user');
    expect_true(is_array($completion) && (string) ($completion['subject_name'] ?? '') === 'Lifecycle Invitation Doctor', 'E. Completion audit subject name is trusted');
    expect_true(is_array($completion) && (string) ($completion['subject_role'] ?? '') === 'doctor', 'E. Completion audit subject role is doctor');
    expect_true(is_array($completion) && (string) ($completion['outcome'] ?? '') === 'success', 'E. Completion audit outcome is success');
    expect_true(is_array($completion) && !empty($completion['created_at']), 'E. Completion audit timestamp is present');
    expect_true(is_array($completion) && $completion['actor_user_id'] === null, 'E. Completion actor_user_id is NULL');
    expect_true(is_array($completion) && (string) ($completion['actor_name'] ?? '') === 'System', 'E. Completion actor_name is System');
    expect_true(is_array($completion) && (string) ($completion['actor_role'] ?? '') === 'system', 'E. Completion actor_role is system');
    expect_true(is_array($completion) && (string) ($completion['actor_role'] ?? '') !== 'admin', 'E. Doctor is not represented as an administrator');
    $auditBlob = json_encode($completion) ?: '';
    $passwordHash = (string) ($activated->password ?? '');
    expect_true(
        !str_contains($auditBlob, $oldRaw)
        && !str_contains($auditBlob, $newRaw)
        && !str_contains($auditBlob, 'LifecyclePass!234')
        && ($passwordHash === '' || !str_contains($auditBlob, $passwordHash))
        && !str_contains($auditBlob, 'setup-password?token='),
        'E. Completion audit stores no token, password, hash, or setup URL'
    );
    expect_true(count_audit_events($db, $doctorId, 'doctor_account_created') === 1, 'E. Setup does not duplicate doctor_account_created');
    expect_true(count_audit_events($db, $doctorId, 'doctor_invitation_resent') === 1, 'E. Setup does not duplicate doctor_invitation_resent');

    $login = AuthService::authenticate($createEmail, 'LifecyclePass!234');
    expect_true($login !== null && (int) $login->id === $doctorId && $login->getRole() === 'doctor', 'F. Existing /login authentication succeeds');

    $passwordBeforeReplay = $passwordHash;
    $changedAtBefore = (string) ($stateC['password_changed_at'] ?? '');
    $replay = DoctorPasswordSetupService::complete([
        '_token' => Csrf::generate(),
        'token' => $newRaw,
        'password' => 'ReplayPass!234',
        'confirm_password' => 'ReplayPass!234',
    ]);
    expect_true(($replay['success'] ?? true) === false, 'G. Token replay fails');
    expect_true(($replay['message'] ?? '') === DoctorPasswordSetupService::INVALID_MESSAGE, 'G. Token replay uses the generic invalid message');
    expect_true(count_audit_events($db, $doctorId, 'doctor_password_setup_completed') === 1, 'G. No second completion audit event');
    $afterReplay = User::findById($doctorId);
    $replayState = $db->prepare(
        'SELECT password, status, force_password_reset, password_changed_at FROM users WHERE id = :id'
    );
    $replayState->execute([':id' => $doctorId]);
    $stateD = $replayState->fetch(PDO::FETCH_ASSOC) ?: [];
    $replayToken = DoctorInvitationService::findByTokenHash(hash('sha256', $newRaw));
    expect_true((string) ($stateD['password'] ?? '') === $passwordBeforeReplay, 'State D password is unchanged');
    expect_true((string) ($stateD['status'] ?? '') === Status::USER_ACTIVE, 'State D status remains active');
    expect_true((int) ($stateD['force_password_reset'] ?? 1) === 0, 'State D force_password_reset is unchanged');
    expect_true((string) ($stateD['password_changed_at'] ?? '') === $changedAtBefore, 'State D password_changed_at is unchanged');
    expect_true(is_array($replayToken) && (string) ($replayToken['used_at'] ?? '') === (string) ($usedToken['used_at'] ?? ''), 'State D token used_at is unchanged');
    expect_true($afterReplay !== null && password_verify('LifecyclePass!234', (string) $afterReplay->password), 'G. Replay does not change the password');

    $csrf2 = Csrf::generate();
    expect_true((AdminDoctorService::resendDoctorInvitation($doctorId, $csrf2)['success'] ?? true) === false, 'Active doctor cannot resend after setup');

    $patient = new User();
    $patient->role_id = (int) $patientRoleId;
    $patient->full_name = 'Lifecycle Patient';
    $patient->email = 'lifecycle.patient+' . $suffix . '@telehealth.test';
    $patient->password = password_hash('PatientPass!234', PASSWORD_DEFAULT);
    $patient->status = 'active';
    expect_true($patient->save() && $patient->id !== null, 'Patient fixture created');
    $createdUserIds[] = (int) $patient->id;
    expect_true((AdminDoctorService::resendDoctorInvitation((int) $patient->id, $csrf2)['success'] ?? true) === false, 'Patients cannot resend');
    expect_true((AdminDoctorService::resendDoctorInvitation((int) $admin->id, $csrf2)['success'] ?? true) === false, 'Administrators cannot resend');

    Session::set('user_id', (int) $admin->id);
    Session::set('user_role', 'admin');
    $guardUser = User::createInvitedDoctorUser([
        'role_id' => (int) $doctorRoleId,
        'full_name' => 'Lifecycle Guard Doctor',
        'email' => 'lifecycle.guard+' . $suffix . '@telehealth.test',
    ]);
    expect_true($guardUser !== null && $guardUser->id !== null, 'User-management guard fixture can be created');
    $guardId = (int) $guardUser->id;
    $createdUserIds[] = $guardId;
    $guardProfile = new Doctor();
    $guardProfile->user_id = $guardId;
    $guardProfile->phone = '+675 7005002';
    $guardProfile->gender = 'male';
    $guardProfile->professional_title = 'Dr.';
    $guardProfile->specialization = 'Surgery';
    $guardProfile->employee_id = 'EMP-LG-' . $suffix;
    expect_true($guardProfile->save(), 'User-management guard doctor profile can be created');
    $guardToken = (string) (DoctorInvitationService::issueToken($guardId)['raw_token'] ?? '');

    $userMgmtActions = array_column(AdminUserService::availableActions([
        'id' => $guardId,
        'status' => Status::USER_INVITATION_PENDING,
        'role_name' => 'doctor',
    ], (int) $admin->id), 'key');
    expect_true(!in_array('reset_password', $userMgmtActions, true), 'User management does not expose Reset password for pending doctors');
    expect_true(!in_array('reactivate', $userMgmtActions, true), 'User management does not expose Reactivate for pending doctors');

    $userActivate = AdminUserService::changeAccountStatus($guardId, (int) $admin->id, Status::USER_ACTIVE, Csrf::generate());
    $guardAfterActivate = User::findById($guardId);
    expect_true(($userActivate['success'] ?? true) === false, 'User management cannot activate a pending doctor');
    expect_true(
        $guardAfterActivate !== null
        && $guardAfterActivate->status === Status::USER_INVITATION_PENDING
        && password_is_sql_null($db, $guardId),
        'Direct user-management activate leaves pending doctor unchanged'
    );

    $userReset = AdminUserService::resetUserPassword($guardId, (int) $admin->id, [
        '_token' => Csrf::generate(),
        'password' => 'BypassPass!234',
        'confirm_password' => 'BypassPass!234',
    ]);
    expect_true(($userReset['success'] ?? true) === false, 'User management cannot reset a pending doctor password');
    expect_true(password_is_sql_null($db, $guardId), 'Pending doctor password remains NULL after refused user-management reset');

    $userForce = AdminUserService::forcePasswordReset($guardId, (int) $admin->id, Csrf::generate());
    expect_true(($userForce['success'] ?? true) === false, 'User management cannot force a password reset on a pending doctor');

    $missingCreateCsrf = AdminDoctorService::createDoctorAccount([
        'full_name' => 'Lifecycle CSRF Doctor',
        'email' => 'lifecycle.csrf+' . $suffix . '@telehealth.test',
        'phone' => '+675 7005003',
        'gender' => 'female',
        'professional_title' => 'Dr.',
        'specialization' => 'General Medicine',
        'employee_id' => 'EMP-CS-' . $suffix,
    ]);
    expect_true(($missingCreateCsrf['accountCreated'] ?? true) === false, 'Doctor creation POST requires CSRF');

    $inconsistentPending = User::createInvitedDoctorUser([
        'role_id' => (int) $doctorRoleId,
        'full_name' => 'Lifecycle Inconsistent Pending',
        'email' => 'lifecycle.inconsist+' . $suffix . '@telehealth.test',
    ]);
    expect_true($inconsistentPending !== null && $inconsistentPending->id !== null, 'Inconsistent pending fixture can be created');
    $inconsistentId = (int) $inconsistentPending->id;
    $createdUserIds[] = $inconsistentId;
    $inconsistentRaw = (string) (DoctorInvitationService::issueToken($inconsistentId)['raw_token'] ?? '');
    $db->prepare('UPDATE users SET password = :password WHERE id = :id')->execute([
        ':password' => password_hash('AlreadyThere!234', PASSWORD_DEFAULT),
        ':id' => $inconsistentId,
    ]);
    $inconsistentInspect = DoctorPasswordSetupService::inspect($inconsistentRaw);
    $inconsistentComplete = DoctorPasswordSetupService::complete([
        '_token' => Csrf::generate(),
        'token' => $inconsistentRaw,
        'password' => 'OverwritePass!234',
        'confirm_password' => 'OverwritePass!234',
    ]);
    $inconsistentAfter = User::findById($inconsistentId);
    expect_true(($inconsistentInspect['valid'] ?? true) === false, 'Pending doctor with an existing password is rejected by setup');
    expect_true(($inconsistentComplete['success'] ?? true) === false, 'Setup does not overwrite an existing password on a pending doctor');
    expect_true(
        $inconsistentAfter !== null
        && $inconsistentAfter->status === Status::USER_INVITATION_PENDING
        && password_verify('AlreadyThere!234', (string) $inconsistentAfter->password),
        'Inconsistent pending+password state is left unchanged'
    );
    expect_true(count_audit_events($db, $inconsistentId, 'doctor_password_setup_completed') === 0, 'Inconsistent pending+password state writes no completion audit');

    $activeNull = User::createInvitedDoctorUser([
        'role_id' => (int) $doctorRoleId,
        'full_name' => 'Lifecycle Active Null',
        'email' => 'lifecycle.activenull+' . $suffix . '@telehealth.test',
    ]);
    expect_true($activeNull !== null && $activeNull->id !== null, 'Active-null fixture can be created');
    $activeNullId = (int) $activeNull->id;
    $createdUserIds[] = $activeNullId;
    $activeNullRaw = (string) (DoctorInvitationService::issueToken($activeNullId)['raw_token'] ?? '');
    $db->prepare("UPDATE users SET status = 'active' WHERE id = :id")->execute([':id' => $activeNullId]);
    $activeNullComplete = DoctorPasswordSetupService::complete([
        '_token' => Csrf::generate(),
        'token' => $activeNullRaw,
        'password' => 'ActivatePass!234',
        'confirm_password' => 'ActivatePass!234',
    ]);
    $activeNullAfter = User::findById($activeNullId);
    expect_true(($activeNullComplete['success'] ?? true) === false, 'Active doctor with NULL password is rejected by setup');
    expect_true(
        $activeNullAfter !== null
        && $activeNullAfter->status === Status::USER_ACTIVE
        && password_is_sql_null($db, $activeNullId),
        'Active+NULL password state is not auto-repaired by setup'
    );
    expect_true(count_audit_events($db, $activeNullId, 'doctor_password_setup_completed') === 0, 'Active+NULL password state writes no completion audit');
    expect_true(AuthService::authenticate('lifecycle.activenull+' . $suffix . '@telehealth.test', 'ActivatePass!234') === null, 'Active+NULL password cannot authenticate');

    $bookableCompleted = $db->prepare(
        "SELECT COUNT(*)
         FROM doctor
         INNER JOIN users ON users.id = doctor.user_id
         INNER JOIN roles ON roles.id = users.role_id
         WHERE doctor.user_id = :id
           AND roles.name = 'doctor'
           AND users.status = 'active'"
    );
    $bookableCompleted->execute([':id' => $doctorId]);
    expect_true((int) $bookableCompleted->fetchColumn() === 1, 'Completed active doctor follows existing bookable-doctor query');

    $registered = AuthService::register([
        'full_name' => 'Lifecycle Registration Patient',
        'email' => 'lifecycle.reg+' . $suffix . '@telehealth.test',
        'password' => 'RegPass!234',
        'dob' => '1992-04-05',
        'gender' => 'female',
        'address' => 'Alotau',
    ]);
    if ($registered instanceof User && $registered->id !== null) {
        $createdUserIds[] = (int) $registered->id;
    }
    expect_true(
        $registered instanceof User
        && $registered->getRole() === 'patient'
        && AuthService::authenticate('lifecycle.reg+' . $suffix . '@telehealth.test', 'RegPass!234') !== null,
        'Patient registration and login remain unaffected'
    );
    expect_true(
        method_exists(GoogleAuthService::class, 'authenticate')
        && method_exists(AuthService::class, 'registerGooglePatient'),
        'Google patient login remains unaffected'
    );
    expect_true(
        method_exists(User::class, 'updatePasswordHash')
        && method_exists(AdminDoctorService::class, 'resetDoctorPassword'),
        'Existing password update and reset functions remain available'
    );
} catch (Throwable $exception) {
    expect_true(false, 'Invitation lifecycle tests completed without an unexpected exception: ' . $exception->getMessage());
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
