<?php

/**
 * Apply migration 035 idempotently.
 *
 * Usage: php bin/apply_migration_035.php
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

echo "Applying migration 035…\n";

if (tableExists($db, 'ai_conversations') && tableExists($db, 'ai_messages')) {
    echo "  ai_conversations and ai_messages already exist\n";
    echo "Migration 035 complete.\n";
    exit(0);
}

$sql = file_get_contents(dirname(__DIR__) . '/database/migrations/035_create_ai_conversation_tables.sql');
if (!is_string($sql) || trim($sql) === '') {
    fwrite(STDERR, "Migration file 035 could not be read.\n");
    exit(1);
}

try {
    $db->exec($sql);
} catch (\PDOException $e) {
    fwrite(STDERR, "Migration 035 failed.\n");
    exit(1);
}

if (!tableExists($db, 'ai_conversations') || !tableExists($db, 'ai_messages')) {
    fwrite(STDERR, "Migration 035 did not create the expected tables.\n");
    exit(1);
}

echo "  ai_conversations created\n";
echo "  ai_messages created\n";
echo "Migration 035 complete.\n";
