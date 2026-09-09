<?php

namespace App\Models;

use App\Core\Database;
use App\Services\DailyService;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

/**
 * Persistence + Daily-room creation for an approved consultation appointment.
 *
 * Responsibilities
 * ----------------
 *  1. Upsert a single consultation_rooms row per consultation_request_id.
 *     (UNIQUE index on consultation_request_id enforces cardinality at the DB
 *     level as a safety net; this model uses INSERT … ON DUPLICATE KEY UPDATE
 *     to make repeated approval calls idempotent.)
 *
 *  2. Derive the Daily room window (start / duration / expiration) from the
 *     linked doctor_availability record so the room lifetime always matches
 *     the actual appointment schedule.
 *
 *  3. On failure from the Daily REST API, throw a RuntimeException so the
 *     caller (already wrapped in a PDO transaction by ConsultationRequest)
 *     can ROLLBACK the Approved status update atomically.
 *
 * @week 6 — Daily Video Consultation Integration
 */
class ConsultationRoom
{
    /**
     * Return an existing room row for a consultation, or null.
     *
     * @return array{id:int,consultation_request_id:int,daily_room_name:string,
     *                daily_room_url:string,created_at:string,expires_at:?string}|null
     */
    public static function findByConsultationRequestId(int $requestId): ?array
    {
        if ($requestId <= 0) {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT id, consultation_request_id, daily_room_name, daily_room_url,
                    created_at, expires_at
             FROM consultation_rooms
             WHERE consultation_request_id = :request_id
             LIMIT 1"
        );
        $stmt->bindValue(':request_id', $requestId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        return [
            'id'                       => (int) $row['id'],
            'consultation_request_id'  => (int) $row['consultation_request_id'],
            'daily_room_name'          => (string) $row['daily_room_name'],
            'daily_room_url'           => (string) $row['daily_room_url'],
            'created_at'               => (string) $row['created_at'],
            'expires_at'               => isset($row['expires_at']) ? (string) $row['expires_at'] : null,
        ];
    }

    /**
     * Upsert a Daily room for the given approved consultation.
     *
     * Idempotent behaviour
     * --------------------
     *  - If a consultation_rooms row already exists for $requestId, this
     *    method returns that existing row WITHOUT calling the Daily API
     *    again.  (Repeated "Approve" button clicks do not waste Daily room
     *    allocations or create duplicates.)
     *  - If no row exists, this method:
     *      1. loads the appointment date/time from doctor_availability,
     *      2. calls DailyService::createRoom() with the computed window,
     *      3. INSERT … ON DUPLICATE KEY UPDATE stores the result.
     *
     * Exceptions
     * ----------
     * Throws RuntimeException on Daily API failure or invalid availability
     * data.  The caller MUST catch and ROLLBACK its surrounding DB
     * transaction to avoid an Approved-without-room inconsistency.
     *
     * @return array{id:int,consultation_request_id:int,daily_room_name:string,
     *                daily_room_url:string,created_at:string,expires_at:?string}
     */
    public static function upsertForApprovedConsultation(int $requestId): array
    {
        if ($requestId <= 0) {
            throw new RuntimeException('Invalid consultation request ID for Daily room creation.');
        }

        $existing = self::findByConsultationRequestId($requestId);
        if ($existing !== null) {
            return $existing;
        }

        $appointment = self::loadAppointmentWindow($requestId);
        if ($appointment === null) {
            throw new RuntimeException(
                'Approved consultation is not linked to a valid appointment schedule.'
            );
        }

        $startUnix   = $appointment['start_unix'];
        $durationMin = $appointment['duration_minutes'];
        $reference   = 'TH-' . $requestId;

        $dailyResult = DailyService::createRoom($reference, $startUnix, $durationMin);
        if (!($dailyResult['ok'] ?? false)) {
            throw new RuntimeException(
                sprintf(
                    'Daily video room creation failed. %s',
                    (string) ($dailyResult['message'] ?? 'Please try again.')
                )
            );
        }

        $roomName = trim((string) ($dailyResult['room_name'] ?? ''));
        $roomUrl  = trim((string) ($dailyResult['room_url']  ?? ''));
        if ($roomName === '' || $roomUrl === '') {
            throw new RuntimeException('Daily API returned an incomplete room response.');
        }

        $expiresAtUnix = (int) ($dailyResult['expires_at'] ?? 0);
        $expiresAtSql  = $expiresAtUnix > 0
            ? gmdate('Y-m-d H:i:s', $expiresAtUnix)
            : null;

        $db = Database::getInstance();

        $upsert = $db->prepare(
            "INSERT INTO consultation_rooms (
                consultation_request_id,
                daily_room_name,
                daily_room_url,
                expires_at
            ) VALUES (
                :request_id,
                :room_name,
                :room_url,
                :expires_at
            )
            ON DUPLICATE KEY UPDATE
                daily_room_name = VALUES(daily_room_name),
                daily_room_url  = VALUES(daily_room_url),
                expires_at      = VALUES(expires_at)"
        );
        $upsert->bindValue(':request_id', $requestId, PDO::PARAM_INT);
        $upsert->bindValue(':room_name',  $roomName);
        $upsert->bindValue(':room_url',   $roomUrl);
        if ($expiresAtSql === null) {
            $upsert->bindValue(':expires_at', null, PDO::PARAM_NULL);
        } else {
            $upsert->bindValue(':expires_at', $expiresAtSql);
        }
        $upsert->execute();

        $loaded = self::findByConsultationRequestId($requestId);
        if ($loaded === null) {
            throw new RuntimeException('Daily room metadata could not be persisted.');
        }

        return $loaded;
    }

