<?php

namespace App\Helpers;

use App\Config\Environment;
use App\Config\Paths;

class Helper
{
    public static function escape(?string $string): string
    {
        return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
    }

    public static function baseUrl(): string
    {
        $configured = rtrim((string) Environment::get('APP_URL', ''), '/');
        if ($configured !== '') {
            return $configured;
        }

        $host = $_SERVER['HTTP_HOST'] ?? null;
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? null;

        if ($host && $scriptName) {
            $scheme = self::requestScheme();
            $scriptDirectory = str_replace('\\', '/', dirname($scriptName));
            $scriptDirectory = $scriptDirectory === '/' || $scriptDirectory === '.' ? '' : rtrim($scriptDirectory, '/');

            return $scheme . '://' . $host . $scriptDirectory;
        }

        return '';
    }

    public static function url(string $path = ''): string
    {
        $baseUrl = self::baseUrl();

        if ($path === '') {
            return $baseUrl;
        }

        return $baseUrl . '/' . ltrim($path, '/');
    }

    /**
     * Same-origin path for browser navigation (JSON redirects, fetch follow-ups).
     * Includes the application subdirectory so window.location.assign() does
     * not resolve against Apache's document root on XAMPP installs.
     */
    public static function browserPath(string $path = '/'): string
    {
        $absolute = self::url($path === '' ? '/' : $path);
        $urlPath = parse_url($absolute, PHP_URL_PATH);
        if (!is_string($urlPath) || $urlPath === '') {
            $urlPath = '/' . ltrim($path, '/');
        }

        return self::safeInternalPath($urlPath, '/');
    }

    /**
     * Absolute URL from APP_URL. Use this for emails so links follow
     * the deployed application address rather than the current request host.
     */
    public static function applicationUrl(string $path = ''): string
    {
        $baseUrl = rtrim((string) Environment::get('APP_URL', ''), '/');
        if ($baseUrl === '') {
            $baseUrl = rtrim(self::baseUrl(), '/');
        }

        $host = strtolower((string) (parse_url($baseUrl, PHP_URL_HOST) ?: ''));
        $environment = strtolower(trim((string) Environment::get('APP_ENV', 'production')));
        if ($environment === 'production' && in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            $baseUrl = '';
        }

        if ($path === '') {
            return $baseUrl;
        }

        if ($baseUrl === '') {
            return '/' . ltrim($path, '/');
        }

        return $baseUrl . '/' . ltrim($path, '/');
    }

