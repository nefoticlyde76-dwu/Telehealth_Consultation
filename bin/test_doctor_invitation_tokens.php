<?php

/**
 * Doctor invitation token persistence checks (Feature 2 Step 6).
 *
 * Usage: php bin/test_doctor_invitation_tokens.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\App;
use App\Config\Environment;
use App\Core\Database;
use App\Models\DoctorPasswordSetupToken;
use App\Models\User;
use App\Services\DoctorInvitationService;

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

DoctorInvitationService::resetTestState();

$db = Database::getInstance();
$roleId = User::findRoleIdByName('doctor') ?? User::findRoleIdByName('patient');
$suffix = bin2hex(random_bytes(4));
$testEmail = 'invite.token+' . $suffix . '@telehealth.test';
$userId = 0;

try {
    expect_true($roleId !== null && $roleId > 0, 'A role id is available for the throwaway user');

    $user = new User();
    $user->role_id = (int) $roleId;
    $user->full_name = 'Invite Token Test';
    $user->email = $testEmail;
    $user->password = null;
    $user->status = 'invitation_pending';
    expect_true($user->save() && $user->id !== null, 'Throwaway user can be created for FK-safe token tests');
    $userId = (int) $user->id;

    expect_true(DoctorInvitationService::issueToken(0) === null, 'issueToken rejects a non-positive user id');
    expect_true(DoctorInvitationService::issueToken(2147483647) === null, 'issueToken rejects an unknown user id');

    $first = DoctorInvitationService::issueToken($userId);
    expect_true(is_array($first), 'Token is generated');
    expect_true(
        isset($first['raw_token']) && strlen((string) $first['raw_token']) === 64 && ctype_xdigit((string) $first['raw_token']),
        'Raw token is 64 hex characters'
    );

    $second = DoctorInvitationService::issueToken($userId);
    expect_true(is_array($second), 'A second token can be issued');
    expect_true(
        (string) ($first['raw_token'] ?? '') !== (string) ($second['raw_token'] ?? ''),
        'Two issued tokens are different'
    );

    $stored = DoctorInvitationService::findById((int) $second['token_id']);
    expect_true(is_array($stored), 'Issued token row can be loaded by id');
    expect_true(!array_key_exists('raw_token', $stored ?? []), 'Persisted token row has no raw_token column');
    expect_true(
        preg_match('/^[a-f0-9]{64}$/', (string) ($stored['token_hash'] ?? '')) === 1,
        'Stored hash is exactly 64 hex characters'
    );
    expect_true(
        (string) ($stored['token_hash'] ?? '') === hash('sha256', (string) $second['raw_token']),
        'Stored hash equals SHA-256 of the raw token'
    );
    expect_true(
        (string) ($stored['token_hash'] ?? '') === (string) $second['token_hash'],
        'Returned token_hash matches the stored hash'
    );
    $rowValues = implode('|', array_map(static fn ($value): string => (string) $value, $stored ?? []));
    expect_true(
        !str_contains($rowValues, (string) $second['raw_token']),
        'Raw token is not stored anywhere in the token row'
    );

    $ttlHours = (int) App::getConfig()['doctor_invitation']['token_ttl_hours'];
    $secondsStmt = $db->prepare(
        'SELECT TIMESTAMPDIFF(SECOND, NOW(), expires_at) FROM doctor_password_setup_tokens WHERE id = :id'
    );
    $secondsStmt->execute([':id' => (int) $second['token_id']]);
    $secondsRemaining = (int) $secondsStmt->fetchColumn();
    $expectedSeconds = $ttlHours * 3600;
    expect_true($ttlHours >= 1, 'Configured TTL is at least 1 hour');
    expect_true(
        abs($secondsRemaining - $expectedSeconds) <= 30,
        'Expiry uses the configured TTL from application config'
    );
    expect_true(array_key_exists('sent_at', $stored) && $stored['sent_at'] === null, 'sent_at is NULL after issuance');
    expect_true(array_key_exists('used_at', $stored) && $stored['used_at'] === null, 'used_at is NULL after issuance');

    $firstAfterResend = DoctorInvitationService::findById((int) $first['token_id']);
    $firstExpiredStmt = $db->prepare(
        'SELECT expires_at <= NOW() FROM doctor_password_setup_tokens WHERE id = :id'
    );
    $firstExpiredStmt->execute([':id' => (int) $first['token_id']]);
    expect_true(
        (int) $firstExpiredStmt->fetchColumn() === 1 && array_key_exists('used_at', $firstAfterResend) && $firstAfterResend['used_at'] === null,
        'Existing unused/unexpired token is expired when a new token is issued'
    );

    $usedSeed = $db->prepare(
        "INSERT INTO doctor_password_setup_tokens (user_id, token_hash, expires_at, used_at, sent_at)
         VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW(), NULL)"
    );
    $usedHash = hash('sha256', 'used-seed-' . $suffix);
    $usedSeed->execute([
        ':user_id' => $userId,
        ':token_hash' => $usedHash,
    ]);
    $usedId = (int) $db->lastInsertId();
    $usedBefore = DoctorPasswordSetupToken::findById($usedId);

    $expiredSeed = $db->prepare(
        "INSERT INTO doctor_password_setup_tokens (user_id, token_hash, expires_at, used_at, sent_at)
         VALUES (:user_id, :token_hash, DATE_SUB(NOW(), INTERVAL 1 HOUR), NULL, NULL)"
    );
    $expiredHash = hash('sha256', 'expired-seed-' . $suffix);
    $expiredSeed->execute([
        ':user_id' => $userId,
        ':token_hash' => $expiredHash,
    ]);
    $expiredId = (int) $db->lastInsertId();
    $expiredBefore = DoctorPasswordSetupToken::findById($expiredId);

    $third = DoctorInvitationService::issueToken($userId);
    expect_true(is_array($third), 'A third token can be issued after seeding used and expired rows');

    $usedAfter = DoctorPasswordSetupToken::findById($usedId);
    expect_true(
        (string) ($usedAfter['expires_at'] ?? '') === (string) ($usedBefore['expires_at'] ?? '')
        && (string) ($usedAfter['used_at'] ?? '') === (string) ($usedBefore['used_at'] ?? ''),
        'Used tokens are not modified when a new token is issued'
    );

    $expiredAfter = DoctorPasswordSetupToken::findById($expiredId);
    expect_true(
        (string) ($expiredAfter['expires_at'] ?? '') === (string) ($expiredBefore['expires_at'] ?? '')
        && $expiredAfter['used_at'] === null,
        'Already-expired tokens are not treated as active during issuance'
    );

    $found = DoctorInvitationService::findByTokenHash((string) $third['token_hash']);
    expect_true(
        is_array($found) && (int) ($found['id'] ?? 0) === (int) $third['token_id'],
        'Hash lookup finds the correct token'
    );
    expect_true(
        DoctorInvitationService::findByTokenHash(hash('sha256', 'unknown-token-' . $suffix)) === null,
        'Unknown hash returns null'
    );

    expect_true(DoctorInvitationService::markUsed((int) $third['token_id']) === true, 'First markUsed() succeeds');
    expect_true(DoctorInvitationService::markUsed((int) $third['token_id']) === false, 'Second markUsed() affects zero rows');

    $live = DoctorInvitationService::issueToken($userId);
    expect_true(is_array($live), 'A live unused token exists before the failed-insert check');
    $beforeFail = DoctorInvitationService::findById((int) $live['token_id']);
    $beforeFailFuture = $db->prepare(
        'SELECT expires_at > NOW() FROM doctor_password_setup_tokens WHERE id = :id'
    );
    $beforeFailFuture->execute([':id' => (int) $live['token_id']]);
    expect_true((int) $beforeFailFuture->fetchColumn() === 1, 'Live token is unexpired before the failed insert');

    $logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mbpha_invite_token_test.log';
    @unlink($logFile);
    $previousLog = ini_get('error_log');
    ini_set('error_log', $logFile);

    DoctorInvitationService::$testFailInsert = true;
    $failedIssue = DoctorInvitationService::issueToken($userId);
    DoctorInvitationService::resetTestState();
    ini_set('error_log', is_string($previousLog) ? $previousLog : '');

    expect_true($failedIssue === null, 'Failed insert returns null');
    $afterFail = DoctorInvitationService::findById((int) $live['token_id']);
    $afterFailFuture = $db->prepare(
        'SELECT expires_at > NOW() FROM doctor_password_setup_tokens WHERE id = :id'
    );
    $afterFailFuture->execute([':id' => (int) $live['token_id']]);
    expect_true(
        (string) ($afterFail['expires_at'] ?? '') === (string) ($beforeFail['expires_at'] ?? '')
        && (int) $afterFailFuture->fetchColumn() === 1
        && $afterFail['used_at'] === null,
        'If new-token insertion fails, previous-token expiry is rolled back'
    );

    $logContents = is_file($logFile) ? (string) file_get_contents($logFile) : '';
    @unlink($logFile);
    expect_true(
        !str_contains($logContents, (string) ($first['raw_token'] ?? 'missing'))
        && !str_contains($logContents, (string) ($second['raw_token'] ?? 'missing'))
        && !str_contains($logContents, (string) ($third['raw_token'] ?? 'missing'))
        && !str_contains($logContents, (string) ($live['raw_token'] ?? 'missing')),
        'Raw token does not appear in application logs/error output'
    );
    expect_true(
        str_contains($logContents, 'Token issuance failed'),
        'Failed issuance writes a generic error without token material'
    );

    expect_true(
        str_contains((string) file_get_contents($root . '/app/Services/DoctorInvitationService.php'), 'MailService::send'),
        'Invitation email is sent through MailService::send'
    );
} catch (Throwable $exception) {
    expect_true(false, 'Token tests completed without an unexpected exception');
} finally {
    DoctorInvitationService::resetTestState();
    if ($userId > 0) {
        $deleteUser = $db->prepare('DELETE FROM users WHERE id = :id AND email = :email');
        $deleteUser->execute([
            ':id' => $userId,
            ':email' => $testEmail,
        ]);
    }
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
