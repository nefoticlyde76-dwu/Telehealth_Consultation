<?php

/**
 * Apply migration 024 idempotently.
 *
 * Usage: php bin/apply_migration_024.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Core\Database;
use PDO;

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

function tableExists(PDO $db, string $table): bool
{
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table'
    );
    $stmt->execute([':table' => $table]);

    return (int) $stmt->fetchColumn() > 0;
}

echo "Applying migration 024…\n";

$db->exec(
    "ALTER TABLE users
     MODIFY COLUMN status ENUM('active', 'suspended', 'inactive', 'deleted') NOT NULL DEFAULT 'active'"
);
echo "  users.status ENUM updated\n";

$columns = [
    'last_login_at' => "ADD COLUMN last_login_at DATETIME NULL DEFAULT NULL AFTER status",
    'force_password_reset' => "ADD COLUMN force_password_reset TINYINT(1) NOT NULL DEFAULT 0 AFTER last_login_at",
    'password_changed_at' => "ADD COLUMN password_changed_at DATETIME NULL DEFAULT NULL AFTER force_password_reset",
    'deleted_at' => "ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL AFTER password_changed_at",
    'anonymized_at' => "ADD COLUMN anonymized_at DATETIME NULL DEFAULT NULL AFTER deleted_at",
];

foreach ($columns as $name => $ddl) {
    if (columnExists($db, 'users', $name)) {
        echo "  users.{$name} already exists\n";
        continue;
    }
    $db->exec('ALTER TABLE users ' . $ddl);
    echo "  users.{$name} added\n";
}

if (!indexExists($db, 'users', 'idx_users_last_login_at')) {
    $db->exec('CREATE INDEX idx_users_last_login_at ON users (last_login_at)');
    echo "  idx_users_last_login_at created\n";
}

if (!indexExists($db, 'users', 'idx_users_deleted_at')) {
    $db->exec('CREATE INDEX idx_users_deleted_at ON users (deleted_at)');
    echo "  idx_users_deleted_at created\n";
}

if (!tableExists($db, 'user_sessions')) {
    $db->exec(
        "CREATE TABLE user_sessions (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            session_token_hash CHAR(64) NOT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent VARCHAR(255) NULL,
            last_seen_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_user_sessions_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE,
            UNIQUE INDEX uq_user_sessions_token_hash (session_token_hash),
            INDEX idx_user_sessions_user_seen (user_id, last_seen_at)
        ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
    echo "  user_sessions created\n";
} else {
    echo "  user_sessions already exists\n";
}

if (!tableExists($db, 'notification_preferences')) {
    $db->exec(
        "CREATE TABLE notification_preferences (
            user_id BIGINT PRIMARY KEY,
            appointment_in_app TINYINT(1) NOT NULL DEFAULT 1,
            consultation_in_app TINYINT(1) NOT NULL DEFAULT 1,
            email_enabled TINYINT(1) NOT NULL DEFAULT 1,
            sms_enabled TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_notification_preferences_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
    echo "  notification_preferences created\n";
} else {
    echo "  notification_preferences already exists\n";
}

echo "Migration 024 complete.\n";
