<?php

namespace App\Models;

use App\Core\Database;

class AuditLog
{
    /**
     * @var array<string,bool>|null
     */
    private static ?array $columnMap = null;

    public static function create(array $data): bool
    {
        $db = Database::getInstance();
        if (!self::hasColumn('event_type')) {
            $stmt = $db->prepare(
                "INSERT INTO audit_logs (
                    actor_user_id,
                    actor_name,
                    action,
                    subject_name,
                    subject_role,
                    description
                ) VALUES (
                    :actor_user_id,
                    :actor_name,
                    :action,
                    :subject_name,
                    :subject_role,
                    :description
                )"
            );

            return $stmt->execute([
                ':actor_user_id' => $data['actor_user_id'] ?? null,
                ':actor_name' => $data['actor_name'] ?? '',
                ':action' => $data['action'] ?? ($data['event_type'] ?? ''),
                ':subject_name' => $data['subject_name'] ?? '',
                ':subject_role' => $data['subject_role'] ?? '',
                ':description' => $data['description'] ?? null,
            ]);
        }

        $stmt = $db->prepare(
            "INSERT INTO audit_logs (
                actor_user_id,
                actor_name,
                actor_role,
                action,
                event_type,
                event_label,
                event_category,
                severity,
                subject_name,
                subject_role,
                entity_type,
                entity_id,
                description,
                outcome
            ) VALUES (
                :actor_user_id,
                :actor_name,
                :actor_role,
                :action,
                :event_type,
                :event_label,
                :event_category,
                :severity,
                :subject_name,
                :subject_role,
                :entity_type,
                :entity_id,
                :description,
                :outcome
            )"
        );

        return $stmt->execute([
            ':actor_user_id' => $data['actor_user_id'] ?? null,
            ':actor_name' => $data['actor_name'] ?? '',
            ':actor_role' => $data['actor_role'] ?? null,
            ':action' => $data['action'] ?? ($data['event_type'] ?? ''),
            ':event_type' => $data['event_type'] ?? ($data['action'] ?? ''),
            ':event_label' => $data['event_label'] ?? 'System Event',
            ':event_category' => $data['event_category'] ?? 'system',
            ':severity' => $data['severity'] ?? 'info',
            ':subject_name' => $data['subject_name'] ?? '',
            ':subject_role' => $data['subject_role'] ?? '',
            ':entity_type' => $data['entity_type'] ?? '',
            ':entity_id' => $data['entity_id'] ?? null,
            ':description' => $data['description'] ?? null,
            ':outcome' => $data['outcome'] ?? 'success',
        ]);
    }

    public static function countForAdmin(array $filters = []): int
    {
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) FROM audit_logs LEFT JOIN users ON users.id = audit_logs.actor_user_id";
        [$conditions, $parameters] = self::buildFilters($filters);
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }

        $stmt = $db->prepare($sql);
        self::bind($stmt, $parameters);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public static function findForAdmin(array $filters = [], int $limit = 25, int $offset = 0): array
    {
        $db = Database::getInstance();
        $sql = "SELECT
                audit_logs.id,
                audit_logs.actor_user_id,
                audit_logs.actor_name,
                " . (self::hasColumn('actor_role') ? "audit_logs.actor_role" : "audit_logs.subject_role") . " AS actor_role,
                audit_logs.action,
                " . (self::hasColumn('event_type') ? "audit_logs.event_type" : "audit_logs.action") . " AS event_type,
                " . (self::hasColumn('event_label') ? "audit_logs.event_label" : "audit_logs.action") . " AS event_label,
                " . (self::hasColumn('event_category') ? "audit_logs.event_category" : "'system'") . " AS event_category,
                " . (self::hasColumn('severity') ? "audit_logs.severity" : "'info'") . " AS severity,
                audit_logs.subject_name,
                audit_logs.subject_role,
                " . (self::hasColumn('entity_type') ? "audit_logs.entity_type" : "''") . " AS entity_type,
                " . (self::hasColumn('entity_id') ? "audit_logs.entity_id" : "NULL") . " AS entity_id,
                audit_logs.description,
                " . (self::hasColumn('outcome') ? "audit_logs.outcome" : "'success'") . " AS outcome,
                audit_logs.created_at,
                users.email AS actor_email,
                users.last_login_at AS actor_last_login_at
            FROM audit_logs
            LEFT JOIN users ON users.id = audit_logs.actor_user_id";
        [$conditions, $parameters] = self::buildFilters($filters);
        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sort = (string) ($filters['sort'] ?? 'newest');
        $sql .= $sort === 'oldest'
            ? ' ORDER BY audit_logs.created_at ASC, audit_logs.id ASC'
            : ' ORDER BY audit_logs.created_at DESC, audit_logs.id DESC';
        $sql .= ' LIMIT :limit OFFSET :offset';

        $stmt = $db->prepare($sql);
        self::bind($stmt, $parameters);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public static function findByIdForAdmin(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                audit_logs.id,
                audit_logs.actor_user_id,
                audit_logs.actor_name,
                " . (self::hasColumn('actor_role') ? "audit_logs.actor_role" : "audit_logs.subject_role") . " AS actor_role,
                audit_logs.action,
                " . (self::hasColumn('event_type') ? "audit_logs.event_type" : "audit_logs.action") . " AS event_type,
                " . (self::hasColumn('event_label') ? "audit_logs.event_label" : "audit_logs.action") . " AS event_label,
                " . (self::hasColumn('event_category') ? "audit_logs.event_category" : "'system'") . " AS event_category,
                " . (self::hasColumn('severity') ? "audit_logs.severity" : "'info'") . " AS severity,
                audit_logs.subject_name,
                audit_logs.subject_role,
                " . (self::hasColumn('entity_type') ? "audit_logs.entity_type" : "''") . " AS entity_type,
                " . (self::hasColumn('entity_id') ? "audit_logs.entity_id" : "NULL") . " AS entity_id,
                audit_logs.description,
                " . (self::hasColumn('outcome') ? "audit_logs.outcome" : "'success'") . " AS outcome,
                audit_logs.created_at,
                users.email AS actor_email
             FROM audit_logs
             LEFT JOIN users ON users.id = audit_logs.actor_user_id
             WHERE audit_logs.id = :id
             LIMIT 1"
        );
        $stmt->bindValue(':id', $id, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function buildFilters(array $filters): array
    {
        $conditions = [];
        $parameters = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $eventLabelColumn = self::hasColumn('event_label') ? 'audit_logs.event_label' : 'audit_logs.action';
            $entityExpr = self::hasColumn('entity_type')
                ? 'CONCAT(audit_logs.entity_type, " #", audit_logs.entity_id)'
                : 'audit_logs.subject_name';
            $conditions[] = '(audit_logs.actor_name LIKE :search_actor
                OR users.email LIKE :search_email
                OR ' . $eventLabelColumn . ' LIKE :search_event
                OR audit_logs.description LIKE :search_description
                OR ' . $entityExpr . ' LIKE :search_entity)';
            $like = \App\Helpers\ListFilter::likeContains($search);
            $parameters[':search_actor'] = $like;
            $parameters[':search_email'] = $like;
            $parameters[':search_event'] = $like;
            $parameters[':search_description'] = $like;
            $parameters[':search_entity'] = $like;
        }

        $action = trim((string) ($filters['action'] ?? ''));
        if ($action !== '') {
            $eventTypeColumn = self::hasColumn('event_type') ? 'audit_logs.event_type' : 'audit_logs.action';
            $conditions[] = $eventTypeColumn . ' = :event_type';
            $parameters[':event_type'] = $action;
        }

        $role = trim((string) ($filters['role'] ?? ''));
        if ($role !== '' && self::hasColumn('actor_role')) {
            $conditions[] = 'audit_logs.actor_role = :actor_role';
            $parameters[':actor_role'] = $role;
        }

        $userId = (int) ($filters['user_id'] ?? 0);
        if ($userId > 0) {
            $conditions[] = 'audit_logs.actor_user_id = :actor_user_id';
            $parameters[':actor_user_id'] = $userId;
        }

        $outcome = strtolower(trim((string) ($filters['outcome'] ?? '')));
        if (in_array($outcome, ['success', 'failed'], true) && self::hasColumn('outcome')) {
            $conditions[] = 'audit_logs.outcome = :outcome';
            $parameters[':outcome'] = $outcome;
        }

        $range = is_array($filters['date_range'] ?? null) ? $filters['date_range'] : [];
        if (($range['from'] ?? '') !== '' || ($range['to'] ?? '') !== '') {
            \App\Helpers\ListFilter::appendDateRange('DATE(audit_logs.created_at)', $range, $conditions, $parameters, 'audit_date');
        }

        return [$conditions, $parameters];
    }

    private static function hasColumn(string $column): bool
    {
        if (self::$columnMap === null) {
            self::$columnMap = [];
            try {
                $db = Database::getInstance();
                $stmt = $db->query('SHOW COLUMNS FROM audit_logs');
                foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
                    $name = (string) ($row['Field'] ?? '');
                    if ($name !== '') {
                        self::$columnMap[$name] = true;
                    }
                }
            } catch (\Throwable) {
                self::$columnMap = [];
            }
        }

        return isset(self::$columnMap[$column]);
    }

    private static function bind(\PDOStatement $stmt, array $parameters): void
    {
        foreach ($parameters as $name => $value) {
            $type = is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $stmt->bindValue($name, $value, $type);
        }
    }
}
