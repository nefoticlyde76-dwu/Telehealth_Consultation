<?php

use App\Helpers\Helper;
use App\Models\ConsultationRecord;

$request = is_array($request ?? null) ? $request : [];
$clinicalRecord = is_array($clinicalRecord ?? null) ? $clinicalRecord : [];
$logoSrc = (string) ($logoSrc ?? '');

$patientName = trim((string) ($request['patient_name'] ?? ''));
$patientAddress = trim((string) ($request['patient_address'] ?? ''));
$patientDob = (string) ($request['patient_dob'] ?? '');
$patientGender = trim((string) ($request['patient_gender'] ?? ''));
$doctorName = trim((string) ($request['doctor_name'] ?? ''));
$doctorTitle = trim((string) ($request['doctor_title'] ?? ''));
$specialization = trim((string) ($request['specialization'] ?? ''));
$doctorClinic = trim((string) ($request['doctor_clinic_address'] ?? ''));
$reason = trim((string) ($request['reason'] ?? ''));
$status = trim((string) ($request['status'] ?? 'Completed'));
$consultationDate = Helper::formatDate((string) ($request['consultation_date'] ?? ($clinicalRecord['consultation_date'] ?? '')), 'd M Y', 'Not available');
$startTime = substr((string) ($request['start_time'] ?? ''), 0, 5);
$endTime = substr((string) ($request['end_time'] ?? ''), 0, 5);
$timeLabel = trim($startTime . ($startTime !== '' && $endTime !== '' ? ' – ' : '') . $endTime);
$completedAt = Helper::formatDate(
    (string) ($request['completed_at'] ?? ($clinicalRecord['finalized_at'] ?? '')),
    'd M Y H:i',
    ''
);

$pdfValue = static function (string $value, string $empty = 'Not recorded'): string {
    $value = trim($value);
    if ($value === '') {
        return Helper::escape($empty);
    }

    return nl2br(Helper::escape($value), false);
};

