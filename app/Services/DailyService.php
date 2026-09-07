<?php

namespace App\Services;

use App\Config\Environment;
use RuntimeException;

/**
 * Daily.co Video Consultation Service
 *
 * Server-side PHP cURL interface to the Daily REST API for video consultations.
 * Responsibilities:
 *   - Create per-appointment private rooms (2 participants, short-lived)
 *   - Generate role-scoped meeting tokens for authenticated users
 *   - Delete rooms after consultation completion or cancellation
 *
 * Security:
 *   - The DAILY_API_KEY is read server-side only and never leaves this service.
 *   - Only meeting tokens (short-lived, role- and room-scoped) are ever
 *     returned to higher layers that might pass them to the browser.
 *   - Raw Daily responses / HTTP errors are never returned directly to users;
 *     only sanitized 'ok/message' summaries are propagated. Low-level details
 *     are written to PHP's error_log() for developers only.
 *
 * @week 6 — Video Consultation Integration
 */
class DailyService
{
    public const API_BASE = 'https://api.daily.co/v1';

    public const DEFAULT_MAX_PARTICIPANTS = 2;

    public const DEFAULT_TOKEN_EXPIRES_SECONDS = 4 * 60 * 60; // 4 hours max

    public const ROOM_CLEANUP_BUFFER_SECONDS = 30 * 60; // 30 min after consultation end

    /**
     * Create a private Daily room for a 1:1 consultation.
     *
     * @param string      $consultationReference  Short readable identifier used as stable
     *                                            prefix for the room name (e.g. 'th-12345').
     * @param int         $startUnix              Consultation start time (Unix seconds, UTC).
     * @param int         $durationMinutes        Consultation duration (minutes).
     * @param string|null $roomSlugSuffix         Optional random suffix (recommended null to
     *                                            let this method generate a safe suffix).
     *
     * @return array{
     *     ok: bool,
     *     message: string,
     *     room_name?: non-empty-string,
     *     room_url?: non-empty-string,
     *     expires_at?: int,
     *     created_at?: int
     * }
     */
    public static function createRoom(
        string $consultationReference,
        int $startUnix,
        int $durationMinutes,
        ?string $roomSlugSuffix = null,
    ): array {
        $config = self::getConfigOrFail();
        $now  = time();

        if ($durationMinutes < 1) {
            return self::failResult('Invalid consultation duration.');
        }
        if ($startUnix < $now - 24 * 3600) {
            return self::failResult('Consultation start time is too far in the past.');
        }

        $referenceSlug = self::slugifyReference($consultationReference);
        if ($referenceSlug === '') {
            $referenceSlug = 'consultation';
        }

        $suffix = $roomSlugSuffix ?? self::randomSuffix(8);
        $roomName = sprintf('mbpha-%s-%s', $referenceSlug, $suffix);

        $nbf = max($now, $startUnix - 15 * 60);       // joinable 15 min before start
        $exp = $startUnix + ($durationMinutes * 60) + self::ROOM_CLEANUP_BUFFER_SECONDS;

        $payload = [
            'name'       => $roomName,
            'privacy'    => 'private',
            'properties' => [
                'max_participants'      => self::DEFAULT_MAX_PARTICIPANTS,
                'enable_chat'           => false,
                'enable_screenshare'    => true,
                'enable_people_ui'      => true,
                'enable_prejoin_ui'     => true,
                'enable_live_captions_ui' => false,
                'start_video_off'       => false,
                'start_audio_off'       => false,
                'owner_only_broadcast'  => false,
                'enable_knocking'       => false,
                'enable_recording'      => false,
                'exp'                   => $exp,
                'nbf'                   => $nbf,
            ],
        ];

        $response = self::apiRequest('POST', '/rooms', $payload, $config);
        if (!$response['ok']) {
            return $response;
        }

        $data = (array) ($response['data'] ?? []);
        $respName = trim((string) ($data['name'] ?? ''));
        $respUrl  = trim((string) ($data['url']  ?? ''));
        $respCreated = (int) ($data['created_at'] ?? $now);
        $respConfig  = (array) ($data['config'] ?? []);
        $respExp = (int) ($respConfig['exp'] ?? $exp);

        if ($respName === '' || $respUrl === '') {
            error_log('[DailyService::createRoom] Missing name/url in Daily response for reference=' . $consultationReference);
            return self::failResult('Daily API returned an unexpected response. Please try again.');
        }

        return [
            'ok'         => true,
            'message'    => 'Room created successfully.',
            'room_name'  => $respName,
            'room_url'   => $respUrl,
            'expires_at' => $respExp,
            'created_at' => $respCreated,
        ];
    }

