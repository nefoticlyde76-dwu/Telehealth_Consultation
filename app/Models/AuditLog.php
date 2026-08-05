<?php

namespace App\Models;

use App\Core\Database;

class AuditLog
{
    public static function create(array $data): bool
    {
        $db = Database::getInstance();
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
            ':action' => $data['action'] ?? '',
            ':subject_name' => $data['subject_name'] ?? '',
            ':subject_role' => $data['subject_role'] ?? '',
            ':description' => $data['description'] ?? null,
        ]);
    }
}
