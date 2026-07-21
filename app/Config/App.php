<?php

namespace App\Config;

class App
{
    public static function getConfig(): array
    {
        return [
            'name' => Environment::get('APP_NAME', 'TeleHealth Consultation System'),
            'env' => Environment::get('APP_ENV', 'development'),
            'debug' => (bool) Environment::get('APP_DEBUG', true),
            'url' => Environment::get('APP_URL', 'http://localhost'),
            'session' => [
                'name' => Environment::get('SESSION_NAME', 'TELEHEALTH_SESSION'),
                'lifetime' => (int) Environment::get('SESSION_LIFETIME', 7200),
            ],
        ];
    }
}
