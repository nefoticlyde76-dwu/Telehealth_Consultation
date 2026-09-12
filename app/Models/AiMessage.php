<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * One MediMate turn. Always loaded through an owned conversation.
 */
class AiMessage
{
    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    /**
     * @return list<array<string, mixed>>
     */
    public static function findForOwnedConversation(int $conversationId, int $patientId): array
    {
        if ($conversationId <= 0 || $patientId <= 0) {
            return [];
        }

        $stmt = Database::getInstance()->prepare(
            'SELECT m.id, m.conversation_id, m.role, m.message, m.created_at
             FROM ai_messages m
             INNER JOIN ai_conversations c
                     ON c.id = m.conversation_id
                    AND c.patient_id = :patient_id
             WHERE m.conversation_id = :conversation_id
             ORDER BY m.created_at ASC, m.id ASC'
        );
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Recent turns for Gemini context. Oldest first.
     *
     * @return list<array<string, mixed>>
     */
    public static function findRecentForOwnedConversation(int $conversationId, int $patientId, int $limit): array
    {
        if ($conversationId <= 0 || $patientId <= 0) {
            return [];
        }

        $limit = max(1, min(80, $limit));
        $stmt = Database::getInstance()->prepare(
            'SELECT m.id, m.conversation_id, m.role, m.message, m.created_at
             FROM ai_messages m
             INNER JOIN ai_conversations c
                     ON c.id = m.conversation_id
                    AND c.patient_id = :patient_id
             WHERE m.conversation_id = :conversation_id
             ORDER BY m.created_at DESC, m.id DESC
             LIMIT ' . $limit
        );
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows) || $rows === []) {
            return [];
        }

        return array_values(array_reverse($rows));
    }

    public static function createForOwnedConversation(int $conversationId, int $patientId, string $role, string $message): int
    {
        if ($conversationId <= 0 || $patientId <= 0 || $message === '') {
            return 0;
        }
        if (!in_array($role, [self::ROLE_USER, self::ROLE_ASSISTANT], true)) {
            return 0;
        }

        $owned = AiConversation::findOwned($conversationId, $patientId);
        if ($owned === null) {
            return 0;
        }

        $stmt = Database::getInstance()->prepare(
            'INSERT INTO ai_messages (conversation_id, role, message, created_at)
             VALUES (:conversation_id, :role, :message, NOW())'
        );
        $stmt->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);
        $stmt->bindValue(':role', $role);
        $stmt->bindValue(':message', $message);
        $stmt->execute();

        $id = (int) Database::getInstance()->lastInsertId();
        if ($id > 0) {
            AiConversation::touch($conversationId, $patientId);
        }

        return $id;
    }
}
