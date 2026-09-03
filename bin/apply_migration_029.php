<?php

/**
 * Apply migration 029 idempotently.
 *
 * Usage: php bin/apply_migration_029.php
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

function columnExists(\PDO $db, string $table, string $column): bool
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

echo "Applying migration 029…\n";

if (!columnExists($db, 'consultation_requests', 'complaint_image_path')) {
    $db->exec(
        'ALTER TABLE consultation_requests
         ADD COLUMN complaint_image_path VARCHAR(255) NULL DEFAULT NULL AFTER reason'
    );
    echo "  consultation_requests.complaint_image_path added\n";
} else {
    echo "  consultation_requests.complaint_image_path already exists\n";
}

echo "Migration 029 complete.\n";
