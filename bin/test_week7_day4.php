<?php

/**
 * Week 7 Day 4 — patient history, access control, and data-integrity checks.
 *
 * Usage: php bin/test_week7_day4.php
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
use App\Models\ConsultationRequest;
use App\Models\Prescription;
use App\Services\DoctorClinicalDocumentationService;
use App\Services\DoctorPrescriptionService;
use App\Services\PatientClinicalRecordService;
use App\Services\PatientConsultationBookingService;

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

$routes = (string) file_get_contents($root . '/routes/web.php');
expect_true(!str_contains($routes, "patient/consultations/{id}/complete"), 'No patient route can mark a consultation Completed');
expect_true(!str_contains($routes, "patient/consultations/{id}/clinical-record"), 'No patient route can edit a clinical record');
expect_true(!preg_match("/post\\(\\s*'\\/patient\\/[^']*prescription/", $routes), 'No patient POST prescription route exists');
expect_true(str_contains($routes, "get('/patient/consultation-requests'"), 'Patient consultation history route exists');
expect_true(str_contains($routes, "get('/doctor/consultations'"), 'Doctor consultation history route exists');
expect_true(str_contains($routes, "post('/doctor/consultations/{id}/join-token'"), 'Daily doctor join-token route remains registered');
expect_true(!str_contains($routes, "generate-ai-review"), 'Admin AI review route is not registered');
expect_true(!str_contains($routes, "/ai-review"), 'Admin AI review fragment route is not registered');

$db = Database::getInstance();
$doctors = $db->query('SELECT user_id FROM doctor LIMIT 2')->fetchAll(PDO::FETCH_ASSOC);
$patients = $db->query('SELECT user_id FROM patient LIMIT 2')->fetchAll(PDO::FETCH_ASSOC);
expect_true($doctors !== [] && $patients !== [], 'Test accounts exist for integrity checks');

if ($doctors === [] || $patients === []) {
    echo "\n{$passed} passed, {$failed} failed\n";
    exit($failed === 0 ? 0 : 1);
}

$doctorId = (int) $doctors[0]['user_id'];
$otherDoctorId = isset($doctors[1]['user_id']) ? (int) $doctors[1]['user_id'] : 0;
$patientId = (int) $patients[0]['user_id'];
$otherPatientId = isset($patients[1]['user_id']) ? (int) $patients[1]['user_id'] : 0;

$requestId = 0;
$recordId = 0;

try {
    $insert = $db->prepare(
        "INSERT INTO consultation_requests (patient_id, doctor_id, reason, status)
         VALUES (:patient_id, :doctor_id, :reason, 'Approved')"
    );
    $insert->execute([
        ':patient_id' => $patientId,
        ':doctor_id' => $doctorId,
        ':reason' => 'W7D4-TEST history and access',
    ]);
    $requestId = (int) $db->lastInsertId();

    $draft = ConsultationRecord::ensureDraftForDoctor(
        $requestId,
        $doctorId,
        $patientId,
        date('Y-m-d H:i:s'),
        'W7D4-TEST history and access'
    );
    expect_true(is_array($draft) && ($draft['record_status'] ?? '') === ConsultationRecord::STATUS_DRAFT, 'Draft is created when the assigned doctor opens the consultation');

    $firstSave = ConsultationRecord::upsertDraftForDoctor($requestId, $doctorId, $patientId, date('Y-m-d H:i:s'), [
        'chief_complaint' => 'Headache',
        'symptoms' => 'Two days',
        'clinical_findings' => '',
        'diagnosis' => '',
        'treatment_plan' => '',
        'additional_notes' => '',
    ]);
    expect_true(($firstSave['success'] ?? false) === true, 'First autosave persists the draft');

    $secondSave = ConsultationRecord::upsertDraftForDoctor($requestId, $doctorId, $patientId, date('Y-m-d H:i:s'), [
        'chief_complaint' => 'Headache',
        'symptoms' => 'Two days of frontal headache',
        'clinical_findings' => 'Alert',
        'diagnosis' => 'Tension headache',
        'treatment_plan' => 'Rest and fluids',
        'additional_notes' => 'Return if worse',
    ]);
    expect_true(($secondSave['success'] ?? false) === true, 'A later save updates the same draft row');

    $reopened = ConsultationRecord::findByConsultationForDoctor($requestId, $doctorId);
    expect_true(is_array($reopened) && (int) ($reopened['id'] ?? 0) === (int) ($draft['id'] ?? 0), 'Reopening loads the same draft record');
    expect_true(($reopened['diagnosis'] ?? '') === 'Tension headache', 'Reopened draft contains the latest saved diagnosis');

    $otherDoctorDraft = DoctorClinicalDocumentationService::saveDraft($otherDoctorId > 0 ? $otherDoctorId : ($doctorId + 999999), $requestId, 'invalid', []);
    expect_true(($otherDoctorDraft['ok'] ?? true) === false, 'Another doctor cannot save notes on this consultation');

    $complete = ConsultationRecord::completeConsultationForDoctor(
        $requestId,
        $doctorId,
        $patientId,
        date('Y-m-d H:i:s'),
        [
            'chief_complaint' => 'Headache',
            'symptoms' => 'Two days of frontal headache',
            'clinical_findings' => 'Alert',
            'diagnosis' => 'Tension headache',
            'treatment_plan' => 'Rest and fluids',
            'additional_notes' => 'Return if worse',
        ]
    );
    expect_true(($complete['success'] ?? false) === true, 'Assigned doctor can complete after documenting');
    $recordId = (int) (($complete['record']['id'] ?? 0) ?: ($reopened['id'] ?? 0));

    $rxCount = $db->prepare('SELECT COUNT(*) FROM prescriptions WHERE consultation_record_id = :id');
    $rxCount->execute([':id' => $recordId]);
    expect_true((int) $rxCount->fetchColumn() === 0, 'Completion does not create a prescription');

    $patientHistory = PatientConsultationBookingService::getHistoryPageData($patientId, ['page' => 1]);
    $historyRow = null;
    foreach (($patientHistory['requests'] ?? []) as $row) {
        if ((int) ($row['id'] ?? 0) === $requestId) {
            $historyRow = $row;
            break;
        }
    }
    expect_true(is_array($historyRow) && ($historyRow['status'] ?? '') === 'Completed', 'Patient history includes the completed consultation');
    expect_true((int) ($historyRow['has_final_record'] ?? 0) === 1, 'Patient history marks that a finalized record exists');
    expect_true((int) ($historyRow['has_prescription'] ?? 1) === 0, 'Patient history shows no prescription before the doctor saves one');

    if ($otherPatientId > 0) {
        $otherHistory = ConsultationRequest::findForPatient($otherPatientId, 50, 0);
        $leaked = false;
        foreach ($otherHistory as $row) {
            if ((int) ($row['id'] ?? 0) === $requestId) {
                $leaked = true;
                break;
            }
        }
        expect_true(!$leaked, 'Patient B history does not include Patient A consultation');
        expect_true(ConsultationRequest::findByIdForPatient($requestId, $otherPatientId) === null, 'Patient B cannot open Patient A consultation by ID');
        expect_true(PatientClinicalRecordService::getCompletedRecordForPatient($otherPatientId, $requestId) === null, 'Patient B cannot load Patient A clinical record');
    } else {
        echo "SKIP  Second patient account not available for cross-patient history check\n";
    }

    expect_true(ConsultationRequest::findByIdForDoctor($requestId, $otherDoctorId > 0 ? $otherDoctorId : ($doctorId + 999999)) === null, 'Doctor B cannot open Doctor A consultation by ID');

    $csrf = Csrf::generate();
    $patientCreate = DoctorPrescriptionService::createPrescription($patientId, $requestId, $csrf, [
        'medications' => [['medication_name' => 'Ibuprofen', 'dosage' => '400 mg', 'frequency' => 'TDS']],
    ]);
    expect_true(($patientCreate['success'] ?? true) === false, 'A patient id cannot create a prescription through the doctor service');

    $rx = Prescription::createForCompletedConsultation($recordId, $doctorId, $patientId, [[
        'medication_name' => 'Ibuprofen',
        'dosage' => '400 mg',
        'frequency' => 'TDS',
        'duration' => '3 days',
        'quantity' => '9',
    ]]);
    expect_true(($rx['success'] ?? false) === true, 'Assigned doctor can explicitly save a prescription');

    $patientView = PatientClinicalRecordService::getCompletedRecordForPatient($patientId, $requestId);
    expect_true(is_array($patientView['record'] ?? null), 'Patient can view the finalized consultation record');
    expect_true(($patientView['prescriptions'][0]['medication_name'] ?? '') === 'Ibuprofen', 'Patient can view the linked prescription');
    expect_true(!isset($patientView['ai_review']), 'Patient record payload does not include AI review data');

    $historyAfterRx = ConsultationRequest::findForPatient($patientId, 50, 0);
    $flagged = null;
    foreach ($historyAfterRx as $row) {
        if ((int) ($row['id'] ?? 0) === $requestId) {
            $flagged = $row;
            break;
        }
    }
    expect_true((int) ($flagged['has_prescription'] ?? 0) === 1, 'Patient history shows that a prescription exists');

    $doctorHistory = ConsultationRequest::findForDoctor($doctorId, ['status' => 'Completed'], 50, 0);
    $doctorRow = null;
    foreach ($doctorHistory as $row) {
        if ((int) ($row['id'] ?? 0) === $requestId) {
            $doctorRow = $row;
            break;
        }
    }
    expect_true(is_array($doctorRow) && (int) ($doctorRow['has_prescription'] ?? 0) === 1, 'Doctor history includes the conducted consultation and prescription flag');

    if ($otherDoctorId > 0) {
        $otherDoctorHistory = ConsultationRequest::findForDoctor($otherDoctorId, [], 50, 0);
        $doctorLeak = false;
        foreach ($otherDoctorHistory as $row) {
            if ((int) ($row['id'] ?? 0) === $requestId) {
                $doctorLeak = true;
                break;
            }
        }
        expect_true(!$doctorLeak, 'Doctor B history does not include Doctor A consultation');
    } else {
        echo "SKIP  Second doctor account not available for cross-doctor history check\n";
    }

    $chain = $db->prepare(
        "SELECT consultation_requests.patient_id,
                consultation_requests.doctor_id,
                consultation_records.id AS record_id,
                prescriptions.id AS prescription_id
           FROM consultation_requests
           INNER JOIN consultation_records
              ON consultation_records.consultation_request_id = consultation_requests.id
           INNER JOIN prescriptions
              ON prescriptions.consultation_record_id = consultation_records.id
          WHERE consultation_requests.id = :id"
    );
    $chain->execute([':id' => $requestId]);
    $linked = $chain->fetch(PDO::FETCH_ASSOC) ?: [];
    expect_true(
        (int) ($linked['patient_id'] ?? 0) === $patientId
        && (int) ($linked['doctor_id'] ?? 0) === $doctorId
        && (int) ($linked['record_id'] ?? 0) === $recordId
        && (int) ($linked['prescription_id'] ?? 0) > 0,
        'Patient → request → record → prescription relationship is intact'
    );
} finally {
    if ($recordId > 0) {
        $deleteRx = $db->prepare('DELETE FROM prescriptions WHERE consultation_record_id = :id');
        $deleteRx->execute([':id' => $recordId]);
        $deleteRecord = $db->prepare('DELETE FROM consultation_records WHERE id = :id');
        $deleteRecord->execute([':id' => $recordId]);
    }
    if ($requestId > 0) {
        $deleteRequest = $db->prepare("DELETE FROM consultation_requests WHERE id = :id AND reason = 'W7D4-TEST history and access'");
        $deleteRequest->execute([':id' => $requestId]);
    }
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
