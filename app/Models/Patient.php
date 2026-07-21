<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Patient
{
    public ?int $user_id = null;
    public ?string $dob = null;
    public ?string $gender = null;
    public ?string $address = null;
    public ?string $medical_history = null;

    public static function findByUserId(int $userId): ?self
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM patient WHERE user_id = :user_id LIMIT 1");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $patient = new self();
            $patient->user_id = $row['user_id'];
            $patient->dob = $row['dob'];
            $patient->gender = $row['gender'];
            $patient->address = $row['address'];
            $patient->medical_history = $row['medical_history'];
            return $patient;
        }

        return null;
    }

    public function save(): bool
    {
        $db = Database::getInstance();

        if (self::findByUserId($this->user_id)) {
            $stmt = $db->prepare("UPDATE patient SET dob = :dob, gender = :gender, address = :address, medical_history = :medical_history WHERE user_id = :user_id");
        } else {
            $stmt = $db->prepare("INSERT INTO patient (user_id, dob, gender, address, medical_history) VALUES (:user_id, :dob, :gender, :address, :medical_history)");
        }

        $stmt->bindParam(':user_id', $this->user_id, PDO::PARAM_INT);
        $stmt->bindParam(':dob', $this->dob);
        $stmt->bindParam(':gender', $this->gender);
        $stmt->bindParam(':address', $this->address);
        $stmt->bindParam(':medical_history', $this->medical_history);

        return $stmt->execute();
    }
}
