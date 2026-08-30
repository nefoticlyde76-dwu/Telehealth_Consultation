<?php

/**
 * GoogleAuthService orchestration checks.
 *
 * Usage: php bin/test_google_auth_service.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\App;
use App\Config\Environment;
use App\Core\Database;
use App\Core\Session;
use App\Models\Admin;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use App\Services\AuthService;
use App\Services\GoogleAuthService;
use App\Services\GoogleIdTokenValidator;

$root = dirname(__DIR__);
Environment::load($root . '/.env');
Session::start();
ob_start();

$opensslConfigPath = '';
$existingOpenSslConf = getenv('OPENSSL_CONF');
$needsOpenSslConf = !is_string($existingOpenSslConf) || $existingOpenSslConf === '' || !is_file($existingOpenSslConf);
if ($needsOpenSslConf) {
    foreach ([
        dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'openssl' . DIRECTORY_SEPARATOR . 'openssl.cnf',
        dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf',
    ] as $opensslConfig) {
        if (is_file($opensslConfig)) {
            $opensslConfigPath = $opensslConfig;
            putenv('OPENSSL_CONF=' . $opensslConfig);
            break;
        }
    }
} else {
    $opensslConfigPath = $existingOpenSslConf;
}

$failed = 0;
$passed = 0;
$createdUserIds = [];
$errorLogPath = $root . '/tmp/google_auth_service_test.log';
if (!is_dir(dirname($errorLogPath))) {
    mkdir(dirname($errorLogPath), 0775, true);
}
file_put_contents($errorLogPath, '');
ini_set('error_log', $errorLogPath);

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

function b64url(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

/**
 * @return array{private: OpenSSLAsymmetricKey, jwks: array{keys: list<array<string, string>>}, kid: string}
 */
function make_test_rsa_jwks(string $kid = 'step8-kid'): array
{
    $config = [
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];
    global $opensslConfigPath;
    if (is_string($opensslConfigPath) && $opensslConfigPath !== '' && is_file($opensslConfigPath)) {
        $config['config'] = $opensslConfigPath;
    }

    $private = openssl_pkey_new($config);
    if ($private === false) {
        throw new RuntimeException('Unable to generate a test RSA key.');
    }

    $details = openssl_pkey_get_details($private);
    if ($details === false || !isset($details['rsa']['n'], $details['rsa']['e'])) {
        throw new RuntimeException('Unable to read the test RSA key.');
    }

    return [
        'private' => $private,
        'kid' => $kid,
        'jwks' => [
            'keys' => [[
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'kid' => $kid,
                'n' => b64url($details['rsa']['n']),
                'e' => b64url($details['rsa']['e']),
            ]],
        ],
    ];
}

/**
 * @param array<string, mixed> $header
 * @param array<string, mixed> $payload
 */
function sign_jwt(array $header, array $payload, OpenSSLAsymmetricKey $private): string
{
    $headerPart = b64url(json_encode($header, JSON_UNESCAPED_SLASHES));
    $payloadPart = b64url(json_encode($payload, JSON_UNESCAPED_SLASHES));
    $signingInput = $headerPart . '.' . $payloadPart;
    $signature = '';
    if (!openssl_sign($signingInput, $signature, $private, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Unable to sign the test JWT.');
    }

    return $signingInput . '.' . b64url($signature);
}

/**
 * @param array<string, mixed> $claims
 */
function google_token(array $rsa, string $clientId, array $claims): string
{
    $now = time();
    $payload = array_merge([
        'iss' => 'https://accounts.google.com',
        'aud' => $clientId,
        'azp' => $clientId,
        'exp' => $now + 3600,
        'iat' => $now,
        'email_verified' => true,
        'name' => 'Google Patient',
    ], $claims);

    $header = [
        'alg' => 'RS256',
        'typ' => 'JWT',
        'kid' => $rsa['kid'],
    ];

    return sign_jwt($header, $payload, $rsa['private']);
}

function forget_login(): void
{
    Session::remove('user_id');
    Session::remove('user_role');
}

function track_user(?User $user): void
{
    global $createdUserIds;
    if ($user instanceof User && $user->id !== null) {
        $createdUserIds[] = (int) $user->id;
    }
}

function delete_test_user(\PDO $db, int $userId): void
{
    $db->prepare('DELETE FROM user_sessions WHERE user_id = :id')->execute([':id' => $userId]);
    $db->prepare('DELETE FROM patient WHERE user_id = :id')->execute([':id' => $userId]);
    $db->prepare('DELETE FROM doctor WHERE user_id = :id')->execute([':id' => $userId]);
    $db->prepare('DELETE FROM admin WHERE user_id = :id')->execute([':id' => $userId]);
    $db->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $userId]);
}

