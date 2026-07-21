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
            $admin = new self();
            $admin->user_id = $row['user_id'];
            $admin->employee_id = $row['employee_id'];
            return $admin;
        }

        return null;
    }
}
