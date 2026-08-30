<?php

namespace App\Config;

class App
{
    public static function getConfig(): array
    {
        $environment = strtolower(trim((string) Environment::get('APP_ENV', 'production')));
        if ($environment === '') {
            $environment = 'production';
        }

        $debug = Environment::getBool('APP_DEBUG', false);
        if ($environment === 'production') {
            $debug = false;
        }

        return [
            'name' => Environment::get('APP_NAME', 'MBPHA TeleHealth Consultation System'),
            'env' => $environment,
            'debug' => $debug,
            'url' => Environment::get('APP_URL', ''),
            'session' => [
                'name' => Environment::get('SESSION_NAME', 'TELEHEALTH_SESSION'),
                'lifetime' => (int) Environment::get('SESSION_LIFETIME', 7200),
            ],
            'mail' => [
                'mailer' => Environment::get('MAIL_MAILER', 'log'),
                'host' => Environment::get('MAIL_HOST', ''),
                'port' => (int) Environment::get('MAIL_PORT', 587),
                'encryption' => Environment::get('MAIL_ENCRYPTION', 'tls'),
                'from_address' => Environment::get('MAIL_FROM_ADDRESS', 'telehealth76@gmail.com'),
                'from_name' => Environment::get('MAIL_FROM_NAME', 'TeleHealth PNG'),
            ],
            'google' => [
                'client_id' => trim((string) Environment::get('GOOGLE_CLIENT_ID', '')),
            ],
            'doctor_invitation' => [
                'token_ttl_hours' => self::boundedInt('DOCTOR_INVITE_TOKEN_TTL_HOURS', 24, 1, 168),
                'resend_cooldown_minutes' => self::boundedInt('DOCTOR_INVITE_RESEND_COOLDOWN_MINUTES', 5, 1),
                'max_sends_per_day' => self::boundedInt('DOCTOR_INVITE_MAX_SENDS_PER_DAY', 8, 1),
            ],
            'password_reset' => [
                'token_ttl_minutes' => self::boundedInt('PASSWORD_RESET_TOKEN_TTL_MINUTES', 30, 1, 1440),
                'request_cooldown_minutes' => self::boundedInt('PASSWORD_RESET_REQUEST_COOLDOWN_MINUTES', 5, 1, 60),
            ],
        ];
    }

    /**
     * Read an integer environment setting and clamp it to an allowed range.
     * Missing, empty, or non-integer values fall back to the default.
     */
    private static function boundedInt(string $key, int $default, int $min, ?int $max = null): int
    {
        $raw = Environment::get($key, null);
        if ($raw === null || $raw === false) {
            return $default;
        }

        $raw = trim((string) $raw);
        if ($raw === '' || filter_var($raw, FILTER_VALIDATE_INT) === false) {
            return $default;
        }

        $value = (int) $raw;
        if ($value < $min) {
            return $min;
        }
        if ($max !== null && $value > $max) {
            return $max;
        }

        return $value;
    }
}
