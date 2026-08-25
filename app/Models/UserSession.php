<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class UserSession
{
    /**
     * @param array{
     *   user_id:int,
     *   session_token_hash:string,
     *   ip_address?:string,
     *   user_agent?:string
     * } $data
     */
    public static function upsert(array $data): bool
    {
        $userId = (int) ($data['user_id'] ?? 0);
        $hash = strtolower(trim((string) ($data['session_token_hash'] ?? '')));
        if ($userId <= 0 || !preg_match('/^[a-f0-9]{64}$/', $hash)) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT INTO user_sessions (
                user_id,
                session_token_hash,
                ip_address,
                user_agent,
                last_seen_at
            ) VALUES (
                :user_id,
                :session_token_hash,
                :ip_address,
                :user_agent,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                ip_address = VALUES(ip_address),
                user_agent = VALUES(user_agent),
                last_seen_at = NOW()"
        );

        return $stmt->execute([
            ':user_id' => $userId,
            ':session_token_hash' => $hash,
            ':ip_address' => self::nullableString($data['ip_address'] ?? null, 45),
            ':user_agent' => self::nullableString($data['user_agent'] ?? null, 255),
        ]);
    }

    public static function existsForUser(int $userId, string $hash): bool
    {
        if ($userId <= 0 || $hash === '') {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT id
            FROM user_sessions
            WHERE user_id = :user_id
              AND session_token_hash = :hash
            LIMIT 1"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':hash', $hash);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    public static function touch(int $userId, string $hash): bool
    {
        if ($userId <= 0 || $hash === '') {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE user_sessions
            SET last_seen_at = NOW()
            WHERE user_id = :user_id
              AND session_token_hash = :hash"
        );

        return $stmt->execute([
            ':user_id' => $userId,
            ':hash' => $hash,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function findForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT id, user_id, session_token_hash, ip_address, user_agent, last_seen_at, created_at
            FROM user_sessions
            WHERE user_id = :user_id
            ORDER BY last_seen_at DESC, id DESC"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function countForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT COUNT(*) FROM user_sessions WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function deleteOthersForUser(int $userId, string $keepHash): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "DELETE FROM user_sessions
            WHERE user_id = :user_id
              AND session_token_hash <> :hash"
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':hash' => $keepHash,
        ]);

        return $stmt->rowCount();
    }

    public static function deleteAllForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM user_sessions WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public static function deleteCurrent(int $userId, string $hash): bool
    {
        if ($userId <= 0 || $hash === '') {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "DELETE FROM user_sessions
            WHERE user_id = :user_id
              AND session_token_hash = :hash"
        );

        return $stmt->execute([
            ':user_id' => $userId,
            ':hash' => $hash,
        ]);
    }

    private static function nullableString(mixed $value, int $max): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $max);
    }
}
