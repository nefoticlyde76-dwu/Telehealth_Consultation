<?php

/**
 * Patient registration GIS frontend checks.
 *
 * Usage: php bin/test_google_registration_gis.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\App;
use App\Config\Environment;

$root = dirname(__DIR__);
Environment::load($root . '/.env');

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

$registerView = (string) file_get_contents($root . '/app/Views/auth/register.php');
$loginView = (string) file_get_contents($root . '/app/Views/auth/login.php');
$gisJs = (string) file_get_contents($root . '/public/js/google-auth.js');
$layout = (string) file_get_contents($root . '/app/Views/layouts/app.php');
$routes = (string) file_get_contents($root . '/routes/web.php');
$authController = (string) file_get_contents($root . '/app/Controllers/AuthController.php');
$configuredClientId = trim((string) (App::getConfig()['google']['client_id'] ?? ''));

expect_true(str_contains($registerView, 'https://accounts.google.com/gsi/client'), 'Official GIS script is available on the registration page');
expect_true(str_contains($registerView, "App::getConfig()['google']['client_id']"), 'Registration page uses the configured Google client ID');
expect_true($configuredClientId !== '', 'GOOGLE_CLIENT_ID is configured');
expect_true(str_contains($registerView, 'js/google-auth.js'), 'Registration page uses the shared Google GIS implementation');
expect_true(str_contains($loginView, 'js/google-auth.js'), 'Login page uses the same shared Google GIS implementation');
expect_true(!is_file($root . '/public/js/auth-google-login.js'), 'Login-specific Google script was not left as a duplicate copy');
expect_true(substr_count($gisJs, 'initialized = true') === 1, 'GIS initializes once');
expect_true(str_contains($gisJs, 'renderButton'), 'Official GIS button is rendered once per page host');
expect_true(str_contains($registerView, 'id="mbphaGoogleSignInButton"'), 'Registration Google button host is present once');
expect_true(substr_count($registerView, 'id="mbphaGoogleSignInButton"') === 1, 'Registration page does not insert duplicate Google button hosts');
expect_true(substr_count($registerView, 'accounts.google.com/gsi/client') === 1, 'Registration page does not insert duplicate GIS script tags');

expect_true(str_contains($gisJs, 'credential: credential'), 'Credential callback sends only credential');
expect_true(str_contains($gisJs, '_token: csrf'), 'Existing _token is included');
expect_true(str_contains($gisJs, 'name="_token"'), 'CSRF token is read from the existing registration form');
expect_true(str_contains($gisJs, "method: \"POST\""), 'Request is POST');
expect_true(str_contains($gisJs, 'application/json'), 'Request is JSON');
expect_true(str_contains($registerView, "Helper::url('/auth/google')"), 'Request goes to same-origin /auth/google');
expect_true(str_contains($gisJs, 'credentials: "same-origin"'), 'Request is same-origin');
expect_true(str_contains($gisJs, 'requestInProgress'), 'Duplicate callbacks are prevented');
expect_true(!str_contains($gisJs, '/register') && !str_contains($gisJs, '/auth/google/register'), 'Google registration does not call /register or a second Google endpoint');
expect_true(!str_contains($gisJs, 'generatePassword') && !str_contains($gisJs, 'Math.random'), 'No password is generated client-side for Google registration');

expect_true(!str_contains($gisJs, 'localStorage'), 'Credential is not stored in localStorage');
expect_true(!str_contains($gisJs, 'sessionStorage'), 'Credential is not stored in sessionStorage');
expect_true(!str_contains($gisJs, 'document.cookie'), 'Credential is not stored in cookies');
expect_true(!str_contains($gisJs, 'atob(') && !str_contains($gisJs, 'jwt'), 'Browser code does not decode the Google credential');
expect_true(!str_contains($gisJs, 'access_token') && !str_contains($gisJs, 'refresh_token'), 'No access or refresh tokens are used');
expect_true(!str_contains($gisJs, 'client_secret') && !str_contains($gisJs, 'tokeninfo'), 'No client secret or tokeninfo is used');
expect_true(!str_contains($gisJs, 'role: "patient"') && !str_contains($gisJs, '"role": "admin"'), 'Frontend does not send a role field');

expect_true(str_contains($gisJs, 'window.location.assign(data.redirect)'), 'Backend success redirects according to the returned response');
expect_true(str_contains($gisJs, 'data.success === true'), 'Redirect happens only after a successful backend response');
expect_true(str_contains($registerView, 'This Google account could not be used to create or access a patient account.'), 'Backend 401 uses a generic patient-account error');
expect_true(!str_contains($registerView, 'email_collision') && !str_contains($gisJs, 'google_sub'), 'Internal collision reasons are not exposed');

expect_true(str_contains($registerView, "Helper::url('/register')"), 'Existing /register form still exists');
expect_true(str_contains($registerView, 'name="password"') && str_contains($registerView, 'name="confirm_password"'), 'Existing password fields remain');
expect_true(str_contains($registerView, 'name="_token"'), 'Existing CSRF remains on the registration form');
expect_true(str_contains($registerView, 'name="full_name"') && str_contains($registerView, 'name="email"'), 'Existing registration fields remain');
expect_true(str_contains($registerView, 'needs-validation'), 'Existing registration validation remains');
expect_true(str_contains($authController, "render('auth/register'"), 'Existing registration controller path remains unchanged');
expect_true(str_contains($authController, 'validateRegistrationRequest'), 'Existing registration validation remains in the controller');
expect_true(!str_contains($routes, '/auth/google/register') && !str_contains($routes, '/auth/google/signup'), 'No new Google registration endpoint exists');
expect_true(str_contains($routes, "post('/auth/google', [GoogleAuthController::class, 'authenticate'])"), 'POST /auth/google remains the Google authentication route');
expect_true(!str_contains($layout, 'accounts.google.com/gsi/client'), 'GIS is not loaded globally');

$doctorCreate = (string) file_get_contents($root . '/app/Views/admin/doctors/create.php');
$adminUsers = (string) file_get_contents($root . '/app/Views/admin/users/index.php');
expect_true(!str_contains($doctorCreate, 'gsi/client') && !str_contains($adminUsers, 'gsi/client'), 'Staff account-creation screens do not include GIS');

$registerUrl = rtrim((string) Environment::get('APP_URL', ''), '/') . '/register';
$registerHtml = '';
$registerHttp = 0;
if (function_exists('curl_init') && $registerUrl !== '/register') {
    $ch = curl_init($registerUrl);
    if ($ch !== false) {
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $fetched = curl_exec($ch);
        $registerHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);
        if (is_string($fetched)) {
            $registerHtml = $fetched;
        }
    }
}

if ($registerHttp >= 200 && $registerHttp < 400 && $registerHtml !== '') {
    expect_true(str_contains($registerHtml, 'accounts.google.com/gsi/client'), 'Rendered registration page includes the official GIS script');
    expect_true(str_contains($registerHtml, 'mbphaGoogleSignInButton'), 'Rendered registration page includes the Google button host');
    expect_true(str_contains($registerHtml, 'google-auth.min.js') || str_contains($registerHtml, 'google-auth.js'), 'Rendered registration page includes the shared GIS script');
    expect_true(str_contains($registerHtml, 'name="password"') && str_contains($registerHtml, 'Create patient account'), 'Rendered registration page still includes normal registration');
    expect_true(str_contains($registerHtml, 'name="_token"'), 'Rendered registration page still includes CSRF');
    expect_true(str_contains($registerHtml, '/auth/google'), 'Rendered registration page posts Google credentials to /auth/google');
    expect_true(!str_contains($registerHtml, '/auth/google/register'), 'Rendered registration page has no Google signup endpoint');
} else {
    echo "SKIP  Live registration page HTML could not be fetched in this environment (HTTP {$registerHttp})\n";
}

echo PHP_EOL . "Passed: {$passed}" . PHP_EOL;
echo "Failed: {$failed}" . PHP_EOL;

exit($failed > 0 ? 1 : 0);