    /**
     * Recreate the Daily room after an approved consultation is moved to a
     * different slot. Deletes the local room row inside the caller's
     * transaction so upsert can allocate a room for the new times. The
     * previous Daily room name is returned so the caller can delete it
     * after a successful commit.
     */
    public static function replaceForRescheduledConsultation(int $requestId): string
    {
        $existing = self::findByConsultationRequestId($requestId);
        $previousName = trim((string) ($existing['daily_room_name'] ?? ''));

        if ($existing !== null) {
            $db = Database::getInstance();
            $delete = $db->prepare(
                'DELETE FROM consultation_rooms WHERE consultation_request_id = :request_id'
            );
            $delete->bindValue(':request_id', $requestId, PDO::PARAM_INT);
            $delete->execute();
        }

        self::upsertForApprovedConsultation($requestId);

        return $previousName;
    }

    /* ------------------------------------------------------------------ *\
       Private helpers
    \* ------------------------------------------------------------------ */

    /**
     * Load the appointment scheduling window for a consultation request.
     *
     * Daily room expiration policy: appointment END time + 2 hours.
     * (Duration here captures the real appointment length; DailyService
     *  then applies the extra 2h buffer by adding its own 30 min cleanup
     *  plus the caller extends the duration passed to DailyService below
     *  so total exp = appointment_end + 2h exactly as required.)
     *
     * @return array{consultation_date:string,start_time:string,end_time:string,start_unix:int,duration_minutes:int}|null
     */
    private static function loadAppointmentWindow(int $requestId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                doctor_availability.consultation_date,
                doctor_availability.start_time,
                doctor_availability.end_time
             FROM consultation_requests
             INNER JOIN doctor_availability
                ON doctor_availability.id = consultation_requests.availability_id
             WHERE consultation_requests.id = :id
               AND consultation_requests.availability_id IS NOT NULL
             LIMIT 1"
        );
        $stmt->bindValue(':id', $requestId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        $date = (string) ($row['consultation_date'] ?? '');
        $start = (string) ($row['start_time'] ?? '');
        $end   = (string) ($row['end_time'] ?? '');

        if ($date === '' || $start === '' || $end === '') {
            return null;
        }

        $tz = self::resolveAppointmentTimezone();
        $utc = new DateTimeZone('UTC');

        try {
            $startDt = new DateTimeImmutable($date . ' ' . $start, $tz);
            $endDt   = new DateTimeImmutable($date . ' ' . $end,   $tz);
        } catch (\Throwable $e) {
            return null;
        }

        if ($endDt <= $startDt) {
            return null;
        }

        $startUtc = $startDt->setTimezone($utc);
        $endUtc   = $endDt->setTimezone($utc);

        // Required: Daily exp = appointment END + 2 HOURS.
        // DailyService formula  = startUnix + durationMinutes*60 + 1800 (30 min buffer)
        // Solve for durationMinutes so: endUnix + (2 * 3600) = startUnix + durationMin*60 + 1800
        //   → durationMin = ((endUnix + 7200 - 1800 - startUnix) / 60) = ((endUnix + 5400 - startUnix)/60)
        // Use raw seconds arithmetic to avoid TZ/DST drift issues.
        $rawSeconds = ($endUtc->getTimestamp() + 5400) - $startUtc->getTimestamp();
        $durationMin = (int) ceil($rawSeconds / 60);
        if ($durationMin < 1) {
            $durationMin = 1;
        }

        return [
            'consultation_date' => $date,
            'start_time'        => $start,
            'end_time'          => $end,
            'start_unix'        => $startUtc->getTimestamp(),
            'duration_minutes'  => $durationMin,
        ];
    }

    /**
     * Resolve the timezone used when parsing doctor_availability date/time
     * strings for Daily room nbf / exp math.
     *
     * HARD-PINNED to Pacific/Port_Moresby (Papua New Guinea, UTC+10:00).
     * We intentionally do NOT read php.ini's date.timezone because that
     * reflects the webserver operator locale (e.g. Europe/Berlin on a
     * German XAMPP install) and availability/consultation times in the
     * database were entered by PNG users thinking in PNG local time.
     *
     * If the IANA DB is somehow missing the entry we fall back to UTC
     * only as a last resort.
     */
    private static function resolveAppointmentTimezone(): DateTimeZone
    {
        try {
            return new DateTimeZone('Pacific/Port_Moresby');
        } catch (\Throwable) {
            return new DateTimeZone('UTC');
        }
    }
}