function create_local_user(int $roleId, string $name, string $email, string $password): User
{
    $user = new User();
    $user->role_id = $roleId;
    $user->full_name = $name;
    $user->email = $email;
    $user->password = password_hash($password, PASSWORD_DEFAULT);
    $user->status = 'active';
    if (!$user->save() || $user->id === null) {
        throw new RuntimeException('Unable to create a local test user.');
    }
    track_user($user);

    return User::findById((int) $user->id) ?? $user;
}

function source_of(string $relativePath): string
{
    global $root;
    $source = file_get_contents($root . '/' . $relativePath);
    if (!is_string($source)) {
        return '';
    }

    return $source;
}

$db = Database::getInstance();
$suffix = bin2hex(random_bytes(6));
$patientRoleId = User::findRoleIdByName('patient');
$doctorRoleId = User::findRoleIdByName('doctor');
$adminRoleId = User::findRoleIdByName('admin');
$clientId = trim((string) (App::getConfig()['google']['client_id'] ?? ''));
$rsa = make_test_rsa_jwks();
GoogleAuthService::$testJwksOverride = $rsa['jwks'];
AuthService::$testFailGooglePatientInsert = false;
forget_login();

$serviceSource = source_of('app/Services/GoogleAuthService.php');
$authSource = source_of('app/Services/AuthService.php');

