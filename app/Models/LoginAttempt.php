<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Persistence for login_attempts.
 * One row per email + IP. Unknown emails are stored so lockout cannot enumerate accounts.
 */
class LoginAttempt
{
    /**
     * @return array<string, mixed>|null
     */
    public static function findByEmailAndIp(string $email, string $ip, bool $forUpdate = false): ?array
    {
        $db = Database::getInstance();
        $sql = "SELECT
                id,
                email,
                ip_address,
                failed_count,
                window_started_at,
                last_failed_at,
                locked_until,
                created_at
            FROM login_attempts
            WHERE email = :email
              AND ip_address = :ip_address
            LIMIT 1";

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':ip_address', $ip);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array{
     *   email:string,
     *   ip_address:string,
     *   failed_count:int,
     *   window_started_at:string,
     *   last_failed_at:string,
     *   locked_until:?string
     * } $data
     */
    public static function upsert(array $data): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT INTO login_attempts (
                email,
                ip_address,
                failed_count,
                window_started_at,
                last_failed_at,
                locked_until
            ) VALUES (
                :email,
                :ip_address,
                :failed_count,
                :window_started_at,
                :last_failed_at,
                :locked_until
            )
            ON DUPLICATE KEY UPDATE
                failed_count = VALUES(failed_count),
                window_started_at = VALUES(window_started_at),
                last_failed_at = VALUES(last_failed_at),
                locked_until = VALUES(locked_until)"
        );

        $stmt->bindValue(':email', $data['email']);
        $stmt->bindValue(':ip_address', $data['ip_address']);
        $stmt->bindValue(':failed_count', (int) $data['failed_count'], PDO::PARAM_INT);
        $stmt->bindValue(':window_started_at', $data['window_started_at']);
        $stmt->bindValue(':last_failed_at', $data['last_failed_at']);
        if ($data['locked_until'] === null || $data['locked_until'] === '') {
            $stmt->bindValue(':locked_until', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':locked_until', $data['locked_until']);
        }

        return $stmt->execute();
    }

    public static function deleteByEmailAndIp(string $email, string $ip): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "DELETE FROM login_attempts
            WHERE email = :email
              AND ip_address = :ip_address"
        );
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':ip_address', $ip);
        $stmt->execute();
    }
}
