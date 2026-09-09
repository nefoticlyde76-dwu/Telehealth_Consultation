<?php

/**
 * Apply migration 032 idempotently.
 *
 * Usage: php bin/apply_migration_032.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Core\Database;

Environment::load(dirname(__DIR__) . '/.env');

$db = Database::getInstance();

function tableExists(\PDO $db, string $table): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table'
    );
    $stmt->execute([':table' => $table]);

    return (int) $stmt->fetchColumn() > 0;
}

echo "Applying migration 032…\n";

if (tableExists($db, 'login_attempts')) {
    echo "  login_attempts already exists\n";
} else {
    $sql = file_get_contents(dirname(__DIR__) . '/database/migrations/032_create_login_attempts_table.sql');
    if (!is_string($sql) || trim($sql) === '') {
        fwrite(STDERR, "Migration file 032 could not be read.\n");
        exit(1);
    }

    $db->exec($sql);
    echo "  login_attempts created\n";
}

echo "Migration 032 complete.\n";
