<?php

/**
 * Admin doctor invitation-based creation (Feature 2 Step 8).
 *
 * Usage: php bin/test_doctor_invitation_create.php
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
use App\Models\Patient;
use App\Models\User;
use App\Services\AdminDoctorService;
use App\Services\AuthService;
use App\Services\DoctorInvitationService;
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

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function invite_payload(string $csrf, array $overrides = []): array
{
    return array_merge([
        '_token' => $csrf,
        'full_name' => 'Invite Create Clinician',
        'email' => 'invite.create@telehealth.test',
        'phone' => '+675 7001001',
        'gender' => 'female',
        'professional_title' => 'Dr.',
        'specialization' => 'General Medicine',
        'employee_id' => 'EMP-INVITE-1',
        'status' => 'active',
        'password' => 'ShouldIgnore!234',
        'confirm_password' => 'ShouldIgnore!234',
    ], $overrides);
}

function password_is_sql_null(PDO $db, int $userId): bool
{
    $stmt = $db->prepare('SELECT password IS NULL FROM users WHERE id = :id LIMIT 1');
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    return (int) $stmt->fetchColumn() === 1;
}

$originalMailer = (string) Environment::get('MAIL_MAILER', 'log');
$originalTtl = (string) Environment::get('DOCTOR_INVITE_TOKEN_TTL_HOURS', '24');

AdminDoctorService::resetTestState();
DoctorInvitationService::resetTestState();
MailService::resetTestState();

$db = Database::getInstance();
$suffix = bin2hex(random_bytes(4));
$createdUserIds = [];
$adminId = 0;
$editDoctorId = 0;

try {
    $doctorRoleId = User::findRoleIdByName('doctor');
    $adminRoleId = User::findRoleIdByName('admin');
    expect_true($doctorRoleId !== null && $doctorRoleId > 0, 'Doctor role is available');
    expect_true($adminRoleId !== null && $adminRoleId > 0, 'Admin role is available');

    $admin = new User();
    $admin->role_id = (int) $adminRoleId;
    $admin->full_name = 'Invite Create Admin';
    $admin->email = 'invite.admin+' . $suffix . '@telehealth.test';
    $admin->password = password_hash('AdminPass!234', PASSWORD_DEFAULT);
    $admin->status = 'active';
    if (!$admin->save() || $admin->id === null) {
        throw new RuntimeException('Unable to create the throwaway admin.');
    }
    $adminId = (int) $admin->id;
    $createdUserIds[] = $adminId;
    Session::set('user_id', $adminId);
    Session::set('user_role', 'admin');
    expect_true($adminId > 0, 'Throwaway admin can be created for audit actor tests');

    setEnvKey('MAIL_MAILER', 'array');
    setEnvKey('DOCTOR_INVITE_TOKEN_TTL_HOURS', '24');
    MailService::resetTestState();

    $successEmail = 'invite.create+' . $suffix . '@telehealth.test';
    $successResult = AdminDoctorService::createDoctorAccount(invite_payload(Csrf::generate(), [
        'full_name' => 'Dr Invite Success',
        'email' => $successEmail,
        'phone' => '+675 7001001',
        'employee_id' => 'EMP-S-' . $suffix,
        'specialization' => 'Paediatrics',
        'professional_title' => 'Consultant',
    ]));
    $successId = (int) ($successResult['doctorId'] ?? 0);
    if ($successId > 0) {
        $createdUserIds[] = $successId;
    }

    expect_true(($successResult['accountCreated'] ?? false) === true, '1. Admin creates doctor (accountCreated)');
    expect_true(($successResult['invitationSent'] ?? false) === true, '1. Invitation email is accepted');
    expect_true(
        ($successResult['message'] ?? '') === 'Doctor account created successfully. A password setup invitation has been sent to the doctor\'s email.',
        '12. Correct success result message'
    );

    $createdUser = User::findById($successId);
    expect_true($createdUser !== null && strtolower((string) $createdUser->email) === $successEmail, '2. users row exists');

    $createdDoctor = Doctor::findByUserId($successId);
    expect_true($createdDoctor !== null, '3. doctor row exists');
    expect_true(password_is_sql_null($db, $successId), '4. users.password IS NULL');
    expect_true($createdUser !== null && $createdUser->status === Status::USER_INVITATION_PENDING, '5. users.status = invitation_pending');
    expect_true($createdUser !== null && $createdUser->getRole() === 'doctor', '6. users.role = doctor');
    expect_true(
        $createdDoctor !== null
        && $createdDoctor->phone === '+675 7001001'
        && $createdDoctor->gender === 'female'
        && $createdDoctor->professional_title === 'Consultant'
        && $createdDoctor->specialization === 'Paediatrics'
        && $createdDoctor->employee_id === 'EMP-S-' . $suffix,
        '7. Existing professional fields are preserved'
    );
    expect_true($createdUser !== null && $createdUser->auth_provider === User::AUTH_PROVIDER_LOCAL, 'Created user is auth_provider local');
    expect_true($createdUser !== null && $createdUser->google_sub === null && $createdUser->google_email === null, 'Google identity fields remain NULL');
    expect_true(AuthService::getUserId() === $adminId, 'Created doctor does not receive a session');
    expect_true(AuthService::authenticate($successEmail, 'ShouldIgnore!234') === null, 'Posted password is not a login credential');

    $tokenStmt = $db->prepare(
        'SELECT id, token_hash, sent_at, used_at, expires_at
         FROM doctor_password_setup_tokens
         WHERE user_id = :user_id
         ORDER BY id DESC'
    );
    $tokenStmt->execute([':user_id' => $successId]);
    $tokenRows = $tokenStmt->fetchAll(PDO::FETCH_ASSOC);
    expect_true(count($tokenRows) === 1, '8. Token row is created (exactly one)');
    $successToken = $tokenRows[0] ?? [];
    expect_true(
        isset($successToken['token_hash'])
        && strlen((string) $successToken['token_hash']) === 64
        && ctype_xdigit((string) $successToken['token_hash']),
        '9. Token hash is stored'
    );
    $rawColumnStmt = $db->query("SHOW COLUMNS FROM doctor_password_setup_tokens LIKE 'raw_token'");
    expect_true($rawColumnStmt !== false && $rawColumnStmt->fetch() === false, '9. Raw token is not stored as a column');

    expect_true(count(MailService::$outbox) === 1, '10. MailService accepts the message in the array mailer');
    $html = (string) (MailService::$outbox[0]['html'] ?? '');
    $matched = preg_match('/setup-password\?token=([a-f0-9]{64})/', $html, $tokenMatch) === 1;
    $rawFromEmail = $matched ? (string) $tokenMatch[1] : '';
    expect_true($matched && $rawFromEmail !== '', 'Invitation email contains the raw setup token');
    expect_true(
        $rawFromEmail !== '' && hash('sha256', $rawFromEmail) === (string) ($successToken['token_hash'] ?? ''),
        '9. Stored value is the hash of the emailed token, not the raw token'
    );
    expect_true($successToken['sent_at'] !== null && $successToken['sent_at'] !== '', '11. sent_at becomes non-NULL');

    $auditStmt = $db->prepare(
        'SELECT actor_user_id, subject_name, subject_role, entity_id
         FROM audit_logs
         WHERE entity_id = :entity_id
           AND (event_type = :event_type OR action = :event_action)
         ORDER BY id DESC
         LIMIT 1'
    );
    $auditStmt->execute([
        ':entity_id' => $successId,
        ':event_type' => 'doctor_account_created',
        ':event_action' => 'doctor_account_created',
    ]);
    $audit = $auditStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    expect_true(is_array($audit), 'Existing doctor_account_created audit event is recorded');
    expect_true(is_array($audit) && (int) ($audit['actor_user_id'] ?? 0) === $adminId, 'Audit identifies the creating admin');
    expect_true(is_array($audit) && (int) ($audit['entity_id'] ?? 0) === $successId, 'Audit identifies the doctor user ID');
    expect_true(is_array($audit) && (string) ($audit['subject_name'] ?? '') === 'Dr Invite Success', 'Audit identifies the doctor name');
    expect_true(is_array($audit) && (string) ($audit['subject_role'] ?? '') === 'doctor', 'Audit identifies role doctor');

    MailService::resetTestState();
    setEnvKey('MAIL_MAILER', 'fail');

    $failEmail = 'invite.fail+' . $suffix . '@telehealth.test';
    $failResult = AdminDoctorService::createDoctorAccount(invite_payload(Csrf::generate(), [
        'full_name' => 'Dr Invite Fail',
        'email' => $failEmail,
        'phone' => '+675 7001002',
        'employee_id' => 'EMP-F-' . $suffix,
    ]));
    $failId = (int) ($failResult['doctorId'] ?? 0);
    if ($failId > 0) {
        $createdUserIds[] = $failId;
    }

    expect_true(($failResult['accountCreated'] ?? false) === true, '13. Doctor account still exists after email failure');
    expect_true(($failResult['invitationSent'] ?? true) === false, '18. invitationSent is false when SMTP is rejected');
    expect_true(
        ($failResult['message'] ?? '') === 'Doctor account was created, but the invitation email could not be sent. Please use Resend Invitation.',
        '18. Correct warning result message'
    );

    $failUser = User::findById($failId);
    expect_true($failUser !== null && strtolower((string) $failUser->email) === $failEmail, '13. Failed-email doctor users row remains');
    expect_true(password_is_sql_null($db, $failId), '14. Password remains NULL');
    expect_true($failUser !== null && $failUser->status === Status::USER_INVITATION_PENDING, '15. Status remains invitation_pending');

    $failTokenStmt = $db->prepare(
        'SELECT id, sent_at, used_at, expires_at
         FROM doctor_password_setup_tokens
         WHERE user_id = :user_id
         ORDER BY id DESC
         LIMIT 1'
    );
    $failTokenStmt->execute([':user_id' => $failId]);
    $failToken = $failTokenStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    expect_true(is_array($failToken), '16. Token remains after email failure');
    expect_true(
        is_array($failToken)
        && $failToken['used_at'] === null
        && strtotime((string) $failToken['expires_at']) > time(),
        '16. Token remains usable'
    );
    expect_true(is_array($failToken) && $failToken['sent_at'] === null, '17. sent_at remains NULL');
    expect_true(count(MailService::$outbox) === 0, 'Fail mailer does not record a sent message');

    setEnvKey('MAIL_MAILER', 'array');
    MailService::resetTestState();

    $missingName = AdminDoctorService::createDoctorAccount(invite_payload(Csrf::generate(), [
        'full_name' => '',
        'email' => 'missing.name+' . $suffix . '@telehealth.test',
        'employee_id' => '',
    ]));
    expect_true(
        ($missingName['accountCreated'] ?? true) === false
        && isset($missingName['fieldErrors']['full_name']),
        '19. Missing name rejected'
    );

    $invalidEmail = AdminDoctorService::createDoctorAccount(invite_payload(Csrf::generate(), [
        'email' => 'not-an-email',
        'employee_id' => '',
    ]));
    expect_true(
        ($invalidEmail['accountCreated'] ?? true) === false
        && isset($invalidEmail['fieldErrors']['email']),
        '20. Invalid email rejected'
    );

    $duplicateEmail = AdminDoctorService::createDoctorAccount(invite_payload(Csrf::generate(), [
        'email' => $successEmail,
        'employee_id' => 'EMP-DUP-EMAIL-' . $suffix,
    ]));
    expect_true(
        ($duplicateEmail['accountCreated'] ?? true) === false
        && isset($duplicateEmail['fieldErrors']['email']),
        '21. Duplicate email rejected'
    );

    $invalidPhone = AdminDoctorService::createDoctorAccount(invite_payload(Csrf::generate(), [
        'email' => 'invalid.phone+' . $suffix . '@telehealth.test',
        'phone' => 'abc',
        'employee_id' => '',
    ]));
    expect_true(
        ($invalidPhone['accountCreated'] ?? true) === false
        && isset($invalidPhone['fieldErrors']['phone']),
        '22. Invalid phone rejected'
    );

    $invalidGender = AdminDoctorService::createDoctorAccount(invite_payload(Csrf::generate(), [
        'email' => 'invalid.gender+' . $suffix . '@telehealth.test',
        'gender' => 'not-a-gender',
        'employee_id' => '',
    ]));
    expect_true(
        ($invalidGender['accountCreated'] ?? true) === false
        && isset($invalidGender['fieldErrors']['gender']),
        '23. Invalid gender rejected'
    );

    $missingTitle = AdminDoctorService::createDoctorAccount(invite_payload(Csrf::generate(), [
        'email' => 'missing.title+' . $suffix . '@telehealth.test',
        'professional_title' => '',
        'employee_id' => '',
    ]));
    expect_true(
        ($missingTitle['accountCreated'] ?? true) === false
        && isset($missingTitle['fieldErrors']['professional_title']),
        '24. Missing professional title rejected'
    );

    $missingSpec = AdminDoctorService::createDoctorAccount(invite_payload(Csrf::generate(), [
        'email' => 'missing.spec+' . $suffix . '@telehealth.test',
        'specialization' => '',
        'employee_id' => '',
    ]));
    expect_true(
        ($missingSpec['accountCreated'] ?? true) === false
        && isset($missingSpec['fieldErrors']['specialization']),
        '25. Missing specialization rejected'
    );

    $duplicateEmployee = AdminDoctorService::createDoctorAccount(invite_payload(Csrf::generate(), [
        'email' => 'dup.employee+' . $suffix . '@telehealth.test',
        'employee_id' => 'EMP-S-' . $suffix,
    ]));
    expect_true(
        ($duplicateEmployee['accountCreated'] ?? true) === false
        && isset($duplicateEmployee['fieldErrors']['employee_id']),
        '26. Duplicate employee ID rejected'
    );

    $noPassword = AdminDoctorService::createDoctorAccount([
        '_token' => Csrf::generate(),
        'full_name' => 'No Password Doctor',
        'email' => 'invite.nopw+' . $suffix . '@telehealth.test',
        'phone' => '+675 7001003',
        'gender' => 'male',
        'professional_title' => 'Dr.',
        'specialization' => 'Surgery',
        'employee_id' => 'EMP-NP-' . $suffix,
    ]);
    $noPasswordId = (int) ($noPassword['doctorId'] ?? 0);
    if ($noPasswordId > 0) {
        $createdUserIds[] = $noPasswordId;
    }
    expect_true(
        ($noPassword['accountCreated'] ?? false) === true
        && ($noPassword['invitationSent'] ?? false) === true
        && !isset($noPassword['fieldErrors']['password'])
        && !isset($noPassword['fieldErrors']['confirm_password']),
        '27. Password fields are no longer required'
    );
    expect_true($noPasswordId > 0 && password_is_sql_null($db, $noPasswordId), '27. Password-less create still stores SQL NULL');

    MailService::resetTestState();
    AdminDoctorService::$testFailDoctorProfileInsert = true;
    $rollbackEmail = 'invite.rollback+' . $suffix . '@telehealth.test';
    $rollbackResult = AdminDoctorService::createDoctorAccount(invite_payload(Csrf::generate(), [
        'full_name' => 'Rollback Doctor',
        'email' => $rollbackEmail,
        'phone' => '+675 7001004',
        'employee_id' => 'EMP-RB-' . $suffix,
    ]));
    AdminDoctorService::resetTestState();

    expect_true(($rollbackResult['accountCreated'] ?? true) === false, '28. Simulated doctor profile insert failure is rejected');
    $rollbackUser = User::findByEmail($rollbackEmail);
    expect_true($rollbackUser === null, '29. users insert rolls back');
    $orphanToken = $db->prepare(
        'SELECT COUNT(*)
         FROM doctor_password_setup_tokens t
         INNER JOIN users u ON u.id = t.user_id
         WHERE u.email = :email'
    );
    $orphanToken->execute([':email' => $rollbackEmail]);
    expect_true((int) $orphanToken->fetchColumn() === 0, '30. No invitation token is issued');
    expect_true(count(MailService::$outbox) === 0, '31. No email is sent');

    $edit = new User();
    $edit->role_id = (int) $doctorRoleId;
    $edit->full_name = 'Edit Regression Doctor';
    $edit->email = 'invite.edit+' . $suffix . '@telehealth.test';
    $edit->password = password_hash('EditPass!234', PASSWORD_DEFAULT);
    $edit->status = 'active';
    expect_true($edit->save() && $edit->id !== null, 'Regression doctor user can be created');
    $editDoctorId = (int) $edit->id;
    $createdUserIds[] = $editDoctorId;

    $editProfile = new Doctor();
    $editProfile->user_id = $editDoctorId;
    $editProfile->phone = '+675 7001099';
    $editProfile->gender = 'male';
    $editProfile->professional_title = 'Dr.';
    $editProfile->specialization = 'Cardiology';
    $editProfile->employee_id = 'EMP-ED-' . $suffix;
    expect_true($editProfile->save(), 'Regression doctor profile can be created');

    $editResult = AdminDoctorService::updateDoctorAccount($editDoctorId, [
        '_token' => Csrf::generate(),
        'full_name' => 'Edit Regression Doctor Updated',
        'email' => 'invite.edit+' . $suffix . '@telehealth.test',
        'phone' => '+675 7001098',
        'gender' => 'male',
        'professional_title' => 'Consultant',
        'specialization' => 'Cardiology',
        'employee_id' => 'EMP-ED-' . $suffix,
        'status' => 'active',
    ]);
    expect_true(($editResult['success'] ?? false) === true, '32. Existing doctor edit still works');
    $edited = User::findById($editDoctorId);
    $editedProfile = Doctor::findByUserId($editDoctorId);
    expect_true(
        $edited !== null
        && $edited->full_name === 'Edit Regression Doctor Updated'
        && $edited->status === 'active'
        && $editedProfile !== null
        && $editedProfile->phone === '+675 7001098',
        '32. Edit persists identity and professional fields'
    );

    $resetResult = AdminDoctorService::resetDoctorPassword($editDoctorId, [
        '_token' => Csrf::generate(),
        'password' => 'ResetPass!234',
        'confirm_password' => 'ResetPass!234',
    ]);
    expect_true(($resetResult['success'] ?? false) === true, '33. Existing admin reset-password path still works');
    expect_true(
        AuthService::authenticate('invite.edit+' . $suffix . '@telehealth.test', 'ResetPass!234') !== null,
        '33. Reset password can be used at login'
    );

    $patientEmail = 'invite.patient+' . $suffix . '@telehealth.test';
    $patient = AuthService::register([
        'full_name' => 'Invite Patient Regression',
        'email' => $patientEmail,
        'password' => 'PatientPass!234',
        'dob' => '1992-03-04',
        'gender' => 'female',
        'address' => 'Alotau',
    ]);
    if ($patient instanceof User && $patient->id !== null) {
        $createdUserIds[] = (int) $patient->id;
    }
    expect_true(
        $patient instanceof User
        && is_string($patient->password)
        && $patient->password !== ''
        && password_verify('PatientPass!234', $patient->password)
        && $patient->status === 'active'
        && $patient->getRole() === 'patient',
        '34. Patient registration remains unaffected'
    );
    $patientRow = $patient instanceof User && $patient->id !== null ? Patient::findByUserId((int) $patient->id) : null;
    expect_true($patientRow !== null, '34. Patient profile row is still created');

    expect_true(
        AuthService::authenticate($patientEmail, 'PatientPass!234') !== null,
        '35. Normal login remains unaffected'
    );
    expect_true(
        AuthService::authenticate($successEmail, 'anything') === null,
        'Invitation-pending doctor cannot authenticate through /login'
    );
} catch (Throwable $exception) {
    expect_true(false, 'Doctor invitation create tests completed without an unexpected exception: ' . $exception->getMessage());
} finally {
    AdminDoctorService::resetTestState();
    DoctorInvitationService::resetTestState();
    MailService::resetTestState();
    setEnvKey('MAIL_MAILER', $originalMailer);
    setEnvKey('DOCTOR_INVITE_TOKEN_TTL_HOURS', $originalTtl);

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
