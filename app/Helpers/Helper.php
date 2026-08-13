<?php

namespace App\Helpers;

use App\Config\Environment;

class Helper
{
    public static function escape(?string $string): string
    {
        return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
    }

    public static function baseUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? null;
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? null;

        if ($host && $scriptName) {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $scriptDirectory = str_replace('\\', '/', dirname($scriptName));
            $scriptDirectory = $scriptDirectory === '/' || $scriptDirectory === '.' ? '' : rtrim($scriptDirectory, '/');

            return $scheme . '://' . $host . $scriptDirectory;
        }

        return rtrim((string) Environment::get('APP_URL', 'http://localhost'), '/');
    }

    public static function url(string $path = ''): string
    {
        $baseUrl = self::baseUrl();

        if ($path === '') {
            return $baseUrl;
        }

        return $baseUrl . '/' . ltrim($path, '/');
    }

    public static function redirect(string $url): void
    {
        header('Location: ' . self::url($url));
        exit;
    }

    public static function asset(string $path): string
    {
        return self::url($path);
    }

    public static function currentPath(): string
    {
        $requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $basePath = parse_url(self::baseUrl(), PHP_URL_PATH) ?: '';

        if ($basePath !== '' && strpos($requestUri, $basePath) === 0) {
            $requestUri = substr($requestUri, strlen($basePath));
        }

        return $requestUri === '' ? '/' : $requestUri;
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
