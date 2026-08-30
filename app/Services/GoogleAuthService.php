<?php

namespace App\Services;

use App\Helpers\Status;
use App\Models\User;

/**
 * Orchestrates Google Identity Services authentication after the ID token
 * has been cryptographically validated.
 *
 * This service does not parse JWTs, create its own session, or assign roles.
 * Successful resolution always logs in through AuthService::login().
 */
class GoogleAuthService
{
    public const REASON_INVALID_GOOGLE_IDENTITY = 'invalid_google_identity';
    public const REASON_GOOGLE_USER_NOT_ALLOWED = 'google_user_not_allowed';
    public const REASON_GOOGLE_IDENTITY_COLLISION = 'google_identity_collision';
    public const REASON_EMAIL_COLLISION = 'email_collision';
    public const REASON_GOOGLE_LINK_FAILED = 'google_link_failed';
    public const REASON_GOOGLE_REGISTRATION_FAILED = 'google_registration_failed';
    public const REASON_GOOGLE_LOGIN_FAILED = 'google_login_failed';
    public const REASON_ACCOUNT_DISABLED = 'account_disabled';
    public const REASON_ACCOUNT_DELETED = 'account_deleted';

    /**
     * Test-only JWKS forwarded to GoogleIdTokenValidator::validate().
     * Production callers must leave this null. Signature verification is never skipped.
     *
     * @var array{keys?: list<array<string, mixed>>}|null
     */
    public static ?array $testJwksOverride = null;

    private static ?string $lastFailureReason = null;

    /**
     * Internal diagnostic reason from the most recent authenticate() call.
     * Controllers must not expose this value to patients.
     */
    public static function lastFailureReason(): ?string
    {
        return self::$lastFailureReason;
    }

    /**
     * Validate a raw Google ID token and resolve it to an authenticated patient.
     * Returns null on any controlled failure. Does not throw Google identity errors.
     */
    public static function authenticate(string $rawIdToken): ?User
    {
        self::$lastFailureReason = null;

        try {
            $identity = GoogleIdTokenValidator::validate($rawIdToken, self::$testJwksOverride);
        } catch (GoogleIdTokenException $exception) {
            self::fail(self::REASON_INVALID_GOOGLE_IDENTITY, $exception->getReason());
            return null;
        } catch (\Throwable) {
            self::fail(self::REASON_INVALID_GOOGLE_IDENTITY, 'ID token validation failed.');
            return null;
        }

        $sub = trim((string) ($identity['sub'] ?? ''));
        $email = strtolower(trim((string) ($identity['email'] ?? '')));
        if ($sub === '' || $email === '') {
            self::fail(self::REASON_INVALID_GOOGLE_IDENTITY, 'Trusted identity was incomplete.');
            return null;
        }

        try {
            $bySub = User::findByGoogleSub($sub);
            if ($bySub !== null) {
                if (self::isDeletedAccount($bySub) && $bySub->id !== null) {
                    User::releaseGoogleIdentityIfDeleted((int) $bySub->id);
                    error_log('[GoogleAuthService] Released Google identity from deleted account id ' . (int) $bySub->id);
                } else {
                    return self::authenticateExistingGoogleUser($bySub, $identity);
                }
            }

            $byEmail = User::findByEmail($email);
            if ($byEmail !== null) {
                return self::resolveEmailAccount($byEmail, $identity);
            }

            return self::registerAndLogin($identity);
        } catch (\Throwable $exception) {
            self::fail(self::REASON_GOOGLE_LOGIN_FAILED, 'Unexpected Google authentication failure.');
            error_log('[GoogleAuthService] Unexpected failure: ' . $exception::class);

            return null;
        }
    }

    /**
     * @param array{sub:string,email:string,email_verified:true,name:string} $identity
     */
    private static function authenticateExistingGoogleUser(User $user, array $identity): ?User
    {
        if (self::isDeletedAccount($user)) {
            self::fail(self::REASON_ACCOUNT_DELETED, 'Google identity matches a deleted account id ' . (int) $user->id);
            return null;
        }

        $role = $user->getRole();
        if ($role !== 'patient') {
            self::fail(self::REASON_GOOGLE_USER_NOT_ALLOWED, 'Google identity belongs to a non-patient account id ' . (int) $user->id);
            return null;
        }

        if (!Status::canAuthenticateUserStatus((string) ($user->status ?? ''))) {
            self::fail(self::REASON_ACCOUNT_DISABLED, 'Google patient is not eligible to authenticate id ' . (int) $user->id);
            return null;
        }

        $googleEmail = (string) $identity['email'];
        if ($user->id !== null && $user->google_email !== $googleEmail) {
            User::updateGoogleEmail((int) $user->id, $googleEmail);
        }

        return self::loginPatient($user);
    }

