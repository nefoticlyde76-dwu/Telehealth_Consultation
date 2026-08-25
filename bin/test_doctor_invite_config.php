<?php

/**
 * Doctor invitation configuration checks (Feature 2 Step 5).
 *
 * Usage: php bin/test_doctor_invite_config.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\App;
use App\Config\Environment;

Environment::load(dirname(__DIR__) . '/.env');

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

function setInviteEnv(string $key, ?string $value): void
{
    if ($value === null) {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
        return;
    }

    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
    putenv($key . '=' . $value);
}

function restoreInviteEnv(array $snapshot): void
{
    foreach ($snapshot as $key => $value) {
        setInviteEnv($key, $value);
    }
}

$keys = [
    'DOCTOR_INVITE_TOKEN_TTL_HOURS',
    'DOCTOR_INVITE_RESEND_COOLDOWN_MINUTES',
    'DOCTOR_INVITE_MAX_SENDS_PER_DAY',
];

$snapshot = [];
foreach ($keys as $key) {
    $current = Environment::get($key, null);
    $snapshot[$key] = ($current === null || $current === false) ? null : (string) $current;
}

$config = App::getConfig();
$invite = $config['doctor_invitation'] ?? null;

expect_true(is_array($invite), 'App::getConfig() exposes doctor_invitation');
expect_true(array_key_exists('token_ttl_hours', (array) $invite), 'token_ttl_hours is present');
expect_true(array_key_exists('resend_cooldown_minutes', (array) $invite), 'resend_cooldown_minutes is present');
expect_true(array_key_exists('max_sends_per_day', (array) $invite), 'max_sends_per_day is present');

expect_true(is_int($invite['token_ttl_hours'] ?? null), 'token_ttl_hours is an integer');
expect_true(is_int($invite['resend_cooldown_minutes'] ?? null), 'resend_cooldown_minutes is an integer');
expect_true(is_int($invite['max_sends_per_day'] ?? null), 'max_sends_per_day is an integer');

expect_true(($invite['token_ttl_hours'] ?? 0) === 24, 'TTL default/current value is 24 hours');
expect_true(($invite['resend_cooldown_minutes'] ?? 0) === 5, 'Resend cooldown default/current value is 5 minutes');
expect_true(($invite['max_sends_per_day'] ?? 0) === 8, 'Daily cap default/current value is 8');

expect_true(
    filter_var((string) Environment::get('DOCTOR_INVITE_TOKEN_TTL_HOURS', ''), FILTER_VALIDATE_INT) !== false,
    'DOCTOR_INVITE_TOKEN_TTL_HOURS is read from the environment as an integer'
);
expect_true(
    filter_var((string) Environment::get('DOCTOR_INVITE_RESEND_COOLDOWN_MINUTES', ''), FILTER_VALIDATE_INT) !== false,
    'DOCTOR_INVITE_RESEND_COOLDOWN_MINUTES is read from the environment as an integer'
);
expect_true(
    filter_var((string) Environment::get('DOCTOR_INVITE_MAX_SENDS_PER_DAY', ''), FILTER_VALIDATE_INT) !== false,
    'DOCTOR_INVITE_MAX_SENDS_PER_DAY is read from the environment as an integer'
);

expect_true(isset($config['google']) && is_array($config['google']), 'Existing google config section remains');
expect_true(array_key_exists('client_id', $config['google']), 'Existing GOOGLE_CLIENT_ID config remains');
expect_true(isset($config['mail']) && is_array($config['mail']), 'Existing mail config section remains');
expect_true(array_key_exists('mailer', $config['mail']), 'Existing MAIL_MAILER config remains');
expect_true(isset($config['session']) && is_array($config['session']), 'Existing session config section remains');
expect_true(isset($config['url']) && is_string($config['url']) && $config['url'] !== '', 'Existing APP_URL config remains');
expect_true(isset($config['name']) && is_string($config['name']), 'Existing APP_NAME config remains');

$example = (string) file_get_contents(dirname(__DIR__) . '/.env.example');
expect_true(str_contains($example, 'DOCTOR_INVITE_TOKEN_TTL_HOURS='), '.env.example documents TTL');
expect_true(str_contains($example, 'DOCTOR_INVITE_RESEND_COOLDOWN_MINUTES='), '.env.example documents cooldown');
expect_true(str_contains($example, 'DOCTOR_INVITE_MAX_SENDS_PER_DAY='), '.env.example documents daily cap');

setInviteEnv('DOCTOR_INVITE_TOKEN_TTL_HOURS', null);
setInviteEnv('DOCTOR_INVITE_RESEND_COOLDOWN_MINUTES', null);
setInviteEnv('DOCTOR_INVITE_MAX_SENDS_PER_DAY', null);
$defaults = App::getConfig()['doctor_invitation'];
expect_true($defaults['token_ttl_hours'] === 24, 'Missing TTL falls back to 24');
expect_true($defaults['resend_cooldown_minutes'] === 5, 'Missing cooldown falls back to 5');
expect_true($defaults['max_sends_per_day'] === 8, 'Missing daily cap falls back to 8');

setInviteEnv('DOCTOR_INVITE_TOKEN_TTL_HOURS', 'abc');
setInviteEnv('DOCTOR_INVITE_RESEND_COOLDOWN_MINUTES', '5.5');
setInviteEnv('DOCTOR_INVITE_MAX_SENDS_PER_DAY', '');
$invalid = App::getConfig()['doctor_invitation'];
expect_true($invalid['token_ttl_hours'] === 24, 'Non-integer TTL falls back to 24');
expect_true($invalid['resend_cooldown_minutes'] === 5, 'Non-integer cooldown falls back to 5');
expect_true($invalid['max_sends_per_day'] === 8, 'Empty daily cap falls back to 8');

setInviteEnv('DOCTOR_INVITE_TOKEN_TTL_HOURS', '0');
setInviteEnv('DOCTOR_INVITE_RESEND_COOLDOWN_MINUTES', '-3');
setInviteEnv('DOCTOR_INVITE_MAX_SENDS_PER_DAY', '0');
$low = App::getConfig()['doctor_invitation'];
expect_true($low['token_ttl_hours'] === 1, 'TTL below 1 is clamped to 1');
expect_true($low['resend_cooldown_minutes'] === 1, 'Cooldown below 1 is clamped to 1');
expect_true($low['max_sends_per_day'] === 1, 'Daily cap below 1 is clamped to 1');

setInviteEnv('DOCTOR_INVITE_TOKEN_TTL_HOURS', '200');
$high = App::getConfig()['doctor_invitation'];
expect_true($high['token_ttl_hours'] === 168, 'TTL above 168 is clamped to 168');

setInviteEnv('DOCTOR_INVITE_TOKEN_TTL_HOURS', '48');
setInviteEnv('DOCTOR_INVITE_RESEND_COOLDOWN_MINUTES', '10');
setInviteEnv('DOCTOR_INVITE_MAX_SENDS_PER_DAY', '3');
$valid = App::getConfig()['doctor_invitation'];
expect_true($valid['token_ttl_hours'] === 48, 'Valid TTL 48 is accepted');
expect_true($valid['resend_cooldown_minutes'] === 10, 'Valid cooldown 10 is accepted');
expect_true($valid['max_sends_per_day'] === 3, 'Valid daily cap 3 is accepted');

restoreInviteEnv($snapshot);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
