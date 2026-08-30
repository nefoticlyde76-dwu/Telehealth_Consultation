<?php

/**
 * Patient login GIS frontend checks.
 *
 * Usage: php bin/test_google_login_gis.php
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

$loginView = (string) file_get_contents($root . '/app/Views/auth/login.php');
$registerView = (string) file_get_contents($root . '/app/Views/auth/register.php');
$gisJs = (string) file_get_contents($root . '/public/js/google-auth.js');
$loginCss = (string) file_get_contents($root . '/public/css/auth-login.css');
$layout = (string) file_get_contents($root . '/app/Views/layouts/app.php');
$routes = (string) file_get_contents($root . '/routes/web.php');
$authController = (string) file_get_contents($root . '/app/Controllers/AuthController.php');
$htaccess = (string) file_get_contents($root . '/public/.htaccess');
$googleAuthController = (string) file_get_contents($root . '/app/Controllers/GoogleAuthController.php');
$googleAuthService = (string) file_get_contents($root . '/app/Services/GoogleAuthService.php');
$configuredClientId = trim((string) (App::getConfig()['google']['client_id'] ?? ''));

expect_true(str_contains($loginView, 'https://accounts.google.com/gsi/client'), 'Official GIS script is referenced on the login page');
expect_true(str_contains($loginView, 'async defer'), 'GIS script is loaded asynchronously');
expect_true(str_contains($loginView, 'mbphaGoogleGisLoaded'), 'GIS onload initializes after the library is ready');
expect_true(str_contains($loginView, "App::getConfig()['google']['client_id']"), 'Login view uses the application Google client ID');
expect_true(!str_contains($loginView, 'your-google-web-client-id.apps.googleusercontent.com'), 'Login view does not hardcode a placeholder client ID');
expect_true($configuredClientId !== '', 'GOOGLE_CLIENT_ID is configured for the frontend');
expect_true(str_contains($loginView, 'id="mbphaGoogleSignInButton"'), 'Google button host is rendered once');
expect_true(str_contains($gisJs, 'initialized = true'), 'GIS initializes once');
expect_true(str_contains($gisJs, 'renderButton'), 'Official GIS button is rendered');
expect_true(str_contains($gisJs, 'ux_mode: "popup"'), 'GIS uses popup credential flow rather than OAuth redirect');
expect_true(!str_contains($gisJs, 'prompt('), 'One Tap prompt is not auto-started on the shared login page');

expect_true(str_contains($gisJs, 'onCredentialResponse'), 'Credential callback is received from GIS');
expect_true(str_contains($gisJs, "method: \"POST\""), 'Credential request uses POST');
expect_true(str_contains($gisJs, 'application/json'), 'Credential request uses JSON');
expect_true(str_contains($gisJs, 'credentials: "same-origin"'), 'Credential request is same-origin');
expect_true(str_contains($gisJs, 'credential: credential'), 'JSON field is exactly credential');
expect_true(str_contains($gisJs, '_token: csrf'), 'Existing CSRF token is included as _token');
expect_true(str_contains($gisJs, 'name="_token"'), 'CSRF token is read from the existing login form');
expect_true(str_contains($loginView, "Helper::url('/auth/google')"), 'Frontend posts to /auth/google');
expect_true(!str_contains($gisJs, 'id_token') && !str_contains($gisJs, 'access_token') && !str_contains($gisJs, 'refresh_token'), 'Frontend does not send OAuth token aliases');
expect_true(!str_contains($gisJs, 'localStorage'), 'Credential is not stored in localStorage');
expect_true(!str_contains($gisJs, 'sessionStorage'), 'Credential is not stored in sessionStorage');
expect_true(!str_contains($gisJs, 'document.cookie'), 'Credential is not stored in cookies');
expect_true(str_contains($gisJs, 'requestInProgress'), 'Duplicate Google authentication requests are prevented');
expect_true(str_contains($gisJs, 'data.success === true'), 'Successful backend response is checked before redirect');
expect_true(str_contains($gisJs, 'window.location.assign(data.redirect)'), 'Success navigates to the backend-supplied redirect');
expect_true(!str_contains($gisJs, 'user_id') && !str_contains($gisJs, 'user_role'), 'No client-created authentication session exists');
expect_true(str_contains($gisJs, 'isSafeRedirect'), 'Redirect URLs are restricted to same-origin paths');
expect_true(str_contains($googleAuthController, 'Helper::browserPath'), 'Google JSON redirect includes the application base path');
expect_true(str_contains($googleAuthController, 'approvedRedirectPath'), 'Google redirect is not taken from browser or Google input');

expect_true(str_contains($gisJs, 'status === 419'), 'Backend 419 is handled as a CSRF failure');
expect_true(str_contains($gisJs, 'session security token is invalid'), '419 uses the existing CSRF message');
expect_true(str_contains($gisJs, 'data.message'), 'Backend 400/401 prefer the server-supplied message');
expect_true(str_contains($gisJs, 'Google sign-in could not be completed.'), 'Missing backend messages fall back to a generic authentication error');
expect_true(str_contains($gisJs, 'temporarily unavailable'), 'Backend 500 is displayed generically');
expect_true(str_contains($gisJs, '.catch(function ()'), 'Network failure is handled');
expect_true(!str_contains($gisJs, 'alertBox.innerHTML'), 'Raw server bodies are not injected into the page');

expect_true(!str_contains($gisJs, 'atob(') && !str_contains($gisJs, 'jwt'), 'Browser code does not decode the Google JWT');
expect_true(!str_contains($gisJs, 'email_verified') && !str_contains($gisJs, 'payload.sub'), 'Browser code does not inspect Google claims');
expect_true(!str_contains($gisJs, 'client_secret') && !str_contains($gisJs, 'tokeninfo'), 'Frontend does not use a client secret or tokeninfo');
expect_true(!str_contains($gisJs, 'response_type') && !str_contains($gisJs, 'authorization_code'), 'Frontend does not use OAuth authorization-code flow');
expect_true(!str_contains($gisJs, 'platform.js'), 'Deprecated Google Sign-In platform.js is not used');

expect_true(str_contains($loginView, 'name="email"') && str_contains($loginView, 'name="password"'), 'Password login fields remain on the login page');
expect_true(str_contains($loginView, "Helper::url('/login')"), 'Password login still posts to /login');
$authTabs = (string) file_get_contents($root . '/app/Views/partials/auth/tabs.php');
expect_true(str_contains($loginView, 'auth/tabs.php') && str_contains($authTabs, "Helper::url('/register')"), 'Registration tab remains available from the login page');
expect_true(str_contains($authController, 'AuthService::authenticate($email, $password)'), 'Password login controller path remains unchanged');
expect_true(str_contains($authController, "render('auth/register'"), 'Registration controller path remains unchanged');
expect_true(str_contains($loginView, 'js/google-auth.js'), 'Login page loads the shared Google authentication script');
expect_true(str_contains($registerView, 'js/google-auth.js'), 'Registration page reuses the shared Google authentication script');
expect_true(str_contains($registerView, 'accounts.google.com/gsi/client'), 'Registration page loads official GIS for patients only');
expect_true(is_file($root . '/app/Views/admin/users/reset_password.php'), 'Administrator password reset remains available');
expect_true(str_contains($routes, "post('/auth/google', [GoogleAuthController::class, 'authenticate'])"), '/auth/google backend route remains unchanged');
expect_true(str_contains($googleAuthController, 'GoogleAuthService::authenticate'), 'GoogleAuthService remains the backend authority');
expect_true(str_contains($googleAuthService, 'AuthService::login'), 'Existing AuthService::login remains the session mechanism');

expect_true(str_contains($loginCss, '.auth-login-google'), 'Login CSS includes a scoped Google button layout');
expect_true(str_contains($loginCss, 'max-width: 100%'), 'Google button container cannot overflow the form');
expect_true(str_contains($loginView, 'Patients can continue with Google'), 'Shared login page states Google is for patients');
expect_true(!str_contains($loginView, '"role": "admin"') && !str_contains($gisJs, 'role:'), 'Frontend does not submit a role with Google sign-in');
expect_true(!str_contains($layout, 'accounts.google.com/gsi/client'), 'GIS is not loaded on every layout page');
expect_true(!str_contains($htaccess, 'Content-Security-Policy'), 'No CSP exists in public/.htaccess that needed a GIS exception');
expect_true(!str_contains($loginView, 'Access-Control-Allow-Origin'), 'Login GIS integration does not add CORS headers');

$doctorDashboard = (string) file_get_contents($root . '/app/Views/doctor/dashboard.php');
$adminDashboard = (string) file_get_contents($root . '/app/Views/admin/dashboard.php');
expect_true(!str_contains($doctorDashboard, 'gsi/client') && !str_contains($adminDashboard, 'gsi/client'), 'Doctor and admin dashboards do not include GIS');

$loginUrl = rtrim((string) Environment::get('APP_URL', ''), '/') . '/login';
$loginHtml = '';
$loginHttp = 0;
if (function_exists('curl_init') && $loginUrl !== '/login') {
    $ch = curl_init($loginUrl);
    if ($ch !== false) {
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $fetched = curl_exec($ch);
        $loginHttp = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (is_string($fetched)) {
            $loginHtml = $fetched;
        }
    }
}

if ($loginHttp >= 200 && $loginHttp < 400 && $loginHtml !== '') {
    expect_true(str_contains($loginHtml, 'accounts.google.com/gsi/client'), 'Rendered login page includes the official GIS script');
    expect_true(str_contains($loginHtml, 'mbphaGoogleSignInButton'), 'Rendered login page includes the Google button host');
    expect_true(str_contains($loginHtml, 'google-auth.js'), 'Rendered login page includes the GIS callback script');
    expect_true(str_contains($loginHtml, 'name="email"') && str_contains($loginHtml, 'name="password"'), 'Rendered login page still includes email/password login');
    expect_true(str_contains($loginHtml, 'name="_token"'), 'Rendered login page still includes the CSRF token');
    expect_true(str_contains($loginHtml, '/auth/google'), 'Rendered login page posts Google credentials to /auth/google');
    expect_true(str_contains($loginHtml, 'data-client-id="' . htmlspecialchars($configuredClientId, ENT_QUOTES, 'UTF-8') . '"') || str_contains($loginHtml, $configuredClientId), 'Rendered login page uses the configured Google client ID');
} else {
    echo "SKIP  Live login page HTML could not be fetched in this environment (HTTP {$loginHttp})\n";
}

echo PHP_EOL . "Passed: {$passed}" . PHP_EOL;
echo "Failed: {$failed}" . PHP_EOL;

exit($failed > 0 ? 1 : 0);
