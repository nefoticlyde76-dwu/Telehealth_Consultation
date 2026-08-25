<?php

/**
 * Apply migration 025 idempotently.
 *
 * Usage: php bin/apply_migration_025.php
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

function columnExists(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table
           AND COLUMN_NAME = :column'
    );
    $stmt->execute([':table' => $table, ':column' => $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function indexExists(PDO $db, string $table, string $index): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table
           AND INDEX_NAME = :index'
    );
    $stmt->execute([':table' => $table, ':index' => $index]);

    return (int) $stmt->fetchColumn() > 0;
}

echo "Applying migration 025…\n";

if (!columnExists($db, 'users', 'google_sub')) {
    $db->exec('ALTER TABLE users ADD COLUMN google_sub VARCHAR(255) NULL DEFAULT NULL AFTER password');
    echo "  users.google_sub added\n";
} else {
    echo "  users.google_sub already exists\n";
}

if (!indexExists($db, 'users', 'uq_users_google_sub')) {
    $db->exec('CREATE UNIQUE INDEX uq_users_google_sub ON users (google_sub)');
    echo "  uq_users_google_sub created\n";
} else {
    echo "  uq_users_google_sub already exists\n";
}

if (!columnExists($db, 'users', 'google_email')) {
    $db->exec('ALTER TABLE users ADD COLUMN google_email VARCHAR(255) NULL DEFAULT NULL AFTER google_sub');
    echo "  users.google_email added\n";
} else {
    echo "  users.google_email already exists\n";
}

if (!columnExists($db, 'users', 'auth_provider')) {
    $db->exec("ALTER TABLE users ADD COLUMN auth_provider ENUM('local','google','both') NOT NULL DEFAULT 'local' AFTER google_email");
    echo "  users.auth_provider added\n";
} else {
    echo "  users.auth_provider already exists\n";
}

echo "Migration 025 complete.\n";
