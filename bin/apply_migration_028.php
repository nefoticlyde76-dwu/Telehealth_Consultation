<?php

/**
 * Apply migration 028 idempotently.
 *
 * Usage: php bin/apply_migration_028.php
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

function availabilityStatusColumn(PDO $db): array
{
    $stmt = $db->query(
        "SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'doctor_availability'
           AND COLUMN_NAME = 'status'
         LIMIT 1"
    );
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : [];
}

echo "Applying migration 028…\n";

$column = availabilityStatusColumn($db);
$columnType = strtolower((string) ($column['COLUMN_TYPE'] ?? ''));

if ($columnType === '') {
    fwrite(STDERR, "doctor_availability.status could not be inspected.\n");
    exit(1);
}

if (str_contains($columnType, 'expired')) {
    echo "  doctor_availability.status already includes Expired\n";
} else {
    $values = ['Available', 'Booked', 'Expired'];
    if (preg_match_all("/'((?:\\\\'|[^'])*)'/", (string) ($column['COLUMN_TYPE'] ?? ''), $matches) > 0) {
        foreach ($matches[1] as $existing) {
            $existing = stripcslashes((string) $existing);
            if ($existing !== '' && !in_array($existing, $values, true)) {
                $values[] = $existing;
            }
        }
    }

    $quoted = implode(', ', array_map(
        static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'",
        $values
    ));
    $db->exec(
        "ALTER TABLE doctor_availability
         MODIFY COLUMN status ENUM({$quoted}) NOT NULL DEFAULT 'Available'"
    );
    echo "  doctor_availability.status ENUM updated\n";
}

$column = availabilityStatusColumn($db);
echo '  COLUMN_TYPE=' . (string) ($column['COLUMN_TYPE'] ?? '') . "\n";
echo '  IS_NULLABLE=' . (string) ($column['IS_NULLABLE'] ?? '') . "\n";
echo '  COLUMN_DEFAULT=' . (string) ($column['COLUMN_DEFAULT'] ?? '') . "\n";
echo "Migration 028 complete.\n";
