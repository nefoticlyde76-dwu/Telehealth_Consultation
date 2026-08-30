<?php

namespace App\Config;

class Environment
{
    private static array $variables = [];

    public static function load(string $path): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException('.env file not found.');
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
            $value = self::unquote(trim($value));

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

    /**
     * Read a boolean environment flag.
     *
     * PHP casts the string "false" to true, so APP_DEBUG=false must be parsed
     * explicitly. Accepted true values: 1, true, yes, on. Accepted false
     * values: 0, false, no, off, empty string.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $value = self::get($key, null);
        if ($value === null || $value === false) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return match ($normalized) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off', '' => false,
            default => $default,
        };
    }

    private static function unquote(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $first = $value[0];
        $last = $value[strlen($value) - 1];

        if (strlen($value) >= 2 && (($first === '"' && $last === '"') || ($first === "'" && $last === "'"))) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
