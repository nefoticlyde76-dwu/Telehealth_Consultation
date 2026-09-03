<?php

namespace App\Services;

use App\Config\Environment;
use App\Core\Database;
use App\Helpers\Helper;
use App\Helpers\Status;
use App\Models\PasswordResetToken;
use App\Models\User;
use PDO;

/**
 * Public forgot-password / reset-password.
 * Issues hash-only tokens, sends mail through MailService, and completes
 * password changes atomically. Does not create sessions or auto-login.
 */
class PasswordResetService
{
    public const REQUEST_MESSAGE = 'If an account exists for that email, you will receive reset instructions shortly.';
    public const SUCCESS_MESSAGE = 'Password saved successfully. You can now sign in with your email and new password.';
    public const INVALID_MESSAGE = 'This password reset link is invalid or has expired. You can request a new one from the sign-in page.';

    /**
     * Test-only hook: update the password, then fail before the token is
     * claimed so callers can prove the transaction rolls back. Production
     * must leave this false.
     */
    public static bool $testFailAfterPasswordUpdate = false;

    public static function resetTestState(): void
    {
        self::$testFailAfterPasswordUpdate = false;
    }

    /**
     * Enumeration-safe reset request. Valid email format always yields the
     * same public success payload whether or not a token was issued.
     *
     * @return array{
     *   success:bool,
     *   message:string,
     *   errors:list<string>,
     *   fieldErrors:array<string,string>
     * }
     */
    public static function requestReset(string $email): array
    {
        $email = strtolower(trim($email));
        $fieldErrors = [];

        if ($email === '') {
            $fieldErrors['email'] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $fieldErrors['email'] = 'Please provide a valid email address.';
        }

        if ($fieldErrors !== []) {
            return [
                'success' => false,
                'message' => 'Please correct the highlighted fields.',
                'errors' => ['Please correct the highlighted fields.'],
                'fieldErrors' => $fieldErrors,
            ];
        }

        try {
            $user = User::findByEmail($email);
            if (self::isEligibleForReset($user) && $user !== null && $user->id !== null) {
                self::issueAndSend((int) $user->id, $email, (string) ($user->full_name ?? ''));
            }
        } catch (\Throwable) {
            error_log('[PasswordResetService::requestReset] Password reset request failed.');
        }

        return self::requestAccepted();
    }

    /**
     * Complete a password reset from a raw bearer token. Does not create a
     * session or set force_password_reset.
     *
     * @return array{
     *   success:bool,
     *   invalidLink:bool,
     *   message:string,
     *   errors:list<string>,
     *   fieldErrors:array<string,string>
     * }
     */
    public static function completeReset(string $rawToken, string $newPassword, string $confirmPassword): array
    {
        $rawToken = strtolower(trim($rawToken));
        if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            return self::invalidResult();
        }

        $peek = PasswordResetToken::findValidResetByRawToken($rawToken, false);
        if ($peek === null) {
            return self::invalidResult();
        }

        $fieldErrors = [];

        if ($newPassword === '') {
            $fieldErrors['password'] = 'New password is required.';
        } elseif (!Helper::isStrongPassword($newPassword)) {
            $fieldErrors['password'] = 'Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.';
        }

        if ($confirmPassword === '') {
            $fieldErrors['confirm_password'] = 'Please confirm the new password.';
        } elseif ($confirmPassword !== $newPassword) {
            $fieldErrors['confirm_password'] = 'Passwords do not match.';
        }

        if ($fieldErrors !== []) {
            return [
                'success' => false,
                'invalidLink' => false,
                'message' => 'Please correct the highlighted password fields.',
                'errors' => ['Please correct the highlighted password fields.'],
                'fieldErrors' => $fieldErrors,
            ];
        }

        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            $locked = PasswordResetToken::findValidResetByRawToken($rawToken, true);
            if ($locked === null) {
                throw new \RuntimeException('reset_invalid');
            }

            $userId = (int) $locked['user_id'];
            $tokenId = (int) $locked['token_id'];
            if ($userId <= 0 || $tokenId <= 0) {
                throw new \RuntimeException('reset_invalid');
            }

            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            if (!is_string($passwordHash) || $passwordHash === '') {
                throw new \RuntimeException('reset_invalid');
            }

