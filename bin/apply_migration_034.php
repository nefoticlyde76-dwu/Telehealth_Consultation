<?php

/**
 * Apply migration 034 idempotently.
 *
 * Usage: php bin/apply_migration_034.php
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

echo "Applying migration 034…\n";

$stmt = $db->query(
    "SELECT COLUMN_TYPE
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'consultation_requests'
       AND COLUMN_NAME = 'status'
     LIMIT 1"
);
$columnType = strtolower((string) ($stmt->fetchColumn() ?: ''));

if ($columnType === '') {
    fwrite(STDERR, "consultation_requests.status could not be inspected.\n");
    exit(1);
}

if (str_contains($columnType, 'no-show')) {
    echo "  consultation_requests.status already includes No-Show\n";
} else {
    $db->exec(
        "ALTER TABLE consultation_requests
         MODIFY COLUMN status ENUM('Pending', 'Approved', 'Rejected', 'Cancelled', 'Completed', 'No-Show')
         NOT NULL DEFAULT 'Pending'"
    );
    echo "  consultation_requests.status now includes No-Show\n";
}

echo "Migration 034 complete.\n";