    /**
     * @param array{sub:string,email:string,email_verified:true,name:string} $identity
     */
    private static function resolveEmailAccount(User $user, array $identity): ?User
    {
        $role = $user->getRole();
        if ($role !== 'patient') {
            self::fail(self::REASON_EMAIL_COLLISION, 'Google email matches a non-patient account id ' . (int) $user->id);
            return null;
        }

        if (self::isDeletedAccount($user)) {
            self::fail(self::REASON_ACCOUNT_DELETED, 'Google email matches a deleted patient id ' . (int) $user->id);
            return null;
        }

        if (!Status::canAuthenticateUserStatus((string) ($user->status ?? ''))) {
            self::fail(self::REASON_ACCOUNT_DISABLED, 'Google email matches an ineligible patient id ' . (int) $user->id);
            return null;
        }

        $existingSub = is_string($user->google_sub) ? trim($user->google_sub) : '';
        if ($existingSub !== '') {
            self::fail(self::REASON_GOOGLE_IDENTITY_COLLISION, 'Google email matches a patient with a different google_sub id ' . (int) $user->id);
            return null;
        }

        if ($user->auth_provider !== User::AUTH_PROVIDER_LOCAL) {
            self::fail(self::REASON_GOOGLE_IDENTITY_COLLISION, 'Google email matches a patient with an inconsistent Google identity id ' . (int) $user->id);
            return null;
        }

        if ($user->id === null) {
            self::fail(self::REASON_GOOGLE_LINK_FAILED, 'Local patient is missing an id.');
            return null;
        }

        try {
            $linked = User::linkGoogleIdentity(
                (int) $user->id,
                (string) $identity['sub'],
                (string) $identity['email']
            );
        } catch (\Throwable) {
            self::fail(self::REASON_GOOGLE_LINK_FAILED, 'Google identity link failed for user id ' . (int) $user->id);
            return null;
        }

        if (!$linked) {
            self::fail(self::REASON_GOOGLE_LINK_FAILED, 'Google identity link did not update user id ' . (int) $user->id);
            return null;
        }

        $reloaded = User::findById((int) $user->id);
        if (
            $reloaded === null
            || $reloaded->google_sub !== (string) $identity['sub']
            || $reloaded->auth_provider !== User::AUTH_PROVIDER_BOTH
        ) {
            self::fail(self::REASON_GOOGLE_LINK_FAILED, 'Google identity link could not be confirmed for user id ' . (int) $user->id);
            return null;
        }

        return self::loginPatient($reloaded);
    }

    /**
     * @param array{sub:string,email:string,email_verified:true,name:string} $identity
     */
    private static function registerAndLogin(array $identity): ?User
    {
        $user = AuthService::registerGooglePatient($identity);
        if ($user === null || $user->id === null) {
            self::fail(self::REASON_GOOGLE_REGISTRATION_FAILED, 'Google patient registration failed.');
            return null;
        }

        return self::loginPatient($user);
    }

    private static function loginPatient(User $user): ?User
    {
        $role = $user->getRole();
        if ($user->id === null || $role !== 'patient') {
            self::fail(self::REASON_GOOGLE_LOGIN_FAILED, 'Unable to establish a patient session.');
            return null;
        }

        AuthService::login((int) $user->id, $role);

        $fresh = User::findById((int) $user->id);

        return $fresh ?? $user;
    }

    private static function isDeletedAccount(User $user): bool
    {
        if (Status::isDeletedUserStatus((string) ($user->status ?? ''))) {
            return true;
        }

        if ($user->deleted_at !== null && trim((string) $user->deleted_at) !== '') {
            return true;
        }

        return $user->anonymized_at !== null && trim((string) $user->anonymized_at) !== '';
    }

    private static function fail(string $reason, string $detail): void
    {
        self::$lastFailureReason = $reason;
        error_log('[GoogleAuthService] ' . $reason . ' ' . $detail);
    }
}