    /**
     * Generate a short-lived Daily meeting token scoped to a room + user.
     *
     * @param string $roomName       Exact Daily room name (returned from createRoom).
     * @param int    $userId         Authenticated user id (database primary key).
     * @param string $userFullName   Display name rendered in the meeting.
     * @param string $role           One of: 'doctor' | 'patient' | 'admin' (other roles are rejected
     *                               or downgraded; doctors get is_owner for host controls).
     * @param int    $expiresSeconds Maximum token lifetime (clamped to 4 hours).
     *
     * @return array{
     *     ok: bool,
     *     message: string,
     *     token?: non-empty-string,
     *     expires_at?: int
     * }
     */
    public static function createMeetingToken(
        string $roomName,
        int $userId,
        string $userFullName,
        string $role,
        int $expiresSeconds = self::DEFAULT_TOKEN_EXPIRES_SECONDS,
    ): array {
        $config = self::getConfigOrFail();

        $roomName = trim($roomName);
        $name     = trim($userFullName);
        $role     = strtolower(trim($role));

        if ($roomName === '') {
            return self::failResult('A room name is required to issue a meeting token.');
        }
        if ($userId <= 0) {
            return self::failResult('An authenticated user is required to issue a meeting token.');
        }
        if ($name === '') {
            return self::failResult('User display name is required.');
        }
        if (!in_array($role, ['doctor', 'patient', 'admin'], true)) {
            return self::failResult('Unknown user role for meeting token.');
        }

        $expiresSeconds = max(5 * 60, min(self::DEFAULT_TOKEN_EXPIRES_SECONDS, $expiresSeconds));
        $isOwner = $role === 'doctor'; // only doctors get host/ownership controls

        $payload = [
            'properties' => [
                'room_name'       => $roomName,
                'user_name'       => $name,
                'user_id'         => (string) $userId,
                'is_owner'        => $isOwner,
                'exp'             => time() + $expiresSeconds,
                'enable_screenshare'   => true,
                'start_video_off'      => false,
                'start_audio_off'      => false,
                'enable_recording'     => false,
            ],
        ];

        $response = self::apiRequest('POST', '/meeting-tokens', $payload, $config);
        if (!$response['ok']) {
            return $response;
        }

        $data = (array) ($response['data'] ?? []);
        $token = trim((string) ($data['token'] ?? ''));
        if ($token === '') {
            error_log('[DailyService::createMeetingToken] Empty token in Daily response for room=' . $roomName);
            return self::failResult('Daily API returned an invalid meeting token.');
        }

        return [
            'ok'         => true,
            'message'    => 'Meeting token issued.',
            'token'      => $token,
            'expires_at' => time() + $expiresSeconds,
        ];
    }

    /**
     * Delete a Daily room (used when a consultation is cancelled / completed / expired).
     *
     * @param string $roomName Exact Daily room name.
     *
     * @return array{ok: bool, message: string}
     */
    public static function deleteRoom(string $roomName): array
    {
        $config = self::getConfigOrFail();
        $roomName = trim($roomName);

        if ($roomName === '') {
            return self::failResult('Room name is required to delete a Daily room.');
        }

        $response = self::apiRequest('DELETE', '/rooms/' . rawurlencode($roomName), null, $config);
        if (!$response['ok']) {
            return $response;
        }

        return [
            'ok'      => true,
            'message' => 'Room deleted successfully.',
        ];
    }

    /* ------------------------------------------------------------------ *\
     |  Internal helpers (private)                                        |
    \* ------------------------------------------------------------------ */

    /**
     * Resolves & validates the API configuration from environment.
     *
     * @return array{api_key: non-empty-string, domain: non-empty-string}
     * @throws RuntimeException
     */
    private static function getConfigOrFail(): array
    {
        $apiKey = trim((string) Environment::get('DAILY_API_KEY', ''));
        $domain = trim((string) Environment::get('DAILY_DOMAIN', ''));

        if ($apiKey === '' || $domain === '') {
            error_log('[DailyService] DAILY_API_KEY / DAILY_DOMAIN environment variables are missing or empty.');
            throw new RuntimeException(
                'Daily video service is not configured. Please set DAILY_API_KEY and DAILY_DOMAIN.'
            );
        }

        return [
            'api_key' => $apiKey,
            'domain'  => $domain,
        ];
    }