$clinicalFields = [
    ['label' => ConsultationRecord::REQUIRED_FIELDS['chief_complaint'], 'value' => (string) ($clinicalRecord['chief_complaint'] ?? '')],
    ['label' => ConsultationRecord::REQUIRED_FIELDS['symptoms'], 'value' => (string) ($clinicalRecord['symptoms'] ?? '')],
    ['label' => ConsultationRecord::REQUIRED_FIELDS['clinical_findings'], 'value' => (string) ($clinicalRecord['clinical_findings'] ?? '')],
    ['label' => ConsultationRecord::REQUIRED_FIELDS['diagnosis'], 'value' => (string) ($clinicalRecord['diagnosis'] ?? '')],
    ['label' => ConsultationRecord::REQUIRED_FIELDS['treatment_plan'], 'value' => (string) ($clinicalRecord['treatment_plan'] ?? '')],
];
$additionalNotes = trim((string) ($clinicalRecord['additional_notes'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MBPHA TeleHealth Consultation Record</title>
  <style>
    @page {
      margin: 16mm 14mm 20mm 14mm;
    }
    * {
      box-sizing: border-box;
    }
    body {
      margin: 0;
      padding: 0;
      color: #102A43;
      font-family: DejaVu Sans, sans-serif;
      font-size: 10.5pt;
      line-height: 1.45;
    }
    .doc-header {
      width: 100%;
      border-bottom: 2.5px solid #0A6FB6;
      padding-bottom: 10px;
      margin-bottom: 14px;
    }
    .doc-header td {
      vertical-align: middle;
    }
    .logo {
      width: 52px;
      height: 52px;
    }
    .authority {
      margin: 0;
      font-size: 8.5pt;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #4A5568;
      font-weight: 700;
    }
    .brand {
      margin: 2px 0 0;
      font-size: 16pt;
      font-weight: 700;
      color: #0A6FB6;
    }
    .doc-type {
      margin: 2px 0 0;
      font-size: 11pt;
      color: #40C4FF;
      font-weight: 700;
    }
    .meta {
      width: 100%;
      margin: 0 0 14px;
    }
    .meta td {
      width: 33%;
      vertical-align: top;
      padding: 0 10px 0 0;
    }
    .kicker {
      display: block;
      font-size: 7.5pt;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: #4A5568;
      font-weight: 700;
      margin-bottom: 2px;
    }
    .meta-value {
      font-size: 10.5pt;
      font-weight: 700;
      color: #102A43;
    }
    .meta-sub {
      font-size: 9pt;
      color: #4A5568;
    }
    h2 {
      margin: 16px 0 8px;
      padding: 4px 0 5px;
      font-size: 10pt;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      color: #0A6FB6;
      border-bottom: 1px solid #D9E2EC;
    }
    .grid {
      width: 100%;
      margin: 0;
    }
    .grid td {
      width: 50%;
      vertical-align: top;
      padding: 0 12px 8px 0;
    }
    .label {
      display: block;
      font-size: 8pt;
      color: #4A5568;
      font-weight: 700;
      margin-bottom: 1px;
    }
    .value {
      font-size: 10.5pt;
      color: #102A43;
      word-wrap: break-word;
      overflow-wrap: anywhere;
    }
    .block {
      margin: 0 0 9px;
      page-break-inside: avoid;
    }
    .block-label {
      font-size: 8.5pt;
      font-weight: 700;
      color: #0A6FB6;
      margin: 0 0 3px;
    }
    .block-value {
      margin: 0;
      padding: 7px 9px;
      background: #F5F7FA;
      border: 1px solid #D9E2EC;
      border-left: 3px solid #40C4FF;
      font-size: 10.5pt;
      line-height: 1.5;
      white-space: pre-wrap;
      word-wrap: break-word;
      overflow-wrap: anywhere;
    }
    .notice {
      margin-top: 16px;
      font-size: 8pt;
      color: #4A5568;
    }
  </style>
</head>
<body>
  <table class="doc-header">
    <tr>
      <?php if ($logoSrc !== ''): ?>
        <td style="width:64px;">
          <img src="<?= Helper::escape($logoSrc) ?>" alt="MBPHA TeleHealth" class="logo">
        </td>
      <?php endif; ?>
      <td>
        <p class="authority">Milne Bay Provincial Health Authority</p>
        <p class="brand">MBPHA TeleHealth</p>
        <p class="doc-type">Consultation Record</p>
      </td>
    </tr>
  </table>

  <table class="meta">
    <tr>
      <td>
        <span class="kicker">Status</span>
        <div class="meta-value"><?= Helper::escape($status !== '' ? $status : 'Completed') ?></div>
      </td>
      <td>
        <span class="kicker">Consultation date</span>
        <div class="meta-value"><?= Helper::escape($consultationDate) ?></div>
        <?php if ($timeLabel !== ''): ?>
          <div class="meta-sub"><?= Helper::escape($timeLabel) ?></div>
        <?php endif; ?>
      </td>
      <td>
        <span class="kicker">Completed</span>
        <div class="meta-value"><?= Helper::escape($completedAt !== '' ? $completedAt : 'Not recorded') ?></div>
      </td>
    </tr>
  </table>

  <h2>Patient information</h2>
  <table class="grid">
    <tr>
      <td>
        <span class="label">Full name</span>
        <div class="value"><?= $pdfValue($patientName) ?></div>
      </td>
      <td>
        <span class="label">Date of birth</span>
        <div class="value"><?= Helper::escape(Helper::formatDate($patientDob !== '' ? $patientDob : null, 'd M Y', 'Not provided')) ?></div>
      </td>
    </tr>
    <tr>
      <td>
        <span class="label">Gender</span>
        <div class="value"><?= Helper::escape($patientGender !== '' ? ucfirst($patientGender) : 'Not provided') ?></div>
      </td>
      <td>
        <span class="label">Address</span>
        <div class="value"><?= $pdfValue($patientAddress, 'Not provided') ?></div>
      </td>
    </tr>
  </table>

  <h2>Consultation information</h2>
  <table class="grid">
    <tr>
      <td>
        <span class="label">Doctor</span>
        <div class="value"><?= $pdfValue($doctorName, 'Not recorded') ?></div>
      </td>
      <td>
        <span class="label">Professional title</span>
        <div class="value"><?= $pdfValue($doctorTitle, 'Not recorded') ?></div>
      </td>
    </tr>
    <tr>
      <td>
        <span class="label">Specialization</span>
        <div class="value"><?= $pdfValue($specialization !== '' ? $specialization : '', 'General Practice') ?></div>
      </td>
      <td>
        <span class="label">Clinic</span>
        <div class="value"><?= $pdfValue($doctorClinic, 'Not provided') ?></div>
      </td>
    </tr>
  </table>
  <?php if ($reason !== ''): ?>
    <div class="block">
      <p class="block-label">Reason for visit</p>
      <div class="block-value"><?= $pdfValue($reason) ?></div>
    </div>
  <?php endif; ?>

  <h2>Clinical record</h2>
  <?php foreach ($clinicalFields as $field): ?>
    <div class="block">
      <p class="block-label"><?= Helper::escape((string) $field['label']) ?></p>
      <div class="block-value"><?= $pdfValue((string) $field['value']) ?></div>
    </div>
  <?php endforeach; ?>
  <?php if ($additionalNotes !== ''): ?>
    <div class="block">
      <p class="block-label">Additional notes</p>
      <div class="block-value"><?= $pdfValue($additionalNotes) ?></div>
    </div>
  <?php endif; ?>

  <p class="notice">This document is an export of the finalized consultation record. It does not replace the clinical record stored in MBPHA TeleHealth.</p>
</body>
</html>
