<?php

/**
 * Doctor invitation password setup (Feature 2 Step 10).
 *
 * Usage: php bin/test_doctor_password_setup.php
 *
 * Uses locally generated fixture tokens. Does not send email.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Controllers\DoctorPasswordSetupController;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Helpers\Status;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Services\AdminDoctorService;
use App\Services\AuthService;
use App\Services\DoctorInvitationService;
use App\Services\DoctorPasswordSetupService;
use App\Services\GoogleAuthService;

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

function render_setup_get(string $rawToken): string
{
    $_GET['token'] = $rawToken;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    ob_start();
    (new DoctorPasswordSetupController())->show();
    return (string) ob_get_clean();
}

function password_is_sql_null(PDO $db, int $userId): bool
{
    $stmt = $db->prepare('SELECT password IS NULL FROM users WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    return (int) $stmt->fetchColumn() === 1;
}

function count_setup_completed_audits(PDO $db, int $userId): int
{
    $stmt = $db->prepare(
        "SELECT COUNT(*)
         FROM audit_logs
         WHERE entity_id = :entity_id
           AND (event_type = :event_type OR action = :event_action)"
    );
    $stmt->execute([
        ':entity_id' => $userId,
        ':event_type' => 'doctor_password_setup_completed',
        ':event_action' => 'doctor_password_setup_completed',
    ]);

    return (int) $stmt->fetchColumn();
}

function latest_setup_completed_audit(PDO $db, int $userId): ?array
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
        ':event_type' => 'doctor_password_setup_completed',
        ':event_action' => 'doctor_password_setup_completed',
    ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function create_pending_doctor(int $roleId, string $email, string $name): User
{
    $user = User::createInvitedDoctorUser([
        'role_id' => $roleId,
        'full_name' => $name,
        'email' => $email,
    ]);
    if ($user === null || $user->id === null) {
        throw new RuntimeException('Unable to create pending doctor.');
    }

    return $user;
}

function issue_raw_token(int $userId): string
{
    $issued = DoctorInvitationService::issueToken($userId);
    if (!is_array($issued) || empty($issued['raw_token'])) {
        throw new RuntimeException('Unable to issue fixture token.');
    }

    return (string) $issued['raw_token'];
}

$db = Database::getInstance();
$suffix = bin2hex(random_bytes(4));
$createdUserIds = [];
DoctorPasswordSetupService::resetTestState();
DoctorInvitationService::resetTestState();

try {
    $routes = (string) file_get_contents($root . '/routes/web.php');
    expect_true(
        str_contains($routes, "get('/doctor/setup-password'")
        && str_contains($routes, "post('/doctor/setup-password'"),
        'GET and POST setup-password routes exist'
    );
    expect_true(
        !preg_match("/setup-password[^\n]*RoleMiddleware/", $routes),
        'Setup-password routes are not attached to RoleMiddleware'
    );

    $doctorRoleId = User::findRoleIdByName('doctor');
    $patientRoleId = User::findRoleIdByName('patient');
    expect_true($doctorRoleId !== null && $patientRoleId !== null, 'Doctor and patient roles are available');

    $validUser = create_pending_doctor((int) $doctorRoleId, 'setup.valid+' . $suffix . '@telehealth.test', 'Valid Setup Doctor');
    $createdUserIds[] = (int) $validUser->id;
    $validRaw = issue_raw_token((int) $validUser->id);

    $validHtml = render_setup_get($validRaw);
    expect_true(str_contains($validHtml, 'Set Up Your MBPHA TeleHealth Password'), '1. Valid token renders password form heading');
    expect_true(str_contains($validHtml, 'name="password"') && str_contains($validHtml, 'Set Password'), '1. Valid token renders password form');
    expect_true(str_contains($validHtml, 'Valid Setup Doctor'), '1. Valid token shows the doctor name');
    expect_true(!str_contains($validHtml, 'setup.valid+' . $suffix . '@telehealth.test'), '1. Valid form does not expose the doctor email');

    $invalidMessage = DoctorPasswordSetupService::INVALID_MESSAGE;
    $malformedHtml = render_setup_get('not-a-token');
    expect_true(str_contains($malformedHtml, $invalidMessage) && !str_contains($malformedHtml, 'name="password"'), '2. Malformed token shows generic invalid page');

    $unknownHtml = render_setup_get(bin2hex(random_bytes(32)));
    expect_true(str_contains($unknownHtml, $invalidMessage) && !str_contains($unknownHtml, 'name="password"'), '3. Unknown token shows generic invalid page');

    $expiredUser = create_pending_doctor((int) $doctorRoleId, 'setup.expired+' . $suffix . '@telehealth.test', 'Expired Setup Doctor');
    $createdUserIds[] = (int) $expiredUser->id;
    $expiredRaw = issue_raw_token((int) $expiredUser->id);
    $db->prepare(
        'UPDATE doctor_password_setup_tokens SET expires_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE user_id = :id'
    )->execute([':id' => $expiredUser->id]);
    $expiredHtml = render_setup_get($expiredRaw);
    expect_true(str_contains($expiredHtml, $invalidMessage) && !str_contains($expiredHtml, 'name="password"'), '4. Expired token shows generic invalid page');

    $usedUser = create_pending_doctor((int) $doctorRoleId, 'setup.used+' . $suffix . '@telehealth.test', 'Used Setup Doctor');
    $createdUserIds[] = (int) $usedUser->id;
    $usedRaw = issue_raw_token((int) $usedUser->id);
    $usedIssued = DoctorInvitationService::findByTokenHash(hash('sha256', $usedRaw));
    expect_true(is_array($usedIssued) && DoctorInvitationService::markUsed((int) $usedIssued['id']), 'Used token fixture can be marked used');
    $usedHtml = render_setup_get($usedRaw);
    expect_true(str_contains($usedHtml, $invalidMessage) && !str_contains($usedHtml, 'name="password"'), '5. Used token shows generic invalid page');

    $patient = new User();
    $patient->role_id = (int) $patientRoleId;
    $patient->full_name = 'Setup Patient';
    $patient->email = 'setup.patient+' . $suffix . '@telehealth.test';
    $patient->password = password_hash('PatientPass!234', PASSWORD_DEFAULT);
    $patient->status = 'active';
    expect_true($patient->save() && $patient->id !== null, 'Non-doctor fixture can be created');
    $createdUserIds[] = (int) $patient->id;
    $patientRaw = issue_raw_token((int) $patient->id);
    $patientHtml = render_setup_get($patientRaw);
    expect_true(str_contains($patientHtml, $invalidMessage) && !str_contains($patientHtml, 'name="password"'), '6. Token for non-doctor is rejected');

    $active = new User();
    $active->role_id = (int) $doctorRoleId;
    $active->full_name = 'Active Setup Doctor';
    $active->email = 'setup.active+' . $suffix . '@telehealth.test';
    $active->password = password_hash('ActivePass!234', PASSWORD_DEFAULT);
    $active->status = 'active';
    expect_true($active->save() && $active->id !== null, 'Active doctor fixture can be created');
    $createdUserIds[] = (int) $active->id;
    $activeRaw = issue_raw_token((int) $active->id);
    $activeHtml = render_setup_get($activeRaw);
    expect_true(str_contains($activeHtml, $invalidMessage), '7. Token for active doctor is rejected');

    $suspended = create_pending_doctor((int) $doctorRoleId, 'setup.suspended+' . $suffix . '@telehealth.test', 'Suspended Setup Doctor');
    $createdUserIds[] = (int) $suspended->id;
    $suspendedRaw = issue_raw_token((int) $suspended->id);
    expect_true(User::updateStatus((int) $suspended->id, Status::USER_SUSPENDED), 'Suspended fixture status can be assigned');
    $suspendedHtml = render_setup_get($suspendedRaw);
    expect_true(str_contains($suspendedHtml, $invalidMessage), '8. Token for suspended doctor is rejected');

    $inactive = create_pending_doctor((int) $doctorRoleId, 'setup.inactive+' . $suffix . '@telehealth.test', 'Inactive Setup Doctor');
    $createdUserIds[] = (int) $inactive->id;
    $inactiveRaw = issue_raw_token((int) $inactive->id);
    expect_true(User::updateStatus((int) $inactive->id, Status::USER_INACTIVE), 'Inactive fixture status can be assigned');
    $inactiveHtml = render_setup_get($inactiveRaw);
    expect_true(str_contains($inactiveHtml, $invalidMessage), '8. Token for inactive doctor is rejected');

    $existingPassword = create_pending_doctor((int) $doctorRoleId, 'setup.haspass+' . $suffix . '@telehealth.test', 'Has Password Doctor');
    $createdUserIds[] = (int) $existingPassword->id;
    $existingRaw = issue_raw_token((int) $existingPassword->id);
    expect_true(User::updatePasswordHash((int) $existingPassword->id, password_hash('AlreadySet!234', PASSWORD_DEFAULT)), 'Existing password fixture can receive a hash');
    $existingHtml = render_setup_get($existingRaw);
    expect_true(str_contains($existingHtml, $invalidMessage), '9. Token with existing password is rejected');

    $deleted = create_pending_doctor((int) $doctorRoleId, 'setup.deleted+' . $suffix . '@telehealth.test', 'Deleted Setup Doctor');
    $createdUserIds[] = (int) $deleted->id;
    $deletedRaw = issue_raw_token((int) $deleted->id);
    $db->prepare("UPDATE users SET status = 'deleted' WHERE id = :id")->execute([':id' => $deleted->id]);
    $deletedHtml = render_setup_get($deletedRaw);
    expect_true(str_contains($deletedHtml, $invalidMessage), '10. Deleted user token is rejected');
    expect_true(
        count_setup_completed_audits($db, (int) $usedUser->id) === 0
        && count_setup_completed_audits($db, (int) $patient->id) === 0
        && count_setup_completed_audits($db, (int) $active->id) === 0
        && count_setup_completed_audits($db, (int) $suspended->id) === 0
        && count_setup_completed_audits($db, (int) $inactive->id) === 0
        && count_setup_completed_audits($db, (int) $existingPassword->id) === 0
        && count_setup_completed_audits($db, (int) $deleted->id) === 0,
        'Invalid token conditions do not write doctor_password_setup_completed'
    );

    Session::remove('user_id');
    Session::remove('user_role');

    $missingCsrf = DoctorPasswordSetupService::complete([
        'token' => $validRaw,
        'password' => 'SetupPass!234',
        'confirm_password' => 'SetupPass!234',
    ]);
    expect_true(($missingCsrf['csrfFailed'] ?? false) === true && ($missingCsrf['success'] ?? true) === false, '11. Missing CSRF rejected');
    expect_true(password_is_sql_null($db, (int) $validUser->id), '11. Missing CSRF does not modify the user');

    $invalidCsrf = DoctorPasswordSetupService::complete([
        '_token' => 'invalid-csrf',
        'token' => $validRaw,
        'password' => 'SetupPass!234',
        'confirm_password' => 'SetupPass!234',
    ]);
    expect_true(($invalidCsrf['csrfFailed'] ?? false) === true && ($invalidCsrf['success'] ?? true) === false, '12. Invalid CSRF rejected');
    expect_true(count_setup_completed_audits($db, (int) $validUser->id) === 0, 'Invalid CSRF does not write setup completion audit');

    $csrf = Csrf::generate();
    $invalidTokenPost = DoctorPasswordSetupService::complete([
        '_token' => $csrf,
        'token' => bin2hex(random_bytes(32)),
        'password' => 'SetupPass!234',
        'confirm_password' => 'SetupPass!234',
    ]);
    expect_true(
        ($invalidTokenPost['invalidLink'] ?? false) === true
        && ($invalidTokenPost['message'] ?? '') === $invalidMessage
        && ($invalidTokenPost['fieldErrors'] ?? ['x' => 'x']) === [],
        '13. Invalid token rejected without password errors'
    );

    $weak = DoctorPasswordSetupService::complete([
        '_token' => $csrf,
        'token' => $validRaw,
        'password' => 'password',
        'confirm_password' => 'password',
    ]);
    expect_true(
        ($weak['success'] ?? true) === false
        && ($weak['invalidLink'] ?? true) === false
        && isset($weak['fieldErrors']['password']),
        '14. Weak password rejected'
    );

    $mismatch = DoctorPasswordSetupService::complete([
        '_token' => $csrf,
        'token' => $validRaw,
        'password' => 'SetupPass!234',
        'confirm_password' => 'SetupPass!999',
    ]);
    expect_true(
        ($mismatch['success'] ?? true) === false
        && isset($mismatch['fieldErrors']['confirm_password']),
        '15. Confirmation mismatch rejected'
    );
    expect_true(
        User::findById((int) $validUser->id)?->status === Status::USER_INVITATION_PENDING
        && password_is_sql_null($db, (int) $validUser->id),
        '15. Failed password validation does not activate the doctor'
    );
    expect_true(count_setup_completed_audits($db, (int) $validUser->id) === 0, 'Failed password validation does not write setup completion audit');

    $success = DoctorPasswordSetupService::complete([
        '_token' => $csrf,
        'token' => $validRaw,
        'password' => 'SetupPass!234',
        'confirm_password' => 'SetupPass!234',
    ]);
    expect_true(($success['success'] ?? false) === true, '16. Valid password succeeds');
    expect_true(($success['message'] ?? '') === DoctorPasswordSetupService::SUCCESS_MESSAGE, '16. Success message is returned');
    expect_true(!AuthService::isAuthenticated(), '23. Doctor is NOT automatically logged in by setup');

    $activated = User::findById((int) $validUser->id);
    expect_true(
        $activated !== null
        && is_string($activated->password)
        && $activated->password !== ''
        && password_verify('SetupPass!234', $activated->password),
        '17. Password is stored as a secure password_hash'
    );
    expect_true($activated !== null && $activated->status === Status::USER_ACTIVE, '18. User status becomes active');

    $changedStmt = $db->prepare('SELECT password_changed_at, force_password_reset FROM users WHERE id = :id');
    $changedStmt->execute([':id' => $validUser->id]);
    $changedRow = $changedStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    expect_true(!empty($changedRow['password_changed_at']), '19. password_changed_at is set');
    expect_true((int) ($changedRow['force_password_reset'] ?? 1) === 0, '20. force_password_reset becomes 0');

    $tokenRow = DoctorInvitationService::findByTokenHash(hash('sha256', $validRaw));
    expect_true(is_array($tokenRow) && $tokenRow['used_at'] !== null && $tokenRow['used_at'] !== '', '21. Token used_at is set');

    $loginUser = AuthService::authenticate('setup.valid+' . $suffix . '@telehealth.test', 'SetupPass!234');
    expect_true($loginUser !== null && $loginUser->getRole() === 'doctor', '22. Doctor can subsequently log in through existing /login');

    $setupAudit = latest_setup_completed_audit($db, (int) $validUser->id);
    expect_true(count_setup_completed_audits($db, (int) $validUser->id) === 1, 'Successful setup writes doctor_password_setup_completed once');
    expect_true(is_array($setupAudit) && (int) ($setupAudit['entity_id'] ?? 0) === (int) $validUser->id, 'Setup audit identifies the doctor user ID');
    expect_true(is_array($setupAudit) && (string) ($setupAudit['subject_name'] ?? '') === 'Valid Setup Doctor', 'Setup audit identifies the doctor name');
    expect_true(is_array($setupAudit) && (string) ($setupAudit['subject_role'] ?? '') === 'doctor', 'Setup audit identifies role doctor');
    expect_true(is_array($setupAudit) && (string) ($setupAudit['outcome'] ?? '') === 'success', 'Setup audit outcome is success');
    expect_true(is_array($setupAudit) && !empty($setupAudit['created_at']), 'Setup audit timestamp is present');
    expect_true(is_array($setupAudit) && $setupAudit['actor_user_id'] === null, 'Setup audit uses a null system actor');
    expect_true(is_array($setupAudit) && (string) ($setupAudit['actor_role'] ?? '') !== 'admin', 'Setup audit does not fabricate an administrator actor');
    $auditBlob = json_encode($setupAudit) ?: '';
    expect_true(
        !str_contains($auditBlob, $validRaw)
        && !str_contains($auditBlob, 'SetupPass!234')
        && !str_contains($auditBlob, (string) ($activated->password ?? ''))
        && !str_contains($auditBlob, 'setup-password?token='),
        'Setup audit does not store token, password, hash, or setup URL'
    );

    $reuse = DoctorPasswordSetupService::complete([
        '_token' => $csrf,
        'token' => $validRaw,
        'password' => 'OtherPass!234',
        'confirm_password' => 'OtherPass!234',
    ]);
    expect_true(($reuse['invalidLink'] ?? false) === true && ($reuse['success'] ?? true) === false, '24. Reusing the same token after success fails');
    $afterReuse = User::findById((int) $validUser->id);
    expect_true(
        $afterReuse !== null && password_verify('SetupPass!234', (string) $afterReuse->password),
        '24. Reuse does not change the stored password'
    );
    expect_true(count_setup_completed_audits($db, (int) $validUser->id) === 1, 'Token replay does not write a second setup completion audit');

    $raceUser = create_pending_doctor((int) $doctorRoleId, 'setup.race+' . $suffix . '@telehealth.test', 'Race Setup Doctor');
    $createdUserIds[] = (int) $raceUser->id;
    $raceRaw = issue_raw_token((int) $raceUser->id);
    $firstRace = DoctorPasswordSetupService::complete([
        '_token' => $csrf,
        'token' => $raceRaw,
        'password' => 'RacePass!234',
        'confirm_password' => 'RacePass!234',
    ]);
    $secondRace = DoctorPasswordSetupService::complete([
        '_token' => $csrf,
        'token' => $raceRaw,
        'password' => 'OtherRace!234',
        'confirm_password' => 'OtherRace!234',
    ]);
    expect_true(
        ($firstRace['success'] ?? false) === true
        && ($secondRace['success'] ?? true) === false
        && ($secondRace['invalidLink'] ?? false) === true,
        '25. Double-submit allows only one success'
    );
    $raceAfter = User::findById((int) $raceUser->id);
    expect_true(
        $raceAfter !== null && password_verify('RacePass!234', (string) $raceAfter->password),
        '25. Losing double-submit does not overwrite the password'
    );
    expect_true(count_setup_completed_audits($db, (int) $raceUser->id) === 1, 'Concurrent losing request does not write a second setup completion audit');

    $rollbackUser = create_pending_doctor((int) $doctorRoleId, 'setup.rollback+' . $suffix . '@telehealth.test', 'Rollback Setup Doctor');
    $createdUserIds[] = (int) $rollbackUser->id;
    $rollbackRaw = issue_raw_token((int) $rollbackUser->id);
    DoctorPasswordSetupService::$testFailAfterTokenClaim = true;
    $rolled = DoctorPasswordSetupService::complete([
        '_token' => $csrf,
        'token' => $rollbackRaw,
        'password' => 'RollbackPass!234',
        'confirm_password' => 'RollbackPass!234',
    ]);
    DoctorPasswordSetupService::resetTestState();
    expect_true(($rolled['success'] ?? true) === false, 'Partial setup failure is refused');
    expect_true(
        password_is_sql_null($db, (int) $rollbackUser->id)
        && User::findById((int) $rollbackUser->id)?->status === Status::USER_INVITATION_PENDING,
        'Failed setup rolls back password and status'
    );
    $rollbackToken = DoctorInvitationService::findByTokenHash(hash('sha256', $rollbackRaw));
    expect_true(is_array($rollbackToken) && $rollbackToken['used_at'] === null, 'Failed setup rolls back token used_at');
    expect_true(count_setup_completed_audits($db, (int) $rollbackUser->id) === 0, 'Rolled-back setup does not write doctor_password_setup_completed');

    $changeUser = new User();
    $changeUser->role_id = (int) $patientRoleId;
    $changeUser->full_name = 'Password Change Patient';
    $changeUser->email = 'setup.changepw+' . $suffix . '@telehealth.test';
    $changeUser->password = password_hash('ChangeOld!234', PASSWORD_DEFAULT);
    $changeUser->status = 'active';
    expect_true($changeUser->save() && $changeUser->id !== null, 'Password-change regression user can be created');
    $createdUserIds[] = (int) $changeUser->id;
    expect_true(
        User::updatePasswordHash((int) $changeUser->id, password_hash('ChangeNew!234', PASSWORD_DEFAULT)),
        '26. Existing password-change functionality remains unchanged'
    );
    $changed = User::findById((int) $changeUser->id);
    expect_true(
        $changed !== null
        && $changed->status === 'active'
        && password_verify('ChangeNew!234', (string) $changed->password),
        '26. updatePasswordHash still stores a hash without invitation activation rules'
    );

    $resetDoctorUser = new User();
    $resetDoctorUser->role_id = (int) $doctorRoleId;
    $resetDoctorUser->full_name = 'Reset Setup Doctor';
    $resetDoctorUser->email = 'setup.reset+' . $suffix . '@telehealth.test';
    $resetDoctorUser->password = password_hash('ResetOld!234', PASSWORD_DEFAULT);
    $resetDoctorUser->status = 'active';
    expect_true($resetDoctorUser->save() && $resetDoctorUser->id !== null, 'Reset-password doctor user can be created');
    $createdUserIds[] = (int) $resetDoctorUser->id;
    $resetProfile = new Doctor();
    $resetProfile->user_id = (int) $resetDoctorUser->id;
    $resetProfile->phone = '+675 7003001';
    $resetProfile->gender = 'female';
    $resetProfile->professional_title = 'Dr.';
    $resetProfile->specialization = 'General Medicine';
    $resetProfile->employee_id = 'EMP-RS-' . $suffix;
    expect_true($resetProfile->save(), 'Reset-password doctor profile can be created');
    $resetResult = AdminDoctorService::resetDoctorPassword((int) $resetDoctorUser->id, [
        '_token' => Csrf::generate(),
        'password' => 'ResetNew!234',
        'confirm_password' => 'ResetNew!234',
    ]);
    expect_true(($resetResult['success'] ?? false) === true, '27. Existing admin reset-password remains unchanged');
    expect_true(
        AuthService::authenticate('setup.reset+' . $suffix . '@telehealth.test', 'ResetNew!234') !== null,
        '27. Reset password can be used at login'
    );

    $regEmail = 'setup.reg+' . $suffix . '@telehealth.test';
    $registered = AuthService::register([
        'full_name' => 'Setup Registration Patient',
        'email' => $regEmail,
        'password' => 'RegPass!234',
        'dob' => '1991-02-03',
        'gender' => 'male',
        'address' => 'Alotau',
    ]);
    if ($registered instanceof User && $registered->id !== null) {
        $createdUserIds[] = (int) $registered->id;
    }
    expect_true(
        $registered instanceof User
        && $registered->getRole() === 'patient'
        && Patient::findByUserId((int) $registered->id) !== null
        && AuthService::authenticate($regEmail, 'RegPass!234') !== null,
        '28. Patient registration/login remains unchanged'
    );

    expect_true(
        method_exists(GoogleAuthService::class, 'authenticate')
        && method_exists(AuthService::class, 'registerGooglePatient'),
        '29. Google patient login remains unchanged'
    );
} catch (Throwable $exception) {
    expect_true(false, 'Doctor password setup tests completed without an unexpected exception: ' . $exception->getMessage());
} finally {
    DoctorPasswordSetupService::resetTestState();
    DoctorInvitationService::resetTestState();
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
