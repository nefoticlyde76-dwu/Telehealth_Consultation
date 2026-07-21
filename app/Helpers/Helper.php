<?php

namespace App\Helpers;

class Helper
{
    public static function escape(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }

    public static function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    public static function asset(string $path): string
    {
        return rtrim($_ENV['APP_URL'], '/') . '/' . ltrim($path, '/');
    }
}
