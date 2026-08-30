<?php

/**
 * Week 7 Day 3 checks: schema, authorization, and model guards.
 *
 * Usage: php bin/test_week7_day3.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use App\Models\ConsultationRecord;
use App\Models\Prescription;
use App\Services\DoctorClinicalDocumentationService;
use App\Services\DoctorConsultationService;
use App\Services\DoctorPrescriptionService;
use App\Services\PatientClinicalRecordService;

$root = dirname(__DIR__);
Environment::load($root . '/.env');
date_default_timezone_set('Pacific/Port_Moresby');
Session::start();

$failed = 0;
$passed = 0;

function expect_true(bool $condition, string $label): void
{
    global $failed, $passed;
    if ($condition) {
        $passed++;
        echo "PASS  {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL  {$label}\n";
}

$db = Database::getInstance();

$completedAt = (int) $db->query(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'consultation_requests'
        AND COLUMN_NAME = 'completed_at'"
)->fetchColumn();
$finalizedAt = (int) $db->query(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'consultation_records'
        AND COLUMN_NAME = 'finalized_at'"
)->fetchColumn();
$quantity = (int) $db->query(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'prescriptions'
        AND COLUMN_NAME = 'quantity'"
)->fetchColumn();
$patientAddress = (int) $db->query(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'patient'
        AND COLUMN_NAME = 'address'"
)->fetchColumn();
$signaturePath = (int) $db->query(
    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'doctor'
        AND COLUMN_NAME = 'signature_path'"
)->fetchColumn();

expect_true($completedAt === 1, 'consultation_requests.completed_at exists');
expect_true($finalizedAt === 1, 'consultation_records.finalized_at exists');
expect_true($quantity === 1, 'prescriptions.quantity exists');
expect_true($patientAddress === 1, 'patient.address already exists (no duplicate address field)');
expect_true($signaturePath === 1, 'doctor.signature_path already exists (no duplicate signature store)');

$missing = ConsultationRecord::missingRequiredFields([
    'chief_complaint' => '',
    'symptoms' => 'cough',
    'clinical_findings' => 'clear',
    'diagnosis' => 'uri',
    'treatment_plan' => 'rest',
]);
expect_true($missing === ['Chief complaint / presenting problem'], 'Required clinical field validation reports missing chief complaint');

$lines = Prescription::normalizeMedications([
    ['medication_name' => 'Amoxicillin', 'dosage' => '500 mg', 'frequency' => 'TDS', 'duration' => '5 days', 'quantity' => '15', 'additional_notes' => 'After food'],
    ['medication_name' => '', 'dosage' => '', 'frequency' => ''],
    'not-an-array',
]);
expect_true(count($lines) === 1 && $lines[0]['medication_name'] === 'Amoxicillin', 'Medication normalization keeps complete lines only');

$unconfirmed = DoctorClinicalDocumentationService::completeConsultation(1, 1, 'invalid-token', [], false);
expect_true(($unconfirmed['success'] ?? true) === false, 'Complete consultation rejects missing confirmation and invalid CSRF');

$legacyComplete = DoctorConsultationService::completeConsultation(1, 1, 'invalid-token');
expect_true(($legacyComplete['success'] ?? true) === false, 'Legacy list-complete path cannot mark a consultation Completed');

$rxUnauthorized = DoctorPrescriptionService::getPrescriptionPageForDoctor(999999, 1);
expect_true($rxUnauthorized === null, 'Another doctor cannot load a prescription page by guessing an ID');

$patientUnauthorized = PatientClinicalRecordService::getCompletedRecordForPatient(999999, 1);
expect_true($patientUnauthorized === null, 'Another patient cannot load a completed record by guessing an ID');

$orphan = Prescription::createForCompletedConsultation(0, 1, 1, [
    ['medication_name' => 'Amoxicillin', 'dosage' => '500 mg', 'frequency' => 'TDS'],
]);
expect_true(($orphan['success'] ?? true) === false, 'Orphan prescriptions without a consultation record are rejected');

$wrongDoctorLines = Prescription::findByRecordForDoctor(1, 999999);
expect_true($wrongDoctorLines === [], 'Doctor cannot read another doctor\'s prescription lines');

$wrongPatientLines = Prescription::findByRecordForPatient(1, 999999);
expect_true($wrongPatientLines === [], 'Patient cannot read another patient\'s prescription lines');

runLiveWorkflow($db);

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);

function runLiveWorkflow(PDO $db): void
{
    $doctor = $db->query(
        "SELECT doctor.user_id, doctor.signature_path
           FROM doctor
          LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    $patient = $db->query(
        "SELECT patient.user_id, patient.address
           FROM patient
          LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);

    if (!is_array($doctor) || !is_array($patient)) {
        echo "SKIP  Live workflow: no doctor and patient accounts are available in this database\n";
        return;
    }

    $doctorId = (int) $doctor['user_id'];
    $patientId = (int) $patient['user_id'];
    $requestId = 0;
    $recordId = 0;

    try {
        $insert = $db->prepare(
            "INSERT INTO consultation_requests (patient_id, doctor_id, reason, status)
             VALUES (:patient_id, :doctor_id, :reason, 'Approved')"
        );
        $insert->bindValue(':patient_id', $patientId, PDO::PARAM_INT);
        $insert->bindValue(':doctor_id', $doctorId, PDO::PARAM_INT);
        $insert->bindValue(':reason', 'W7D3-TEST workflow check');
        $insert->execute();
        $requestId = (int) $db->lastInsertId();

        $fields = [
            'chief_complaint' => 'Fever',
            'symptoms' => 'Two days of fever and cough',
            'clinical_findings' => 'Alert, no distress',
            'diagnosis' => 'Viral illness',
            'treatment_plan' => 'Rest and fluids',
            'additional_notes' => 'Review if worse',
        ];

        $incomplete = ConsultationRecord::completeConsultationForDoctor(
            $requestId,
            $doctorId,
            $patientId,
            date('Y-m-d H:i:s'),
            ['chief_complaint' => 'Fever']
        );
        expect_true(($incomplete['success'] ?? false) === true, 'Live workflow: incomplete clinical notes can still complete the consultation');

        $completed = ConsultationRecord::completeConsultationForDoctor(
            $requestId,
            $doctorId,
            $patientId,
            date('Y-m-d H:i:s'),
            $fields
        );
        expect_true(($completed['success'] ?? false) === true, 'Live workflow: assigned doctor can complete after documenting');

        $request = $db->prepare(
            "SELECT status, completed_at FROM consultation_requests WHERE id = :id"
        );
        $request->bindValue(':id', $requestId, PDO::PARAM_INT);
        $request->execute();
        $requestRow = $request->fetch(PDO::FETCH_ASSOC) ?: [];
        expect_true(($requestRow['status'] ?? '') === 'Completed', 'Live workflow: consultation status is Completed');
        expect_true(trim((string) ($requestRow['completed_at'] ?? '')) !== '', 'Live workflow: completion timestamp is stored');

        $record = ConsultationRecord::findByConsultationForDoctor($requestId, $doctorId);
        expect_true(is_array($record) && ($record['record_status'] ?? '') === ConsultationRecord::STATUS_FINAL, 'Live workflow: clinical record is Final');
        expect_true(is_array($record) && trim((string) ($record['finalized_at'] ?? '')) !== '', 'Live workflow: finalized_at is stored');
        $recordId = (int) ($record['id'] ?? 0);

        $rxAfterComplete = $db->prepare(
            'SELECT COUNT(*) FROM prescriptions WHERE consultation_record_id = :id'
        );
        $rxAfterComplete->bindValue(':id', $recordId, PDO::PARAM_INT);
        $rxAfterComplete->execute();
        expect_true((int) $rxAfterComplete->fetchColumn() === 0, 'Live workflow: completing the consultation does not auto-create a prescription');

        $pageReady = DoctorPrescriptionService::getPrescriptionPageForDoctor($doctorId, $requestId);
        expect_true(is_array($pageReady) && ($pageReady['prescriptions'] ?? ['x']) === [], 'Live workflow: prescription page stays empty until the doctor saves');
        $hasSignature = (bool) ($pageReady['has_signature'] ?? false);
        expect_true(($pageReady['can_create'] ?? false) === $hasSignature, 'Live workflow: Save Prescription is available only after completion when the doctor signature is on file');

        $csrf = Csrf::generate();
        $emptySave = DoctorPrescriptionService::createPrescription($doctorId, $requestId, $csrf, ['medications' => []]);
        expect_true(($emptySave['success'] ?? true) === false, 'Live workflow: an empty prescription is not saved');

        $locked = ConsultationRecord::upsertDraftForDoctor(
            $requestId,
            $doctorId,
            $patientId,
            date('Y-m-d H:i:s'),
            $fields
        );
        expect_true(($locked['success'] ?? true) === false, 'Live workflow: finalized record cannot be overwritten by draft editing');

        $otherDoctor = Prescription::createForCompletedConsultation(
            $recordId,
            $doctorId + 999999,
            $patientId,
            [['medication_name' => 'Amoxicillin', 'dosage' => '500 mg', 'frequency' => 'TDS', 'duration' => '5 days']]
        );
        expect_true(($otherDoctor['success'] ?? true) === false, 'Live workflow: unrelated doctor cannot insert a prescription line');

        $medications = [[
            'medication_name' => 'Amoxicillin',
            'dosage' => '500 mg',
            'frequency' => 'TDS',
            'duration' => '5 days',
            'quantity' => '15',
            'additional_notes' => 'After food',
        ]];
        if ($hasSignature) {
            $rx = DoctorPrescriptionService::createPrescription($doctorId, $requestId, Csrf::generate(), [
                'medications' => $medications,
            ]);
        } else {
            $rx = Prescription::createForCompletedConsultation($recordId, $doctorId, $patientId, $medications);
        }
        expect_true(($rx['success'] ?? false) === true, 'Live workflow: assigned doctor can explicitly save a prescription');

        $second = Prescription::createForCompletedConsultation(
            $recordId,
            $doctorId,
            $patientId,
            [['medication_name' => 'Paracetamol', 'dosage' => '500 mg', 'frequency' => 'TDS', 'duration' => '3 days']]
        );
        expect_true(($second['success'] ?? true) === false, 'Live workflow: a second prescription for the same consultation is blocked');

        $patientLines = Prescription::findByRecordForPatient($recordId, $patientId);
        expect_true($patientLines !== [] && ($patientLines[0]['medication_name'] ?? '') === 'Amoxicillin', 'Live workflow: patient can read their own prescription');
        expect_true(Prescription::findByRecordForPatient($recordId, $patientId + 999999) === [], 'Live workflow: other patient cannot read the prescription');
        expect_true(Prescription::findByRecordForDoctor($recordId, $doctorId + 999999) === [], 'Live workflow: other doctor cannot read the prescription');

        $page = DoctorPrescriptionService::getPrescriptionPageForDoctor($doctorId, $requestId);
        $patientName = (string) (($page['request']['patient_name'] ?? ''));
        $patientAddress = (string) (($page['request']['patient_address'] ?? ''));
        $doctorName = (string) (($page['request']['doctor_name'] ?? ''));
        $signature = (string) (($page['request']['doctor_signature_path'] ?? ''));
        expect_true($patientName !== '' && $doctorName !== '', 'Live workflow: prescription page auto-loads patient and doctor names');
        expect_true($patientAddress === trim((string) ($patient['address'] ?? '')), 'Live workflow: prescription address comes from the patient profile');
        expect_true($signature === trim((string) ($doctor['signature_path'] ?? '')), 'Live workflow: prescription signature path comes from the doctor profile');
        expect_true(($page['can_create'] ?? true) === false, 'Live workflow: create form is closed after the prescription is issued');

        $patientView = PatientClinicalRecordService::getCompletedRecordForPatient($patientId, $requestId);
        expect_true(is_array($patientView) && ($patientView['prescriptions'][0]['medication_name'] ?? '') === 'Amoxicillin', 'Live workflow: patient completed-record view includes the prescription');
    } finally {
        if ($recordId > 0) {
            $deleteRx = $db->prepare('DELETE FROM prescriptions WHERE consultation_record_id = :id');
            $deleteRx->bindValue(':id', $recordId, PDO::PARAM_INT);
            $deleteRx->execute();
            $deleteRecord = $db->prepare('DELETE FROM consultation_records WHERE id = :id');
            $deleteRecord->bindValue(':id', $recordId, PDO::PARAM_INT);
            $deleteRecord->execute();
        }
        if ($requestId > 0) {
            $deleteRequest = $db->prepare("DELETE FROM consultation_requests WHERE id = :id AND reason = 'W7D3-TEST workflow check'");
            $deleteRequest->bindValue(':id', $requestId, PDO::PARAM_INT);
            $deleteRequest->execute();
        }
    }
}
