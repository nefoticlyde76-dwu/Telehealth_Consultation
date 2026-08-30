<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class NotificationPreference
{
    /**
     * @return array{
     *   user_id:int,
     *   appointment_in_app:int,
     *   consultation_in_app:int,
     *   email_enabled:int,
     *   sms_enabled:int
     * }
     */
    public static function defaults(int $userId): array
    {
        return [
            'user_id' => $userId,
            'appointment_in_app' => 1,
            'consultation_in_app' => 1,
            'email_enabled' => 1,
            'sms_enabled' => 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function findOrDefault(int $userId): array
    {
        $defaults = self::defaults($userId);
        if ($userId <= 0) {
            return $defaults;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT user_id, appointment_in_app, consultation_in_app, email_enabled, sms_enabled
            FROM notification_preferences
            WHERE user_id = :user_id
            LIMIT 1"
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return $defaults;
        }

        return [
            'user_id' => $userId,
            'appointment_in_app' => (int) ($row['appointment_in_app'] ?? 1),
            'consultation_in_app' => (int) ($row['consultation_in_app'] ?? 1),
            'email_enabled' => (int) ($row['email_enabled'] ?? 1),
            'sms_enabled' => (int) ($row['sms_enabled'] ?? 0),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function upsert(int $userId, array $data): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "INSERT INTO notification_preferences (
                user_id,
                appointment_in_app,
                consultation_in_app,
                email_enabled,
                sms_enabled
            ) VALUES (
                :user_id,
                :appointment_in_app,
                :consultation_in_app,
                :email_enabled,
                :sms_enabled
            )
            ON DUPLICATE KEY UPDATE
                appointment_in_app = VALUES(appointment_in_app),
                consultation_in_app = VALUES(consultation_in_app),
                email_enabled = VALUES(email_enabled),
                sms_enabled = VALUES(sms_enabled)"
        );

        return $stmt->execute([
            ':user_id' => $userId,
            ':appointment_in_app' => !empty($data['appointment_in_app']) ? 1 : 0,
            ':consultation_in_app' => !empty($data['consultation_in_app']) ? 1 : 0,
            ':email_enabled' => !empty($data['email_enabled']) ? 1 : 0,
            ':sms_enabled' => !empty($data['sms_enabled']) ? 1 : 0,
        ]);
    }

    public static function deleteForUser(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM notification_preferences WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }
}
