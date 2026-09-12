<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Patient-owned MediMate conversation. Not a clinical record.
 */
class AiConversation
{
    /**
     * @return array<string, mixed>|null
     */
    public static function findOwned(int $conversationId, int $patientId): ?array
    {
        if ($conversationId <= 0 || $patientId <= 0) {
            return null;
        }

        $stmt = Database::getInstance()->prepare(
            'SELECT id, patient_id, title, summary, created_at, updated_at
             FROM ai_conversations
             WHERE id = :id
               AND patient_id = :patient_id
             LIMIT 1'
        );
        $stmt->bindValue(':id', $conversationId, PDO::PARAM_INT);
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findLatestForPatient(int $patientId): ?array
    {
        if ($patientId <= 0) {
            return null;
        }

        $stmt = Database::getInstance()->prepare(
            'SELECT id, patient_id, title, summary, created_at, updated_at
             FROM ai_conversations
             WHERE patient_id = :patient_id
             ORDER BY updated_at DESC, id DESC
             LIMIT 1'
        );
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForPatient(int $patientId, int $limit = 40): array
    {
        if ($patientId <= 0) {
            return [];
        }

        $limit = max(1, min(80, $limit));
        $stmt = Database::getInstance()->prepare(
            'SELECT id, patient_id, title, created_at, updated_at
             FROM ai_conversations
             WHERE patient_id = :patient_id
             ORDER BY updated_at DESC, id DESC
             LIMIT ' . $limit
        );
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public static function create(int $patientId, string $title = ''): int
    {
        if ($patientId <= 0) {
            return 0;
        }

        $stmt = Database::getInstance()->prepare(
            'INSERT INTO ai_conversations (patient_id, title, created_at, updated_at)
             VALUES (:patient_id, :title, NOW(), NOW())'
        );
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->bindValue(':title', $title);
        $stmt->execute();

        return (int) Database::getInstance()->lastInsertId();
    }

    public static function updateTitle(int $conversationId, int $patientId, string $title): bool
    {
        if ($conversationId <= 0 || $patientId <= 0 || $title === '') {
            return false;
        }

        $stmt = Database::getInstance()->prepare(
            'UPDATE ai_conversations
             SET title = :title, updated_at = NOW()
             WHERE id = :id
               AND patient_id = :patient_id'
        );
        $stmt->bindValue(':title', $title);
        $stmt->bindValue(':id', $conversationId, PDO::PARAM_INT);
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public static function touch(int $conversationId, int $patientId): void
    {
        if ($conversationId <= 0 || $patientId <= 0) {
            return;
        }

        $stmt = Database::getInstance()->prepare(
            'UPDATE ai_conversations
             SET updated_at = NOW()
             WHERE id = :id
               AND patient_id = :patient_id'
        );
        $stmt->bindValue(':id', $conversationId, PDO::PARAM_INT);
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public static function deleteOwned(int $conversationId, int $patientId): bool
    {
        if ($conversationId <= 0 || $patientId <= 0) {
            return false;
        }

        $stmt = Database::getInstance()->prepare(
            'DELETE FROM ai_conversations
             WHERE id = :id
               AND patient_id = :patient_id'
        );
        $stmt->bindValue(':id', $conversationId, PDO::PARAM_INT);
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }
}
