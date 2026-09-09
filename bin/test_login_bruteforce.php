<?php

/**
 * Password-login brute-force protection.
 *
 * Usage: php bin/test_login_bruteforce.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Controllers\AuthController;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Helpers\Helper;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\AuthService;
use App\Services\LoginAttemptService;

$root = dirname(__DIR__);
Environment::load($root . '/.env');
date_default_timezone_set('Pacific/Port_Moresby');
Session::start();

$_SERVER['REQUEST_URI'] = '/login';
$_SERVER['REMOTE_ADDR'] = '198.51.100.77';
$_SERVER['HTTP_HOST'] = 'localhost';

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

function post_login(string $email, string $password): string
{
    Session::remove('user_id');
    Session::remove('user_role');

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'email' => $email,
        'password' => $password,
        '_token' => Csrf::generate(),
    ];

    ob_start();
    (new AuthController())->login();

    return (string) ob_get_clean();
}

$db = Database::getInstance();

$tableStmt = $db->prepare(
    'SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = :table'
);
$tableStmt->execute([':table' => 'login_attempts']);
if ((int) $tableStmt->fetchColumn() === 0) {
    $sql = file_get_contents($root . '/database/migrations/032_create_login_attempts_table.sql');
    if (!is_string($sql) || trim($sql) === '') {
        fwrite(STDERR, "Migration 032 could not be read.\n");
        exit(1);
    }
    $db->exec($sql);
}

$catalog = AuditLogService::catalog();
expect_true(isset($catalog['login_lockout']), 'Audit catalog includes login_lockout');
expect_true(
    ($catalog['login_lockout']['category'] ?? '') === 'authentication',
    'login_lockout uses the authentication category'
);

expect_true(LoginAttemptService::delaySecondsForFailures(0) === 0, 'No delay before the first failure');
expect_true(LoginAttemptService::delaySecondsForFailures(1) === 1, 'Backoff is 1s after one failure');
expect_true(LoginAttemptService::delaySecondsForFailures(2) === 2, 'Backoff is 2s after two failures');
expect_true(LoginAttemptService::delaySecondsForFailures(3) === 4, 'Backoff is 4s after three failures');
expect_true(LoginAttemptService::delaySecondsForFailures(4) === 8, 'Backoff is 8s after four failures');
expect_true(LoginAttemptService::delaySecondsForFailures(9) === 8, 'Backoff is capped at 8s');

$authControllerSource = (string) file_get_contents($root . '/app/Controllers/AuthController.php');
expect_true(
    str_contains($authControllerSource, 'AuthService::authenticate($email, $password)'),
    'Password login still authenticates through AuthService'
);
expect_true(
    str_contains($authControllerSource, 'LoginAttemptService::inspect')
        && str_contains($authControllerSource, 'LoginAttemptService::clear'),
    'Login controller checks lockout and resets the counter on success'
);

$suffix = bin2hex(random_bytes(4));
$email = 'bruteforce+' . $suffix . '@telehealth.test';
$unknownEmail = 'unknown+' . $suffix . '@telehealth.test';
$password = 'Week9!LockA';
$createdUserId = 0;
$auditMaxBefore = 0;

LoginAttemptService::resetTestState();
LoginAttemptService::$testSkipDelay = true;
LoginAttemptService::$testWindowSeconds = 30;
LoginAttemptService::$testMaxAttempts = LoginAttemptService::MAX_ATTEMPTS;

try {
    $roleStmt = $db->query("SELECT id FROM roles WHERE name = 'patient' LIMIT 1");
    $patientRoleId = (int) $roleStmt->fetchColumn();
    expect_true($patientRoleId > 0, 'Patient role exists');

    $insert = $db->prepare(
        "INSERT INTO users (role_id, full_name, email, password, status)
         VALUES (:role_id, :full_name, :email, :password, 'active')"
    );
    $insert->execute([
        ':role_id' => $patientRoleId,
        ':full_name' => 'Brute Force Test Patient',
        ':email' => $email,
        ':password' => password_hash($password, PASSWORD_DEFAULT),
    ]);
    $createdUserId = (int) $db->lastInsertId();
    expect_true($createdUserId > 0, 'Test patient account created');

    LoginAttemptService::clear($email);
    LoginAttemptService::clear($unknownEmail);

    $auditMaxStmt = $db->query('SELECT COALESCE(MAX(id), 0) FROM audit_logs');
    $auditMaxBefore = (int) $auditMaxStmt->fetchColumn();

    $maxAttempts = LoginAttemptService::maxAttempts();
    $lastHtml = '';
    for ($i = 1; $i <= $maxAttempts; $i++) {
        $lastHtml = post_login($email, 'WrongPass1!');
        $state = LoginAttemptService::inspect($email);
        if ($i < $maxAttempts) {
            expect_true(
                str_contains($lastHtml, 'Invalid email, password, or account status.'),
                "Failure {$i} uses the generic credential error"
            );
            expect_true($state['locked'] === false, "Failure {$i} does not lock yet");
            expect_true($state['failed_count'] === $i, "Failure {$i} increments the counter");
        }
    }

    $lockedState = LoginAttemptService::inspect($email);
    expect_true($lockedState['locked'] === true, 'N failed logins lock the email and IP');
    expect_true(
        str_contains($lastHtml, LoginAttemptService::LOCKOUT_MESSAGE),
        'Lockout returns the public lockout message'
    );
    expect_true(
        !str_contains($lastHtml, 'Invalid email, password, or account status.'),
        'Lockout message does not reveal whether the email exists'
    );

    $lockoutRow = $db->prepare(
        "SELECT event_type, actor_name, actor_role, subject_name, subject_role, outcome, entity_type
         FROM audit_logs
         WHERE id > :max_before
           AND event_type = 'login_lockout'
         ORDER BY id DESC
         LIMIT 1"
    );
    $lockoutRow->bindValue(':max_before', $auditMaxBefore, PDO::PARAM_INT);
    $lockoutRow->execute();
    $lockoutAudit = $lockoutRow->fetch(PDO::FETCH_ASSOC) ?: null;
    expect_true(is_array($lockoutAudit), 'Lockout is written to the audit log');
    if (is_array($lockoutAudit)) {
        expect_true((string) ($lockoutAudit['actor_name'] ?? '') === 'Guest', 'Lockout actor_name matches login_failed');
        expect_true((string) ($lockoutAudit['actor_role'] ?? '') === 'guest', 'Lockout actor_role matches login_failed');
        expect_true((string) ($lockoutAudit['subject_name'] ?? '') === $email, 'Lockout subject_name is the attempted email');
        expect_true((string) ($lockoutAudit['subject_role'] ?? '') === 'guest', 'Lockout subject_role matches login_failed');
        expect_true((string) ($lockoutAudit['outcome'] ?? '') === 'failed', 'Lockout outcome is failed');
        expect_true((string) ($lockoutAudit['entity_type'] ?? '') === AuditLogService::ENTITY_AUTH, 'Lockout entity_type is auth');
    }

    $correctWhileLocked = post_login($email, $password);
    expect_true(
        str_contains($correctWhileLocked, LoginAttemptService::LOCKOUT_MESSAGE),
        'Correct password during lockout is still rejected'
    );
    expect_true(Session::get('user_id') === null, 'Lockout does not create a session');
    expect_true(AuthService::isAuthenticated() === false, 'Lockout leaves the guest unauthenticated');
    $stillValid = AuthService::authenticate($email, $password);
    expect_true($stillValid instanceof User, 'Stored password remains valid while the login path is locked');

    $otherIpState = LoginAttemptService::inspect($email, '198.51.100.80');
    expect_true($otherIpState['locked'] === false, 'Lockout is scoped to email and IP together');

    LoginAttemptService::$testNow = Helper::now()->modify('+' . (LoginAttemptService::windowSeconds() + 1) . ' seconds');
    $expiredState = LoginAttemptService::inspect($email);
    expect_true($expiredState['locked'] === false, 'Lockout clears after the window expires');
    expect_true($expiredState['failed_count'] === 0, 'Expired lockout resets the failed-attempt counter');

    $afterExpiryHtml = post_login($email, 'WrongPass1!');
    expect_true(
        str_contains($afterExpiryHtml, 'Invalid email, password, or account status.'),
        'After the window expires, a failed login is treated as a new attempt'
    );
    expect_true(
        !str_contains($afterExpiryHtml, LoginAttemptService::LOCKOUT_MESSAGE),
        'After the window expires, the lockout message is not shown'
    );

    $afterOne = LoginAttemptService::inspect($email);
    expect_true($afterOne['locked'] === false && $afterOne['failed_count'] === 1, 'A new window starts at one failure');

    LoginAttemptService::clear($email);
    $cleared = LoginAttemptService::inspect($email);
    expect_true($cleared['failed_count'] === 0 && $cleared['locked'] === false, 'Successful login path resets the counter');

    $unknownHtml = '';
    for ($i = 1; $i <= $maxAttempts; $i++) {
        $unknownHtml = post_login($unknownEmail, 'WrongPass1!');
    }
    expect_true(
        str_contains($unknownHtml, LoginAttemptService::LOCKOUT_MESSAGE),
        'Unknown emails receive the same lockout message'
    );
    expect_true(
        !str_contains(strtolower($unknownHtml), 'does not exist')
            && !str_contains(strtolower($unknownHtml), 'no account'),
        'Lockout does not disclose that the email is unknown'
    );
} catch (Throwable $exception) {
    expect_true(false, 'Brute-force tests completed without an unexpected exception: ' . $exception->getMessage());
}

LoginAttemptService::resetTestState();

try {
    LoginAttemptService::clear($email);
    LoginAttemptService::clear($unknownEmail);
    if ($createdUserId > 0) {
        $db->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $createdUserId]);
    }
} catch (Throwable $exception) {
    echo 'Cleanup warning: ' . $exception->getMessage() . "\n";
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
