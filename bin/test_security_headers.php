<?php

/**
 * Centralized security-header baseline.
 *
 * Usage: php bin/test_security_headers.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Helpers\Helper;
use App\Middleware\SecurityHeadersMiddleware;

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

$router = (string) file_get_contents($root . '/app/Core/Router.php');
$middleware = (string) file_get_contents($root . '/app/Middleware/SecurityHeadersMiddleware.php');
$roleMiddleware = (string) file_get_contents($root . '/app/Middleware/RoleMiddleware.php');
$layouts = (string) file_get_contents($root . '/app/Views/layouts/app.php')
    . (string) file_get_contents($root . '/app/Views/layouts/dashboard.php')
    . (string) file_get_contents($root . '/app/Views/layouts/print.php');
$loginView = (string) file_get_contents($root . '/app/Views/auth/login.php');
$roomView = (string) file_get_contents($root . '/app/Views/patient/consultations/room.php');
$doctorController = (string) file_get_contents($root . '/app/Controllers/DoctorController.php');
$adminController = (string) file_get_contents($root . '/app/Controllers/AdminController.php');
$patientController = (string) file_get_contents($root . '/app/Controllers/PatientController.php');

expect_true(str_contains($router, 'SecurityHeadersMiddleware::apply()'), 'Router applies security headers on every dispatch');
expect_true(str_contains($roleMiddleware, 'class RoleMiddleware implements Middleware'), 'RoleMiddleware pattern is unchanged');
expect_true(str_contains($middleware, 'implements Middleware'), 'Security headers follow the existing Middleware interface');

$headers = SecurityHeadersMiddleware::headers();
$csp = SecurityHeadersMiddleware::contentSecurityPolicy();

expect_true(($headers['X-Frame-Options'] ?? '') === 'DENY', 'X-Frame-Options is DENY');
expect_true(!str_contains($csp, 'frame-ancestors'), 'CSP does not duplicate X-Frame-Options with frame-ancestors');
expect_true(($headers['Referrer-Policy'] ?? '') === 'strict-origin-when-cross-origin', 'Referrer-Policy is strict-origin-when-cross-origin');
expect_true(($headers['X-Content-Type-Options'] ?? '') === 'nosniff', 'X-Content-Type-Options is nosniff');
expect_true(($headers['X-Robots-Tag'] ?? '') === 'noindex, nofollow', 'X-Robots-Tag tells crawlers not to index');
expect_true(str_contains($csp, "default-src 'self'"), 'CSP default-src is self');
expect_true(str_contains($csp, 'https://cdn.jsdelivr.net'), 'CSP allows the Bootstrap/Chart.js/Daily jsDelivr CDN');
expect_true(!str_contains($csp, 'fonts.googleapis.com'), 'CSP no longer allows Google Fonts stylesheets');
expect_true(!str_contains($csp, 'fonts.gstatic.com'), 'CSP no longer allows Google Font files');
expect_true(str_contains($csp, "font-src 'self' https://cdn.jsdelivr.net"), 'CSP font-src is self plus jsDelivr icon fonts');
expect_true(str_contains($csp, 'https://accounts.google.com'), 'CSP allows Google Identity Services');
expect_true(str_contains($csp, 'https://*.daily.co') && str_contains($csp, 'wss://*.daily.co'), 'CSP allows Daily.co HTTPS and WebSocket origins');
expect_true(str_contains($csp, "script-src 'self' 'unsafe-inline'"), 'CSP allows existing inline layout and GIS onload scripts');

expect_true(str_contains($layouts, 'Cdn::BOOTSTRAP_CSS'), 'Layouts load Bootstrap CSS through the Cdn helper');
expect_true(str_contains($layouts, 'Cdn::BOOTSTRAP_JS'), 'Layouts load Bootstrap JS through the Cdn helper');
expect_true(str_contains($layouts, 'Cdn::BOOTSTRAP_ICONS_CSS'), 'Layouts load Bootstrap Icons through the Cdn helper');
expect_true(str_contains($layouts, "asset('css/fonts.css')"), 'Layouts self-host Inter and Poppins');
expect_true(!str_contains($layouts, 'fonts.googleapis.com'), 'Layouts no longer load Google Fonts from the CDN');
expect_true(str_contains($layouts, 'includeChartJs'), 'Chart.js is gated behind includeChartJs');
expect_true(!str_contains($layouts, 'chart.js@'), 'Dashboard layout does not hardcode Chart.js globally');
expect_true(str_contains($loginView, 'accounts.google.com/gsi/client'), 'Login still loads the GIS client');
expect_true(str_contains($roomView, 'Cdn::DAILY_JS'), 'Consultation room still loads Daily.js from jsDelivr');
expect_true(str_contains($roomView, 'Cdn::DAILY_JS_INTEGRITY'), 'Consultation room pins Daily.js with SRI');
expect_true(str_contains($doctorController, "'includeChartJs'"), 'Doctor dashboard opts into Chart.js');
expect_true(!str_contains($adminController, 'includeChartJs'), 'Admin pages do not load Chart.js');
expect_true(!str_contains($patientController, 'includeChartJs'), 'Patient pages do not load Chart.js');

$bootstrapLink = \App\Helpers\Cdn::stylesheet(\App\Helpers\Cdn::BOOTSTRAP_CSS, \App\Helpers\Cdn::BOOTSTRAP_CSS_INTEGRITY);
expect_true(str_contains($bootstrapLink, 'integrity="sha384-'), 'CDN helper emits SRI integrity attributes');
expect_true(str_contains($bootstrapLink, 'crossorigin="anonymous"'), 'CDN helper sets crossorigin anonymous');
expect_true(str_starts_with(\App\Helpers\Cdn::BOOTSTRAP_CSS_INTEGRITY, 'sha384-'), 'Bootstrap CSS has a sha384 integrity hash');
expect_true(str_starts_with(\App\Helpers\Cdn::BOOTSTRAP_JS_INTEGRITY, 'sha384-'), 'Bootstrap JS has a sha384 integrity hash');
expect_true(str_starts_with(\App\Helpers\Cdn::BOOTSTRAP_ICONS_CSS_INTEGRITY, 'sha384-'), 'Bootstrap Icons CSS has a sha384 integrity hash');
expect_true(str_starts_with(\App\Helpers\Cdn::CHART_JS_INTEGRITY, 'sha384-'), 'Chart.js has a sha384 integrity hash');
expect_true(str_starts_with(\App\Helpers\Cdn::DAILY_JS_INTEGRITY, 'sha384-'), 'Daily.js has a sha384 integrity hash');
expect_true(is_file($root . '/public/css/dashboard-ui.min.css') && is_file($root . '/public/js/dashboard.min.js'), 'Minified dashboard CSS and JS are present');
expect_true(str_contains(\App\Helpers\Helper::asset('css/theme.css'), 'theme.min.css'), 'Helper::asset serves minified CSS when it exists');
expect_true(str_contains(\App\Helpers\Helper::asset('js/app.js'), 'app.min.js'), 'Helper::asset serves minified JS when it exists');

$https = $_SERVER['HTTPS'] ?? null;
$port = $_SERVER['SERVER_PORT'] ?? null;
$forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;
$_SERVER['HTTPS'] = 'off';
$_SERVER['SERVER_PORT'] = '80';
unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
$httpHeaders = SecurityHeadersMiddleware::headers();
expect_true(!isset($httpHeaders['Strict-Transport-Security']), 'HSTS is omitted on HTTP requests');
expect_true(Helper::isHttpsRequest() === false, 'Helper reports HTTP when HTTPS is off');

$_SERVER['HTTPS'] = 'on';
$httpsHeaders = SecurityHeadersMiddleware::headers();
expect_true(($httpsHeaders['Strict-Transport-Security'] ?? '') === 'max-age=31536000; includeSubDomains', 'HSTS is set on HTTPS requests');
expect_true(Helper::isHttpsRequest() === true, 'Helper reports HTTPS when HTTPS is on');

$_SERVER['HTTPS'] = 'off';
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
expect_true(Helper::isHttpsRequest() === true, 'Helper treats X-Forwarded-Proto as HTTPS (App Platform / Cloudflare)');
$forwardedHeaders = SecurityHeadersMiddleware::headers();
expect_true(isset($forwardedHeaders['Strict-Transport-Security']), 'HSTS is set when X-Forwarded-Proto is https');

if ($https === null) {
    unset($_SERVER['HTTPS']);
} else {
    $_SERVER['HTTPS'] = $https;
}
if ($port === null) {
    unset($_SERVER['SERVER_PORT']);
} else {
    $_SERVER['SERVER_PORT'] = $port;
}
if ($forwarded === null) {
    unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
} else {
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = $forwarded;
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
