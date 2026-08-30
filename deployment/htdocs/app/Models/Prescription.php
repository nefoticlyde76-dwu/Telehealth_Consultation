<?php

namespace App\Models;

use App\Core\Database;
use PDO;
use PDOException;

class Prescription
{
    public const MAX_TEXT = 255;
    public const MAX_NOTES = 2000;
    public const MAX_LINES = 12;

    /**
     * @return list<array<string, mixed>>
     */
    public static function findByRecordForDoctor(int $consultationRecordId, int $doctorId): array
    {
        if ($consultationRecordId <= 0 || $doctorId <= 0) {
            return [];
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT *
               FROM prescriptions
              WHERE consultation_record_id = :consultation_record_id
                AND doctor_id = :doctor_id
              ORDER BY id ASC"
        );
        $stmt->bindValue(':consultation_record_id', $consultationRecordId, PDO::PARAM_INT);
        $stmt->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function findByRecordForPatient(int $consultationRecordId, int $patientId): array
    {
        if ($consultationRecordId <= 0 || $patientId <= 0) {
            return [];
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT *
               FROM prescriptions
              WHERE consultation_record_id = :consultation_record_id
                AND patient_id = :patient_id
              ORDER BY id ASC"
        );
        $stmt->bindValue(':consultation_record_id', $consultationRecordId, PDO::PARAM_INT);
        $stmt->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param list<array<string, mixed>> $medications
     * @return array{success:bool,message:string,type:string,prescriptions?:list<array<string,mixed>>}
     */
    public static function createForCompletedConsultation(
        int $consultationRecordId,
        int $doctorId,
        int $patientId,
        array $medications
    ): array {
        if ($consultationRecordId <= 0 || $doctorId <= 0 || $patientId <= 0) {
            return [
                'success' => false,
                'message' => 'The prescription could not be linked to this consultation.',
                'type' => 'danger',
            ];
        }

        $lines = self::normalizeMedications($medications);
        if ($lines === []) {
            return [
                'success' => false,
                'message' => 'Enter at least one medication with name, dosage, and frequency.',
                'type' => 'warning',
            ];
        }

        $db = Database::getInstance();
        $ownsTransaction = !$db->inTransaction();

        try {
            if ($ownsTransaction) {
                $db->beginTransaction();
            }

            $existing = $db->prepare(
                "SELECT id
                   FROM prescriptions
                  WHERE consultation_record_id = :consultation_record_id
                  LIMIT 1
                  FOR UPDATE"
            );
            $existing->bindValue(':consultation_record_id', $consultationRecordId, PDO::PARAM_INT);
            $existing->execute();
            if ($existing->fetch(PDO::FETCH_ASSOC)) {
                if ($ownsTransaction && $db->inTransaction()) {
                    $db->rollBack();
                }
                return [
                    'success' => false,
                    'message' => 'A prescription has already been issued for this consultation.',
                    'type' => 'warning',
                ];
            }

            $insert = $db->prepare(
                "INSERT INTO prescriptions (
                    consultation_record_id,
                    doctor_id,
                    patient_id,
                    medication_name,
                    dosage,
                    frequency,
                    duration,
                    quantity,
                    additional_notes,
                    issued_date
                 ) VALUES (
                    :consultation_record_id,
                    :doctor_id,
                    :patient_id,
                    :medication_name,
                    :dosage,
                    :frequency,
                    :duration,
                    :quantity,
                    :additional_notes,
                    NOW()
                 )"
            );

            foreach ($lines as $line) {
                $insert->bindValue(':consultation_record_id', $consultationRecordId, PDO::PARAM_INT);
                $insert->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
                $insert->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
                $insert->bindValue(':medication_name', $line['medication_name']);
                $insert->bindValue(':dosage', $line['dosage']);
                $insert->bindValue(':frequency', $line['frequency']);
                $insert->bindValue(':duration', $line['duration']);
                $insert->bindValue(':quantity', $line['quantity'] !== '' ? $line['quantity'] : null);
                $insert->bindValue(':additional_notes', $line['additional_notes'] !== '' ? $line['additional_notes'] : null);
                $insert->execute();
            }

            if ($ownsTransaction) {
                $db->commit();
            }
        } catch (PDOException $e) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[Prescription::createForCompletedConsultation] ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'The prescription could not be saved right now.',
                'type' => 'danger',
            ];
        }

        return [
            'success' => true,
            'message' => 'Prescription issued and linked to this consultation.',
            'type' => 'success',
            'prescriptions' => self::findByRecordForDoctor($consultationRecordId, $doctorId),
        ];
    }

    /**
     * @param list<array<string, mixed>> $medications
     * @return list<array{medication_name:string,dosage:string,frequency:string,duration:string,quantity:string,additional_notes:string}>
     */
    public static function normalizeMedications(array $medications): array
    {
        $lines = [];
        foreach ($medications as $medication) {
            if (count($lines) >= self::MAX_LINES) {
                break;
            }
            if (!is_array($medication)) {
                continue;
            }
            $name = self::clip(trim((string) ($medication['medication_name'] ?? '')), self::MAX_TEXT);
            $dosage = self::clip(trim((string) ($medication['dosage'] ?? '')), self::MAX_TEXT);
            $frequency = self::clip(trim((string) ($medication['frequency'] ?? '')), self::MAX_TEXT);
            if ($name === '' && $dosage === '' && $frequency === '') {
                continue;
            }
            if ($name === '' || $dosage === '' || $frequency === '') {
                continue;
            }
            $lines[] = [
                'medication_name' => $name,
                'dosage' => $dosage,
                'frequency' => $frequency,
                'duration' => self::clip(trim((string) ($medication['duration'] ?? '')), self::MAX_TEXT),
                'quantity' => self::clip(trim((string) ($medication['quantity'] ?? '')), 100),
                'additional_notes' => self::clip(trim((string) ($medication['additional_notes'] ?? '')), self::MAX_NOTES),
            ];
        }

        return $lines;
    }

    private static function clip(string $value, int $max): string
    {
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($length <= $max) {
            return $value;
        }

        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }
}
