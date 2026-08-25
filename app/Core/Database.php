<?php

namespace App\Core;

use App\Config\Database as DatabaseConfig;
use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $config = DatabaseConfig::getConfig();

            try {
                self::$instance = new PDO(
                    self::buildDsn($config),
                    $config['user'],
                    $config['pass'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );
            } catch (PDOException $e) {
                error_log('Database connection failed: ' . $e->getMessage());
                throw new \RuntimeException('Database connection failed.');
            }
        }

        return self::$instance;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function buildDsn(array $config): string
    {
        $name = trim((string) ($config['name'] ?? ''));
        $charset = trim((string) ($config['charset'] ?? 'utf8mb4')) ?: 'utf8mb4';
        $socket = trim((string) ($config['socket'] ?? ''));

        if ($name === '') {
            throw new \RuntimeException('Database connection failed.');
        }

        if ($socket !== '') {
            return sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $socket, $name, $charset);
        }

        $host = trim((string) ($config['host'] ?? ''));
        $port = trim((string) ($config['port'] ?? '3306')) ?: '3306';

        if ($host === '') {
            throw new \RuntimeException('Database connection failed.');
        }

        return sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $host,
            $port,
            $name,
            $charset
        );
    }
}
