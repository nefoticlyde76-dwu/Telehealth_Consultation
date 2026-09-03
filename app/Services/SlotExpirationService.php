<?php

namespace App\Services;

use App\Helpers\Helper;
use App\Models\DoctorAvailability;
use DateTimeImmutable;

/**
 * Expires unbooked availability slots whose end datetime has passed.
 *
 * The application clock (Pacific/Port_Moresby via Helper::now()) is authoritative.
 * Booking and display queries still exclude ended slots even if this sweep has
 * not run yet.
 */
class SlotExpirationService
{
    private static bool $sweptThisRequest = false;

    /**
     * Reset the per-request guard. Used by tests only.
     */
    public static function resetRequestGuard(): void
    {
        self::$sweptThisRequest = false;
    }

    public static function now(): DateTimeImmutable
    {
        return Helper::now();
    }

    public static function nowDatetime(): string
    {
        return Helper::nowDatetime();
    }

    public static function nowIso(): string
    {
        return Helper::nowIso();
    }

    public static function hasEnded(string $consultationDate, string $endTime, ?DateTimeImmutable $now = null): bool
    {
        $now = $now ?? self::now();
        $consultationDate = trim($consultationDate);
        $endTime = trim($endTime);

        if ($consultationDate === '' || $endTime === '') {
            return true;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $endTime) === 1) {
            $endTime .= ':00';
        }

        try {
            $endAt = DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $consultationDate . ' ' . $endTime,
                $now->getTimezone()
            );
        } catch (\Throwable) {
            return true;
        }

        if (!$endAt instanceof DateTimeImmutable) {
            return true;
        }

        return $now >= $endAt;
    }

    /**
     * Mark Available slots that have reached their end time as Expired, then
     * remove Expired rows that are not referenced by a consultation request.
     *
     * Booked slots (including past appointments) are never changed.
     */
    public static function sweep(): int
    {
        if (self::$sweptThisRequest) {
            return 0;
        }

        self::$sweptThisRequest = true;

        try {
            $expired = DoctorAvailability::expireUnbookedPastSlots(self::nowDatetime());
        } catch (\Throwable $exception) {
            error_log('Availability slot expiration sweep failed: ' . $exception->getMessage());

            return 0;
        }

        if ($expired > 0) {
            AuditLogService::record(
                'availability_slots_expired',
                $expired . ' unbooked availability slot' . ($expired === 1 ? '' : 's') . ' expired after the end time.',
                AuditLogService::ENTITY_AVAILABILITY,
                null
            );
        }

        return $expired;
    }

    /**
     * @param array<string, mixed> $slot
     */
    public static function slotHasEnded(array $slot, ?DateTimeImmutable $now = null): bool
    {
        return self::hasEnded(
            (string) ($slot['consultation_date'] ?? ''),
            (string) ($slot['end_time'] ?? ''),
            $now
        );
    }
}
