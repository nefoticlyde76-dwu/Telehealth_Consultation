<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Helpers\Helper;
use App\Models\ConsultationRoom;
use App\Models\User;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

/**
 * Video Consultation — Join Authorization service.
 *
 * Centralises the "can the currently authenticated session user join
 * this consultation?" decision.  The endpoint (both patient and doctor
 * controllers) should DELEGATE to this class so the authorization rule
 * lives in exactly one place.
 *
 * Design principles:
 *   • NEVER accept user id / role / name from the request body.  Only
 *     accept them from the authenticated PHP session.
 *   • ALLOWED JOIN WINDOW = [appointment_start - 10 min, appointment_end].
 *   • Doctor receives Daily is_owner (host controls).
 *   • Patient receives a normal participant token.
 *   • Meeting tokens are generated FRESH on each successful request and
 *     NEVER persisted.
 *
 * @week 6 — Video Consultation Integration
 */
class VideoConsultationService
{
    public const JOIN_WINDOW_BEFORE_START_SECONDS = 10 * 60; // 10 min

    public const TOKEN_EXTRA_AFTER_END_SECONDS = 5 * 60; // 5 min buffer after appointment end

    private const STATUS_UNAUTHENTICATED = 'unauthenticated';

    private const STATUS_NOT_FOUND = 'not_found';

    private const STATUS_NOT_APPROVED = 'not_approved';

    private const STATUS_FORBIDDEN_OWNERSHIP = 'forbidden_ownership';

    private const STATUS_ROOM_MISSING = 'room_missing';

    private const STATUS_TOO_EARLY = 'too_early';

    private const STATUS_TOO_LATE = 'too_late';

    private const STATUS_TOKEN_ERROR = 'token_error';

