<?php

/**
 * Week 8 Day 2 — consultation record PDF export.
 *
 * Usage: php bin/test_week8_day2.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Core\Session;
use App\Models\ConsultationRecord;
use App\Services\ConsultationRecordPdfService;
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

$longNotes = str_repeat('Follow-up review in two weeks with clear written advice for rest, oral fluids, and return if fever persists. ', 12);

$completedPage = [
    'request' => [
        'id' => 4172,
        'status' => 'Completed',
        'patient_id' => 11,
        'doctor_id' => 22,
        'patient_name' => 'Mary Kila',
        'patient_address' => 'Alotau, Milne Bay Province',
        'patient_dob' => '1990-04-12',
        'patient_gender' => 'female',
        'doctor_name' => 'Dr John Tau',
        'doctor_title' => 'Medical Officer',
        'specialization' => 'General Practice',
        'doctor_clinic_address' => 'Alotau Provincial Hospital',
        'reason' => 'Fever and cough for three days',
        'consultation_date' => '2026-08-19',
        'start_time' => '09:00:00',
        'end_time' => '09:30:00',
        'completed_at' => '2026-08-19 09:28:00',
    ],
    'record' => [
        'id' => 88,
        'record_status' => ConsultationRecord::STATUS_FINAL,
        'chief_complaint' => 'Fever and cough',
        'symptoms' => 'Dry cough, mild fever, no shortness of breath.',
        'clinical_findings' => 'Alert, chest clear, no respiratory distress.',
        'diagnosis' => 'Viral upper respiratory infection',
        'treatment_plan' => 'Supportive care, oral fluids, paracetamol as needed.',
        'additional_notes' => $longNotes,
        'consultation_date' => '2026-08-19',
        'finalized_at' => '2026-08-19 09:28:00',
    ],
    'prescriptions' => [],
];

$routes = (string) file_get_contents($root . '/routes/web.php');
$patientController = (string) file_get_contents($root . '/app/Controllers/PatientController.php');
$doctorController = (string) file_get_contents($root . '/app/Controllers/DoctorController.php');
$patientHistory = (string) file_get_contents($root . '/app/Views/patient/consultation_requests/_history_table.php');
$doctorHistory = (string) file_get_contents($root . '/app/Views/doctor/consultations/_history_table.php');

expect_true(str_contains($routes, "get('/patient/consultation-requests/{id}/download-record'"), 'Patient record download route remains registered');
expect_true(str_contains($routes, "get('/doctor/consultations/{id}/download-record'"), 'Doctor record download route remains registered');
expect_true(str_contains($routes, "get('/patient/consultations/{id}/room'"), 'Patient Daily room route remains registered');
expect_true(str_contains($routes, "get('/doctor/consultations/{id}'"), 'Doctor historical record route remains registered');
expect_true(str_contains($patientHistory, 'Download Consultation Record'), 'Patient history still exposes Download Consultation Record');
expect_true(str_contains($doctorHistory, 'Download Consultation Record'), 'Doctor history still exposes Download Consultation Record');
expect_true(str_contains($patientController, 'ConsultationRecordPdfService::stream'), 'Patient download streams a generated PDF');
expect_true(str_contains($doctorController, 'ConsultationRecordPdfService::stream'), 'Doctor download streams a generated PDF');
expect_true(
    !preg_match('/isCompleted[\s\S]{0,400}consultations\/.*\/room/', $patientHistory),
    'Patient completed View Record still does not point at the Daily room'
);

expect_true(PatientClinicalRecordService::isDownloadableDocument($completedPage, 'record'), 'Completed Final record is downloadable');
expect_true(
    !PatientClinicalRecordService::isDownloadableDocument([
        'request' => ['status' => 'Approved', 'patient_name' => 'Mary Kila'],
        'record' => $completedPage['record'],
        'prescriptions' => [],
    ], 'record'),
    'Approved consultations cannot generate a finalized-record PDF'
);
expect_true(
    !PatientClinicalRecordService::isDownloadableDocument([
        'request' => $completedPage['request'],
        'record' => ['record_status' => ConsultationRecord::STATUS_DRAFT],
        'prescriptions' => [],
    ], 'record'),
    'Draft clinical notes cannot generate a finalized-record PDF'
);

try {
    expect_true(
        PatientClinicalRecordService::getPrintableDocumentForPatient(999999, 1, 'record') === null,
        'Unknown patient id cannot prepare a consultation-record PDF'
    );
    expect_true(
        PatientClinicalRecordService::getPrintableDocumentForDoctor(999999, 1, 'record') === null,
        'Unknown doctor id cannot prepare a consultation-record PDF'
    );
} catch (\Throwable $e) {
    echo "SKIP  Database is not available for live authorization ID checks (" . $e->getMessage() . ")\n";
}

$html = ConsultationRecordPdfService::renderDocumentHtml($completedPage);
expect_true(str_contains($html, 'Patient information'), 'PDF template includes patient information');
expect_true(str_contains($html, 'Consultation information'), 'PDF template includes consultation information');
expect_true(str_contains($html, 'Clinical record'), 'PDF template includes the clinical record heading');
expect_true(str_contains($html, 'Mary Kila'), 'PDF includes the patient full name');
expect_true(str_contains($html, 'Alotau, Milne Bay Province'), 'PDF includes the patient address');
expect_true(str_contains($html, 'Dr John Tau'), 'PDF includes the doctor name');
expect_true(str_contains($html, 'Medical Officer'), 'PDF includes doctor professional information');
expect_true(str_contains($html, ConsultationRecord::REQUIRED_FIELDS['chief_complaint']), 'PDF uses the existing chief complaint label');
expect_true(str_contains($html, ConsultationRecord::REQUIRED_FIELDS['symptoms']), 'PDF keeps history / symptoms as its own field');
expect_true(str_contains($html, ConsultationRecord::REQUIRED_FIELDS['clinical_findings']), 'PDF keeps clinical findings as its own field');
expect_true(str_contains($html, 'Viral upper respiratory infection'), 'PDF includes the finalized diagnosis');
expect_true(str_contains($html, 'Supportive care, oral fluids, paracetamol as needed.'), 'PDF includes treatment / medical advice');
expect_true(str_contains($html, 'overflow-wrap: anywhere'), 'PDF CSS allows long clinical notes to wrap');
expect_true(!str_contains($html, 'images/LOGOS.png'), 'Consultation-record PDF does not embed the full-resolution brand PNG');
expect_true(!str_contains($html, 'dashboard-sidebar'), 'PDF does not include dashboard chrome');
expect_true(!str_contains($html, 'bi-grid'), 'PDF does not copy dashboard icons');

$approvedBuild = ConsultationRecordPdfService::buildFromAuthorizedPage([
    'request' => ['status' => 'Approved', 'patient_name' => 'Mary Kila', 'consultation_date' => '2026-08-19'],
    'record' => $completedPage['record'],
    'prescriptions' => [],
]);
expect_true($approvedBuild === null, 'PDF builder refuses approved consultations even if a record payload is supplied');

$built = ConsultationRecordPdfService::buildFromAuthorizedPage($completedPage);
expect_true(is_array($built), 'Finalized consultation record can be exported as a PDF');
expect_true(is_array($built) && str_starts_with((string) ($built['binary'] ?? ''), '%PDF'), 'Generated file is a PDF');
expect_true(is_array($built) && strlen((string) ($built['binary'] ?? '')) > 1500, 'Generated PDF is not empty');
expect_true(
    is_array($built) && (string) ($built['filename'] ?? '') === 'MBPHA-Consultation-Record-Mary-Kila-19-Aug-2026.pdf',
    'PDF filename uses patient name and consultation date without internal IDs'
);
expect_true(
    is_array($built) && !str_contains((string) ($built['filename'] ?? ''), '4172'),
    'PDF filename does not expose the consultation request id'
);

if (is_array($built)) {
    $out = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $built['filename'];
    $written = file_put_contents($out, $built['binary']);
    expect_true($written !== false && is_file($out) && filesize($out) > 1500, 'PDF can be written and reopened from disk');
    if (is_file($out)) {
        unlink($out);
    }
}

try {
    $db = \App\Core\Database::getInstance();
    $live = $db->query(
        "SELECT consultation_records.consultation_request_id,
                consultation_records.patient_id,
                consultation_records.doctor_id
           FROM consultation_records
           INNER JOIN consultation_requests
              ON consultation_requests.id = consultation_records.consultation_request_id
          WHERE consultation_records.record_status = 'Final'
            AND consultation_requests.status = 'Completed'
          LIMIT 1"
    )->fetch(\PDO::FETCH_ASSOC);

    if (is_array($live) && $live !== []) {
        $requestId = (int) ($live['consultation_request_id'] ?? 0);
        $patientId = (int) ($live['patient_id'] ?? 0);
        $doctorId = (int) ($live['doctor_id'] ?? 0);

        $patientPage = PatientClinicalRecordService::getPrintableDocumentForPatient($patientId, $requestId, 'record');
        $patientPdf = is_array($patientPage) ? ConsultationRecordPdfService::buildFromAuthorizedPage($patientPage) : null;
        expect_true(
            is_array($patientPdf) && str_starts_with((string) ($patientPdf['binary'] ?? ''), '%PDF'),
            'Live patient-authorized consultation record exports as PDF'
        );

        $doctorPage = PatientClinicalRecordService::getPrintableDocumentForDoctor($doctorId, $requestId, 'record');
        $doctorPdf = is_array($doctorPage) ? ConsultationRecordPdfService::buildFromAuthorizedPage($doctorPage) : null;
        expect_true(
            is_array($doctorPdf) && str_starts_with((string) ($doctorPdf['binary'] ?? ''), '%PDF'),
            'Live doctor-authorized consultation record exports as PDF'
        );

        expect_true(
            PatientClinicalRecordService::getPrintableDocumentForPatient($patientId + 999999, $requestId, 'record') === null,
            'Live unauthorized patient cannot export another patient PDF by changing the ID'
        );
        expect_true(
            PatientClinicalRecordService::getPrintableDocumentForDoctor($doctorId + 999999, $requestId, 'record') === null,
            'Live unauthorized doctor cannot export another doctor PDF by changing the ID'
        );
    } else {
        echo "SKIP  No completed Final consultation record is available for live PDF export\n";
    }
} catch (\Throwable $e) {
    echo "SKIP  Live PDF export checks were not run (" . $e->getMessage() . ")\n";
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
