<?php

namespace App\Services;

use App\Config\App;

/**
 * Validates a Google Identity Services ID token (JWT) using Google's JWKS.
 *
 * Success returns only trusted identity claims. The raw JWT is never stored,
 * logged, or returned. This class does not create users, sessions, or routes.
 */
class GoogleIdTokenValidator
{
    public const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private const ALLOWED_ALG = 'RS256';
    private const CLOCK_SKEW_SECONDS = 60;
    private const MAX_TOKEN_BYTES = 8192;
    private const DEFAULT_JWKS_TTL_SECONDS = 3600;
    private const MAX_JWKS_TTL_SECONDS = 86400;

    /** @var array{keys: list<array<string, mixed>>}|null */
    private static ?array $memoryJwks = null;

    private static int $memoryJwksExpiresAt = 0;

    /**
     * Test-only JWKS fetch replacement. Production callers must leave this null
     * so JWKS is loaded only from JWKS_URL over TLS.
     *
     * @var (callable(): array{jwks: array{keys: list<array<string, mixed>>}, expires_at: int})|null
     */
    public static $testFetchJwks = null;

    /**
     * Drop in-memory and file JWKS cache. Used by tests after seeding rotation fixtures.
     */
    public static function resetJwksCache(): void
    {
        self::$memoryJwks = null;
        self::$memoryJwksExpiresAt = 0;
        $path = self::jwksCachePath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Seed the JWKS cache as if a previous fetch succeeded. Production callers
     * must not use this. Signature verification is never skipped.
     *
     * @param array{keys: list<array<string, mixed>>} $jwks
     */
    public static function seedJwksCache(array $jwks, int $ttlSeconds = 3600): void
    {
        $expiresAt = time() + max(1, $ttlSeconds);
        self::$memoryJwks = $jwks;
        self::$memoryJwksExpiresAt = $expiresAt;
        self::writeJwksCache($jwks, $expiresAt);
    }

    /**
     * @param array{keys?: list<array<string, mixed>>}|null $jwksOverride
     *        Optional JWKS used by tests. Production callers must omit this.
     *        Signature verification is never skipped.
     * @return array{sub: string, email: string, email_verified: true, name: string}
     * @throws GoogleIdTokenException
     */
    public static function validate(string $idToken, ?array $jwksOverride = null): array
    {
        $parts = self::parseJwt($idToken);
        $header = $parts['header'];
        $payload = $parts['payload'];
        $signingInput = $parts['signing_input'];
        $signature = $parts['signature'];

        $kid = trim((string) ($header['kid'] ?? ''));
        $usedOverride = $jwksOverride !== null;
        $jwks = $jwksOverride ?? self::getJwks();
        $jwk = self::findRsaKey($jwks, $kid);
        if ($jwk === null && !$usedOverride) {
            $jwks = self::refreshJwks();
            $jwk = self::findRsaKey($jwks, $kid);
        }
        if ($jwk === null) {
            self::fail(GoogleIdTokenException::UNKNOWN_KID);
        }

        $publicKey = self::rsaJwkToPem($jwk);
        $verified = openssl_verify($signingInput, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            self::fail(GoogleIdTokenException::INVALID_SIGNATURE);
        }

        return self::trustedIdentity($payload);
    }

    /**
     * @return array{
     *   header: array<string, mixed>,
     *   payload: array<string, mixed>,
     *   signing_input: string,
     *   signature: string
     * }
     */
    private static function parseJwt(string $idToken): array
    {
        if ($idToken === '' || strlen($idToken) > self::MAX_TOKEN_BYTES) {
            self::fail(GoogleIdTokenException::MALFORMED_TOKEN);
        }

        $segments = explode('.', $idToken);
        if (count($segments) !== 3 || $segments[0] === '' || $segments[1] === '' || $segments[2] === '') {
            self::fail(GoogleIdTokenException::MALFORMED_TOKEN);
        }

        $headerJson = self::base64UrlDecode($segments[0]);
        $payloadJson = self::base64UrlDecode($segments[1]);
        $signature = self::base64UrlDecode($segments[2]);
        if ($headerJson === null || $payloadJson === null || $signature === null) {
            self::fail(GoogleIdTokenException::MALFORMED_TOKEN);
        }

        $header = json_decode($headerJson, true);
        $payload = json_decode($payloadJson, true);
        if (!is_array($header) || !is_array($payload)) {
            self::fail(GoogleIdTokenException::MALFORMED_TOKEN);
        }

        $alg = $header['alg'] ?? null;
        if (!is_string($alg) || $alg !== self::ALLOWED_ALG) {
            self::fail(GoogleIdTokenException::INVALID_ALGORITHM);
        }

        if (!isset($header['kid']) || !is_string($header['kid']) || trim($header['kid']) === '') {
            self::fail(GoogleIdTokenException::UNKNOWN_KID);
        }

        if (!extension_loaded('openssl')) {
            error_log('[GoogleIdTokenValidator] openssl extension is not available.');
            self::fail(GoogleIdTokenException::INVALID_SIGNATURE);
        }

        return [
            'header' => $header,
            'payload' => $payload,
            'signing_input' => $segments[0] . '.' . $segments[1],
            'signature' => $signature,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{sub: string, email: string, email_verified: true, name: string}
     */
    private static function trustedIdentity(array $payload): array
    {
        $clientId = trim((string) (App::getConfig()['google']['client_id'] ?? ''));
        if ($clientId === '') {
            error_log('[GoogleIdTokenValidator] Google client ID is not configured.');
            self::fail(GoogleIdTokenException::INVALID_AUDIENCE);
        }

        $issuer = $payload['iss'] ?? null;
        if ($issuer !== 'https://accounts.google.com' && $issuer !== 'accounts.google.com') {
            self::fail(GoogleIdTokenException::INVALID_ISSUER);
        }

        if (!self::audienceMatches($payload['aud'] ?? null, $clientId)) {
            self::fail(GoogleIdTokenException::INVALID_AUDIENCE);
        }

        if (array_key_exists('azp', $payload)) {
            $azp = $payload['azp'];
            if (!is_string($azp) || !hash_equals($clientId, $azp)) {
                self::fail(GoogleIdTokenException::INVALID_AUDIENCE);
            }
        }

        $now = time();
        if (!isset($payload['exp']) || !is_numeric($payload['exp'])) {
            self::fail(GoogleIdTokenException::EXPIRED_TOKEN);
        }
        if (((int) $payload['exp']) + self::CLOCK_SKEW_SECONDS < $now) {
            self::fail(GoogleIdTokenException::EXPIRED_TOKEN);
        }

        if (!isset($payload['iat']) || !is_numeric($payload['iat'])) {
            self::fail(GoogleIdTokenException::INVALID_IAT);
        }
        if ((int) $payload['iat'] > $now + self::CLOCK_SKEW_SECONDS) {
            self::fail(GoogleIdTokenException::INVALID_IAT);
        }

        $sub = $payload['sub'] ?? null;
        if (!is_string($sub) || trim($sub) === '') {
            self::fail(GoogleIdTokenException::MISSING_SUB);
        }

        $emailRaw = $payload['email'] ?? null;
        if (!is_string($emailRaw) || trim($emailRaw) === '') {
            self::fail(GoogleIdTokenException::MISSING_EMAIL);
        }
        $email = strtolower(trim($emailRaw));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            self::fail(GoogleIdTokenException::MISSING_EMAIL);
        }

        if (!self::isEmailVerified($payload['email_verified'] ?? null)) {
            self::fail(GoogleIdTokenException::EMAIL_UNVERIFIED);
        }

        $name = '';
        if (isset($payload['name']) && is_string($payload['name'])) {
            $name = trim($payload['name']);
        }

        return [
            'sub' => trim($sub),
            'email' => $email,
            'email_verified' => true,
            'name' => $name,
        ];
    }

    private static function isEmailVerified(mixed $value): bool
    {
        return $value === true;
    }

    private static function audienceMatches(mixed $aud, string $clientId): bool
    {
        if (is_string($aud)) {
            return hash_equals($clientId, $aud);
        }

        if (!is_array($aud)) {
            return false;
        }

        foreach ($aud as $entry) {
            if (is_string($entry) && hash_equals($clientId, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{keys?: mixed} $jwks
     * @return array<string, mixed>|null
     */
    private static function findRsaKey(array $jwks, string $kid): ?array
    {
        $keys = $jwks['keys'] ?? null;
        if (!is_array($keys)) {
            return null;
        }

        foreach ($keys as $key) {
            if (!is_array($key)) {
                continue;
            }
            if (($key['kid'] ?? null) !== $kid) {
                continue;
            }
            if (($key['kty'] ?? null) !== 'RSA') {
                continue;
            }
            if (isset($key['alg']) && $key['alg'] !== self::ALLOWED_ALG) {
                continue;
            }
            if (!is_string($key['n'] ?? null) || !is_string($key['e'] ?? null)) {
                continue;
            }

            return $key;
        }

        return null;
    }

    /**
     * @return array{keys: list<array<string, mixed>>}
     */
    private static function getJwks(): array
    {
        $now = time();
        if (self::$memoryJwks !== null && self::$memoryJwksExpiresAt > $now) {
            return self::$memoryJwks;
        }

        $cached = self::readJwksCache();
        if ($cached !== null) {
            self::$memoryJwks = $cached['jwks'];
            self::$memoryJwksExpiresAt = $cached['expires_at'];
            return $cached['jwks'];
        }

        $fetched = self::fetchJwks();
        self::$memoryJwks = $fetched['jwks'];
        self::$memoryJwksExpiresAt = $fetched['expires_at'];
        self::writeJwksCache($fetched['jwks'], $fetched['expires_at']);

        return $fetched['jwks'];
    }

    /**
     * Discard cached JWKS and fetch once from Google. Used when a token
     * presents a kid that is not in the current cache (key rotation).
     *
     * @return array{keys: list<array<string, mixed>>}
     */
    private static function refreshJwks(): array
    {
        self::$memoryJwks = null;
        self::$memoryJwksExpiresAt = 0;
        $path = self::jwksCachePath();
        if (is_file($path)) {
            @unlink($path);
        }

        $fetched = self::fetchJwks();
        self::$memoryJwks = $fetched['jwks'];
        self::$memoryJwksExpiresAt = $fetched['expires_at'];
        self::writeJwksCache($fetched['jwks'], $fetched['expires_at']);

        return $fetched['jwks'];
    }

    /**
     * @return array{jwks: array{keys: list<array<string, mixed>>}, expires_at: int}|null
     */
    private static function readJwksCache(): ?array
    {
        $path = self::jwksCachePath();
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        $expiresAt = (int) ($decoded['expires_at'] ?? 0);
        $jwks = $decoded['jwks'] ?? null;
        if ($expiresAt <= time() || !is_array($jwks) || !isset($jwks['keys']) || !is_array($jwks['keys'])) {
            return null;
        }

        /** @var array{keys: list<array<string, mixed>>} $jwks */
        return [
            'jwks' => $jwks,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @param array{keys: list<array<string, mixed>>} $jwks
     */
    private static function writeJwksCache(array $jwks, int $expiresAt): void
    {
        $directory = dirname(self::jwksCachePath());
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $payload = json_encode([
            'expires_at' => $expiresAt,
            'jwks' => $jwks,
        ]);
        if (!is_string($payload)) {
            return;
        }

        @file_put_contents(self::jwksCachePath(), $payload, LOCK_EX);
    }

    private static function jwksCachePath(): string
    {
        return dirname(__DIR__, 2) . '/tmp/google_jwks.json';
    }

    /**
     * @return array{jwks: array{keys: list<array<string, mixed>>}, expires_at: int}
     */
    private static function fetchJwks(): array
    {
        if (self::$testFetchJwks !== null) {
            try {
                $result = (self::$testFetchJwks)();
            } catch (\Throwable $exception) {
                error_log('[GoogleIdTokenValidator] JWKS fetch failed.');
                self::fail(GoogleIdTokenException::JWKS_UNAVAILABLE);
            }
            $jwks = $result['jwks'] ?? null;
            if (!is_array($jwks) || !isset($jwks['keys']) || !is_array($jwks['keys'])) {
                error_log('[GoogleIdTokenValidator] Test JWKS fetch returned an invalid document.');
                self::fail(GoogleIdTokenException::JWKS_UNAVAILABLE);
            }

            /** @var array{keys: list<array<string, mixed>>} $jwks */
            return [
                'jwks' => $jwks,
                'expires_at' => (int) ($result['expires_at'] ?? (time() + self::DEFAULT_JWKS_TTL_SECONDS)),
            ];
        }

        if (!extension_loaded('curl')) {
            error_log('[GoogleIdTokenValidator] PHP ext-curl is not installed.');
            self::fail(GoogleIdTokenException::JWKS_UNAVAILABLE);
        }

        $cacheControl = '';
        $ch = curl_init(self::JWKS_URL);
        if ($ch === false) {
            self::fail(GoogleIdTokenException::JWKS_UNAVAILABLE);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => 'MBPHA-TeleHealth/1.0',
            CURLOPT_HEADERFUNCTION => static function ($channel, string $header) use (&$cacheControl): int {
                if (stripos($header, 'Cache-Control:') === 0) {
                    $cacheControl = trim(substr($header, strlen('Cache-Control:')));
                }
                return strlen($header);
            },
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $errno !== 0 || $http < 200 || $http >= 300 || !is_string($raw)) {
            error_log('[GoogleIdTokenValidator] JWKS fetch failed.');
            self::fail(GoogleIdTokenException::JWKS_UNAVAILABLE);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['keys']) || !is_array($decoded['keys'])) {
            error_log('[GoogleIdTokenValidator] JWKS response was invalid.');
            self::fail(GoogleIdTokenException::JWKS_UNAVAILABLE);
        }

        /** @var array{keys: list<array<string, mixed>>} $decoded */
        $ttl = self::cacheTtlFromHeader($cacheControl);

        return [
            'jwks' => $decoded,
            'expires_at' => time() + $ttl,
        ];
    }

    private static function cacheTtlFromHeader(string $cacheControl): int
    {
        if (preg_match('/max-age\s*=\s*(\d+)/i', $cacheControl, $matches) === 1) {
            $ttl = (int) $matches[1];
            if ($ttl > 0) {
                return min($ttl, self::MAX_JWKS_TTL_SECONDS);
            }
        }

        return self::DEFAULT_JWKS_TTL_SECONDS;
    }

    /**
     * @param array<string, mixed> $jwk
     */
    private static function rsaJwkToPem(array $jwk): string
    {
        $modulus = self::base64UrlDecode((string) $jwk['n']);
        $exponent = self::base64UrlDecode((string) $jwk['e']);
        if ($modulus === null || $exponent === null || $modulus === '' || $exponent === '') {
            self::fail(GoogleIdTokenException::INVALID_SIGNATURE);
        }

        $rsaPublicKey = self::derSequence(
            self::derUnsignedInteger($modulus) . self::derUnsignedInteger($exponent)
        );
        $algorithmIdentifier = hex2bin('300d06092a864886f70d0101010500');
        if ($algorithmIdentifier === false) {
            self::fail(GoogleIdTokenException::INVALID_SIGNATURE);
        }
        $bitString = "\x03" . self::derLength(strlen($rsaPublicKey) + 1) . "\x00" . $rsaPublicKey;
        $spki = self::derSequence($algorithmIdentifier . $bitString);

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($spki), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    private static function derUnsignedInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '') {
            $bytes = "\x00";
        }
        if ((ord($bytes[0]) & 0x80) !== 0) {
            $bytes = "\x00" . $bytes;
        }

        return "\x02" . self::derLength(strlen($bytes)) . $bytes;
    }

    private static function derSequence(string $value): string
    {
        return "\x30" . self::derLength(strlen($value)) . $value;
    }

    private static function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $packed = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($packed)) . $packed;
    }

    private static function base64UrlDecode(string $value): ?string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    /**
     * @return never
     */
    private static function fail(string $reason): void
    {
        error_log('[GoogleIdTokenValidator] ' . $reason);
        throw new GoogleIdTokenException($reason);
    }
}