    /**
     * Result envelope.  On success only code === 'ok' and data fields
     * room_url + token are populated.  Otherwise a non-2xx $httpCode and
     * safe $message are produced for the controller to echo.
     *
     * @return array{
     *     ok: bool,
     *     http_code: int<100,599>,
     *     code: non-empty-string,
     *     message: non-empty-string,
     *     room_url?: non-empty-string,
     *     token?: non-empty-string,
     * }
     */
    public static function authorizeJoinForCurrentUser(int $consultationRequestId): array
    {
        // ── 1.  Authentication check ────────────────────────────────────
        $userId = AuthService::getUserId();
        $role   = AuthService::getUserRole();
        if ($userId === null || $userId <= 0 || $role === null || $role === '') {
            return self::deny(401, self::STATUS_UNAUTHENTICATED, 'Not authenticated.');
        }
        $role = strtolower(trim($role));
        if (!in_array($role, ['patient', 'doctor'], true)) {
            return self::deny(403, self::STATUS_FORBIDDEN_OWNERSHIP, 'You are not authorized to join this consultation.');
        }

        if ($consultationRequestId <= 0) {
            return self::deny(404, self::STATUS_NOT_FOUND, 'Consultation not found.');
        }

        // ── 2.  Consultation lookup — exact fields needed for checks ──
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                cr.id AS request_id,
                cr.status AS request_status,
                cr.patient_id,
                cr.doctor_id,
                cr.availability_id,
                da.consultation_date,
                da.start_time,
                da.end_time
             FROM consultation_requests cr
             INNER JOIN doctor_availability da
                ON da.id = cr.availability_id
             WHERE cr.id = :id
             LIMIT 1"
        );
        $stmt->bindValue(':id', $consultationRequestId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($row === null) {
            return self::deny(404, self::STATUS_NOT_FOUND, 'Consultation not found.');
        }

        $requestStatus = strtolower(trim((string) ($row['request_status'] ?? '')));
        $patientUserId = (int) ($row['patient_id'] ?? 0);
        $doctorUserId  = (int) ($row['doctor_id'] ?? 0);
        $availabilityId = (int) ($row['availability_id'] ?? 0);

        if ($availabilityId <= 0) {
            return self::deny(404, self::STATUS_NOT_FOUND, 'Consultation not found.');
        }

        // ── 3.  Approved status ────────────────────────────────────────
        if ($requestStatus !== 'approved') {
            return self::deny(403, self::STATUS_NOT_APPROVED, 'This consultation is not available to join right now.');
        }

        // ── 4.  Ownership check ────────────────────────────────────────
        //     Patient must be the patient on this request;
        //     Doctor must be the doctor on this request.
        //     We compare against the actual DB FKs, never request payload.
        if ($role === 'patient' && $patientUserId !== $userId) {
            return self::deny(403, self::STATUS_FORBIDDEN_OWNERSHIP, 'You are not authorized to join this consultation.');
        }
        if ($role === 'doctor' && $doctorUserId !== $userId) {
            return self::deny(403, self::STATUS_FORBIDDEN_OWNERSHIP, 'You are not authorized to join this consultation.');
        }

        // ── 5.  Associated Daily room ──────────────────────────────────
        $room = ConsultationRoom::findByConsultationRequestId($consultationRequestId);
        if ($room === null) {
            return self::deny(403, self::STATUS_ROOM_MISSING, 'The video consultation room is not ready yet. Please try again shortly.');
        }
        $dailyRoomName = trim((string) ($room['daily_room_name'] ?? ''));
        $dailyRoomUrl  = trim((string) ($room['daily_room_url']  ?? ''));
        if ($dailyRoomName === '' || $dailyRoomUrl === '') {
            return self::deny(403, self::STATUS_ROOM_MISSING, 'The video consultation room is not ready yet. Please try again shortly.');
        }

        // ── 6.  Consultation window ────────────────────────────────────
        $tz = self::resolveAppointmentTimezone();
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $startLocal = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            sprintf('%s %s', (string) $row['consultation_date'], (string) $row['start_time']),
            $tz
        );
        $endLocal = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            sprintf('%s %s', (string) $row['consultation_date'], (string) $row['end_time']),
            $tz
        );

        if ($startLocal === false || $endLocal === false) {
            error_log(sprintf('[VideoConsultationService] Failed to parse times for request %d: date=%s start=%s end=%s', $consultationRequestId, var_export($row['consultation_date'], true), var_export($row['start_time'], true), var_export($row['end_time'], true)));
            return self::deny(403, self::STATUS_ROOM_MISSING, 'The video consultation room schedule could not be determined.');
        }

        $windowStartUtc = $startLocal
            ->setTimezone(new DateTimeZone('UTC'))
            ->modify(sprintf('-%d seconds', self::JOIN_WINDOW_BEFORE_START_SECONDS));
        $windowEndUtc = $endLocal->setTimezone(new DateTimeZone('UTC'));

        if ($now < $windowStartUtc) {
            return self::deny(
                403,
                self::STATUS_TOO_EARLY,
                sprintf(
                    'Consultation opens 10 minutes before the scheduled time (at %s %s).',
                    $startLocal->format('g:i A'),
                    Helper::appTimezoneLabel()
                )
            );
        }
        if ($now > $windowEndUtc) {
            return self::deny(403, self::STATUS_TOO_LATE, 'This consultation has ended.');
        }

        // ── 7.  Authenticated user identity for Daily participant info ─
        $user = User::findById($userId);
        if ($user === null) {
            error_log(sprintf('[VideoConsultationService] Stale session user_id=%d not found in DB for request %d.', $userId, $consultationRequestId));
            return self::deny(401, self::STATUS_UNAUTHENTICATED, 'Not authenticated.');
        }
        $fullName = trim((string) ($user->full_name ?? ''));
        if ($fullName === '') {
            $fullName = sprintf('%s User %d', ucfirst($role), $userId);
        }

        // ── 8.  Compute token expiry = appointment_end + 5 min ─────────
        $nowUnix   = time();
        $endUnix   = $windowEndUtc->getTimestamp();
        $tokenExpiresSeconds = max(60, ($endUnix + self::TOKEN_EXTRA_AFTER_END_SECONDS) - $nowUnix);
        // Clamp to DailyService ceiling (4h) for safety.
        $tokenExpiresSeconds = min($tokenExpiresSeconds, DailyService::DEFAULT_TOKEN_EXPIRES_SECONDS);

        $tokenResult = DailyService::createMeetingToken(
            $dailyRoomName,
            $userId,
            $fullName,
            $role,
            $tokenExpiresSeconds
        );

        if (($tokenResult['ok'] ?? false) !== true || empty($tokenResult['token'])) {
            error_log(sprintf(
                '[VideoConsultationService] Daily token creation failed for request=%d user=%d role=%s: %s',
                $consultationRequestId,
                $userId,
                $role,
                $tokenResult['message'] ?? 'unknown error'
            ));
            return self::deny(
                500,
                self::STATUS_TOKEN_ERROR,
                'The video consultation could not be started right now. Please try again in a moment.'
            );
        }

        return [
            'ok'        => true,
            'http_code' => 200,
            'code'      => 'ok',
            'message'   => 'Authorized.',
            'room_url'  => $dailyRoomUrl,
            'token'     => (string) $tokenResult['token'],
        ];
    }

    /**
     * Resolve the timezone used when interpreting doctor_availability
     * date/time strings for join-window and token math.
     *
     * The MBPHA TeleHealth Consultation System operates in Papua New
     * Guinea, so this method HARD-PINS the interpretation to the
     * Pacific/Port_Moresby IANA timezone (UTC+10:00).  It intentionally
     * ignores the webserver's date.timezone INI value because that
     * setting reflects the server operator's locale (e.g. Europe/Berlin
     * on a German XAMPP install) rather than the MBPHA clinical locale.
     *
     * ⚠ Why ignoring the INI is the correct behaviour here ⚠
     *    Availability slots + consultation start/end times are stored
     *    in MySQL as plain DATE + TIME strings WITHOUT a zone.  They
     *    were entered by PNG-based doctors and patients thinking in
     *    PNG local time.  Re-interpreting them in the server INI TZ
     *    (e.g. Europe/Berlin) shifts the join window by (10 − 1/2) h
     *    and forces end-users to join at the wrong wall-clock.
     *
     * @return DateTimeZone Always Pacific/Port_Moresby, with UTC as a
     *                      final fallback if the IANA DB is incomplete.
     *
     * @internal Helper only exposed for deterministic unit tests; not
     *           for controllers to rely on.
     * @codeCoverageIgnore
     */
    public static function resolveAppointmentTimezone(): DateTimeZone
    {
        try {
            return new DateTimeZone('Pacific/Port_Moresby');
        } catch (RuntimeException) {
            return new DateTimeZone('UTC');
        }
    }

    /**
     * @param non-empty-string $code
     * @param non-empty-string $message
     * @return array{ok: false, http_code: int<100,599>, code: non-empty-string, message: non-empty-string}
     */
    private static function deny(int $httpCode, string $code, string $message): array
    {
        if ($httpCode < 100 || $httpCode > 599) {
            $httpCode = 500;
        }
        $code = $code === '' ? 'error' : $code;
        $message = $message === '' ? 'Request could not be fulfilled.' : $message;

        return [
            'ok'        => false,
            'http_code' => $httpCode,
            'code'      => $code,
            'message'   => $message,
        ];
    }
}
