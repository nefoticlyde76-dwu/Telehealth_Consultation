<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Notification
{
    public const ENTITY_CONSULTATION_REQUEST = 'consultation_request';

    /**
     * Insert a notification once. Duplicate (user, type, entity) rows are ignored.
     *
     * @param array{
     *   user_id:int,
     *   notification_type:string,
     *   title:string,
     *   message:string,
     *   related_entity_type?:string,
     *   related_entity_id:int
     * } $data
     */
    public static function createOnce(array $data): int
    {
        $userId = (int) ($data['user_id'] ?? 0);
        $type = trim((string) ($data['notification_type'] ?? ''));
        $title = trim((string) ($data['title'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));
        $entityType = trim((string) ($data['related_entity_type'] ?? self::ENTITY_CONSULTATION_REQUEST));
        $entityId = (int) ($data['related_entity_id'] ?? 0);

        if ($userId <= 0 || $type === '' || $title === '' || $message === '' || $entityType === '' || $entityId <= 0) {
            return 0;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT IGNORE INTO notifications (
                user_id,
                notification_type,
                title,
                message,
                related_entity_type,
                related_entity_id,
                is_read,
                created_at
            ) VALUES (
                :user_id,
                :notification_type,
                :title,
                :message,
                :related_entity_type,
                :related_entity_id,
                0,
                NOW()
            )"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':notification_type', $type);
        $stmt->bindValue(':title', $title);
        $stmt->bindValue(':message', $message);
        $stmt->bindValue(':related_entity_type', $entityType);
        $stmt->bindValue(':related_entity_id', $entityId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() < 1) {
            return 0;
        }

        return (int) $db->lastInsertId();
    }

    public static function countForUser(int $userId, array $filters = []): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $db = Database::getInstance();
        $sql = 'SELECT COUNT(*) FROM notifications';
        $conditions = ['user_id = :user_id'];
        $parameters = [':user_id' => $userId];
        self::appendFilters($filters, $conditions, $parameters);
        $sql .= ' WHERE ' . implode(' AND ', $conditions);

        $stmt = $db->prepare($sql);
        self::bindFilterParameters($stmt, $parameters);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public static function countUnreadForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT COUNT(*)
            FROM notifications
            WHERE user_id = :user_id
              AND is_read = 0"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function findForUser(int $userId, array $filters = [], int $limit = 10, int $offset = 0): array
    {
        if ($userId <= 0) {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);
        $db = Database::getInstance();
        $sql = 'SELECT * FROM notifications';
        $conditions = ['user_id = :user_id'];
        $parameters = [':user_id' => $userId];
        self::appendFilters($filters, $conditions, $parameters);
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
        $sql .= ' ORDER BY is_read ASC, created_at DESC, id DESC LIMIT :limit OFFSET :offset';

        $stmt = $db->prepare($sql);
        self::bindFilterParameters($stmt, $parameters);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function findRecentForUser(int $userId, int $limit = 8): array
    {
        return self::findForUser($userId, [], $limit, 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findByIdForUser(int $notificationId, int $userId): ?array
    {
        if ($notificationId <= 0 || $userId <= 0) {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT *
            FROM notifications
            WHERE id = :id
              AND user_id = :user_id
            LIMIT 1"
        );
        $stmt->bindValue(':id', $notificationId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public static function markReadForUser(int $notificationId, int $userId): bool
    {
        if ($notificationId <= 0 || $userId <= 0) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE notifications
            SET is_read = 1,
                read_at = COALESCE(read_at, NOW())
            WHERE id = :id
              AND user_id = :user_id
              AND is_read = 0"
        );
        $stmt->bindValue(':id', $notificationId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public static function markUnreadForUser(int $notificationId, int $userId): bool
    {
        if ($notificationId <= 0 || $userId <= 0) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE notifications
            SET is_read = 0,
                read_at = NULL
            WHERE id = :id
              AND user_id = :user_id
              AND is_read = 1"
        );
        $stmt->bindValue(':id', $notificationId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public static function deleteForUser(int $notificationId, int $userId): bool
    {
        if ($notificationId <= 0 || $userId <= 0) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "DELETE FROM notifications
            WHERE id = :id
              AND user_id = :user_id"
        );
        $stmt->bindValue(':id', $notificationId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * @param list<int> $notificationIds
     */
    public static function deleteManyForUser(int $userId, array $notificationIds): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $ids = [];
        foreach ($notificationIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        $ids = array_values($ids);
        if ($ids === []) {
            return 0;
        }
        $ids = array_slice($ids, 0, 50);

        $placeholders = [];
        foreach ($ids as $index => $id) {
            $placeholders[] = ':id' . $index;
        }

        $db = Database::getInstance();
        $sql = 'DELETE FROM notifications WHERE user_id = :user_id AND id IN (' . implode(', ', $placeholders) . ')';
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        foreach ($ids as $index => $id) {
            $stmt->bindValue(':id' . $index, $id, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->rowCount();
    }

    public static function deleteAllForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM notifications WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public static function markAllReadForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "UPDATE notifications
            SET is_read = 1,
                read_at = COALESCE(read_at, NOW())
            WHERE user_id = :user_id
              AND is_read = 0"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public static function existsForUser(
        int $userId,
        string $type,
        int $relatedEntityId,
        string $relatedEntityType = self::ENTITY_CONSULTATION_REQUEST
    ): bool {
        if ($userId <= 0 || $type === '' || $relatedEntityId <= 0) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT id
            FROM notifications
            WHERE user_id = :user_id
              AND notification_type = :notification_type
              AND related_entity_type = :related_entity_type
              AND related_entity_id = :related_entity_id
            LIMIT 1"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':notification_type', $type);
        $stmt->bindValue(':related_entity_type', $relatedEntityType);
        $stmt->bindValue(':related_entity_id', $relatedEntityId, PDO::PARAM_INT);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $filters
     * @param list<string> $conditions
     * @param array<string, mixed> $parameters
     */
    private static function appendFilters(array $filters, array &$conditions, array &$parameters): void
    {
        $readState = trim((string) ($filters['read_state'] ?? ''));
        if ($readState === 'unread') {
            $conditions[] = 'is_read = 0';
        } elseif ($readState === 'read') {
            $conditions[] = 'is_read = 1';
        }

        $type = trim((string) ($filters['type'] ?? ''));
        if ($type !== '') {
            $conditions[] = 'notification_type = :notification_type';
            $parameters[':notification_type'] = $type;
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $conditions[] = '(title LIKE :search_title OR message LIKE :search_message)';
            $parameters[':search_title'] = '%' . $search . '%';
            $parameters[':search_message'] = '%' . $search . '%';
        }
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private static function bindFilterParameters(\PDOStatement $stmt, array $parameters): void
    {
        foreach ($parameters as $name => $value) {
            if ($name === ':user_id') {
                $stmt->bindValue($name, (int) $value, PDO::PARAM_INT);
                continue;
            }
            $stmt->bindValue($name, (string) $value, PDO::PARAM_STR);
        }
    }
}
