<?php

namespace App\Services;

use App\Core\Database;
use App\Helpers\Helper;
use App\Models\LoginAttempt;
use DateTimeImmutable;

/**
 * Password-login brute-force protection keyed by email + IP.
 * Progressive delay, then a temporary lockout. Does not change hashing,
 * sessions, or CSRF.
 */
class LoginAttemptService
{
    public const MAX_ATTEMPTS = 5;
    public const WINDOW_SECONDS = 900;
    public const MAX_DELAY_SECONDS = 8;
    public const LOCKOUT_MESSAGE = 'Too many sign-in attempts. Please wait a few minutes and try again.';

    /**
     * Test-only clock. Production callers must leave this null.
     */
    public static ?DateTimeImmutable $testNow = null;

    /**
     * Test-only window override in seconds. Production callers must leave this null.
     */
    public static ?int $testWindowSeconds = null;

    /**
     * Test-only max-attempt override. Production callers must leave this null.
     */
    public static ?int $testMaxAttempts = null;

    /**
     * Test-only: skip sleep() so CLI checks do not wait on backoff.
     */
    public static bool $testSkipDelay = false;

    public static function resetTestState(): void
    {
        self::$testNow = null;
        self::$testWindowSeconds = null;
        self::$testMaxAttempts = null;
        self::$testSkipDelay = false;
    }

    /**
     * @return array{locked:bool,failed_count:int,delay_seconds:int,locked_until:?string}
     */
    public static function inspect(string $email, ?string $ip = null): array
    {
        $email = self::normalizeEmail($email);
        $ip = self::normalizeIp($ip);
        $row = LoginAttempt::findByEmailAndIp($email, $ip);

        return self::stateFromRow($row);
    }

    /**
     * Sleep for the progressive backoff associated with prior failures.
     */
    public static function throttle(int $delaySeconds): void
    {
        $delaySeconds = max(0, $delaySeconds);
        if ($delaySeconds === 0 || self::$testSkipDelay) {
            return;
        }

        sleep($delaySeconds);
    }

    /**
     * Exponential backoff in seconds: 0, 1, 2, 4, 8 (capped).
     */
    public static function delaySecondsForFailures(int $failedCount): int
    {
        if ($failedCount < 1) {
            return 0;
        }

        $exponent = min($failedCount - 1, 3);

        return (int) min(self::MAX_DELAY_SECONDS, 2 ** $exponent);
    }

    /**
     * @return array{locked:bool,just_locked:bool,failed_count:int,delay_seconds:int,locked_until:?string}
     */
    public static function recordFailure(string $email, ?string $ip = null): array
    {
        $email = self::normalizeEmail($email);
        $ip = self::normalizeIp($ip);
        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            $row = LoginAttempt::findByEmailAndIp($email, $ip, true);
            $state = self::stateFromRow($row);

            if ($state['locked']) {
                $db->commit();

                return [
                    'locked' => true,
                    'just_locked' => false,
                    'failed_count' => $state['failed_count'],
                    'delay_seconds' => 0,
                    'locked_until' => $state['locked_until'],
                ];
            }

            $now = self::now();
            $nowDatetime = $now->format('Y-m-d H:i:s');
            $failedCount = $state['failed_count'] + 1;
            $windowStartedAt = $failedCount === 1
                ? $nowDatetime
                : (string) ($row['window_started_at'] ?? $nowDatetime);
            $lockedUntil = null;
            $justLocked = false;

            if ($failedCount >= self::maxAttempts()) {
                $lockedUntil = $now->modify('+' . self::windowSeconds() . ' seconds')->format('Y-m-d H:i:s');
                $justLocked = true;
            }

            LoginAttempt::upsert([
                'email' => $email,
                'ip_address' => $ip,
                'failed_count' => $failedCount,
                'window_started_at' => $windowStartedAt,
                'last_failed_at' => $nowDatetime,
                'locked_until' => $lockedUntil,
            ]);

            $db->commit();

            return [
                'locked' => $justLocked,
                'just_locked' => $justLocked,
                'failed_count' => $failedCount,
                'delay_seconds' => self::delaySecondsForFailures($failedCount),
                'locked_until' => $lockedUntil,
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public static function clear(string $email, ?string $ip = null): void
    {
        LoginAttempt::deleteByEmailAndIp(self::normalizeEmail($email), self::normalizeIp($ip));
    }

    public static function maxAttempts(): int
    {
        if (self::$testMaxAttempts !== null && self::$testMaxAttempts > 0) {
            return self::$testMaxAttempts;
        }

        return self::MAX_ATTEMPTS;
    }

    public static function windowSeconds(): int
    {
        if (self::$testWindowSeconds !== null && self::$testWindowSeconds > 0) {
            return self::$testWindowSeconds;
        }

        return self::WINDOW_SECONDS;
    }

    /**
     * @param array<string, mixed>|null $row
     * @return array{locked:bool,failed_count:int,delay_seconds:int,locked_until:?string}
     */
    private static function stateFromRow(?array $row): array
    {
        $unlocked = [
            'locked' => false,
            'failed_count' => 0,
            'delay_seconds' => 0,
            'locked_until' => null,
        ];

        if ($row === null) {
            return $unlocked;
        }

        $now = self::now();
        $lockedUntilRaw = trim((string) ($row['locked_until'] ?? ''));
        $lockedUntil = $lockedUntilRaw !== '' ? self::parseDatetime($lockedUntilRaw) : null;

        if ($lockedUntil instanceof DateTimeImmutable && $lockedUntil > $now) {
            $failedCount = max(0, (int) ($row['failed_count'] ?? 0));

            return [
                'locked' => true,
                'failed_count' => $failedCount,
                'delay_seconds' => 0,
                'locked_until' => $lockedUntil->format('Y-m-d H:i:s'),
            ];
        }

        if ($lockedUntil instanceof DateTimeImmutable) {
            return $unlocked;
        }

        $windowStartedAt = self::parseDatetime((string) ($row['window_started_at'] ?? ''));
        if (!$windowStartedAt instanceof DateTimeImmutable) {
            return $unlocked;
        }

        $windowEndsAt = $windowStartedAt->modify('+' . self::windowSeconds() . ' seconds');
        if ($now >= $windowEndsAt) {
            return $unlocked;
        }

        $failedCount = max(0, (int) ($row['failed_count'] ?? 0));

        return [
            'locked' => false,
            'failed_count' => $failedCount,
            'delay_seconds' => self::delaySecondsForFailures($failedCount),
            'locked_until' => null,
        ];
    }

    private static function now(): DateTimeImmutable
    {
        return self::$testNow ?? Helper::now();
    }

    private static function parseDatetime(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $value,
            self::now()->getTimezone()
        );

        if ($parsed instanceof DateTimeImmutable) {
            return $parsed;
        }

        try {
            return new DateTimeImmutable($value, self::now()->getTimezone());
        } catch (\Throwable) {
            return null;
        }
    }

    private static function normalizeEmail(string $email): string
    {
        return mb_substr(strtolower(trim($email)), 0, 255);
    }

    private static function normalizeIp(?string $ip): string
    {
        $ip = trim((string) ($ip ?? Helper::clientIp()));
        if ($ip === '') {
            $ip = '0.0.0.0';
        }

        return mb_substr($ip, 0, 45);
    }
}
