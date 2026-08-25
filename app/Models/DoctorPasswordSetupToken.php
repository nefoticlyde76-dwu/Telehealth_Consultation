<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Persistence for doctor_password_setup_tokens.
 * Stores SHA-256 hashes only. Never persist a raw invitation token.
 */
class DoctorPasswordSetupToken
{
    /**
     * @return array<string, mixed>|null
     */
    public static function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT id, user_id, token_hash, expires_at, used_at, sent_at, created_at
            FROM doctor_password_setup_tokens
            WHERE id = :id
            LIMIT 1"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByTokenHash(string $tokenHash): ?array
    {
        $hash = strtolower(trim($tokenHash));
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT id, user_id, token_hash, expires_at, used_at, sent_at, created_at
            FROM doctor_password_setup_tokens
            WHERE token_hash = :token_hash
            LIMIT 1"
        );
        $stmt->bindValue(':token_hash', $hash);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Expire unused tokens that are still within their TTL.
     * Does not set used_at and does not delete rows.
     */
    public static function expireUsableForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE doctor_password_setup_tokens
            SET expires_at = NOW()
            WHERE user_id = :user_id
              AND used_at IS NULL
              AND expires_at > NOW()"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Insert a hash-only invitation token. Expiry is computed with MySQL NOW()
     * so token timestamps share one clock with expire/used/sent updates.
     *
     * @return array<string, mixed>|null
     */
    public static function insert(int $userId, string $tokenHash, int $ttlHours): ?array
    {
        $hash = strtolower(trim($tokenHash));
        if ($userId <= 0 || $ttlHours < 1 || !preg_match('/^[a-f0-9]{64}$/', $hash)) {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT INTO doctor_password_setup_tokens (
                user_id,
                token_hash,
                expires_at,
                used_at,
                sent_at
            ) VALUES (
                :user_id,
                :token_hash,
                DATE_ADD(NOW(), INTERVAL :ttl_hours HOUR),
                NULL,
                NULL
            )"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':token_hash', $hash);
        $stmt->bindValue(':ttl_hours', $ttlHours, PDO::PARAM_INT);

        if (!$stmt->execute()) {
            return null;
        }

        $id = (int) $db->lastInsertId();
        if ($id <= 0) {
            return null;
        }

        return self::findById($id);
    }

    public static function markUsed(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE doctor_password_setup_tokens
            SET used_at = NOW()
            WHERE id = :id
              AND used_at IS NULL"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() === 1;
    }

    public static function markSent(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE doctor_password_setup_tokens
            SET sent_at = NOW()
            WHERE id = :id"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() === 1;
    }

    /**
     * Load a still-usable invitation by hash. Optional row lock for setup POST.
     *
     * @return array<string, mixed>|null
     */
    public static function findValidInvitationByHash(string $tokenHash, bool $forUpdate = false): ?array
    {
        $hash = strtolower(trim($tokenHash));
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            return null;
        }

        $db = Database::getInstance();
        $sql = "SELECT
                t.id AS token_id,
                t.user_id,
                users.full_name
            FROM doctor_password_setup_tokens t
            INNER JOIN users ON users.id = t.user_id
            INNER JOIN roles ON roles.id = users.role_id
            WHERE t.token_hash = :token_hash
              AND t.used_at IS NULL
              AND t.expires_at > NOW()
              AND users.status = 'invitation_pending'
              AND users.password IS NULL
              AND roles.name = 'doctor'
            LIMIT 1";

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':token_hash', $hash);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function claimUsable(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE doctor_password_setup_tokens
            SET used_at = NOW()
            WHERE id = :id
              AND used_at IS NULL
              AND expires_at > NOW()"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() === 1;
    }

    public static function countUsableForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT COUNT(*)
            FROM doctor_password_setup_tokens
            WHERE user_id = :user_id
              AND used_at IS NULL
              AND expires_at > NOW()"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function countSentTodayForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT COUNT(*)
            FROM doctor_password_setup_tokens
            WHERE user_id = :user_id
              AND sent_at IS NOT NULL
              AND sent_at >= DATE(NOW())"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function hasRecentSuccessfulSend(int $userId, int $cooldownMinutes): bool
    {
        if ($userId <= 0 || $cooldownMinutes < 1) {
            return false;
        }

        $cooldownMinutes = max(1, $cooldownMinutes);

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT COUNT(*)
            FROM doctor_password_setup_tokens
            WHERE user_id = :user_id
              AND sent_at IS NOT NULL
              AND sent_at > DATE_SUB(NOW(), INTERVAL {$cooldownMinutes} MINUTE)"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }
}
