<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Persistence for password_reset_tokens.
 * Stores SHA-256 hashes only. Never persist a raw reset token.
 */
class PasswordResetToken
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
            "SELECT id, user_id, token_hash, expires_at, used_at, created_at
            FROM password_reset_tokens
            WHERE id = :id
            LIMIT 1"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Generate a raw token, persist only its SHA-256 hash, and return the raw
     * value in memory for the email step. Does not expire prior tokens.
     *
     * @return array{
     *   token_id:int,
     *   user_id:int,
     *   raw_token:string,
     *   token_hash:string,
     *   expires_at:string,
     *   ttl_minutes:int
     * }|null
     */
    public static function issue(int $userId, int $ttlMinutes): ?array
    {
        if ($userId <= 0 || $ttlMinutes < 1) {
            return null;
        }

        $ttlMinutes = max(1, $ttlMinutes);

        try {
            $rawToken = bin2hex(random_bytes(32));
        } catch (\Throwable) {
            return null;
        }

        $tokenHash = hash('sha256', $rawToken);
        if (!preg_match('/^[a-f0-9]{64}$/', $tokenHash)) {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT INTO password_reset_tokens (
                user_id,
                token_hash,
                expires_at,
                used_at
            ) VALUES (
                :user_id,
                :token_hash,
                DATE_ADD(NOW(), INTERVAL {$ttlMinutes} MINUTE),
                NULL
            )"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':token_hash', $tokenHash);

        if (!$stmt->execute()) {
            return null;
        }

        $id = (int) $db->lastInsertId();
        if ($id <= 0) {
            return null;
        }

        $row = self::findById($id);
        if ($row === null) {
            return null;
        }

        return [
            'token_id' => (int) $row['id'],
            'user_id' => (int) $row['user_id'],
            'raw_token' => $rawToken,
            'token_hash' => (string) $row['token_hash'],
            'expires_at' => (string) $row['expires_at'],
            'ttl_minutes' => $ttlMinutes,
        ];
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
            "UPDATE password_reset_tokens
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
     * Hash a raw 64-character hex token. Returns null when the input is not a
     * well-formed bearer token. Never logs the raw value.
     */
    public static function hashRawToken(string $rawToken): ?string
    {
        $rawToken = strtolower(trim($rawToken));
        if (!preg_match('/^[a-f0-9]{64}$/', $rawToken)) {
            return null;
        }

        $tokenHash = hash('sha256', $rawToken);

        return preg_match('/^[a-f0-9]{64}$/', $tokenHash) === 1 ? $tokenHash : null;
    }

    /**
     * Load a still-usable reset token from a raw bearer token.
     *
     * @return array<string, mixed>|null
     */
    public static function findValidResetByRawToken(string $rawToken, bool $forUpdate = false): ?array
    {
        $tokenHash = self::hashRawToken($rawToken);
        if ($tokenHash === null) {
            return null;
        }

        return self::findValidResetByHash($tokenHash, $forUpdate);
    }

    /**
     * Load a still-usable reset by stored hash. Optional row lock for reset POST.
     * Callers that pass $forUpdate = true must already be inside a transaction.
     *
     * @return array<string, mixed>|null
     */
    public static function findValidResetByHash(string $tokenHash, bool $forUpdate = false): ?array
    {
        $hash = strtolower(trim($tokenHash));
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            return null;
        }

        $db = Database::getInstance();
        $sql = "SELECT
                t.id AS token_id,
                t.user_id,
                t.token_hash,
                users.full_name
            FROM password_reset_tokens t
            INNER JOIN users ON users.id = t.user_id
            WHERE t.token_hash = :token_hash
              AND t.used_at IS NULL
              AND t.expires_at > NOW()
              AND users.status = 'active'
              AND users.password IS NOT NULL
              AND users.password <> ''
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

    /**
     * Mark a still-usable token as used. Intended for use inside a transaction
     * after findValidResetByHash(..., true). Fails closed when the row was
     * already claimed or has expired.
     */
    public static function claimUsable(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE password_reset_tokens
            SET used_at = NOW()
            WHERE id = :id
              AND used_at IS NULL
              AND expires_at > NOW()"
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() === 1;
    }

    /**
     * True when this user already has a reset-token row created inside the
     * cooldown window. Counts all recent rows, including used and expired.
     */
    public static function hasRecentRequest(int $userId, int $cooldownMinutes): bool
    {
        if ($userId <= 0 || $cooldownMinutes < 1) {
            return false;
        }

        $cooldownMinutes = max(1, $cooldownMinutes);

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT COUNT(*)
            FROM password_reset_tokens
            WHERE user_id = :user_id
              AND created_at > DATE_SUB(NOW(), INTERVAL {$cooldownMinutes} MINUTE)"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn() > 0;
    }
}
