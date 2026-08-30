<?php

/**
 * GoogleAuthController HTTP endpoint checks.
 *
 * Usage: php bin/test_google_auth_controller.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\App;
use App\Config\Environment;
use App\Controllers\GoogleAuthController;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Helpers\Helper;
use App\Models\Patient;
use App\Models\User;
use App\Services\AuthService;
use App\Services\GoogleAuthService;

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
$errorLogPath = $root . '/tmp/google_auth_controller_test.log';
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
function make_test_rsa_jwks(string $kid = 'step9-kid'): array
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

    return sign_jwt([
        'alg' => 'RS256',
        'typ' => 'JWT',
        'kid' => $rsa['kid'],
    ], $payload, $rsa['private']);
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

/**
 * @param array{method?:string,content_type?:string,body?:string|null,action?:string} $options
 * @return array{status:int,raw:string,json:?array}
 */
function invoke_google_auth(array $options = []): array
{
    http_response_code(200);
    $_SERVER['REQUEST_METHOD'] = $options['method'] ?? 'POST';
    $_SERVER['CONTENT_TYPE'] = $options['content_type'] ?? 'application/json';
    GoogleAuthController::$testRawBody = array_key_exists('body', $options) ? $options['body'] : null;

    ob_start();
    $controller = new GoogleAuthController();
    $action = $options['action'] ?? 'authenticate';
    $controller->{$action}();
    $raw = (string) ob_get_clean();

    GoogleAuthController::$testRawBody = null;
    $decoded = json_decode($raw, true);

    return [
        'status' => (int) http_response_code(),
        'raw' => $raw,
        'json' => is_array($decoded) ? $decoded : null,
    ];
}

