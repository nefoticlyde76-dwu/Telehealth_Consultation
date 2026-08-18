<?php

namespace App\Config;

class Environment
{
    private static array $variables = [];

    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException('.env file not found at: ' . $path);
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            if (str_starts_with($trimmed, '#') || str_starts_with($trimmed, ';')) {
                continue;
            }
            if (!str_contains($trimmed, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $trimmed, 2);
            $name = trim($name);
            $value = trim($value);

            if ($name === '') {
                continue;
            }

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }

            self::$variables[$name] = $value;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?? $default;

        if ($value === false) {
            return $default;
        }

        return $value;
    }
}
