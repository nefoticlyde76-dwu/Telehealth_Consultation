<?php

namespace App\Services;

use App\Core\Csrf;
use App\Core\Session;
use App\Helpers\Helper;
use App\Helpers\Status;
use App\Models\NotificationPreference;
use App\Models\User;

class AccountSecurityService
{
    public const CONFIRMATION_PHRASE = 'DELETE USER';

    /**
     * @param array<string, mixed> $input
     * @return array{success:bool,message?:string,errors:list<string>,fieldErrors:array<string,string>}
     */
    public static function changeOwnPassword(int $userId, array $input): array
    {
        $fieldErrors = [];
        $errors = [];

        if (!Csrf::verify((string) ($input['_token'] ?? ''))) {
            $fieldErrors['_token'] = 'Unable to verify the request. Please refresh the page and try again.';
        }

        $user = User::findById($userId);
        if ($user === null || $user->id === null) {
            return [
                'success' => false,
                'errors' => ['Your account could not be loaded.'],
                'fieldErrors' => $fieldErrors,
            ];
        }

        if (!Status::canAuthenticateUserStatus((string) $user->status)) {
            return [
                'success' => false,
                'errors' => ['This account cannot change a password in its current status.'],
                'fieldErrors' => $fieldErrors,
            ];
        }

        $currentPassword = (string) ($input['current_password'] ?? '');
        $password = (string) ($input['password'] ?? '');
        $confirmPassword = (string) ($input['confirm_password'] ?? '');

        if ($currentPassword === '') {
            $fieldErrors['current_password'] = 'Current password is required.';
        } elseif (!is_string($user->password) || $user->password === '' || !password_verify($currentPassword, $user->password)) {
            $fieldErrors['current_password'] = 'Current password is incorrect.';
        }

        if ($password === '') {
            $fieldErrors['password'] = 'New password is required.';
        } elseif (!Helper::isStrongPassword($password)) {
            $fieldErrors['password'] = 'Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.';
        } elseif (is_string($user->password) && $user->password !== '' && password_verify($password, $user->password)) {
            $fieldErrors['password'] = 'Please choose a new password that is different from the current password.';
        }

        if ($confirmPassword === '') {
            $fieldErrors['confirm_password'] = 'Please confirm the new password.';
        } elseif ($confirmPassword !== $password) {
            $fieldErrors['confirm_password'] = 'Passwords do not match.';
        }

        if ($fieldErrors !== []) {
            if (isset($fieldErrors['_token'])) {
                $errors[] = $fieldErrors['_token'];
            }
            $errors[] = 'Please correct the highlighted password fields.';

            return [
                'success' => false,
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
            ];
        }

        try {
            if (!User::updatePasswordHash($userId, password_hash($password, PASSWORD_DEFAULT))) {
                throw new \RuntimeException('Password update did not persist.');
            }

            Session::regenerate();
            SessionService::registerCurrent($userId);
            SessionService::revokeOthers($userId);
            AuditLogService::record(
                'password_changed',
                'Account password was changed by the signed-in user.',
                AuditLogService::ENTITY_USER,
                $userId
            );

            return [
                'success' => true,
                'message' => 'Password updated successfully. Other signed-in devices were signed out.',
                'errors' => [],
                'fieldErrors' => [],
            ];
        } catch (\Throwable $exception) {
            error_log('[AccountSecurityService::changeOwnPassword] ' . $exception->getMessage());

            return [
                'success' => false,
                'errors' => ['Password updates are temporarily unavailable. Please try again later.'],
                'fieldErrors' => [],
            ];
        }
    }

    /**
     * @return array{success:bool,message:string,type:string}
     */
    public static function logoutOtherSessions(int $userId, string $csrfToken): array
    {
        if (!Csrf::verify($csrfToken)) {
            return [
                'success' => false,
                'message' => 'Unable to verify the request. Please refresh the page and try again.',
                'type' => 'danger',
            ];
        }

        if ($userId <= 0) {
            return [
                'success' => false,
                'message' => 'Your session could not be verified.',
                'type' => 'danger',
            ];
        }

        $revoked = SessionService::revokeOthers($userId);
        AuditLogService::record(
            'sessions_revoked',
            'User signed out other devices.',
            AuditLogService::ENTITY_AUTH,
            $userId
        );

        return [
            'success' => true,
            'message' => $revoked > 0
                ? 'Other devices have been signed out.'
                : 'No other signed-in devices were found.',
            'type' => 'success',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getSecurityPageData(int $userId): array
    {
        $user = User::findById($userId);

        return [
            'sessions' => SessionService::listForUser($userId),
            'forcePasswordReset' => (int) ($user->force_password_reset ?? 0) === 1,
            'lastLoginAt' => $user->last_login_at ?? null,
            'accountStatus' => (string) ($user->status ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function getPreferencePageData(int $userId): array
    {
        return [
            'preferences' => NotificationPreference::findOrDefault($userId),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success:bool,message:string,type:string,errors:list<string>,fieldErrors:array<string,string>}
     */
    public static function updatePreferences(int $userId, array $input): array
    {
        $fieldErrors = [];
        if (!Csrf::verify((string) ($input['_token'] ?? ''))) {
            $fieldErrors['_token'] = 'Unable to verify the request. Please refresh the page and try again.';
        }

        if ($fieldErrors !== []) {
            return [
                'success' => false,
                'message' => $fieldErrors['_token'],
                'type' => 'danger',
                'errors' => [$fieldErrors['_token']],
                'fieldErrors' => $fieldErrors,
            ];
        }

        try {
            NotificationPreference::upsert($userId, [
                'appointment_in_app' => isset($input['appointment_in_app']) ? 1 : 0,
                'consultation_in_app' => isset($input['consultation_in_app']) ? 1 : 0,
                'email_enabled' => isset($input['email_enabled']) ? 1 : 0,
                'sms_enabled' => isset($input['sms_enabled']) ? 1 : 0,
            ]);

            return [
                'success' => true,
                'message' => 'Notification preferences saved.',
                'type' => 'success',
                'errors' => [],
                'fieldErrors' => [],
            ];
        } catch (\Throwable $exception) {
            error_log('[AccountSecurityService::updatePreferences] ' . $exception->getMessage());

            return [
                'success' => false,
                'message' => 'Notification preferences could not be saved right now.',
                'type' => 'danger',
                'errors' => ['Notification preferences could not be saved right now.'],
                'fieldErrors' => [],
            ];
        }
    }

    public static function verifyCurrentPassword(int $userId, string $password): bool
    {
        $user = User::findById($userId);
        if ($user === null || !is_string($user->password) || $user->password === '') {
            return false;
        }

        return password_verify($password, $user->password);
    }

    public static function confirmationPhraseIsValid(string $phrase): bool
    {
        return trim($phrase) === self::CONFIRMATION_PHRASE;
    }
}
