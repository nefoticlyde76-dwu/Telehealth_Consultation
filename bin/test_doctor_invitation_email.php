<?php

/**
 * Doctor invitation email delivery checks (Feature 2 Step 7).
 *
 * Usage: php bin/test_doctor_invitation_email.php
 *
 * Uses the array/fail mailers only. Does not send real Gmail.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Core\Database;
use App\Helpers\Helper;
use App\Models\User;
use App\Services\DoctorInvitationService;
use App\Services\MailService;

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

function setEnvKey(string $key, string $value): void
{
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
    putenv($key . '=' . $value);
}

$originalMailer = (string) Environment::get('MAIL_MAILER', 'log');
$originalTtl = (string) Environment::get('DOCTOR_INVITE_TOKEN_TTL_HOURS', '24');

DoctorInvitationService::resetTestState();
MailService::resetTestState();

$db = Database::getInstance();
$roleId = User::findRoleIdByName('doctor') ?? User::findRoleIdByName('patient');
$suffix = bin2hex(random_bytes(4));
$testEmail = 'invite.email+' . $suffix . '@telehealth.test';
$unsafeName = 'Casey <script>alert(1)</script> Clinician';
$userId = 0;

try {
    expect_true($roleId !== null && $roleId > 0, 'A role id is available for the throwaway user');

    $user = new User();
    $user->role_id = (int) $roleId;
    $user->full_name = $unsafeName;
    $user->email = $testEmail;
    $user->password = null;
    $user->status = 'invitation_pending';
    expect_true($user->save() && $user->id !== null, 'Throwaway user can be created');
    $userId = (int) $user->id;

    setEnvKey('MAIL_MAILER', 'array');
    setEnvKey('DOCTOR_INVITE_TOKEN_TTL_HOURS', '48');
    MailService::resetTestState();

    $issued = DoctorInvitationService::issueToken($userId);
    expect_true(is_array($issued), 'Token issued');
    $rawToken = (string) ($issued['raw_token'] ?? '');
    $tokenHash = (string) ($issued['token_hash'] ?? '');
    $tokenId = (int) ($issued['token_id'] ?? 0);
    $ttlHours = (int) ($issued['ttl_hours'] ?? 0);

    $result = DoctorInvitationService::sendIssuedInvitation($issued);
    expect_true(($result['success'] ?? false) === true, 'Structured success is returned');
    expect_true(($result['smtp_accepted'] ?? false) === true, 'SMTP accepted is true when MailService returns true');
    expect_true(!array_key_exists('raw_token', $result), 'Success result does not expose the raw token');
    expect_true(count(MailService::$outbox) === 1, 'MailService::send() succeeds through the array mailer');

    $message = MailService::$outbox[0];
    $expectedUrl = Helper::applicationUrl('/doctor/setup-password?token=' . $rawToken);
    $expectedContact = Helper::applicationUrl('/contact');
    $html = (string) ($message['html'] ?? '');
    $text = (string) ($message['text'] ?? '');

    expect_true((string) ($message['to'] ?? '') === strtolower($testEmail), 'Correct recipient is users.email');
    expect_true((string) ($message['subject'] ?? '') === 'MBPHA TeleHealth – Set Up Your Doctor Account', 'Exact invitation subject is used');
    expect_true($html !== '', 'HTML email is generated');
    expect_true($text !== '', 'Plain-text email is generated');
    expect_true(str_contains($expectedUrl, $rawToken), 'Setup URL contains the raw token');
    expect_true(str_contains($html, $expectedUrl), 'HTML contains the setup URL');
    expect_true(str_contains($text, $expectedUrl), 'Plain text contains the setup URL');
    expect_true($ttlHours === 48, 'Issuance result carries the configured TTL');
    expect_true(str_contains($html, '48 hours') && str_contains($text, '48 hours'), 'TTL text matches configured expiry');
    expect_true(str_contains($html, 'Set Up My Password'), 'HTML includes the primary CTA');
    expect_true(str_contains($html, $expectedContact) && str_contains($text, $expectedContact), 'Contact URL is correct');
    expect_true(str_contains($html, 'This link expires in') && str_contains($text, 'This link expires in'), 'Email contains expiry wording');
    expect_true(
        str_contains($html, Helper::escape($unsafeName)) && !str_contains($html, '<script>alert(1)</script>'),
        'Doctor name is escaped in HTML'
    );
    expect_true(str_contains($text, $unsafeName), 'Plain text includes the doctor name');

    $row = DoctorInvitationService::findById($tokenId);
    expect_true(is_array($row) && $row['sent_at'] !== null, 'sent_at becomes non-NULL after a successful send');
    expect_true(is_array($row) && $row['used_at'] === null, 'Successful send does not mark the token used');

    $mailPassword = (string) MailService::config()['password'];
    $combined = $html . "\n" . $text;
    expect_true(!str_contains($combined, $tokenHash), 'Token hash does not appear in the email');
    expect_true(!str_contains(strtolower($combined), 'password_hash'), 'Passwords do not appear in the email');
    expect_true($mailPassword === '' || !str_contains($combined, $mailPassword), 'SMTP password does not appear in the email');
    expect_true(!preg_match('/\buser_id\b/i', $combined) && !preg_match('/account\s*#/i', $combined), 'Internal database IDs do not appear in the email');
    expect_true(!str_contains($html, 'Helper::url(') && str_contains($html, 'setup-password?token='), 'Invitation URL is the applicationUrl setup path');

    setEnvKey('MAIL_MAILER', 'fail');
    MailService::resetTestState();
    $failedIssued = DoctorInvitationService::issueToken($userId);
    expect_true(is_array($failedIssued), 'A second token can be issued for the failure case');
    $failedTokenId = (int) ($failedIssued['token_id'] ?? 0);
    $failedRaw = (string) ($failedIssued['raw_token'] ?? '');

    $logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mbpha_invite_email_test.log';
    @unlink($logFile);
    $previousLog = ini_get('error_log');
    ini_set('error_log', $logFile);

    $failedResult = DoctorInvitationService::sendIssuedInvitation($failedIssued);
    ini_set('error_log', is_string($previousLog) ? $previousLog : '');

    expect_true(($failedResult['success'] ?? true) === false, 'No false success is returned when MailService fails');
    expect_true(($failedResult['smtp_accepted'] ?? true) === false, 'smtp_accepted is false when MailService returns false');
    expect_true(($failedResult['error_code'] ?? '') === 'mail_rejected', 'Failure result includes mail_rejected');
    expect_true(!array_key_exists('raw_token', $failedResult), 'Failure result does not expose the raw token');

    $failedRow = DoctorInvitationService::findById($failedTokenId);
    expect_true(is_array($failedRow) && $failedRow['sent_at'] === null, 'sent_at remains NULL after a failed send');
    expect_true(is_array($failedRow) && $failedRow['used_at'] === null, 'Token remains unused after a failed send');

    $secondsStmt = $db->prepare('SELECT expires_at > NOW() FROM doctor_password_setup_tokens WHERE id = :id');
    $secondsStmt->execute([':id' => $failedTokenId]);
    expect_true((int) $secondsStmt->fetchColumn() === 1, 'Failed send leaves the token usable');

    $logContents = is_file($logFile) ? (string) file_get_contents($logFile) : '';
    @unlink($logFile);
    expect_true(
        !str_contains($logContents, $rawToken)
        && !str_contains($logContents, $failedRaw)
        && !str_contains($logContents, 'setup-password?token='),
        'Raw token and invitation URL do not appear in logs'
    );
} catch (Throwable $exception) {
    expect_true(false, 'Invitation email tests completed without an unexpected exception');
} finally {
    DoctorInvitationService::resetTestState();
    MailService::resetTestState();
    setEnvKey('MAIL_MAILER', $originalMailer);
    setEnvKey('DOCTOR_INVITE_TOKEN_TTL_HOURS', $originalTtl);
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
