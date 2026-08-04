<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class ConsultationRecord
{
    public static function countForPatient(int $patientId): int
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT COUNT(*) FROM consultation_records WHERE patient_id = :patient_id");
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}

