<?php

namespace App\Helpers;

use App\Config\Environment;

class Helper
{
    public static function escape(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
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
}
