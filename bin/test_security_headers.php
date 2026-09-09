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

expect_true(str_contains($router, 'SecurityHeadersMiddleware::apply()'), 'Router applies security headers on every dispatch');
expect_true(str_contains($roleMiddleware, 'class RoleMiddleware implements Middleware'), 'RoleMiddleware pattern is unchanged');
expect_true(str_contains($middleware, 'implements Middleware'), 'Security headers follow the existing Middleware interface');

$headers = SecurityHeadersMiddleware::headers();
$csp = SecurityHeadersMiddleware::contentSecurityPolicy();

expect_true(($headers['X-Frame-Options'] ?? '') === 'DENY', 'X-Frame-Options is DENY');
expect_true(!str_contains($csp, 'frame-ancestors'), 'CSP does not duplicate X-Frame-Options with frame-ancestors');
expect_true(($headers['Referrer-Policy'] ?? '') === 'strict-origin-when-cross-origin', 'Referrer-Policy is strict-origin-when-cross-origin');
expect_true(($headers['X-Content-Type-Options'] ?? '') === 'nosniff', 'X-Content-Type-Options is nosniff');
expect_true(str_contains($csp, "default-src 'self'"), 'CSP default-src is self');
expect_true(str_contains($csp, 'https://cdn.jsdelivr.net'), 'CSP allows the Bootstrap/Chart.js/Daily jsDelivr CDN');
expect_true(str_contains($csp, 'https://fonts.googleapis.com'), 'CSP allows Google Fonts stylesheets');
expect_true(str_contains($csp, 'https://fonts.gstatic.com'), 'CSP allows Google Fonts files');
expect_true(str_contains($csp, 'https://accounts.google.com'), 'CSP allows Google Identity Services');
expect_true(str_contains($csp, 'https://*.daily.co') && str_contains($csp, 'wss://*.daily.co'), 'CSP allows Daily.co HTTPS and WebSocket origins');
expect_true(str_contains($csp, "script-src 'self' 'unsafe-inline'"), 'CSP allows existing inline layout and GIS onload scripts');

expect_true(str_contains($layouts, 'cdn.jsdelivr.net/npm/bootstrap'), 'Layouts still load Bootstrap from jsDelivr');
expect_true(str_contains($layouts, 'fonts.googleapis.com'), 'Layouts still load Google Fonts');
expect_true(str_contains($loginView, 'accounts.google.com/gsi/client'), 'Login still loads the GIS client');
expect_true(str_contains($roomView, 'cdn.jsdelivr.net/npm/@daily-co/daily-js'), 'Consultation room still loads Daily.js from jsDelivr');

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
