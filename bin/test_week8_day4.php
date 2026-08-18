<?php

/**
 * Week 8 Day 4 — integration, authorization, PDF QA, and regression checks.
 *
 * Usage: php bin/test_week8_day4.php
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
use App\Services\ConsultationRecordPdfService;
use App\Services\PatientClinicalRecordService;
use App\Services\PatientConsultationBookingService;
use App\Services\PrescriptionPdfService;

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
$composer = (string) file_get_contents($root . '/composer.json');
$patientController = (string) file_get_contents($root . '/app/Controllers/PatientController.php');
$doctorController = (string) file_get_contents($root . '/app/Controllers/DoctorController.php');
$recordPdfService = (string) file_get_contents($root . '/app/Services/ConsultationRecordPdfService.php');
$rxPdfService = (string) file_get_contents($root . '/app/Services/PrescriptionPdfService.php');
$patientHistory = (string) file_get_contents($root . '/app/Views/patient/consultation_requests/_history_table.php');
$doctorHistory = (string) file_get_contents($root . '/app/Views/doctor/consultations/_history_table.php');
$patientShow = (string) file_get_contents($root . '/app/Views/patient/consultation_requests/show.php');
$doctorShow = (string) file_get_contents($root . '/app/Views/doctor/consultations/show.php');
$recordPartial = (string) file_get_contents($root . '/app/Views/partials/shared/_consultation_record_details.php');
$authController = (string) file_get_contents($root . '/app/Controllers/AuthController.php');

expect_true(str_contains($composer, '"dompdf/dompdf"'), 'DomPDF is declared in composer.json');
expect_true(str_contains($composer, '"ext-gd"'), 'PHP GD is declared as a PDF image dependency');
expect_true(is_file($root . '/vendor/dompdf/dompdf/src/Dompdf.php'), 'DomPDF is installed under vendor/');
expect_true(extension_loaded('gd'), 'PHP GD extension is loaded for PNG logo and signature rendering');

expect_true((bool) preg_match("/download-record'[\\s\\S]{0,160}RoleMiddleware\\(\\['patient'\\]\\)/", $routes), 'Patient record PDF route is role-protected');
expect_true((bool) preg_match("/download-prescription'[\\s\\S]{0,160}RoleMiddleware\\(\\['patient'\\]\\)/", $routes), 'Patient prescription PDF route is role-protected');
expect_true((bool) preg_match("/download-record'[\\s\\S]{0,160}RoleMiddleware\\(\\['doctor'\\]\\)/", $routes), 'Doctor record PDF route is role-protected');
expect_true((bool) preg_match("/download-prescription'[\\s\\S]{0,160}RoleMiddleware\\(\\['doctor'\\]\\)/", $routes), 'Doctor prescription PDF route is role-protected');
expect_true(!preg_match("/download-(record|prescription)[\\s\\S]{0,160}RoleMiddleware\\(\\['admin'\\]\\)/", $routes), 'Administrator has no PDF download routes');

expect_true(str_contains($patientController, "getUserRole() !== 'patient'"), 'Patient PDF stream re-checks the signed-in role');
expect_true(str_contains($doctorController, "getUserRole() !== 'doctor'"), 'Doctor PDF stream re-checks the signed-in role');
expect_true(str_contains($recordPdfService, "header('Content-Type: application/pdf')"), 'Consultation-record download sends application/pdf');
expect_true(str_contains($rxPdfService, "header('Content-Type: application/pdf')"), 'Prescription download sends application/pdf');
expect_true(str_contains($recordPdfService, "Content-Disposition: attachment"), 'Consultation-record PDF is offered as a download');
expect_true(str_contains($rxPdfService, "Content-Disposition: attachment"), 'Prescription PDF is offered as a download');
expect_true(str_contains($rxPdfService, "setPaper('A4', 'portrait')"), 'Prescription PDF is A4 portrait');
expect_true(str_contains($recordPdfService, "setPaper('A4', 'portrait')"), 'Consultation-record PDF is A4 portrait');
expect_true(!str_contains($rxPdfService, 'landscape') && !str_contains($recordPdfService, 'landscape'), 'Neither PDF service uses landscape paper');
expect_true(!str_contains($recordPdfService, 'INSERT') && !str_contains($rxPdfService, 'INSERT'), 'PDF services do not insert rows');
expect_true(!str_contains($recordPdfService, 'UPDATE') && !str_contains($rxPdfService, 'UPDATE'), 'PDF services do not update rows');
expect_true(!str_contains($recordPdfService, 'C:\\') && !str_contains($rxPdfService, 'C:\\'), 'PDF services do not hard-code local Windows filesystem paths');
expect_true(!str_contains($patientController, 'var_dump') && !str_contains($doctorController, 'var_dump'), 'PDF controllers do not contain debug dumps');

expect_true(str_contains($routes, "get('/login'"), 'Patient/doctor/admin login route remains registered');
expect_true(str_contains($routes, "get('/register'"), 'Patient registration route remains registered');
expect_true(str_contains($routes, "get('/doctor/availability'"), 'Doctor availability route remains registered');
expect_true(str_contains($routes, "post('/patient/consultation-requests/book/{id}'"), 'Patient booking route remains registered');
expect_true(str_contains($routes, "post('/admin/consultation-requests/{id}/approve'"), 'Admin approval route remains registered');
expect_true(str_contains($routes, "post('/admin/consultation-requests/{id}/reject'"), 'Admin rejection route remains registered');
expect_true(str_contains($routes, "get('/patient/consultations/{id}/room'"), 'Patient Daily room route remains registered');
expect_true(str_contains($routes, "get('/doctor/consultations/{id}/room'"), 'Doctor Daily room route remains registered');
expect_true(str_contains($routes, "post('/doctor/consultations/{id}/clinical-record'"), 'Clinical documentation autosave route remains registered');
expect_true(str_contains($routes, "post('/doctor/consultations/{id}/complete'"), 'Consultation completion route remains registered');
expect_true(str_contains($routes, "post('/doctor/consultations/{id}/prescription'"), 'Prescription creation route remains registered');
expect_true(str_contains($authController, 'password_verify') || str_contains($authController, 'AuthService'), 'Login still uses the existing authentication service');

expect_true(str_contains($patientHistory, 'View Record'), 'Patient history still exposes View Record');
expect_true(str_contains($patientHistory, 'Download Consultation Record'), 'Patient history still exposes Download Consultation Record');
expect_true(str_contains($patientHistory, 'View Prescription'), 'Patient history still exposes View Prescription');
expect_true(str_contains($patientHistory, 'Download Prescription'), 'Patient history still exposes Download Prescription');
expect_true(
    (bool) preg_match('/if \(\$hasFinalRecord\):[\s\S]{0,400}Download Consultation Record/', $patientHistory),
    'Patient Download Consultation Record is shown only when a final record exists'
);
expect_true(
    (bool) preg_match('/if \(\$hasPrescription\):[\s\S]{0,400}View Prescription[\s\S]{0,400}Download Prescription/', $patientHistory),
    'Patient Download Prescription is shown only when a prescription exists'
);
expect_true(
    !preg_match('/isCompleted[\s\S]{0,400}consultations\/.*\/room/', $patientHistory),
    'Patient completed View Record does not open the Daily room'
);
expect_true(
    (bool) preg_match('/elseif \(\$isCompleted\):[\s\S]{0,250}consultationRecordUrl/', $doctorHistory),
    'Doctor completed View Record uses the historical record URL'
);
expect_true(
    (bool) preg_match('/if \(\$hasFinalRecord\):[\s\S]{0,400}Download Consultation Record/', $doctorHistory),
    'Doctor Download Consultation Record is shown only when a final record exists'
);
expect_true(
    (bool) preg_match('/if \(\$hasPrescription\):[\s\S]{0,400}Download Prescription/', $doctorHistory),
    'Doctor Download Prescription is shown only when a prescription exists'
);
expect_true(
    substr_count($patientShow, 'Download Prescription') === 1,
    'Patient record page has a single Download Prescription action'
);
expect_true(
    substr_count($doctorShow, 'Download Prescription') === 1,
    'Doctor record page has a single Download Prescription action'
);
expect_true(
    !str_contains($recordPartial, 'Download Prescription'),
    'Shared record partial does not duplicate the Download Prescription button'
);
expect_true(!str_contains($recordPartial, '<textarea'), 'Historical record display remains read-only');

$db = Database::getInstance();
$doctors = $db->query('SELECT user_id FROM doctor LIMIT 2')->fetchAll(PDO::FETCH_ASSOC);
$patients = $db->query('SELECT user_id FROM patient LIMIT 2')->fetchAll(PDO::FETCH_ASSOC);
expect_true($doctors !== [] && $patients !== [], 'Test accounts exist for Week 8 integration checks');

if ($doctors === [] || $patients === []) {
    echo "\n{$passed} passed, {$failed} failed\n";
    exit($failed === 0 ? 0 : 1);
}

$doctorId = (int) $doctors[0]['user_id'];
$otherDoctorId = isset($doctors[1]['user_id']) ? (int) $doctors[1]['user_id'] : 0;
$patientId = (int) $patients[0]['user_id'];
$otherPatientId = isset($patients[1]['user_id']) ? (int) $patients[1]['user_id'] : 0;

$nameStmt = $db->prepare('SELECT full_name FROM users WHERE id = :id LIMIT 1');
$nameStmt->execute([':id' => $patientId]);
$patientName = (string) ($nameStmt->fetchColumn() ?: '');
$nameStmt->execute([':id' => $doctorId]);
$doctorName = (string) ($nameStmt->fetchColumn() ?: '');

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
        ':reason' => 'W8D4-TEST fever and cough',
    ]);
    $requestId = (int) $db->lastInsertId();

    $draft = ConsultationRecord::ensureDraftForDoctor(
        $requestId,
        $doctorId,
        $patientId,
        date('Y-m-d H:i:s'),
        'W8D4-TEST fever and cough'
    );
    expect_true(is_array($draft), 'Assigned doctor can still open a live draft for an approved consultation');

    $draftPage = [
        'request' => ['status' => 'Approved', 'patient_id' => $patientId, 'doctor_id' => $doctorId],
        'record' => is_array($draft) ? $draft : ['record_status' => ConsultationRecord::STATUS_DRAFT],
        'prescriptions' => [],
    ];
    expect_true(
        !PatientClinicalRecordService::isDownloadableDocument($draftPage, 'record'),
        'Draft / approved consultation cannot be exported as a finalized-record PDF'
    );
    expect_true(
        ConsultationRecordPdfService::buildFromAuthorizedPage($draftPage) === null,
        'Record PDF builder refuses a draft consultation'
    );
    expect_true(
        PatientClinicalRecordService::getPrintableDocumentForPatient($patientId, $requestId, 'record') === null,
        'Patient cannot download a consultation-record PDF before completion'
    );
    expect_true(
        PatientClinicalRecordService::getPrintableDocumentForDoctor($doctorId, $requestId, 'prescription') === null,
        'Doctor cannot download a prescription PDF before a prescription exists'
    );

    $complete = ConsultationRecord::completeConsultationForDoctor(
        $requestId,
        $doctorId,
        $patientId,
        date('Y-m-d H:i:s'),
        [
            'chief_complaint' => 'Fever and cough',
            'symptoms' => 'Dry cough for three days',
            'clinical_findings' => 'Afebrile, chest clear',
            'diagnosis' => 'Viral upper respiratory infection',
            'treatment_plan' => 'Supportive care and oral fluids',
            'additional_notes' => 'Return if fever develops',
        ]
    );
    expect_true(($complete['success'] ?? false) === true, 'Assigned doctor can still complete the consultation after documenting');
    $recordId = (int) (($complete['record']['id'] ?? 0) ?: ($draft['id'] ?? 0));

    $owned = PatientConsultationBookingService::getRequestDetail($patientId, $requestId);
    expect_true(is_array($owned) && ($owned['status'] ?? '') === 'Completed', 'Patient history/detail path still loads the completed consultation');

    $patientRecordPage = PatientClinicalRecordService::getPrintableDocumentForPatient($patientId, $requestId, 'record');
    expect_true(is_array($patientRecordPage), 'Patient can prepare the finalized consultation record for PDF export');
    expect_true(
        PatientClinicalRecordService::getPrintableDocumentForPatient($patientId, $requestId, 'prescription') === null,
        'Prescription download is refused until a prescription is issued'
    );

    $recordPdf = is_array($patientRecordPage) ? ConsultationRecordPdfService::buildFromAuthorizedPage($patientRecordPage) : null;
    expect_true(is_array($recordPdf) && str_starts_with((string) ($recordPdf['binary'] ?? ''), '%PDF'), 'Finalized consultation record exports as a PDF');
    expect_true(is_array($recordPdf) && strlen((string) ($recordPdf['binary'] ?? '')) > 1500, 'Consultation-record PDF is not empty');
    expect_true(
        is_array($recordPdf) && !str_contains((string) ($recordPdf['filename'] ?? ''), (string) $requestId),
        'Consultation-record PDF filename does not expose the consultation request id'
    );

    $recordHtml = is_array($patientRecordPage) ? ConsultationRecordPdfService::renderDocumentHtml($patientRecordPage) : '';
    expect_true($patientName === '' || str_contains($recordHtml, $patientName), 'Consultation-record PDF contains the patient name');
    expect_true($doctorName === '' || str_contains($recordHtml, $doctorName), 'Consultation-record PDF contains the doctor name');
    expect_true(str_contains($recordHtml, 'Viral upper respiratory infection'), 'Consultation-record PDF contains the finalized diagnosis');
    expect_true(str_contains($recordHtml, 'Fever and cough'), 'Consultation-record PDF contains the chief complaint');
    expect_true(str_contains($recordHtml, 'size: A4 portrait') || str_contains($recordPdfService, "setPaper('A4', 'portrait')"), 'Consultation-record PDF uses A4 portrait');

    $beforeRx = 0;
    $countStmt = $db->prepare('SELECT COUNT(*) FROM prescriptions WHERE consultation_record_id = :id');
    $countStmt->execute([':id' => $recordId]);
    $beforeRx = (int) $countStmt->fetchColumn();
    expect_true($beforeRx === 0, 'Completing the consultation still does not auto-create a prescription');

    $rx = Prescription::createForCompletedConsultation($recordId, $doctorId, $patientId, [[
        'medication_name' => 'Amoxicillin',
        'dosage' => '500 mg',
        'frequency' => 'Three times daily after food',
        'duration' => '7 days',
        'quantity' => '21 capsules',
        'additional_notes' => 'Complete the full course',
    ], [
        'medication_name' => 'Paracetamol',
        'dosage' => '500 mg',
        'frequency' => 'Every 6 hours as needed',
        'duration' => '5 days',
        'quantity' => '20 tablets',
    ]]);
    expect_true(($rx['success'] ?? false) === true, 'Assigned doctor can still issue a prescription after completion');

    $patientRxPage = PatientClinicalRecordService::getPrintableDocumentForPatient($patientId, $requestId, 'prescription');
    $doctorRxPage = PatientClinicalRecordService::getPrintableDocumentForDoctor($doctorId, $requestId, 'prescription');
    expect_true(is_array($patientRxPage), 'Patient can prepare their own prescription PDF');
    expect_true(is_array($doctorRxPage), 'Assigned doctor can prepare the same prescription PDF');

    $countStmt->execute([':id' => $recordId]);
    $afterCreate = (int) $countStmt->fetchColumn();

    $rxPdf = is_array($patientRxPage) ? PrescriptionPdfService::buildFromAuthorizedPage($patientRxPage) : null;
    expect_true(is_array($rxPdf) && str_starts_with((string) ($rxPdf['binary'] ?? ''), '%PDF'), 'Issued prescription exports as a PDF');
    expect_true(is_array($rxPdf) && strlen((string) ($rxPdf['binary'] ?? '')) > 1500, 'Prescription PDF is not empty');
    expect_true(is_array($rxPdf) && !str_contains((string) ($rxPdf['filename'] ?? ''), (string) $requestId), 'Prescription PDF filename does not expose the consultation request id');

    $countStmt->execute([':id' => $recordId]);
    $afterPdf = (int) $countStmt->fetchColumn();
    expect_true($afterPdf === $afterCreate, 'Generating the prescription PDF does not create additional prescription rows');

    $rxHtml = is_array($patientRxPage) ? PrescriptionPdfService::renderDocumentHtml($patientRxPage) : '';
    expect_true(str_contains($rxHtml, 'size: A4 portrait'), 'Prescription PDF template is A4 portrait');
    expect_true($patientName === '' || str_contains($rxHtml, $patientName), 'Prescription PDF contains the patient full name');
    expect_true(str_contains($rxHtml, 'Amoxicillin'), 'Prescription PDF contains the first medication');
    expect_true(str_contains($rxHtml, 'Paracetamol'), 'Prescription PDF contains the second medication');
    expect_true(str_contains($rxHtml, '500 mg'), 'Prescription PDF contains dosage / strength');
    expect_true(str_contains($rxHtml, 'Three times daily after food'), 'Prescription PDF contains frequency / directions');
    expect_true(str_contains($rxHtml, '7 days'), 'Prescription PDF contains duration');
    expect_true(str_contains($rxHtml, '21 capsules'), 'Prescription PDF contains quantity');
    expect_true($doctorName === '' || str_contains($rxHtml, $doctorName), 'Prescription PDF contains the doctor name');

    $rxBinary = is_array($rxPdf) ? (string) $rxPdf['binary'] : '';
    expect_true(
        str_contains($rxBinary, '595.28') || str_contains($rxBinary, '841.89') || (bool) preg_match('/\/MediaBox\s*\[[^\]]*595/', $rxBinary),
        'Generated prescription PDF encodes A4 portrait dimensions'
    );

    $out = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'MBPHA-Week8-Day4-Prescription.pdf';
    $written = file_put_contents($out, $rxBinary);
    expect_true($written !== false && is_file($out) && filesize($out) > 1500, 'Prescription PDF can be written and reopened from disk');
    if (is_file($out)) {
        unlink($out);
    }

    expect_true(ConsultationRequest::findByIdForPatient($requestId, $patientId + 999999) === null, 'Unknown patient id cannot open the consultation');
    expect_true(PatientClinicalRecordService::getPrintableDocumentForPatient($patientId + 999999, $requestId, 'record') === null, 'Unknown patient id cannot export the consultation-record PDF');
    expect_true(PatientClinicalRecordService::getPrintableDocumentForPatient($patientId + 999999, $requestId, 'prescription') === null, 'Unknown patient id cannot export the prescription PDF');
    expect_true(PatientClinicalRecordService::getPrintableDocumentForDoctor($doctorId + 999999, $requestId, 'record') === null, 'Unknown doctor id cannot export the consultation-record PDF');
    expect_true(PatientClinicalRecordService::getPrintableDocumentForDoctor($doctorId + 999999, $requestId, 'prescription') === null, 'Unknown doctor id cannot export the prescription PDF');

    if ($otherPatientId > 0) {
        expect_true(PatientClinicalRecordService::getCompletedRecordForPatient($otherPatientId, $requestId) === null, 'Patient B cannot view Patient A consultation record');
        expect_true(PatientClinicalRecordService::getPrintableDocumentForPatient($otherPatientId, $requestId, 'record') === null, 'Patient B cannot download Patient A consultation-record PDF');
        expect_true(PatientClinicalRecordService::getPrintableDocumentForPatient($otherPatientId, $requestId, 'prescription') === null, 'Patient B cannot download Patient A prescription PDF');
        expect_true(Prescription::findByRecordForPatient($recordId, $otherPatientId) === [], 'Patient B cannot load Patient A prescription rows');
    } else {
        echo "SKIP  Second patient account not available for cross-patient PDF checks\n";
    }

    if ($otherDoctorId > 0) {
        expect_true(PatientClinicalRecordService::getHistoricalRecordForDoctor($otherDoctorId, $requestId) === null, 'Doctor B cannot view Doctor A historical record');
        expect_true(PatientClinicalRecordService::getPrintableDocumentForDoctor($otherDoctorId, $requestId, 'record') === null, 'Doctor B cannot download Doctor A consultation-record PDF');
        expect_true(PatientClinicalRecordService::getPrintableDocumentForDoctor($otherDoctorId, $requestId, 'prescription') === null, 'Doctor B cannot download Doctor A prescription PDF');
        expect_true(Prescription::findByRecordForDoctor($recordId, $otherDoctorId) === [], 'Doctor B cannot load Doctor A prescription rows');
    } else {
        echo "SKIP  Second doctor account not available for cross-doctor PDF checks\n";
    }
} finally {
    if ($recordId > 0) {
        $deleteRx = $db->prepare('DELETE FROM prescriptions WHERE consultation_record_id = :id');
        $deleteRx->execute([':id' => $recordId]);
        $deleteRecord = $db->prepare('DELETE FROM consultation_records WHERE id = :id');
        $deleteRecord->execute([':id' => $recordId]);
    }
    if ($requestId > 0) {
        $deleteRequest = $db->prepare("DELETE FROM consultation_requests WHERE id = :id AND reason = 'W8D4-TEST fever and cough'");
        $deleteRequest->execute([':id' => $requestId]);
    }
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
