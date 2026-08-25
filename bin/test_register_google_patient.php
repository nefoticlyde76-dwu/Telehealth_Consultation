<?php

/**
 * AuthService::registerGooglePatient() checks.
 *
 * Usage: php bin/test_register_google_patient.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Core\Database;
use App\Core\Session;
use App\Models\Patient;
use App\Models\User;
use App\Services\AuthService;

$root = dirname(__DIR__);
Environment::load($root . '/.env');
Session::start();

$failed = 0;
$passed = 0;
$createdUserIds = [];

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

function delete_test_user(\PDO $db, int $userId): void
{
    $db->prepare('DELETE FROM user_sessions WHERE user_id = :id')->execute([':id' => $userId]);
    $db->prepare('DELETE FROM patient WHERE user_id = :id')->execute([':id' => $userId]);
    $db->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $userId]);
}

$db = Database::getInstance();
$suffix = bin2hex(random_bytes(6));
$patientRoleId = User::findRoleIdByName('patient');
AuthService::$testFailGooglePatientInsert = false;

$sessionUserIdBefore = Session::get('user_id');
$sessionRoleBefore = Session::get('user_role');

try {
    $invalid = AuthService::registerGooglePatient([
        'sub' => '',
        'email' => 'not-an-email',
        'email_verified' => true,
        'name' => 'X',
    ]);
    expect_true($invalid === null, 'Invalid identity does not create an account');

    $unverified = AuthService::registerGooglePatient([
        'sub' => 'sub-unverified-' . $suffix,
        'email' => 'unverified+' . $suffix . '@example.com',
        'email_verified' => false,
        'name' => 'Unverified',
    ]);
    expect_true($unverified === null, 'Unverified email does not create an account');
    expect_true(User::findByEmail('unverified+' . $suffix . '@example.com') === null, 'Unverified identity leaves no users row');

    $googleEmail = 'google.reg+' . $suffix . '@example.com';
    $googleSub = 'google-reg-sub-' . $suffix;
    $created = AuthService::registerGooglePatient([
        'sub' => $googleSub,
        'email' => 'Google.Reg+' . $suffix . '@Example.COM',
        'email_verified' => true,
        'name' => 'Google Registered Patient',
        'role' => 'admin',
        'password' => 'should-be-ignored',
        'status' => 'inactive',
    ]);

    expect_true($created instanceof User && $created->id !== null, 'Valid Google identity creates a users row');
    if ($created instanceof User && $created->id !== null) {
        $createdUserIds[] = (int) $created->id;
    }

    expect_true($created !== null && (int) $created->role_id === (int) $patientRoleId, 'Created user has the patient role_id');
    expect_true($created !== null && $created->getRole() === 'patient', 'Created user role name is patient');
    expect_true($created !== null && $created->status === 'active', 'Created user status is active');
    expect_true($created !== null && $created->password === null, 'Created user password is NULL');
    expect_true($created !== null && $created->google_sub === $googleSub, 'Created user stores google_sub');
    expect_true($created !== null && $created->google_email === $googleEmail, 'Created user stores google_email');
    expect_true($created !== null && $created->email === $googleEmail, 'Created user stores normalized email');
    expect_true($created !== null && $created->auth_provider === User::AUTH_PROVIDER_GOOGLE, 'Created user auth_provider is google');
    expect_true($created !== null && $created->full_name === 'Google Registered Patient', 'Created user uses the Google display name');

    $patient = $created !== null && $created->id !== null ? Patient::findByUserId((int) $created->id) : null;
    expect_true($patient !== null && (int) $patient->user_id === (int) $created->id, 'Matching patient row exists for the new user');
    expect_true($patient !== null && $patient->dob === null && $patient->gender === null && $patient->address === null, 'Patient demographics remain unused');

    expect_true(!AuthService::isAuthenticated(), 'registerGooglePatient does not authenticate the user');
    expect_true(Session::get('user_id') === $sessionUserIdBefore, 'registerGooglePatient does not set session user_id');
    expect_true(Session::get('user_role') === $sessionRoleBefore, 'registerGooglePatient does not set session user_role');

    $duplicateSub = AuthService::registerGooglePatient([
        'sub' => $googleSub,
        'email' => 'other+' . $suffix . '@example.com',
        'email_verified' => true,
        'name' => 'Duplicate Sub',
    ]);
    expect_true($duplicateSub === null, 'Existing google_sub prevents duplicate creation');
    expect_true(User::findByEmail('other+' . $suffix . '@example.com') === null, 'Duplicate google_sub does not create another email account');
    $unchanged = User::findByGoogleSub($googleSub);
    expect_true($unchanged !== null && (int) $unchanged->id === (int) $created->id, 'Existing google_sub is not overwritten');

    $localEmail = 'local.reg+' . $suffix . '@example.com';
    $localUser = AuthService::register([
        'full_name' => 'Local Regression Patient',
        'email' => $localEmail,
        'password' => 'LocalPass!234',
        'dob' => '1990-01-15',
        'gender' => 'female',
        'address' => 'Alotau',
    ]);
    expect_true($localUser instanceof User && $localUser->id !== null, 'Existing local registration still creates a user');
    if ($localUser instanceof User && $localUser->id !== null) {
        $createdUserIds[] = (int) $localUser->id;
    }
    expect_true(
        $localUser !== null && is_string($localUser->password) && $localUser->password !== '' && password_verify('LocalPass!234', $localUser->password),
        'Existing local registration still hashes the password'
    );
    expect_true($localUser !== null && $localUser->status === 'active', 'Existing local registration still sets status active');
    expect_true($localUser !== null && $localUser->getRole() === 'patient', 'Existing local registration still assigns patient');
    expect_true($localUser !== null && $localUser->auth_provider === User::AUTH_PROVIDER_LOCAL, 'Existing local registration remains auth_provider local');
    $localPatient = $localUser !== null && $localUser->id !== null ? Patient::findByUserId((int) $localUser->id) : null;
    expect_true($localPatient !== null && $localPatient->dob === '1990-01-15', 'Existing local registration still creates the patient row');
    expect_true(!AuthService::isAuthenticated(), 'Local register() still does not create a session');

    $emailCollision = AuthService::registerGooglePatient([
        'sub' => 'google-email-collision-' . $suffix,
        'email' => $localEmail,
        'email_verified' => true,
        'name' => 'Should Not Link',
    ]);
    expect_true($emailCollision === null, 'Existing email prevents Google duplicate creation');
    $localAfterCollision = User::findByEmail($localEmail);
    expect_true($localAfterCollision !== null && $localAfterCollision->google_sub === null, 'Email collision does not auto-link google_sub');
    expect_true($localAfterCollision !== null && $localAfterCollision->auth_provider === User::AUTH_PROVIDER_LOCAL, 'Email collision does not change auth_provider');

    $deletedSub = 'deleted-google-sub-' . $suffix;
    $deletedHolder = User::createGoogleUser([
        'role_id' => (int) $patientRoleId,
        'full_name' => 'Deleted Holder',
        'email' => 'deleted.holder+' . $suffix . '@example.com',
        'google_sub' => $deletedSub,
        'google_email' => 'deleted.holder+' . $suffix . '@example.com',
        'status' => 'active',
    ]);
    expect_true($deletedHolder !== null && $deletedHolder->id !== null, 'Fixture user with google_sub can be created');
    if ($deletedHolder !== null && $deletedHolder->id !== null) {
        $createdUserIds[] = (int) $deletedHolder->id;
        $db->prepare("UPDATE users SET status = 'deleted' WHERE id = :id")->execute([':id' => $deletedHolder->id]);
    }
    $reuseDeletedSub = AuthService::registerGooglePatient([
        'sub' => $deletedSub,
        'email' => 'reuse.deleted+' . $suffix . '@example.com',
        'email_verified' => true,
        'name' => 'Should Not Reuse Sub',
    ]);
    expect_true($reuseDeletedSub === null, 'Existing google_sub on a deleted row is not silently overwritten');
    expect_true(User::findByEmail('reuse.deleted+' . $suffix . '@example.com') === null, 'Deleted google_sub collision creates no new user');

    $blankName = AuthService::registerGooglePatient([
        'sub' => 'blank-name-sub-' . $suffix,
        'email' => 'blank.name+' . $suffix . '@example.com',
        'email_verified' => true,
        'name' => '   ',
    ]);
    expect_true($blankName instanceof User && $blankName->full_name === 'Patient', 'Blank Google name uses the Patient fallback');
    if ($blankName instanceof User && $blankName->id !== null) {
        $createdUserIds[] = (int) $blankName->id;
    }

    $rollbackEmail = 'rollback+' . $suffix . '@example.com';
    $rollbackSub = 'rollback-sub-' . $suffix;
    AuthService::$testFailGooglePatientInsert = true;
    $rolledBack = AuthService::registerGooglePatient([
        'sub' => $rollbackSub,
        'email' => $rollbackEmail,
        'email_verified' => true,
        'name' => 'Rollback Patient',
    ]);
    AuthService::$testFailGooglePatientInsert = false;
    expect_true($rolledBack === null, 'Patient insert failure returns null');
    expect_true(User::findByEmail($rollbackEmail) === null, 'Patient insert failure rolls back the users row');
    expect_true(User::findByGoogleSub($rollbackSub) === null, 'Patient insert failure leaves no google_sub user');
    $orphan = $db->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
    $orphan->execute([':email' => $rollbackEmail]);
    expect_true((int) $orphan->fetchColumn() === 0, 'No orphan users row remains after rollback');

    $roleHidden = false;
    try {
        $db->exec("UPDATE roles SET name = 'patient_hidden_step7' WHERE name = 'patient'");
        $roleHidden = true;
        $missingRole = AuthService::registerGooglePatient([
            'sub' => 'missing-role-sub-' . $suffix,
            'email' => 'missing.role+' . $suffix . '@example.com',
            'email_verified' => true,
            'name' => 'Missing Role',
        ]);
        expect_true($missingRole === null, 'Missing patient role creates no user');
        expect_true(User::findByEmail('missing.role+' . $suffix . '@example.com') === null, 'Missing patient role creates no users row');
        $patientCountStmt = $db->prepare(
            "SELECT COUNT(*) FROM patient INNER JOIN users ON users.id = patient.user_id WHERE users.email = :email"
        );
        $patientCountStmt->execute([':email' => 'missing.role+' . $suffix . '@example.com']);
        expect_true((int) $patientCountStmt->fetchColumn() === 0, 'Missing patient role creates no patient row');
    } finally {
        if ($roleHidden) {
            $db->exec("UPDATE roles SET name = 'patient' WHERE name = 'patient_hidden_step7'");
        }
    }

    expect_true(User::findRoleIdByName('patient') === $patientRoleId, 'Patient role name was restored after the missing-role test');
} catch (Throwable $exception) {
    expect_true(false, 'Google patient registration tests completed without an unexpected exception: ' . $exception->getMessage());
} finally {
    AuthService::$testFailGooglePatientInsert = false;
    if (User::findRoleIdByName('patient') === null && User::findRoleIdByName('patient_hidden_step7') !== null) {
        $db->exec("UPDATE roles SET name = 'patient' WHERE name = 'patient_hidden_step7'");
    }
    foreach ($createdUserIds as $userId) {
        delete_test_user($db, $userId);
    }
}

echo PHP_EOL . "Passed: {$passed}" . PHP_EOL;
echo "Failed: {$failed}" . PHP_EOL;

exit($failed > 0 ? 1 : 0);