try {
    expect_true($patientRoleId !== null && $doctorRoleId !== null && $adminRoleId !== null, 'Patient, doctor, and admin roles exist');
    expect_true($clientId !== '', 'GOOGLE_CLIENT_ID is configured for signed test tokens');
    expect_true(str_contains($serviceSource, 'GoogleIdTokenValidator::validate'), 'Raw ID token is passed to GoogleIdTokenValidator');
    expect_true(str_contains($serviceSource, 'AuthService::login'), 'Successful resolution delegates to AuthService::login');
    expect_true(str_contains($serviceSource, 'AuthService::registerGooglePatient'), 'New accounts call registerGooglePatient');
    expect_true(!str_contains($serviceSource, '$_SESSION'), 'GoogleAuthService does not manipulate $_SESSION');
    expect_true(!str_contains($serviceSource, 'Session::set'), 'GoogleAuthService does not set session keys itself');
    expect_true(!str_contains($serviceSource, 'session_start'), 'GoogleAuthService does not start its own PHP session');
    expect_true(!str_contains($serviceSource, 'tokeninfo'), 'GoogleAuthService does not call tokeninfo');
    expect_true(!str_contains($serviceSource, 'access_token') && !str_contains($serviceSource, 'refresh_token'), 'GoogleAuthService does not use access or refresh tokens');
    expect_true(!str_contains($serviceSource, 'client_secret'), 'GoogleAuthService does not use a client secret');
    expect_true(!str_contains($serviceSource, 'error_log') || !preg_match('/error_log\([^)]*\$rawIdToken/', $serviceSource), 'Raw ID token is not passed to error_log');
    expect_true(!str_contains($serviceSource, 'bypass') && !str_contains($serviceSource, 'if (testing)'), 'Production code has no signature bypass');
    expect_true(!str_contains($authSource, 'GoogleAuthService'), 'AuthService remains independent of GoogleAuthService');

    $usersBeforeInvalid = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $invalidToken = 'not-a-jwt-' . $suffix;
    $invalidResult = GoogleAuthService::authenticate($invalidToken);
    expect_true($invalidResult === null, 'Validator failure results in authentication failure');
    expect_true(GoogleAuthService::lastFailureReason() === GoogleAuthService::REASON_INVALID_GOOGLE_IDENTITY, 'Validator failure uses invalid_google_identity');
    expect_true((int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() === $usersBeforeInvalid, 'No database mutation occurs when validation fails');
    expect_true(!AuthService::isAuthenticated(), 'Failed validation does not authenticate');
    $logAfterInvalid = (string) file_get_contents($errorLogPath);
    expect_true(!str_contains($logAfterInvalid, $invalidToken), 'Raw Google token is never logged');

    $newEmail = 'new.google+' . $suffix . '@example.com';
    $newSub = 'new-google-sub-' . $suffix;
    $newToken = google_token($rsa, $clientId, [
        'sub' => $newSub,
        'email' => 'New.Google+' . $suffix . '@Example.COM',
        'name' => 'Brand New Google Patient',
        'role' => 'admin',
    ]);
    $created = GoogleAuthService::authenticate($newToken);
    track_user($created);
    expect_true($created instanceof User && $created->id !== null, 'Brand-new verified Google identity creates a user');
    expect_true($created !== null && $created->getRole() === 'patient', 'New Google account is a patient');
    expect_true($created !== null && $created->status === 'active', 'New Google account is active');
    expect_true($created !== null && $created->password === null, 'New Google account password is NULL');
    expect_true($created !== null && $created->google_sub === $newSub, 'New Google account stores google_sub');
    expect_true($created !== null && $created->google_email === $newEmail, 'New Google account stores google_email');
    expect_true($created !== null && $created->auth_provider === User::AUTH_PROVIDER_GOOGLE, 'New Google account auth_provider is google');
    expect_true($created !== null && $created->email === $newEmail, 'New Google account stores normalized email');
    $newPatient = $created !== null && $created->id !== null ? Patient::findByUserId((int) $created->id) : null;
    expect_true($newPatient !== null, 'New Google account creates a patient row');
    expect_true(
        $created !== null
        && AuthService::isAuthenticated()
        && (int) Session::get('user_id') === (int) $created->id
        && Session::get('user_role') === 'patient',
        'New Google account is logged in through AuthService::login'
    );
    $repeatCreate = GoogleAuthService::authenticate($newToken);
    expect_true($repeatCreate instanceof User && $created !== null && (int) $repeatCreate->id === (int) $created->id, 'Existing google_sub patient is found on return');
    $emailCount = $db->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
    $emailCount->execute([':email' => $newEmail]);
    expect_true((int) $emailCount->fetchColumn() === 1, 'Returning Google sign-in does not create a duplicate account');
    expect_true((int) Session::get('user_id') === (int) $created->id, 'Returning Google patient is logged in through AuthService::login');

    $updatedEmailToken = google_token($rsa, $clientId, [
        'sub' => $newSub,
        'email' => 'updated.google+' . $suffix . '@example.com',
        'name' => 'Brand New Google Patient',
    ]);
    $afterEmailChange = GoogleAuthService::authenticate($updatedEmailToken);
    expect_true($afterEmailChange !== null && $afterEmailChange->google_email === 'updated.google+' . $suffix . '@example.com', 'Returning Google patient can refresh google_email');
    expect_true($afterEmailChange !== null && $afterEmailChange->email === $newEmail, 'Returning Google patient local email is unchanged');
    expect_true($afterEmailChange !== null && $afterEmailChange->password === null, 'Returning Google patient password remains NULL');
    expect_true($afterEmailChange !== null && (int) $afterEmailChange->role_id === (int) $patientRoleId, 'Returning Google patient role is unchanged');
    forget_login();

    $doctorSub = 'doctor-google-sub-' . $suffix;
    $doctorGoogle = User::createGoogleUser([
        'role_id' => (int) $doctorRoleId,
        'full_name' => 'Google Doctor Fixture',
        'email' => 'google.doctor+' . $suffix . '@example.com',
        'google_sub' => $doctorSub,
        'google_email' => 'google.doctor+' . $suffix . '@example.com',
        'status' => 'active',
    ]);
    track_user($doctorGoogle);
    $doctorToken = google_token($rsa, $clientId, [
        'sub' => $doctorSub,
        'email' => 'google.doctor+' . $suffix . '@example.com',
        'name' => 'Google Doctor Fixture',
    ]);
    $doctorAuth = GoogleAuthService::authenticate($doctorToken);
    $doctorAfter = User::findByGoogleSub($doctorSub);
    expect_true($doctorAuth === null, 'Existing doctor with the Google sub is rejected');
    expect_true(GoogleAuthService::lastFailureReason() === GoogleAuthService::REASON_GOOGLE_USER_NOT_ALLOWED, 'Non-patient Google identity uses google_user_not_allowed');
    expect_true($doctorAfter !== null && $doctorAfter->getRole() === 'doctor', 'Doctor role is not changed by Google patient sign-in');
    expect_true($doctorAfter !== null && $doctorAfter->google_sub === $doctorSub, 'Doctor google_sub is not modified');
    expect_true(!AuthService::isAuthenticated(), 'Rejected doctor Google identity does not create a session');

    $adminSub = 'admin-google-sub-' . $suffix;
    $adminGoogle = User::createGoogleUser([
        'role_id' => (int) $adminRoleId,
        'full_name' => 'Google Admin Fixture',
        'email' => 'google.admin+' . $suffix . '@example.com',
        'google_sub' => $adminSub,
        'google_email' => 'google.admin+' . $suffix . '@example.com',
        'status' => 'active',
    ]);
    track_user($adminGoogle);
    $adminToken = google_token($rsa, $clientId, [
        'sub' => $adminSub,
        'email' => 'google.admin+' . $suffix . '@example.com',
        'name' => 'Google Admin Fixture',
    ]);
    $adminAuth = GoogleAuthService::authenticate($adminToken);
    $adminAfter = User::findByGoogleSub($adminSub);
    expect_true($adminAuth === null, 'Existing admin with the Google sub is rejected');
    expect_true($adminAfter !== null && $adminAfter->getRole() === 'admin', 'Admin role is not changed by Google patient sign-in');
    expect_true($adminAfter !== null && $adminAfter->auth_provider === User::AUTH_PROVIDER_GOOGLE, 'Admin auth_provider is not rewritten');
    expect_true(!AuthService::isAuthenticated(), 'Rejected admin Google identity does not create a session');

    $localEmail = 'local.link+' . $suffix . '@example.com';
    $localPassword = 'LocalPass!234';
    $localUser = create_local_user((int) $patientRoleId, 'Local Link Patient', $localEmail, $localPassword);
    $localPatient = new Patient();
    $localPatient->user_id = $localUser->id;
    expect_true($localPatient->save(), 'Local patient fixture has a patient row');
    $originalPassword = (string) $localUser->password;
    $originalEmail = (string) $localUser->email;
    $originalRoleId = (int) $localUser->role_id;
    $originalStatus = (string) $localUser->status;
    $linkSub = 'link-google-sub-' . $suffix;
    $linkToken = google_token($rsa, $clientId, [
        'sub' => $linkSub,
        'email' => $localEmail,
        'name' => 'Local Link Patient',
    ]);
    $linked = GoogleAuthService::authenticate($linkToken);
    expect_true($linked instanceof User && (int) $linked->id === (int) $localUser->id, 'Matching verified email finds the existing local patient');
    expect_true($linked !== null && $linked->auth_provider === User::AUTH_PROVIDER_BOTH, 'linkGoogleIdentity sets auth_provider to both');
    expect_true($linked !== null && $linked->google_sub === $linkSub, 'linkGoogleIdentity stores google_sub');
    expect_true($linked !== null && $linked->password === $originalPassword, 'Linking preserves the password hash');
    expect_true($linked !== null && $linked->email === $originalEmail, 'Linking preserves the local email');
    expect_true($linked !== null && (int) $linked->role_id === $originalRoleId, 'Linking preserves the patient role');
    expect_true($linked !== null && $linked->status === $originalStatus, 'Linking preserves status');
    expect_true(password_verify($localPassword, (string) $linked->password), 'Linked patient can still use the original password');
    expect_true(
        AuthService::isAuthenticated()
        && (int) Session::get('user_id') === (int) $localUser->id
        && Session::get('user_role') === 'patient',
        'Linked local patient is logged in through AuthService::login'
    );
    $patientCount = $db->prepare('SELECT COUNT(*) FROM patient WHERE user_id = :id');
    $patientCount->execute([':id' => $localUser->id]);
    expect_true((int) $patientCount->fetchColumn() === 1, 'Linking does not create a second patient account');
    forget_login();

    $mismatchSub = 'mismatch-google-sub-' . $suffix;
    $mismatchEmail = 'mismatch.google+' . $suffix . '@example.com';
    $existingGoogle = AuthService::registerGooglePatient([
        'sub' => 'stored-google-sub-' . $suffix,
        'email' => $mismatchEmail,
        'email_verified' => true,
        'name' => 'Stored Google Patient',
    ]);
    track_user($existingGoogle);
    $mismatchToken = google_token($rsa, $clientId, [
        'sub' => $mismatchSub,
        'email' => $mismatchEmail,
        'name' => 'Other Google Account',
    ]);
    $mismatchAuth = GoogleAuthService::authenticate($mismatchToken);
    $mismatchAfter = User::findByEmail($mismatchEmail);
    expect_true($mismatchAuth === null, 'Existing Google patient with a different sub is rejected');
    expect_true(GoogleAuthService::lastFailureReason() === GoogleAuthService::REASON_GOOGLE_IDENTITY_COLLISION, 'Different google_sub uses google_identity_collision');
    expect_true($mismatchAfter !== null && $mismatchAfter->google_sub === 'stored-google-sub-' . $suffix, 'Existing google_sub is not overwritten');
    expect_true(User::findByGoogleSub($mismatchSub) === null, 'Mismatched Google identity does not attach to another patient');
    expect_true(!AuthService::isAuthenticated(), 'Google mismatch does not create a session');

    $bothEmail = 'both.collision+' . $suffix . '@example.com';
    $bothUser = create_local_user((int) $patientRoleId, 'Both Collision Patient', $bothEmail, 'BothPass!234');
    expect_true(User::linkGoogleIdentity((int) $bothUser->id, 'already-linked-sub-' . $suffix, $bothEmail), 'Fixture patient can be pre-linked');
    $bothToken = google_token($rsa, $clientId, [
        'sub' => 'other-linked-sub-' . $suffix,
        'email' => $bothEmail,
        'name' => 'Both Collision Patient',
    ]);
    $bothAuth = GoogleAuthService::authenticate($bothToken);
    $bothAfter = User::findById((int) $bothUser->id);
    expect_true($bothAuth === null, 'Patient with auth_provider both and a different sub is rejected');
    expect_true($bothAfter !== null && $bothAfter->google_sub === 'already-linked-sub-' . $suffix, 'Linked google_sub is not replaced');
    expect_true($bothAfter !== null && $bothAfter->auth_provider === User::AUTH_PROVIDER_BOTH, 'Linked auth_provider remains both');

    $doctorEmail = 'doctor.email+' . $suffix . '@example.com';
    $doctorLocal = create_local_user((int) $doctorRoleId, 'Email Collision Doctor', $doctorEmail, 'DoctorPass!234');
    $doctorProfile = new Doctor();
    $doctorProfile->user_id = $doctorLocal->id;
    $doctorProfile->employee_id = 'GD' . $suffix;
    $doctorProfile->license_number = 'LIC' . $suffix;
    expect_true($doctorProfile->save(), 'Doctor email fixture has a doctor row');
    $doctorEmailToken = google_token($rsa, $clientId, [
        'sub' => 'doctor-email-sub-' . $suffix,
        'email' => $doctorEmail,
        'name' => 'Email Collision Doctor',
    ]);
    $doctorEmailAuth = GoogleAuthService::authenticate($doctorEmailToken);
    $doctorEmailAfter = User::findByEmail($doctorEmail);
    expect_true($doctorEmailAuth === null, 'Existing doctor email is rejected');
    expect_true(GoogleAuthService::lastFailureReason() === GoogleAuthService::REASON_EMAIL_COLLISION, 'Doctor email collision uses email_collision');
    expect_true($doctorEmailAfter !== null && $doctorEmailAfter->google_sub === null, 'Doctor email collision does not auto-link');
    expect_true($doctorEmailAfter !== null && $doctorEmailAfter->auth_provider === User::AUTH_PROVIDER_LOCAL, 'Doctor auth_provider remains local');
    expect_true($doctorEmailAfter !== null && $doctorEmailAfter->getRole() === 'doctor', 'Doctor role is unchanged after email collision');
    expect_true(User::findByGoogleSub('doctor-email-sub-' . $suffix) === null, 'Doctor email collision does not create another user');

    $adminEmail = 'admin.email+' . $suffix . '@example.com';
    $adminLocal = create_local_user((int) $adminRoleId, 'Email Collision Admin', $adminEmail, 'AdminPass!234');
    $adminProfile = new Admin();
    $adminProfile->user_id = $adminLocal->id;
    $adminProfile->employee_id = 'GA' . $suffix;
    expect_true($adminProfile->save(), 'Admin email fixture has an admin row');
    $adminEmailToken = google_token($rsa, $clientId, [
        'sub' => 'admin-email-sub-' . $suffix,
        'email' => $adminEmail,
        'name' => 'Email Collision Admin',
    ]);
    $adminEmailAuth = GoogleAuthService::authenticate($adminEmailToken);
    $adminEmailAfter = User::findByEmail($adminEmail);
    expect_true($adminEmailAuth === null, 'Existing admin email is rejected');
    expect_true($adminEmailAfter !== null && $adminEmailAfter->google_sub === null, 'Admin email collision does not auto-link');
    expect_true($adminEmailAfter !== null && $adminEmailAfter->getRole() === 'admin', 'Admin role is unchanged after email collision');
    expect_true(User::findByGoogleSub('admin-email-sub-' . $suffix) === null, 'Admin email collision does not create another user');
    expect_true(!AuthService::isAuthenticated(), 'Staff email collision does not create a session');

    $deletedSub = 'deleted-google-sub-' . $suffix;
    $deletedUser = User::createGoogleUser([
        'role_id' => (int) $patientRoleId,
        'full_name' => 'Deleted Google Patient',
        'email' => 'deleted.google+' . $suffix . '@example.com',
        'google_sub' => $deletedSub,
        'google_email' => 'deleted.google+' . $suffix . '@example.com',
        'status' => 'active',
    ]);
    track_user($deletedUser);
    if ($deletedUser !== null && $deletedUser->id !== null) {
        $db->prepare("UPDATE users SET status = 'deleted', deleted_at = NOW(), anonymized_at = NOW() WHERE id = :id")
            ->execute([':id' => $deletedUser->id]);
    }
    $deletedToken = google_token($rsa, $clientId, [
        'sub' => $deletedSub,
        'email' => 'reuse.deleted+' . $suffix . '@example.com',
        'name' => 'Should Not Resurrect',
    ]);
    $deletedAuth = GoogleAuthService::authenticate($deletedToken);
    $deletedHolderAfter = User::findById((int) $deletedUser->id);
    $replacement = User::findByGoogleSub($deletedSub);
    expect_true($deletedHolderAfter !== null && $deletedHolderAfter->status === 'deleted', 'Deleted account status remains deleted');
    expect_true($deletedHolderAfter !== null && $deletedHolderAfter->google_sub === null, 'Deleted google_sub is released so it can be reused');
    expect_true($deletedAuth instanceof User && (int) $deletedAuth->id !== (int) $deletedUser->id, 'Same Google identity can register a new patient after deletion');
    expect_true($replacement instanceof User && (int) $replacement->id === (int) $deletedAuth->id, 'Replacement patient owns the released google_sub');
    expect_true($replacement !== null && $replacement->status === 'active' && $replacement->getRole() === 'patient', 'Replacement Google patient is an active patient');
    track_user($deletedAuth);
    forget_login();

    $suspended = AuthService::registerGooglePatient([
        'sub' => 'suspended-google-sub-' . $suffix,
        'email' => 'suspended.google+' . $suffix . '@example.com',
        'email_verified' => true,
        'name' => 'Suspended Google Patient',
    ]);
    track_user($suspended);
    if ($suspended !== null && $suspended->id !== null) {
        $db->prepare("UPDATE users SET status = 'suspended' WHERE id = :id")->execute([':id' => $suspended->id]);
    }
    $suspendedToken = google_token($rsa, $clientId, [
        'sub' => 'suspended-google-sub-' . $suffix,
        'email' => 'suspended.google+' . $suffix . '@example.com',
        'name' => 'Suspended Google Patient',
    ]);
    $suspendedAuth = GoogleAuthService::authenticate($suspendedToken);
    $suspendedAfter = User::findByGoogleSub('suspended-google-sub-' . $suffix);
    expect_true($suspendedAuth === null, 'Suspended Google patient is not authenticated');
    expect_true(GoogleAuthService::lastFailureReason() === GoogleAuthService::REASON_ACCOUNT_DISABLED, 'Suspended account uses account_disabled');
    expect_true($suspendedAfter !== null && $suspendedAfter->status === 'suspended', 'Suspended status is not silently reactivated');

    $localRegEmail = 'local.reg+' . $suffix . '@example.com';
    $localReg = AuthService::register([
        'full_name' => 'Local Regression Patient',
        'email' => $localRegEmail,
        'password' => 'LocalPass!234',
        'dob' => '1990-01-15',
        'gender' => 'female',
        'address' => 'Alotau',
    ]);
    track_user($localReg);
    expect_true($localReg instanceof User && $localReg->getRole() === 'patient', 'Existing local registration remains unchanged');
    expect_true($localReg !== null && password_verify('LocalPass!234', (string) $localReg->password), 'Existing local registration still hashes the password');
    expect_true($localReg !== null && $localReg->auth_provider === User::AUTH_PROVIDER_LOCAL, 'Existing local registration remains auth_provider local');
    $authenticatedLocal = AuthService::authenticate($localRegEmail, 'LocalPass!234');
    expect_true($authenticatedLocal instanceof User && (int) $authenticatedLocal->id === (int) $localReg->id, 'Existing local password login remains unchanged');
    $authenticatedDoctor = AuthService::authenticate($doctorEmail, 'DoctorPass!234');
    expect_true($authenticatedDoctor instanceof User && $authenticatedDoctor->getRole() === 'doctor', 'Existing doctor authentication remains unchanged');
    $authenticatedAdmin = AuthService::authenticate($adminEmail, 'AdminPass!234');
    expect_true($authenticatedAdmin instanceof User && $authenticatedAdmin->getRole() === 'admin', 'Existing admin authentication remains unchanged');
    expect_true(
        !preg_match('/function\s+login\s*\([^)]*google/i', $authSource)
        && str_contains($authSource, 'function login(int $userId, string $role)'),
        'AuthService::login signature is unchanged'
    );
    expect_true(str_contains($authSource, 'function authenticate(string $email, string $password)'), 'AuthService::authenticate signature is unchanged');

    $freshToken = google_token($rsa, $clientId, [
        'sub' => 'session-check-sub-' . $suffix,
        'email' => 'session.check+' . $suffix . '@example.com',
        'name' => 'Session Check Patient',
    ]);
    forget_login();
    $sessionUser = GoogleAuthService::authenticate($freshToken);
    track_user($sessionUser);
    expect_true(
        $sessionUser !== null
        && (int) AuthService::getUserId() === (int) $sessionUser->id
        && AuthService::getUserRole() === 'patient',
        'Authenticated session fields come from AuthService::login'
    );
    expect_true(!str_contains(source_of('app/Core/Session.php'), 'GoogleAuthService'), 'Session.php was not changed for Google authentication');
} catch (Throwable $exception) {
    expect_true(false, 'GoogleAuthService tests completed without an unexpected exception: ' . $exception->getMessage());
} finally {
    GoogleAuthService::$testJwksOverride = null;
    AuthService::$testFailGooglePatientInsert = false;
    forget_login();
    $createdUserIds = array_values(array_unique($createdUserIds));
    foreach ($createdUserIds as $userId) {
        try {
            delete_test_user($db, $userId);
        } catch (Throwable) {
        }
    }
}

echo PHP_EOL . "Passed: {$passed}" . PHP_EOL;
echo "Failed: {$failed}" . PHP_EOL;
ob_end_flush();

exit($failed > 0 ? 1 : 0);