function json_body(array $payload): string
{
    return (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
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

$controllerSource = (string) file_get_contents($root . '/app/Controllers/GoogleAuthController.php');
$routesSource = (string) file_get_contents($root . '/routes/web.php');
$csrfSource = (string) file_get_contents($root . '/app/Core/Csrf.php');
$sessionSource = (string) file_get_contents($root . '/app/Core/Session.php');
$authControllerSource = (string) file_get_contents($root . '/app/Controllers/AuthController.php');
$googleAuthServiceSource = (string) file_get_contents($root . '/app/Services/GoogleAuthService.php');

try {
    expect_true(str_contains($routesSource, "post('/auth/google', [GoogleAuthController::class, 'authenticate'])"), 'POST /auth/google resolves to GoogleAuthController::authenticate');
    expect_true(str_contains($routesSource, "get('/auth/google', [GoogleAuthController::class, 'methodNotAllowed'])"), 'GET /auth/google is registered as method not allowed');
    expect_true(!str_contains($routesSource, '/auth/google/callback'), 'No OAuth callback route exists');
    expect_true(!str_contains($routesSource, '/oauth/google'), 'No /oauth/google route exists');
    expect_true(!preg_match("/post\\('\\/auth\\/google'[^\\n]*RoleMiddleware/", $routesSource), 'POST /auth/google has no role middleware');
    expect_true(str_contains($routesSource, "get('/login', [AuthController::class, 'login'])"), 'Existing /login GET route remains unchanged');
    expect_true(str_contains($routesSource, "post('/login', [AuthController::class, 'login'])"), 'Existing /login POST route remains unchanged');
    expect_true(str_contains($routesSource, "get('/register', [AuthController::class, 'register'])"), 'Existing /register GET route remains unchanged');
    expect_true(str_contains($routesSource, "post('/register', [AuthController::class, 'register'])"), 'Existing /register POST route remains unchanged');

    expect_true(str_contains($controllerSource, 'GoogleAuthService::authenticate'), 'Controller calls GoogleAuthService::authenticate');
    expect_true(!str_contains($controllerSource, 'GoogleIdTokenValidator'), 'Controller does not call the validator directly');
    expect_true(!str_contains($controllerSource, 'User::findByGoogleSub') && !str_contains($controllerSource, 'User::findByEmail'), 'Controller does not call User lookup methods');
    expect_true(!str_contains($controllerSource, 'registerGooglePatient') && !str_contains($controllerSource, 'linkGoogleIdentity'), 'Controller does not call account-creation helpers');
    expect_true(!str_contains($controllerSource, '$_SESSION'), 'Controller does not manually assign $_SESSION authentication');
    expect_true(!preg_match('/AuthService::login\s*\(/', $controllerSource), 'Controller does not create the session itself');
    expect_true(!str_contains($controllerSource, 'explode(') && !str_contains($controllerSource, 'base64_decode'), 'Controller does not decode JWT claims');
    expect_true(!str_contains($controllerSource, 'tokeninfo') && !str_contains($controllerSource, 'access_token') && !str_contains($controllerSource, 'refresh_token'), 'Controller does not use OAuth token endpoints');
    expect_true(!str_contains($controllerSource, 'Access-Control-Allow-Origin'), 'Controller does not add a wildcard CORS policy');
    expect_true(str_contains($controllerSource, 'Csrf::verify'), 'Controller uses the existing Csrf.php mechanism');
    expect_true($csrfSource !== '' && !str_contains($sessionSource, 'GoogleAuth'), 'Csrf.php and Session.php were not changed for Google authentication');
    expect_true(!str_contains($googleAuthServiceSource, 'GoogleAuthController'), 'GoogleAuthService was not modified to depend on the controller');

    $csrfToken = Csrf::generate();

    $get = invoke_google_auth(['method' => 'GET', 'action' => 'methodNotAllowed', 'body' => null]);
    expect_true($get['status'] === 405, 'GET is rejected with 405');
    expect_true(($get['json']['success'] ?? true) === false, 'GET rejection is a controlled JSON failure');

    $put = invoke_google_auth(['method' => 'PUT', 'action' => 'methodNotAllowed', 'body' => null]);
    expect_true($put['status'] === 405, 'PUT is rejected with 405');

    $patch = invoke_google_auth(['method' => 'PATCH', 'action' => 'methodNotAllowed', 'body' => null]);
    expect_true($patch['status'] === 405, 'PATCH is rejected with 405');

    $delete = invoke_google_auth(['method' => 'DELETE', 'action' => 'methodNotAllowed', 'body' => null]);
    expect_true($delete['status'] === 405, 'DELETE is rejected with 405');

    $missingBody = invoke_google_auth(['body' => '', 'content_type' => 'application/json']);
    expect_true($missingBody['status'] === 400, 'Missing body is rejected with 400');

    $invalidJson = invoke_google_auth(['body' => '{not-json', 'content_type' => 'application/json']);
    expect_true($invalidJson['status'] === 400, 'Invalid JSON is rejected with 400');
    expect_true(!str_contains($invalidJson['raw'], '{not-json'), 'Invalid JSON is not echoed back');

    $wrongType = invoke_google_auth([
        'content_type' => 'application/x-www-form-urlencoded',
        'body' => json_body(['credential' => 'abc', '_token' => $csrfToken]),
    ]);
    expect_true($wrongType['status'] === 400, 'Non-JSON content type is rejected');

    $missingCredential = invoke_google_auth([
        'body' => json_body(['_token' => $csrfToken]),
    ]);
    expect_true($missingCredential['status'] === 400, 'Missing credential is rejected with 400');

    $emptyCredential = invoke_google_auth([
        'body' => json_body(['credential' => '   ', '_token' => $csrfToken]),
    ]);
    $reasonBeforeEmpty = GoogleAuthService::lastFailureReason();
    expect_true($emptyCredential['status'] === 400, 'Empty credential is rejected with 400');

    $nonString = invoke_google_auth([
        'body' => json_body(['credential' => ['not', 'a', 'string'], '_token' => $csrfToken]),
    ]);
    expect_true($nonString['status'] === 400, 'Non-string credential is rejected with 400');

    $aliasToken = invoke_google_auth([
        'body' => json_body(['id_token' => 'abc', 'token' => 'abc', '_token' => $csrfToken]),
    ]);
    expect_true($aliasToken['status'] === 400, 'token/id_token aliases are not accepted');

    $missingCsrf = invoke_google_auth([
        'body' => json_body(['credential' => 'abc']),
    ]);
    expect_true($missingCsrf['status'] === 419, 'Missing CSRF token is rejected with 419');
    expect_true(!str_contains((string) json_encode($missingCsrf['json']), 'abc'), 'Credential is not returned in CSRF errors');

    $usersBefore = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $reasonBefore = GoogleAuthService::lastFailureReason();
    $emptyAfter = invoke_google_auth([
        'body' => json_body(['credential' => '', '_token' => $csrfToken]),
    ]);
    expect_true($emptyAfter['status'] === 400, 'Blank credential does not authenticate');
    expect_true(GoogleAuthService::lastFailureReason() === $reasonBefore || GoogleAuthService::lastFailureReason() === $reasonBeforeEmpty, 'Empty credential does not reach GoogleAuthService');
    expect_true((int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn() === $usersBefore, 'Invalid requests do not mutate users');

    $garbageCredential = 'not-a-jwt-' . $suffix;
    $reachesService = invoke_google_auth([
        'body' => json_body(['credential' => $garbageCredential, '_token' => $csrfToken]),
    ]);
    expect_true($reachesService['status'] === 401, 'Valid JSON with a credential reaches GoogleAuthService');
    expect_true(GoogleAuthService::lastFailureReason() === GoogleAuthService::REASON_INVALID_GOOGLE_IDENTITY, 'Controller delegates token validation to GoogleAuthService');
    expect_true(($reachesService['json']['success'] ?? true) === false, 'Google service failure produces a controlled failure response');
    expect_true(($reachesService['json']['message'] ?? '') === 'Google sign-in could not be verified. Please try again.', 'Authentication failure uses a patient-facing verification message');
    expect_true(!str_contains($reachesService['raw'], 'invalid_google_identity'), 'Internal reason codes do not leak to the client');
    expect_true(!str_contains($reachesService['raw'], 'Stack trace') && !str_contains($reachesService['raw'], 'GoogleIdTokenException'), 'Stack traces do not leak to the client');
    expect_true(!str_contains($reachesService['raw'], $garbageCredential), 'Google token is not returned in the failure response');
    expect_true(!AuthService::isAuthenticated(), 'Failed Google authentication does not leave a logged-in user');
    $logAfterFailure = (string) file_get_contents($errorLogPath);
    expect_true(!str_contains($logAfterFailure, $garbageCredential), 'Raw credential is not logged');
    expect_true(Session::get('credential') === null && Session::get('google_token') === null, 'Raw credential is not stored in session');

    $newEmail = 'controller.google+' . $suffix . '@example.com';
    $newSub = 'controller-google-sub-' . $suffix;
    $validToken = google_token($rsa, $clientId, [
        'sub' => $newSub,
        'email' => $newEmail,
        'name' => 'Controller Google Patient',
    ]);
    forget_login();
    $success = invoke_google_auth([
        'body' => json_body(['credential' => $validToken, '_token' => $csrfToken]),
    ]);
    $created = User::findByGoogleSub($newSub);
    track_user($created);
    expect_true($success['status'] === 200, 'Successful service result returns HTTP 200');
    expect_true(($success['json']['success'] ?? false) === true, 'Successful service result produces the expected success response');
    $expectedPatientRedirect = Helper::browserPath('/patient/dashboard');
    expect_true(($success['json']['redirect'] ?? '') === $expectedPatientRedirect, 'Success redirect uses the application patient dashboard path');
    expect_true(str_ends_with($expectedPatientRedirect, '/patient/dashboard'), 'Application patient dashboard path still ends at /patient/dashboard');
    expect_true(str_starts_with($expectedPatientRedirect, '/') && !str_starts_with($expectedPatientRedirect, '//'), 'Redirect remains a same-origin path');
    expect_true(!str_contains($expectedPatientRedirect, '://'), 'Redirect is not an external URL');
    expect_true($created instanceof User && $created->getRole() === 'patient', 'Successful request creates or resolves a patient');
    expect_true($created !== null && Patient::findByUserId((int) $created->id) !== null, 'Successful Google sign-in has a patient row');
    expect_true(
        AuthService::isAuthenticated()
        && (int) Session::get('user_id') === (int) $created->id
        && Session::get('user_role') === 'patient',
        'Existing AuthService::login session remains the authentication mechanism'
    );
    expect_true(!str_contains($success['raw'], $validToken), 'Google token is not returned in the success response');
    expect_true(!isset($success['json']['credential']) && !isset($success['json']['token']), 'Success payload does not include a token field');
    expect_true(Session::get('credential') === null, 'Raw credential is not stored in the session after success');
    $cookieHeader = implode('; ', array_map('strval', headers_list()));
    expect_true(!str_contains($cookieHeader, $validToken), 'Raw credential is not stored in cookies');
    $subRow = $db->prepare('SELECT google_sub, email FROM users WHERE id = :id');
    $subRow->execute([':id' => $created->id]);
    $stored = $subRow->fetch(PDO::FETCH_ASSOC) ?: [];
    expect_true(($stored['google_sub'] ?? '') === $newSub, 'Database stores google_sub, not the raw ID token');
    expect_true(($stored['email'] ?? '') === $newEmail, 'Database stores the verified email, not the raw ID token');
    expect_true(!str_contains((string) ($stored['google_sub'] ?? ''), 'eyJ'), 'Database google_sub is not a JWT');

    $repeat = invoke_google_auth([
        'body' => json_body(['credential' => $validToken, '_token' => $csrfToken]),
    ]);
    expect_true($repeat['status'] === 200 && ($repeat['json']['success'] ?? false) === true, 'Already-authenticated Google POST returns the existing session redirect');
    $emailCount = $db->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
    $emailCount->execute([':email' => $newEmail]);
    expect_true((int) $emailCount->fetchColumn() === 1, 'Repeat Google sign-in does not create a duplicate account');

    $otherSub = 'switch-google-sub-' . $suffix;
    $otherToken = google_token($rsa, $clientId, [
        'sub' => $otherSub,
        'email' => 'switch.google+' . $suffix . '@example.com',
        'name' => 'Switch Attempt Patient',
    ]);
    $switchAttempt = invoke_google_auth([
        'body' => json_body(['credential' => $otherToken, '_token' => $csrfToken]),
    ]);
    expect_true($switchAttempt['status'] === 200 && ($switchAttempt['json']['redirect'] ?? '') === $expectedPatientRedirect, 'Already-authenticated Google POST keeps the current role redirect');
    expect_true((int) Session::get('user_id') === (int) $created->id, 'Already-authenticated Google POST does not switch user_id');
    expect_true(User::findByGoogleSub($otherSub) === null, 'Already-authenticated Google POST does not create another Google user');
    forget_login();

    $collisionEmail = 'doctor.collision+' . $suffix . '@example.com';
    $doctor = new User();
    $doctor->role_id = (int) $doctorRoleId;
    $doctor->full_name = 'Controller Collision Doctor';
    $doctor->email = $collisionEmail;
    $doctor->password = password_hash('DoctorPass!234', PASSWORD_DEFAULT);
    $doctor->status = 'active';
    expect_true($doctor->save() && $doctor->id !== null, 'Doctor fixture can be created');
    track_user($doctor);
    $doctorToken = google_token($rsa, $clientId, [
        'sub' => 'doctor-collision-sub-' . $suffix,
        'email' => $collisionEmail,
        'name' => 'Controller Collision Doctor',
    ]);
    $collision = invoke_google_auth([
        'body' => json_body(['credential' => $doctorToken, '_token' => $csrfToken]),
    ]);
    $doctorAfter = User::findByEmail($collisionEmail);
    expect_true($collision['status'] === 401, 'Doctor email collision is a controlled authentication failure');
    expect_true(($collision['json']['message'] ?? '') === 'Google sign-in is for patients only. Please sign in with your email and password.', 'Staff Google emails receive a patients-only sign-in message');
    expect_true(!str_contains($collision['raw'], 'email_collision') && !str_contains($collision['raw'], 'admin'), 'Collision responses do not expose account classification');
    expect_true($doctorAfter !== null && $doctorAfter->google_sub === null, 'Doctor collision does not link Google identity');
    expect_true(!AuthService::isAuthenticated(), 'Collision failure does not leave a logged-in user');

    $localEmail = 'local.ctrl+' . $suffix . '@example.com';
    $local = AuthService::register([
        'full_name' => 'Local Controller Patient',
        'email' => $localEmail,
        'password' => 'LocalPass!234',
        'dob' => '1990-01-15',
        'gender' => 'female',
        'address' => 'Alotau',
    ]);
    track_user($local);
    expect_true($local instanceof User, 'Existing local registration remains unchanged');
    expect_true(AuthService::authenticate($localEmail, 'LocalPass!234') instanceof User, 'Existing patient authentication remains unchanged');
    expect_true(AuthService::authenticate($collisionEmail, 'DoctorPass!234') instanceof User, 'Existing doctor authentication remains unchanged');

    $adminEmail = 'admin.ctrl+' . $suffix . '@example.com';
    $admin = new User();
    $admin->role_id = (int) $adminRoleId;
    $admin->full_name = 'Controller Admin';
    $admin->email = $adminEmail;
    $admin->password = password_hash('AdminPass!234', PASSWORD_DEFAULT);
    $admin->status = 'active';
    expect_true($admin->save(), 'Admin fixture can be created');
    track_user($admin);
    expect_true(AuthService::authenticate($adminEmail, 'AdminPass!234') instanceof User, 'Existing admin authentication remains unchanged');
    expect_true(str_contains($authControllerSource, 'function login(): void'), 'AuthController login action remains present');
} catch (Throwable $exception) {
    expect_true(false, 'GoogleAuthController tests completed without an unexpected exception: ' . $exception->getMessage());
} finally {
    GoogleAuthController::$testRawBody = null;
    GoogleAuthService::$testJwksOverride = null;
    AuthService::$testFailGooglePatientInsert = false;
    forget_login();
    foreach (array_values(array_unique($createdUserIds)) as $userId) {
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
