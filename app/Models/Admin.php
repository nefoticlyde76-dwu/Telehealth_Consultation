<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Admin
{
    public ?int $user_id = null;
    public ?string $employee_id = null;

    public static function findByUserId(int $userId): ?self
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM admin WHERE user_id = :user_id LIMIT 1");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return self::fromArray($row);
        }

        return null;
    }

    public static function fromArray(array $row): self
    {
        $admin = new self();
        $admin->user_id = isset($row['user_id']) ? (int) $row['user_id'] : null;
        $admin->employee_id = $row['employee_id'] ?? null;

        return $admin;
    }

    public function save(): bool
    {
        if ($this->user_id === null) {
            return false;
        }

        $db = Database::getInstance();

        if (self::findByUserId($this->user_id) !== null) {
            $stmt = $db->prepare(
                "UPDATE admin
                SET employee_id = :employee_id
                WHERE user_id = :user_id"
            );
        } else {
            $stmt = $db->prepare(
                "INSERT INTO admin (user_id, employee_id)
                VALUES (:user_id, :employee_id)"
            );
        }

        return $stmt->execute([
            ':user_id' => $this->user_id,
            ':employee_id' => $this->employee_id,
        ]);
    }

    public static function findByEmployeeId(string $employeeId, ?int $excludeUserId = null): ?self
    {
        $employeeId = trim($employeeId);

        if ($employeeId === '') {
            return null;
        }

        $db = Database::getInstance();
        $sql = "SELECT * FROM admin WHERE employee_id = :employee_id";

        if ($excludeUserId !== null) {
            $sql .= " AND user_id != :exclude_user_id";
        }

        $sql .= " LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':employee_id', $employeeId);

        if ($excludeUserId !== null) {
            $stmt->bindValue(':exclude_user_id', $excludeUserId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::fromArray($row) : null;
    }

    public static function findProfileDetailByUserId(int $userId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT
                users.id,
                users.full_name,
                users.email,
                users.status,
                users.created_at,
                users.updated_at,
                admin.employee_id
            FROM admin
            INNER JOIN users ON users.id = admin.user_id
            INNER JOIN roles ON roles.id = users.role_id
            WHERE roles.name = 'admin' AND users.id = :user_id
            LIMIT 1"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
