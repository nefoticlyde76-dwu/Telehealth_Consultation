<?php

/**
 * Apply migration 026 idempotently.
 *
 * Usage: php bin/apply_migration_026.php
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

function usersStatusColumn(PDO $db): array
{
    $stmt = $db->query(
        "SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'users'
           AND COLUMN_NAME = 'status'
         LIMIT 1"
    );
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : [];
}

echo "Applying migration 026…\n";

$column = usersStatusColumn($db);
$columnType = strtolower((string) ($column['COLUMN_TYPE'] ?? ''));

if ($columnType === '') {
    fwrite(STDERR, "users.status could not be inspected.\n");
    exit(1);
}

if (str_contains($columnType, 'invitation_pending')) {
    echo "  users.status already includes invitation_pending\n";
} else {
    $db->exec(
        "ALTER TABLE users
         MODIFY COLUMN status ENUM('invitation_pending', 'active', 'suspended', 'inactive', 'deleted') NOT NULL DEFAULT 'active'"
    );
    echo "  users.status ENUM updated\n";
}

$column = usersStatusColumn($db);
echo '  COLUMN_TYPE=' . (string) ($column['COLUMN_TYPE'] ?? '') . "\n";
echo '  IS_NULLABLE=' . (string) ($column['IS_NULLABLE'] ?? '') . "\n";
echo '  COLUMN_DEFAULT=' . (string) ($column['COLUMN_DEFAULT'] ?? '') . "\n";
echo "Migration 026 complete.\n";
