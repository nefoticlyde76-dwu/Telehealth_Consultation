<?php

namespace App\Services;

use App\Core\Csrf;
use App\Core\Database;
use App\Helpers\Helper;
use App\Models\DoctorPasswordSetupToken;
use App\Models\User;

/**
 * Public doctor invitation password setup.
 * Validates the bearer token and completes password activation atomically.
 * Does not send email, create sessions, or auto-login.
 */
class DoctorPasswordSetupService
{
    public const INVALID_MESSAGE = 'This invitation link is invalid or has expired. Ask an MBPHA administrator to send a new invitation.';
    public const SUCCESS_MESSAGE = 'Password saved successfully. You can now sign in with your email and new password.';
    public const CSRF_MESSAGE = 'Unable to verify the request. Please refresh the page and try again.';

    /**
     * Test-only hook: claim the token, then fail before user activation
     * so callers can prove the transaction rolls back. Production must
     * leave this false.
     */
    public static bool $testFailAfterTokenClaim = false;

    public static function resetTestState(): void
    {
        self::$testFailAfterTokenClaim = false;
    }

    /**
     * @return array{valid:bool,doctorName?:string,message?:string}
     */
    public static function inspect(string $rawToken): array
    {
        $context = self::inspectRawToken($rawToken);
        if ($context === null) {
            return [
                'valid' => false,
                'message' => self::INVALID_MESSAGE,
            ];
        }

        return [
            'valid' => true,
            'doctorName' => $context['doctor_name'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *   success:bool,
     *   invalidLink:bool,
     *   csrfFailed:bool,
     *   message:string,
     *   doctorName?:string,
     *   errors:list<string>,
     *   fieldErrors:array<string,string>
     * }
     */
    public static function complete(array $input): array
    {
        if (!Csrf::verify((string) ($input['_token'] ?? ''))) {
            return [
                'success' => false,
                'invalidLink' => false,
                'csrfFailed' => true,
                'message' => self::CSRF_MESSAGE,
                'errors' => [self::CSRF_MESSAGE],
                'fieldErrors' => [],
            ];
        }

        $rawToken = strtolower(trim((string) ($input['token'] ?? '')));
        $context = self::inspectRawToken($rawToken);
        if ($context === null) {
            return self::invalidResult();
        }

        $password = (string) ($input['password'] ?? '');
        $confirmPassword = (string) ($input['confirm_password'] ?? '');
        $fieldErrors = [];

        if ($password === '') {
            $fieldErrors['password'] = 'New password is required.';
        } elseif (!Helper::isStrongPassword($password)) {
            $fieldErrors['password'] = 'Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.';
        }

        if ($confirmPassword === '') {
            $fieldErrors['confirm_password'] = 'Please confirm the new password.';
        } elseif ($confirmPassword !== $password) {
            $fieldErrors['confirm_password'] = 'Passwords do not match.';
        }

        if ($fieldErrors !== []) {
            return [
                'success' => false,
                'invalidLink' => false,
                'csrfFailed' => false,
                'message' => 'Please correct the highlighted password fields.',
                'doctorName' => $context['doctor_name'],
                'errors' => ['Please correct the highlighted password fields.'],
                'fieldErrors' => $fieldErrors,
            ];
        }

        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            $locked = DoctorPasswordSetupToken::findValidInvitationByHash($context['token_hash'], true);
            if ($locked === null) {
                throw new \RuntimeException('invitation_invalid');
            }

            if (!DoctorPasswordSetupToken::claimUsable((int) $locked['token_id'])) {
                throw new \RuntimeException('invitation_invalid');
            }

            if (self::$testFailAfterTokenClaim) {
                throw new \RuntimeException('invitation_invalid');
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            if (!is_string($passwordHash) || $passwordHash === '') {
                throw new \RuntimeException('invitation_invalid');
            }

            if (!User::completeInvitationPassword((int) $locked['user_id'], $passwordHash)) {
                throw new \RuntimeException('invitation_invalid');
            }

            $db->commit();

            $subjectName = trim((string) ($locked['full_name'] ?? $context['doctor_name'] ?? ''));
            AuditLogService::record(
                'doctor_password_setup_completed',
                'Doctor completed password setup through an invitation.',
                AuditLogService::ENTITY_USER,
                (int) $locked['user_id'],
                'success',
                [
                    'system_actor' => true,
                    'actor_name' => 'System',
                    'actor_role' => 'system',
                    'subject_name' => $subjectName,
                    'subject_role' => 'doctor',
                ]
            );

            return [
                'success' => true,
                'invalidLink' => false,
                'csrfFailed' => false,
                'message' => self::SUCCESS_MESSAGE,
                'errors' => [],
                'fieldErrors' => [],
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            if ($exception->getMessage() !== 'invitation_invalid') {
                error_log('Doctor password setup failed.');
            }

            return self::invalidResult();
        }
    }

    /**
     * @return array{token_id:int,user_id:int,token_hash:string,doctor_name:string}|null
     */
    private static function inspectRawToken(string $rawToken): ?array
    {
        $rawToken = strtolower(trim($rawToken));
        if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            return null;
        }

        $tokenHash = hash('sha256', $rawToken);
        $row = DoctorPasswordSetupToken::findValidInvitationByHash($tokenHash);
        if ($row === null) {
            return null;
        }

        $doctorName = trim((string) ($row['full_name'] ?? ''));
        if ($doctorName === '') {
            $doctorName = 'Clinician';
        }

        return [
            'token_id' => (int) $row['token_id'],
            'user_id' => (int) $row['user_id'],
            'token_hash' => $tokenHash,
            'doctor_name' => $doctorName,
        ];
    }

    /**
     * @return array{
     *   success:bool,
     *   invalidLink:bool,
     *   csrfFailed:bool,
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
            'csrfFailed' => false,
            'message' => self::INVALID_MESSAGE,
            'errors' => [self::INVALID_MESSAGE],
            'fieldErrors' => [],
        ];
    }
}