    /**
     * Executes a cURL call to the Daily REST API and returns a normalized response array.
     *
     * NEVER includes the raw HTTP body verbatim in the result to avoid leaking
     * server-level error details upstream.
     *
     * @param 'GET'|'POST'|'DELETE' $method
     * @param string|null|array<array-key,mixed> $body Array body for POST (encoded JSON) or raw for GET/DELETE
     * @param array{api_key: non-empty-string, domain: non-empty-string} $config
     *
     * @return array{
     *     ok: bool,
     *     message: string,
     *     http_status?: int,
     *     data?: mixed
     * }
     */
    private static function apiRequest(
        string $method,
        string $endpoint,
        $body,
        array $config,
    ): array {
        if (!extension_loaded('curl')) {
            error_log('[DailyService] PHP ext-curl is not installed.');
            return self::failResult('Server missing cURL extension.');
        }

        $url = rtrim(self::API_BASE, '/') . '/' . ltrim($endpoint, '/');

        $ch = curl_init($url);
        if ($ch === false) {
            return self::failResult('Failed to initialise cURL.');
        }

        $headers = [
            'Authorization: Bearer ' . $config['api_key'],
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR    => false, // handled manually via http status
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => 'MBPHA-TeleHealth/1.0 (+https://mbpha.gov.pg)',
            CURLOPT_VERBOSE        => false,
        ];

        switch (strtoupper($method)) {
            case 'POST':
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = ($body === null) ? '' : json_encode($body, JSON_UNESCAPED_SLASHES);
                break;
            case 'DELETE':
                $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
            case 'GET':
            default:
                // default GET
                break;
        }

        curl_setopt_array($ch, $options);

        $raw  = curl_exec($ch);
        $errno = curl_errno($ch);
        $err   = curl_error($ch);
        $http  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // Do not call curl_close(): it has had no effect since PHP 8.0 and is
        // deprecated in PHP 8.5. CurlHandle objects are released automatically.

        if ($raw === false || $errno !== 0) {
            error_log(sprintf('[DailyService] cURL error (%d): %s on %s %s', $errno, $err, $method, $endpoint));
            return self::failResult('Unable to connect to the Daily video service at this time.');
        }

        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            error_log(sprintf('[DailyService] Non-JSON response (HTTP %d) on %s %s', $http, $method, $endpoint));
            return self::failResult('Daily API returned an invalid response.');
        }

        if ($http >= 200 && $http < 300) {
            return [
                'ok'          => true,
                'message'     => 'OK',
                'http_status' => $http,
                'data'        => $decoded,
            ];
        }

        // Sanitize error — never return raw body to caller, only to error_log().
        $errorLabel = self::sanitizeErrorLabel($http, $decoded);
        error_log(sprintf(
            '[DailyService] HTTP %d on %s %s | %s | raw_body_len=%d',
            $http,
            $method,
            $endpoint,
            $errorLabel,
            strlen((string) $raw),
        ));

        return self::failResult($errorLabel);
    }

    /**
     * Produce a safe human-readable label for a failing HTTP response without
     * echoing proprietary Daily error message text to end-users.
     *
     * @param int                    $http
     * @param array<array-key,mixed> $decoded
     */
    private static function sanitizeErrorLabel(int $http, array $decoded): string
    {
        $info = trim((string) ($decoded['error'] ?? ''));
        if ($info === '') {
            $info = trim((string) ($decoded['info'] ?? ''));
        }

        $label = match (true) {
            $http === 401 => 'Daily service rejected the API key.',
            $http === 402 => 'Daily service plan requires attention.',
            $http === 403 => 'Action forbidden by Daily service.',
            $http === 404 => 'Daily room or resource not found.',
            $http === 409 => 'A room with this name already exists.',
            $http === 422 => 'Invalid request sent to Daily service.',
            $http === 429 => 'Too many requests sent to Daily service (rate limit).',
            $http >= 500 => 'Daily service returned a server error.',
            default       => 'Daily service request failed (HTTP ' . $http . ').',
        };

        if ($info !== '' && str_contains($info, 'already exists')) {
            $label = 'A room with this identifier already exists.';
        }

        return $label;
    }

    /**
     * Standard failure envelope returned from every public method on error.
     *
     * @return array{ok: false, message: string}
     */
    private static function failResult(string $message): array
    {
        return [
            'ok'      => false,
            'message' => $message,
        ];
    }

    /**
     * Turn an arbitrary consultation identifier into a safe, lowercase segment
     * for use inside a Daily room name (letters, numbers, hyphens only).
     */
    private static function slugifyReference(string $reference): string
    {
        $slug = strtolower(trim($reference));
        $slug = (string) preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return substr($slug, 0, 40);
    }

    /**
     * Cryptographically-strong random suffix appended to each room name.
     *
     * @throws RuntimeException If there is no CSPRNG available.
     */
    private static function randomSuffix(int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        try {
            return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
        } catch (\Exception $e) {
            throw new RuntimeException('Unable to generate a secure room name.');
        }
    }
}