    private static function requestScheme(): string
    {
        $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($forwarded === 'https') {
            return 'https';
        }

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return 'https';
        }

        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return 'https';
        }

        return 'http';
    }

    /**
     * True when this request arrived over HTTPS.
     * Same signals Session uses for the Secure cookie flag (HTTPS / port 443),
     * plus X-Forwarded-Proto for App Platform and Cloudflare.
     */
    public static function isHttpsRequest(): bool
    {
        return self::requestScheme() === 'https';
    }

    /**
     * Test-only: when false, redirect() records the target and returns
     * instead of calling exit(). Production callers must leave this true.
     */
    public static bool $exitOnRedirect = true;

    public static ?string $lastRedirect = null;

    public static function redirect(string $url): void
    {
        self::$lastRedirect = self::url($url);
        if (!headers_sent()) {
            header('Location: ' . self::$lastRedirect);
        }
        if (self::$exitOnRedirect) {
            exit;
        }
    }

    public static function asset(string $path): string
    {
        $url = self::url($path);
        $file = Paths::publicRoot() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/'));
        if (is_file($file)) {
            // App Platform (and similar hosts) stamp checkout files at 1980-01-01,
            // so filemtime() never changes between deploys and browsers keep stale CSS.
            $version = @hash_file('crc32b', $file);
            if (!is_string($version) || $version === '') {
                $version = (string) filemtime($file);
            }
            $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . $version;
        }

        return $url;
    }

    public static function currentPath(): string
    {
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $prefixes = [];

        $basePath = rtrim((string) (parse_url(self::baseUrl(), PHP_URL_PATH) ?: ''), '/');
        if ($basePath !== '' && $basePath !== '/') {
            $prefixes[] = $basePath;
        }

        $scriptDir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        if ($scriptDir !== '' && $scriptDir !== '/' && $scriptDir !== '.' && !in_array($scriptDir, $prefixes, true)) {
            $prefixes[] = $scriptDir;
        }

        foreach ($prefixes as $prefix) {
            if (strpos($requestUri, $prefix) === 0) {
                $requestUri = substr($requestUri, strlen($prefix));
                break;
            }
        }

        if ($requestUri === '' || $requestUri[0] !== '/') {
            $requestUri = '/' . ltrim($requestUri, '/');
        }

        return $requestUri;
    }

    /**
     * Current application path including the query string, used as a
     * post-action return target (e.g. after archiving a notification).
     */
    public static function currentRequestPath(): string
    {
        $path = self::currentPath();
        $query = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));

        return $query === '' ? $path : $path . '?' . $query;
    }

    /**
     * Allow only same-app relative paths. Rejects protocol-relative and
     * external URLs so callers cannot bounce users off-site.
     */
    public static function safeInternalPath(string $path, string $fallback = '/'): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, "\0") || str_contains($path, "\n") || str_contains($path, "\r")) {
            return $fallback;
        }
        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '://') || str_contains($path, '\\')) {
            return $fallback;
        }

        return $path;
    }

    public static function clientIp(): string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));

        return mb_substr($ip, 0, 45);
    }

    public static function userAgent(): string
    {
        return mb_substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255);
    }

    public static function isStrongPassword(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/\d/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);
    }

    public static function formatDate(?string $date, string $format = 'd M Y', string $fallback = 'Not available'): string
    {
        if ($date === null || trim($date) === '') {
            return $fallback;
        }

        $timestamp = strtotime($date);

        if ($timestamp === false) {
            return $fallback;
        }

        return date($format, $timestamp);
    }

    /**
     * Return the IANA timezone identifier the application is pinned to.
     *
     * This is the value set via date_default_timezone_set() during app
     * bootstrap (public/index.php), defaulting to Pacific/Port_Moresby
     * for MBPHA.  Use this value:
     *   • in views that need to render a timezone label to the user
     *   • when handing a consistent TZ to frontend scripts so they can
     *     compute "now" on the same wall-clock the server uses.
     */
    public static function appTimezone(): string
    {
        $name = date_default_timezone_get();
        return $name !== '' ? $name : 'Pacific/Port_Moresby';
    }

    /**
     * Return a user-friendly label for the application timezone suitable
     * for rendering next to appointment times.
     *
     * We intentionally do NOT echo the raw IANA name to end users because
     * the project previously surfaced "Europe/Berlin" on machines whose
     * php.ini set date.timezone to that value.  MBPHA operates in Papua
     * New Guinea so the label is always expressed in local terms, with a
     * UTC offset included for clarity.
     */
    public static function appTimezoneLabel(): string
    {
        $iana = self::appTimezone();
        if ($iana === '' || strcasecmp($iana, 'Pacific/Port_Moresby') === 0) {
            return 'PNG Time (UTC+10)';
        }
        try {
            $tz = new \DateTimeZone($iana);
        } catch (\Throwable) {
            return 'PNG Time (UTC+10)';
        }
        $offsetSeconds = $tz->getOffset(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $sign = $offsetSeconds >= 0 ? '+' : '-';
        $abs = abs($offsetSeconds);
        $hours = intdiv($abs, 3600);
        $minutes = intdiv($abs % 3600, 60);
        $offset = $minutes > 0
            ? sprintf('%s%02d:%02d', $sign, $hours, $minutes)
            : sprintf('%s%02d', $sign, $hours);
        return sprintf('Local Time (UTC%s)', $offset);
    }

    /**
     * Application "now" in the pinned MBPHA timezone.
     * Use this instead of the browser clock or MySQL NOW() when comparing slots.
     */
    public static function now(): \DateTimeImmutable
    {
        $name = trim((string) (Environment::get('APP_TIMEZONE') ?? ''));
        if ($name === '') {
            $name = 'Pacific/Port_Moresby';
        }

        try {
            return new \DateTimeImmutable('now', new \DateTimeZone($name));
        } catch (\Throwable) {
            return new \DateTimeImmutable('now', new \DateTimeZone('Pacific/Port_Moresby'));
        }
    }

    public static function nowDatetime(): string
    {
        return self::now()->format('Y-m-d H:i:s');
    }

    public static function nowIso(): string
    {
        return self::now()->format('c');
    }

    /**
     * Combine a DATE + TIME into an ISO-8601 string in the application timezone.
     */
    public static function combineDateTimeIso(string $date, string $time): string
    {
        $date = trim($date);
        $time = trim($time);

        if ($date === '' || $time === '') {
            return '';
        }

        if (preg_match('/^\d{2}:\d{2}$/', $time) === 1) {
            $time .= ':00';
        }

        try {
            $parsed = \DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $date . ' ' . $time,
                self::now()->getTimezone()
            );
        } catch (\Throwable) {
            return '';
        }

        return $parsed instanceof \DateTimeImmutable ? $parsed->format('c') : '';
    }

    public static function initials(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return 'U';
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : 'U';
    }
}
