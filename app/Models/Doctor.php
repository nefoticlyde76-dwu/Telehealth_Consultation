<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class Doctor
{
    public ?int $user_id = null;
    public ?string $specialization = null;
    public ?string $license_number = null;
    public ?string $clinic_address = null;
    public ?float $consultation_fee = null;
    public ?string $signature_path = null;
    public ?string $consent_doc_path = null;

    public static function findByUserId(int $userId): ?self
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM doctor WHERE user_id = :user_id LIMIT 1");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $doctor = new self();
            $doctor->user_id = $row['user_id'];
            $doctor->specialization = $row['specialization'];
            $doctor->license_number = $row['license_number'];
            $doctor->clinic_address = $row['clinic_address'];
            $doctor->consultation_fee = $row['consultation_fee'];
            $doctor->signature_path = $row['signature_path'];
            $doctor->consent_doc_path = $row['consent_doc_path'];
            return $doctor;
        }

        return null;
    }
}
