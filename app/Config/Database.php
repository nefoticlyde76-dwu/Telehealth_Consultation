
<?php

namespace App\Config;

class Database
{
    public static function getConfig(): array
    {
        return [
            'host' => Environment::get('DB_HOST', '127.0.0.1'),
            'port' => Environment::get('DB_PORT', '3306'),
            'name' => Environment::get('DB_NAME', 'telehealth_db'),
            'user' => Environment::get('DB_USER', 'root'),
            'pass' => Environment::get('DB_PASS', ''),
            'charset' => 'utf8mb4',
        ];
    }
}

