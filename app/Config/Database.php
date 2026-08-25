<?php

namespace App\Config;

class Database
{
    public static function getConfig(): array
    {
        return [
            'host' => trim((string) Environment::get('DB_HOST', '')),
            'port' => trim((string) Environment::get('DB_PORT', '3306')) ?: '3306',
            'name' => trim((string) Environment::get('DB_NAME', '')),
            'user' => trim((string) Environment::get('DB_USER', '')),
            'pass' => (string) Environment::get('DB_PASS', ''),
            'socket' => trim((string) Environment::get('DB_SOCKET', '')),
            'charset' => 'utf8mb4',
        ];
    }
}
