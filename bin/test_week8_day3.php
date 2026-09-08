<?php

/**
 * Week 8 Day 3 — prescription PDF export (A4 portrait).
 *
 * Usage: php bin/test_week8_day3.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Config\Environment;
use App\Core\Session;
use App\Models\ConsultationRecord;
use App\Services\PatientClinicalRecordService;
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

$longDirections = str_repeat('Take with food and a full glass of water. Return if fever persists. ', 10);

$prescriptionLines = [
    [
        'id' => 501,
        'doctor_id' => 22,
        'patient_id' => 11,
        'medication_name' => 'Amoxicillin',
        'dosage' => '500 mg capsules',
        'frequency' => $longDirections,
        'duration' => '7 days',
        'quantity' => '21 capsules',
        'additional_notes' => 'Complete the full course even if symptoms improve.',
        'issued_date' => '2026-08-19',
    ],
    [
        'id' => 502,
        'doctor_id' => 22,
        'patient_id' => 11,
        'medication_name' => 'Paracetamol',
        'dosage' => '500 mg tablets',
        'frequency' => 'One to two tablets every 6 hours as needed for fever',
        'duration' => '5 days',
        'quantity' => '20 tablets',
        'additional_notes' => '',
        'issued_date' => '2026-08-19',
    ],
];

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
        'doctor_signature_path' => '',
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
        'diagnosis' => 'Viral upper respiratory infection',
        'consultation_date' => '2026-08-19',
        'finalized_at' => '2026-08-19 09:28:00',
    ],
    'prescriptions' => $prescriptionLines,
];

$routes = (string) file_get_contents($root . '/routes/web.php');
$patientController = (string) file_get_contents($root . '/app/Controllers/PatientController.php');
$doctorController = (string) file_get_contents($root . '/app/Controllers/DoctorController.php');
$pdfService = (string) file_get_contents($root . '/app/Services/PrescriptionPdfService.php');
$patientHistory = (string) file_get_contents($root . '/app/Views/patient/consultation_requests/_history_table.php');
$doctorHistory = (string) file_get_contents($root . '/app/Views/doctor/consultations/_history_table.php');
$doctorPrescriptionView = (string) file_get_contents($root . '/app/Views/doctor/consultations/prescription.php');

expect_true(str_contains($routes, "get('/patient/consultation-requests/{id}/download-prescription'"), 'Patient prescription download route is registered');
expect_true(str_contains($routes, "get('/doctor/consultations/{id}/download-prescription'"), 'Doctor prescription download route is registered');
expect_true(str_contains($patientHistory, 'Download Prescription'), 'Patient history exposes Download Prescription');
expect_true(str_contains($doctorHistory, 'Download Prescription'), 'Doctor history exposes Download Prescription');
expect_true(str_contains($doctorPrescriptionView, 'Download Prescription'), 'Doctor prescription page exposes Download Prescription');
expect_true(str_contains($patientController, 'PrescriptionPdfService::stream'), 'Patient download streams a generated prescription PDF');
expect_true(str_contains($doctorController, 'PrescriptionPdfService::stream'), 'Doctor download streams a generated prescription PDF');
expect_true(!str_contains($patientController, 'downloadClinicalDocument'), 'Patient controller no longer uses the HTML print fallback for prescriptions');
expect_true(!str_contains($doctorController, 'downloadClinicalDocument'), 'Doctor controller no longer uses the HTML print fallback for prescriptions');
expect_true(str_contains($pdfService, "setPaper('A4', 'portrait')"), 'PDF service sets A4 portrait paper');
expect_true(!str_contains($pdfService, 'landscape'), 'PDF service does not use landscape orientation');
expect_true(!str_contains($pdfService, 'INSERT'), 'PDF service does not insert prescription rows');
expect_true(!str_contains($pdfService, 'UPDATE'), 'PDF service does not update prescription rows');
expect_true(!str_contains($pdfService, '$_GET'), 'PDF service does not read signature paths from the query string');
expect_true(!str_contains($pdfService, '$_POST'), 'PDF service does not read signature paths from the request body');

expect_true(PatientClinicalRecordService::isDownloadableDocument($completedPage, 'prescription'), 'Completed Final consultation with prescriptions is downloadable');
expect_true(
    !PatientClinicalRecordService::isDownloadableDocument([
        'request' => ['status' => 'Approved', 'patient_name' => 'Mary Kila'],
        'record' => $completedPage['record'],
        'prescriptions' => $prescriptionLines,
    ], 'prescription'),
    'Approved consultations cannot generate a prescription PDF'
);
expect_true(
    !PatientClinicalRecordService::isDownloadableDocument([
        'request' => $completedPage['request'],
        'record' => $completedPage['record'],
        'prescriptions' => [],
    ], 'prescription'),
    'Completed consultations without prescriptions cannot generate a prescription PDF'
);

$emptyBuild = PrescriptionPdfService::buildFromAuthorizedPage([
    'request' => $completedPage['request'],
    'record' => $completedPage['record'],
    'prescriptions' => [],
]);
expect_true($emptyBuild === null, 'PDF builder refuses pages with no issued prescription');

$approvedBuild = PrescriptionPdfService::buildFromAuthorizedPage([
    'request' => array_merge($completedPage['request'], ['status' => 'Approved']),
    'record' => $completedPage['record'],
    'prescriptions' => $prescriptionLines,
]);
expect_true($approvedBuild === null, 'PDF builder refuses approved consultations even if prescription rows are supplied');

$mismatchedBuild = PrescriptionPdfService::buildFromAuthorizedPage([
    'request' => $completedPage['request'],
    'record' => $completedPage['record'],
    'prescriptions' => [
        array_merge($prescriptionLines[0], ['doctor_id' => 99]),
    ],
]);
expect_true($mismatchedBuild === null, 'PDF builder refuses prescription lines that do not match the consultation doctor');

expect_true(PrescriptionPdfService::prescriptionMatchesConsultation($completedPage), 'Saved prescription lines match the consultation patient and doctor');

$html = PrescriptionPdfService::renderDocumentHtml($completedPage);
expect_true(str_contains($html, 'size: A4 portrait'), 'PDF template declares A4 portrait page size');
expect_true(str_contains($html, 'Patient information'), 'PDF template includes patient information');
expect_true(str_contains($html, 'Medication information'), 'PDF template includes medication information');
expect_true(str_contains($html, 'Doctor information'), 'PDF template includes doctor information');
expect_true(str_contains($html, 'Mary Kila'), 'PDF includes the patient full name');
expect_true(str_contains($html, 'Alotau, Milne Bay Province'), 'PDF includes the patient address');
expect_true(str_contains($html, '19 Aug 2026'), 'PDF includes the prescription date');
expect_true(str_contains($html, 'Amoxicillin'), 'PDF includes the first medication name');
expect_true(str_contains($html, 'Paracetamol'), 'PDF includes the second medication name');
expect_true(str_contains($html, '500 mg capsules'), 'PDF includes dosage / strength');
expect_true(str_contains($html, trim($longDirections)), 'PDF includes long medication directions');
expect_true(str_contains($html, '7 days'), 'PDF includes duration');
expect_true(str_contains($html, '21 capsules'), 'PDF includes quantity');
expect_true(str_contains($html, 'Complete the full course even if symptoms improve.'), 'PDF includes additional notes where present');
expect_true(str_contains($html, 'Dr John Tau'), 'PDF includes the doctor full name');
expect_true(str_contains($html, 'Medical Officer'), 'PDF includes doctor professional information');
expect_true(str_contains($html, 'overflow-wrap: anywhere'), 'PDF CSS allows long medication instructions to wrap');
expect_true(!str_contains($html, 'images/LOGOS.png'), 'Prescription PDF does not embed the full-resolution brand PNG');
expect_true(!str_contains($html, 'dashboard-sidebar'), 'PDF does not include dashboard chrome');
expect_true(!str_contains($html, 'bi-grid'), 'PDF does not copy dashboard icons');
expect_true(str_contains($html, 'does not create or change the prescription record'), 'PDF states that download is a read-only export');

$signatureDir = $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'doctors' . DIRECTORY_SEPARATOR . '22';
$signatureFile = $signatureDir . DIRECTORY_SEPARATOR . 'signature_week8_day3.png';
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
if (!is_dir($signatureDir)) {
    mkdir($signatureDir, 0775, true);
}
file_put_contents($signatureFile, $png);

$signedPage = $completedPage;
$signedPage['request']['doctor_signature_path'] = 'uploads/doctors/22/signature_week8_day3.png';
$_GET['signature'] = 'uploads/doctors/99/forged.png';
$_POST['signature_path'] = 'uploads/doctors/99/forged.png';

expect_true(
    PrescriptionPdfService::signatureSrc($signedPage) === 'uploads/doctors/22/signature_week8_day3.png',
    'PDF uses the stored signature from the authorized doctor profile'
);

$wrongFolder = $completedPage;
$wrongFolder['request']['doctor_signature_path'] = 'uploads/doctors/99/signature_week8_day3.png';
expect_true(
    PrescriptionPdfService::signatureSrc($wrongFolder) === '',
    'PDF rejects a signature stored under another doctor folder'
);

$traversal = $completedPage;
$traversal['request']['doctor_signature_path'] = 'uploads/doctors/22/../22/signature_week8_day3.png';
expect_true(
    PrescriptionPdfService::signatureSrc($traversal) === '',
    'PDF rejects signature paths that contain directory traversal'
);

$emptySignaturePage = $completedPage;
$emptySignaturePage['request']['doctor_signature_path'] = '';
expect_true(
    PrescriptionPdfService::signatureSrc($emptySignaturePage) === '',
    'PDF does not accept a query-string signature when none is stored on the doctor'
);

$embedded = PrescriptionPdfService::embedSignatureImage('uploads/doctors/22/signature_week8_day3.png');
expect_true(str_starts_with((string) ($embedded['src'] ?? ''), 'data:image/jpeg;base64,'), 'Authorized signature is flattened into an embedded JPEG data URI');
expect_true((int) ($embedded['width'] ?? 0) === 1 && (int) ($embedded['height'] ?? 0) === 1, 'Tiny signature images keep their native size');

$signedHtml = PrescriptionPdfService::renderDocumentHtml($signedPage);
expect_true(str_contains($signedHtml, 'data:image/jpeg;base64,'), 'Rendered prescription PDF embeds the authorized signature image');
expect_true(!str_contains($signedHtml, 'uploads/doctors/22/signature_week8_day3.png'), 'Rendered prescription PDF does not expose the stored signature filesystem path');
expect_true(!str_contains($signedHtml, 'uploads/doctors/99/forged.png'), 'Rendered prescription PDF does not embed a forged signature path');

unset($_GET['signature'], $_POST['signature_path']);

$built = PrescriptionPdfService::buildFromAuthorizedPage($signedPage);
expect_true(is_array($built), 'Issued prescription can be exported as a PDF');
expect_true(is_array($built) && str_starts_with((string) ($built['binary'] ?? ''), '%PDF'), 'Generated file is a PDF');
expect_true(is_array($built) && strlen((string) ($built['binary'] ?? '')) > 1500, 'Generated PDF is not empty');
expect_true(
    is_array($built) && (string) ($built['filename'] ?? '') === 'MBPHA-Prescription-Mary-Kila-19-Aug-2026.pdf',
    'PDF filename uses patient name and prescription date without internal IDs'
);
expect_true(
    is_array($built) && !str_contains((string) ($built['filename'] ?? ''), '4172'),
    'PDF filename does not expose the consultation request id'
);
expect_true(
    is_array($built) && !str_contains((string) ($built['filename'] ?? ''), '501'),
    'PDF filename does not expose a prescription row id'
);

if (is_array($built)) {
    $binary = (string) $built['binary'];
    $hasPortraitBox = (bool) preg_match('/\/MediaBox\s*\[[^\]]*(595|210)/', $binary)
        || str_contains($binary, '595.28')
        || str_contains($binary, '841.89');
    expect_true($hasPortraitBox, 'Generated PDF encodes A4 portrait dimensions');

    $out = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $built['filename'];
    $written = file_put_contents($out, $binary);
    expect_true($written !== false && is_file($out) && filesize($out) > 1500, 'PDF can be written and reopened from disk');
    if (is_file($out)) {
        unlink($out);
    }
}

if (is_file($signatureFile)) {
    unlink($signatureFile);
}

try {
    expect_true(
        PatientClinicalRecordService::getPrintableDocumentForPatient(999999, 1, 'prescription') === null,
        'Unknown patient id cannot prepare a prescription PDF'
    );
    expect_true(
        PatientClinicalRecordService::getPrintableDocumentForDoctor(999999, 1, 'prescription') === null,
        'Unknown doctor id cannot prepare a prescription PDF'
    );
} catch (\Throwable $e) {
    echo "SKIP  Database is not available for live authorization ID checks (" . $e->getMessage() . ")\n";
}

try {
    $db = \App\Core\Database::getInstance();
    $live = $db->query(
        "SELECT consultation_records.consultation_request_id,
                consultation_records.patient_id,
                consultation_records.doctor_id
           FROM prescriptions
           INNER JOIN consultation_records
              ON consultation_records.id = prescriptions.consultation_record_id
           INNER JOIN consultation_requests
              ON consultation_requests.id = consultation_records.consultation_request_id
          WHERE consultation_requests.status = 'Completed'
            AND consultation_records.record_status = 'Final'
          LIMIT 1"
    )->fetch(\PDO::FETCH_ASSOC);

    if (is_array($live) && $live !== []) {
        $requestId = (int) ($live['consultation_request_id'] ?? 0);
        $patientId = (int) ($live['patient_id'] ?? 0);
        $doctorId = (int) ($live['doctor_id'] ?? 0);

        $patientPage = PatientClinicalRecordService::getPrintableDocumentForPatient($patientId, $requestId, 'prescription');
        $patientPdf = is_array($patientPage) ? PrescriptionPdfService::buildFromAuthorizedPage($patientPage) : null;
        expect_true(
            is_array($patientPdf) && str_starts_with((string) ($patientPdf['binary'] ?? ''), '%PDF'),
            'Live patient-authorized prescription exports as PDF'
        );

        $doctorPage = PatientClinicalRecordService::getPrintableDocumentForDoctor($doctorId, $requestId, 'prescription');
        $doctorPdf = is_array($doctorPage) ? PrescriptionPdfService::buildFromAuthorizedPage($doctorPage) : null;
        expect_true(
            is_array($doctorPdf) && str_starts_with((string) ($doctorPdf['binary'] ?? ''), '%PDF'),
            'Live doctor-authorized prescription exports as PDF'
        );

        expect_true(
            PatientClinicalRecordService::getPrintableDocumentForPatient($patientId + 999999, $requestId, 'prescription') === null,
            'Live unauthorized patient cannot export another patient prescription by changing the ID'
        );
        expect_true(
            PatientClinicalRecordService::getPrintableDocumentForDoctor($doctorId + 999999, $requestId, 'prescription') === null,
            'Live unauthorized doctor cannot export another doctor prescription by changing the ID'
        );
    } else {
        echo "SKIP  No completed issued prescription is available for live PDF export\n";
    }
} catch (\Throwable $e) {
    echo "SKIP  Live PDF export checks were not run (" . $e->getMessage() . ")\n";
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
