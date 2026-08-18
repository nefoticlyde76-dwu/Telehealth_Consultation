<?php

/**
 * Week 7 — dedicated consultation record view vs Daily room.
 *
 * Usage: php bin/test_week7_record_view.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Core\Database;
use App\Core\Session;
use App\Models\ConsultationRecord;
use App\Models\ConsultationRequest;
use App\Models\Prescription;
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

$routes = (string) file_get_contents($root . '/routes/web.php');
expect_true(str_contains($routes, "get('/doctor/consultations/{id}'"), 'Doctor historical consultation record route exists');
expect_true(str_contains($routes, "get('/doctor/consultations/{id}/room'"), 'Doctor Daily room route remains registered');
expect_true(str_contains($routes, "get('/patient/consultations/{id}/room'"), 'Patient Daily room route remains registered');
expect_true(str_contains($routes, "get('/patient/consultation-requests/{id}'"), 'Patient consultation details route remains registered');
expect_true(str_contains($routes, "get('/patient/consultation-requests/{id}/download-record'"), 'Patient consultation record download route is registered');
expect_true(str_contains($routes, "get('/patient/consultation-requests/{id}/download-prescription'"), 'Patient prescription download route is registered');
expect_true(str_contains($routes, "get('/doctor/consultations/{id}/download-record'"), 'Doctor consultation record download route is registered');
expect_true(str_contains($routes, "get('/doctor/consultations/{id}/download-prescription'"), 'Doctor prescription download route is registered');
expect_true(str_contains($routes, "post('/doctor/consultations/{id}/join-token'"), 'Doctor Daily join-token route remains registered');
expect_true(!str_contains($routes, "generate-ai-review"), 'Admin AI review route is not registered');
expect_true(!str_contains($routes, "/ai-review"), 'Admin AI review fragment route is not registered');

$patientHistory = (string) file_get_contents($root . '/app/Views/patient/consultation_requests/_history_table.php');
expect_true(str_contains($patientHistory, 'View Record'), 'Patient history uses View Record for completed consultations');
expect_true(str_contains($patientHistory, 'Join Consultation'), 'Patient history uses Join Consultation for approved sessions');
expect_true(str_contains($patientHistory, 'Download Consultation Record'), 'Patient history prepares Download Consultation Record');
expect_true(str_contains($patientHistory, 'Download Prescription'), 'Patient history prepares Download Prescription');
expect_true(
    !preg_match('/isCompleted[\s\S]{0,400}consultations\/.*\/room/', $patientHistory),
    'Patient completed View Record does not point at the Daily room'
);

$doctorHistory = (string) file_get_contents($root . '/app/Views/doctor/consultations/_history_table.php');
expect_true(str_contains($doctorHistory, '$consultationRecordUrl'), 'Doctor history has a dedicated record URL');
expect_true(str_contains($doctorHistory, 'View Record'), 'Doctor history labels completed consultations as View Record');
expect_true(
    str_contains($doctorHistory, 'escape($consultationRecordUrl)')
    && preg_match('/elseif \(\$isCompleted\):[\s\S]{0,250}consultationRecordUrl/', $doctorHistory) === 1,
    'Doctor View Record uses the historical record URL, not the Daily room'
);
expect_true(str_contains($doctorHistory, 'Download Consultation Record'), 'Doctor history prepares Download Consultation Record');
expect_true(
    preg_match('/elseif \(\$isCompleted\):[\s\S]{0,800}consultationRoomUrl/', $doctorHistory) !== 1,
    'Doctor completed View Record does not use the Daily room URL'
);

$doctorShow = (string) file_get_contents($root . '/app/Views/doctor/consultations/show.php');
$patientShow = (string) file_get_contents($root . '/app/Views/patient/consultation_requests/show.php');
$recordPartial = (string) file_get_contents($root . '/app/Views/partials/shared/_consultation_record_details.php');
expect_true(str_contains($patientShow, 'Consultation Record'), 'Patient details page titles completed visits as Consultation Record');
expect_true(str_contains($doctorShow, 'Consultation Record'), 'Doctor details page titles completed visits as Consultation Record');
expect_true(!str_contains($recordPartial, '<textarea'), 'Clinical record display has no editable textareas');
expect_true(!str_contains($recordPartial, 'ai_review'), 'Record page does not expose AI review data');
expect_true(str_contains($recordPartial, 'Patient information'), 'Record page includes a patient information section');
expect_true(str_contains($recordPartial, 'rx-document') || str_contains($recordPartial, '_prescription_document.php'), 'Record page reuses the portrait prescription document');

$rxPage = (string) file_get_contents($root . '/app/Views/doctor/consultations/prescription.php');
expect_true(
    str_contains($rxPage, "/doctor/consultations/' . \$requestId)")
    && !str_contains($rxPage, "/doctor/consultations/' . \$requestId . '/room'"),
    'Doctor prescription Clinical record link no longer opens the Daily room'
);

$doctorController = (string) file_get_contents($root . '/app/Controllers/DoctorController.php');
$patientController = (string) file_get_contents($root . '/app/Controllers/PatientController.php');
expect_true(str_contains($doctorController, "requestStatus !== 'Approved'"), 'Doctor room redirects when the consultation is not approved');
expect_true(str_contains($doctorController, "Helper::redirect('/doctor/consultations/' . (int) \$request['id'])"), 'Completed doctor room visits redirect to the record page');
expect_true(str_contains($patientController, "Helper::redirect('/patient/consultation-requests/' . (int) \$request['id'])"), 'Completed patient room visits redirect to the record page');

$db = Database::getInstance();
$doctors = $db->query('SELECT user_id FROM doctor LIMIT 2')->fetchAll(PDO::FETCH_ASSOC);
$patients = $db->query('SELECT user_id FROM patient LIMIT 2')->fetchAll(PDO::FETCH_ASSOC);
expect_true($doctors !== [] && $patients !== [], 'Test accounts exist for record-view checks');

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
        ':reason' => 'W7REC-TEST consultation record view',
    ]);
    $requestId = (int) $db->lastInsertId();

    $draft = ConsultationRecord::ensureDraftForDoctor(
        $requestId,
        $doctorId,
        $patientId,
        date('Y-m-d H:i:s'),
        'W7REC-TEST consultation record view'
    );
    expect_true(is_array($draft), 'Assigned doctor can still create a live draft for an approved consultation');

    $doctorDraftView = PatientClinicalRecordService::getHistoricalRecordForDoctor($doctorId, $requestId);
    expect_true(is_array($doctorDraftView) && ($doctorDraftView['record'] ?? null) === null, 'Doctor historical page does not expose a draft record');

    $complete = ConsultationRecord::completeConsultationForDoctor(
        $requestId,
        $doctorId,
        $patientId,
        date('Y-m-d H:i:s'),
        [
            'chief_complaint' => 'Cough',
            'symptoms' => 'Dry cough for three days',
            'clinical_findings' => 'Afebrile',
            'diagnosis' => 'Viral upper respiratory infection',
            'treatment_plan' => 'Supportive care',
            'additional_notes' => 'Return if fever develops',
        ]
    );
    expect_true(($complete['success'] ?? false) === true, 'Assigned doctor can finalize the consultation record');
    $recordId = (int) (($complete['record']['id'] ?? 0) ?: ($draft['id'] ?? 0));

    $rx = Prescription::createForCompletedConsultation($recordId, $doctorId, $patientId, [[
        'medication_name' => 'Paracetamol',
        'dosage' => '500 mg',
        'frequency' => 'TDS',
        'duration' => '3 days',
        'quantity' => '9',
    ]]);
    expect_true(($rx['success'] ?? false) === true, 'Assigned doctor can issue a prescription after completion');

    $patientView = PatientClinicalRecordService::getCompletedRecordForPatient($patientId, $requestId);
    expect_true(is_array($patientView['request'] ?? null), 'Patient can load their own completed consultation');
    expect_true(($patientView['request']['status'] ?? '') === 'Completed', 'Patient record view reports Completed status');
    expect_true(($patientView['record']['diagnosis'] ?? '') === 'Viral upper respiratory infection', 'Patient sees the finalized diagnosis');
    expect_true(($patientView['prescriptions'][0]['medication_name'] ?? '') === 'Paracetamol', 'Patient sees the linked prescription');
    expect_true(!isset($patientView['ai_review']), 'Patient record payload does not include AI review data');
    expect_true(isset($patientView['request']['patient_address']), 'Patient record includes profile address from the existing patient row');

    $doctorView = PatientClinicalRecordService::getHistoricalRecordForDoctor($doctorId, $requestId);
    expect_true(is_array($doctorView['record'] ?? null), 'Assigned doctor can load the historical finalized record');
    expect_true(($doctorView['record']['record_status'] ?? '') === ConsultationRecord::STATUS_FINAL, 'Doctor historical view only returns a Final record');
    expect_true(($doctorView['prescriptions'][0]['medication_name'] ?? '') === 'Paracetamol', 'Assigned doctor sees the same linked prescription');

    expect_true(ConsultationRequest::findByIdForPatient($requestId, $patientId + 999999) === null, 'Unknown patient id cannot open the consultation');
    expect_true(PatientClinicalRecordService::getCompletedRecordForPatient($patientId + 999999, $requestId) === null, 'Unknown patient id cannot load the clinical record');
    expect_true(PatientClinicalRecordService::getHistoricalRecordForDoctor($doctorId + 999999, $requestId) === null, 'Unknown doctor id cannot load the historical record');
    expect_true(is_array(PatientClinicalRecordService::getPrintableDocumentForPatient($patientId, $requestId, 'record')), 'Patient can prepare their own consultation record download');
    expect_true(is_array(PatientClinicalRecordService::getPrintableDocumentForPatient($patientId, $requestId, 'prescription')), 'Patient can prepare their own prescription download');
    expect_true(PatientClinicalRecordService::getPrintableDocumentForPatient($patientId + 999999, $requestId, 'record') === null, 'Unknown patient id cannot prepare a record download');
    expect_true(PatientClinicalRecordService::getPrintableDocumentForDoctor($doctorId + 999999, $requestId, 'prescription') === null, 'Unknown doctor id cannot prepare a prescription download');

    if ($otherPatientId > 0) {
        expect_true(ConsultationRequest::findByIdForPatient($requestId, $otherPatientId) === null, 'Patient B cannot open Patient A consultation by changing the ID');
        expect_true(PatientClinicalRecordService::getCompletedRecordForPatient($otherPatientId, $requestId) === null, 'Patient B cannot load Patient A clinical record');
        expect_true(PatientClinicalRecordService::getPrintableDocumentForPatient($otherPatientId, $requestId, 'record') === null, 'Patient B cannot download Patient A consultation record');
        expect_true(PatientClinicalRecordService::getPrintableDocumentForPatient($otherPatientId, $requestId, 'prescription') === null, 'Patient B cannot download Patient A prescription');
        expect_true(Prescription::findByRecordForPatient($recordId, $otherPatientId) === [], 'Patient B cannot load Patient A prescription');
    } else {
        echo "SKIP  Second patient account not available for cross-patient ID check\n";
    }

    if ($otherDoctorId > 0) {
        expect_true(ConsultationRequest::findByIdForDoctor($requestId, $otherDoctorId) === null, 'Doctor B cannot open Doctor A consultation by changing the ID');
        expect_true(PatientClinicalRecordService::getHistoricalRecordForDoctor($otherDoctorId, $requestId) === null, 'Doctor B cannot load Doctor A historical record');
        expect_true(PatientClinicalRecordService::getPrintableDocumentForDoctor($otherDoctorId, $requestId, 'record') === null, 'Doctor B cannot download Doctor A consultation record');
        expect_true(PatientClinicalRecordService::getPrintableDocumentForDoctor($otherDoctorId, $requestId, 'prescription') === null, 'Doctor B cannot download Doctor A prescription');
        expect_true(Prescription::findByRecordForDoctor($recordId, $otherDoctorId) === [], 'Doctor B cannot load Doctor A prescription');
    } else {
        echo "SKIP  Second doctor account not available for cross-doctor ID check\n";
    }

    $patientCreate = \App\Services\DoctorPrescriptionService::createPrescription($patientId, $requestId, 'invalid', [
        'medications' => [['medication_name' => 'Ibuprofen', 'dosage' => '400 mg', 'frequency' => 'TDS']],
    ]);
    expect_true(($patientCreate['success'] ?? true) === false, 'A patient id cannot modify a prescription through the doctor service');

    $ownedRequest = ConsultationRequest::findByIdForPatient($requestId, $patientId);
    expect_true(is_array($ownedRequest) && (int) ($ownedRequest['patient_id'] ?? 0) === $patientId, 'Patient record is loaded from the existing consultation_requests relationship');
} finally {
    if ($recordId > 0) {
        $deleteRx = $db->prepare('DELETE FROM prescriptions WHERE consultation_record_id = :id');
        $deleteRx->execute([':id' => $recordId]);
        $deleteRecord = $db->prepare('DELETE FROM consultation_records WHERE id = :id');
        $deleteRecord->execute([':id' => $recordId]);
    }
    if ($requestId > 0) {
        $deleteRequest = $db->prepare("DELETE FROM consultation_requests WHERE id = :id AND reason = 'W7REC-TEST consultation record view'");
        $deleteRequest->execute([':id' => $requestId]);
    }
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