            if (!User::updatePasswordHash($userId, $passwordHash)) {
                throw new \RuntimeException('reset_invalid');
            }

            if (self::$testFailAfterPasswordUpdate) {
                throw new \RuntimeException('reset_invalid');
            }

            if (!PasswordResetToken::claimUsable($tokenId)) {
                throw new \RuntimeException('reset_invalid');
            }

            SessionService::revokeAll($userId);

            $db->commit();

            $subjectName = trim((string) ($locked['full_name'] ?? ''));
            $subjectRole = '';
            $subject = User::findById($userId);
            if ($subject !== null) {
                $subjectRole = (string) ($subject->getRole() ?? '');
                if ($subjectName === '') {
                    $subjectName = trim((string) ($subject->full_name ?? ''));
                }
            }

            AuditLogService::record(
                'password_reset_completed',
                'Account password was reset through a forgot-password link.',
                AuditLogService::ENTITY_USER,
                $userId,
                'success',
                [
                    'system_actor' => true,
                    'actor_name' => 'System',
                    'actor_role' => 'system',
                    'subject_name' => $subjectName,
                    'subject_role' => $subjectRole,
                ]
            );

            return [
                'success' => true,
                'invalidLink' => false,
                'message' => self::SUCCESS_MESSAGE,
                'errors' => [],
                'fieldErrors' => [],
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            if ($exception->getMessage() !== 'reset_invalid') {
                error_log('[PasswordResetService::completeReset] Password reset failed.');
            }

            return self::invalidResult();
        }
    }

    /**
     * @return array{token_ttl_minutes:int,request_cooldown_minutes:int}
     */
    public static function config(): array
    {
        return [
            'token_ttl_minutes' => self::boundedInt('PASSWORD_RESET_TOKEN_TTL_MINUTES', 30, 1, 1440),
            'request_cooldown_minutes' => self::boundedInt('PASSWORD_RESET_REQUEST_COOLDOWN_MINUTES', 5, 1, 60),
        ];
    }

    /**
     * @return array{
     *   success:bool,
     *   message:string,
     *   errors:list<string>,
     *   fieldErrors:array<string,string>
     * }
     */
    private static function requestAccepted(): array
    {
        return [
            'success' => true,
            'message' => self::REQUEST_MESSAGE,
            'errors' => [],
            'fieldErrors' => [],
        ];
    }

    /**
     * @return array{
     *   success:bool,
     *   invalidLink:bool,
     *   message:string,
     *   errors:list<string>,
     *   fieldErrors:array<string,string>
     * }
     */
    private static function invalidResult(): array
    {
        return [
            'success' => false,
            'invalidLink' => true,
            'message' => self::INVALID_MESSAGE,
            'errors' => [self::INVALID_MESSAGE],
            'fieldErrors' => [],
        ];
    }

    private static function isEligibleForReset(?User $user): bool
    {
        if ($user === null || $user->id === null) {
            return false;
        }

        if (!Status::canAuthenticateUserStatus((string) ($user->status ?? ''))) {
            return false;
        }

        if (!is_string($user->password) || $user->password === '') {
            return false;
        }

        if ($user->auth_provider === User::AUTH_PROVIDER_GOOGLE) {
            return false;
        }

        return true;
    }

    private static function issueAndSend(int $userId, string $email, string $fullName): void
    {
        $settings = self::config();
        $ttlMinutes = (int) $settings['token_ttl_minutes'];
        $cooldownMinutes = (int) $settings['request_cooldown_minutes'];
        if ($ttlMinutes < 1) {
            return;
        }

        $issued = self::issueToken($userId, $ttlMinutes, $cooldownMinutes);
        if ($issued === null) {
            return;
        }

        $rawToken = (string) ($issued['raw_token'] ?? '');
        if ($rawToken === '' || !preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            return;
        }

        $resetUrl = Helper::applicationUrl('/reset-password?token=' . $rawToken);
        $contactUrl = Helper::applicationUrl('/contact');
        $ttlLabel = self::ttlLabel((int) ($issued['ttl_minutes'] ?? $ttlMinutes));
        $displayName = trim($fullName);
        if ($displayName === '') {
            $displayName = 'there';
        }

        $emailData = [
            'userName' => $displayName,
            'resetUrl' => $resetUrl,
            'expiresMinutes' => (int) $ttlMinutes,
            'contactUrl' => $contactUrl,
        ];

        $html = self::renderEmailView('password_reset', [
            'emailData' => $emailData,
            'contactUrl' => $contactUrl,
        ]);
        $text = self::renderEmailView('password_reset_text', [
            'emailData' => $emailData,
            'fullName' => $displayName,
            'resetUrl' => $resetUrl,
            'contactUrl' => $contactUrl,
            'ttlLabel' => $ttlLabel,
        ]);

        $issued['raw_token'] = '';
        $rawToken = '';
        unset($issued['raw_token']);

        if ($html === '' || $text === '') {
            error_log('[PasswordResetService::requestReset] Password reset mail could not be composed.');
            return;
        }

        try {
            $accepted = MailService::send($email, 'MBPHA TeleHealth – Reset Your Password', $html, $text);
        } catch (\Throwable) {
            error_log('[PasswordResetService::requestReset] Password reset mail delivery failed.');
            return;
        }

        if (!$accepted) {
            error_log('[PasswordResetService::requestReset] Password reset mail delivery failed.');
        }
    }

    /**
     * @return array{
     *   token_id:int,
     *   user_id:int,
     *   raw_token:string,
     *   expires_at:string,
     *   ttl_minutes:int
     * }|null
     */
    private static function issueToken(int $userId, int $ttlMinutes, int $cooldownMinutes): ?array
    {
        $db = Database::getInstance();
        $ownsTransaction = !$db->inTransaction();
        $usedSavepoint = false;

        if ($ownsTransaction) {
            $db->beginTransaction();
        } else {
            $db->exec('SAVEPOINT password_reset_issue_token');
            $usedSavepoint = true;
        }

        try {
            $lock = $db->prepare('SELECT id FROM users WHERE id = :id FOR UPDATE');
            $lock->bindValue(':id', $userId, PDO::PARAM_INT);
            $lock->execute();
            if ($lock->fetch(PDO::FETCH_ASSOC) === false) {
                throw new \RuntimeException('Password reset token insert failed.');
            }

            if ($cooldownMinutes >= 1 && PasswordResetToken::hasRecentRequest($userId, $cooldownMinutes)) {
                if ($ownsTransaction) {
                    $db->commit();
                } elseif ($usedSavepoint) {
                    $db->exec('RELEASE SAVEPOINT password_reset_issue_token');
                }

                return null;
            }

            PasswordResetToken::expireUsableForUser($userId);

            $row = PasswordResetToken::issue($userId, $ttlMinutes);
            if ($row === null || (int) ($row['token_id'] ?? 0) <= 0) {
                throw new \RuntimeException('Password reset token insert failed.');
            }

            if ($ownsTransaction) {
                $db->commit();
            } elseif ($usedSavepoint) {
                $db->exec('RELEASE SAVEPOINT password_reset_issue_token');
            }

            return [
                'token_id' => (int) $row['token_id'],
                'user_id' => (int) $row['user_id'],
                'raw_token' => (string) $row['raw_token'],
                'expires_at' => (string) $row['expires_at'],
                'ttl_minutes' => (int) $row['ttl_minutes'],
            ];
        } catch (\Throwable) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            } elseif ($usedSavepoint) {
                $db->exec('ROLLBACK TO SAVEPOINT password_reset_issue_token');
            }

            error_log('[PasswordResetService::requestReset] Token issuance failed.');

            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function renderEmailView(string $view, array $data): string
    {
        $viewPath = dirname(__DIR__) . '/Views/emails/' . $view . '.php';
        if (is_file($viewPath)) {
            extract($data, EXTR_SKIP);
            ob_start();
            require $viewPath;

            return trim((string) ob_get_clean());
        }

        return $view === 'password_reset_text'
            ? self::fallbackTextEmail($data)
            : self::fallbackHtmlEmail($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function fallbackHtmlEmail(array $data): string
    {
        $fullName = Helper::escape((string) ($data['fullName'] ?? 'there'));
        $resetUrl = Helper::escape((string) ($data['resetUrl'] ?? ''));
        $contactUrl = Helper::escape((string) ($data['contactUrl'] ?? ''));
        $ttlLabel = Helper::escape((string) ($data['ttlLabel'] ?? ''));

        if ($resetUrl === '') {
            return '';
        }

        return '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MBPHA TeleHealth – Reset Your Password</title>
</head>
<body style="margin:0;padding:0;background-color:#F8F9FA;font-family:Arial,Helvetica,sans-serif;color:#212529;">
  <!-- Layout tables only (role=presentation). Not data tables — email clients require this structure. -->
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#F8F9FA;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#FFFFFF;border:1px solid #DEE2E6;border-radius:12px;overflow:hidden;">
          <tr>
            <td style="background-color:#0F4C81;padding:24px 28px;">
              <p style="margin:0;font-size:20px;font-weight:700;color:#FFFFFF;">MBPHA TeleHealth</p>
              <p style="margin:6px 0 0;font-size:13px;color:#FFFFFF;">Milne Bay Provincial Health Authority</p>
            </td>
          </tr>
          <tr>
            <td style="padding:28px;">
              <p style="margin:0 0 16px;font-size:16px;">Dear ' . $fullName . ',</p>
              <p style="margin:0 0 16px;font-size:15px;line-height:1.55;">
                We received a request to reset the password for your MBPHA TeleHealth account.
                Use the button below to choose a new password.
              </p>
              <p style="margin:24px 0;text-align:center;">
                <a href="' . $resetUrl . '" style="display:inline-block;background-color:#0F4C81;color:#FFFFFF;text-decoration:none;font-weight:600;font-size:15px;padding:12px 24px;border-radius:8px;">
                  Reset My Password
                </a>
              </p>
              <p style="margin:0 0 16px;font-size:15px;line-height:1.55;">
                This link expires in ' . $ttlLabel . '.
              </p>
              <p style="margin:0 0 16px;font-size:14px;line-height:1.55;color:#6C757D;">
                If you did not request a password reset, you can ignore this email. Your password will stay the same.
              </p>
              <p style="margin:0;font-size:14px;line-height:1.55;">
                Support:
                <a href="' . $contactUrl . '" style="color:#0F4C81;">' . $contactUrl . '</a>
              </p>
            </td>
          </tr>
          <tr>
            <td style="background-color:#F8F9FA;padding:18px 28px;border-top:1px solid #DEE2E6;">
              <p style="margin:0;font-size:13px;font-weight:600;color:#0F4C81;">MBPHA TeleHealth</p>
              <p style="margin:4px 0 0;font-size:12px;color:#6C757D;">Milne Bay Provincial Health Authority</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>';
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function fallbackTextEmail(array $data): string
    {
        $fullName = (string) ($data['fullName'] ?? 'there');
        $resetUrl = (string) ($data['resetUrl'] ?? '');
        $contactUrl = (string) ($data['contactUrl'] ?? '');
        $ttlLabel = (string) ($data['ttlLabel'] ?? '');

        if ($resetUrl === '') {
            return '';
        }

        return "MBPHA TeleHealth – Reset Your Password\n\n"
            . 'Dear ' . $fullName . ",\n\n"
            . "We received a request to reset the password for your MBPHA TeleHealth account.\n"
            . "Use the link below to choose a new password.\n\n"
            . $resetUrl . "\n\n"
            . 'This link expires in ' . $ttlLabel . ".\n\n"
            . "If you did not request a password reset, you can ignore this email. Your password will stay the same.\n\n"
            . "Support:\n"
            . $contactUrl . "\n\n"
            . "MBPHA TeleHealth\n"
            . "Milne Bay Provincial Health Authority\n";
    }

    private static function ttlLabel(int $minutes): string
    {
        if ($minutes <= 1) {
            return '1 minute';
        }

        return $minutes . ' minutes';
    }

    private static function boundedInt(string $key, int $default, int $min, ?int $max = null): int
    {
        $raw = Environment::get($key, null);
        if ($raw === null || $raw === false) {
            return $default;
        }

        $raw = trim((string) $raw);
        if ($raw === '' || filter_var($raw, FILTER_VALIDATE_INT) === false) {
            return $default;
        }

        $value = (int) $raw;
        if ($value < $min) {
            return $min;
        }
        if ($max !== null && $value > $max) {
            return $max;
        }

        return $value;
    }
}
