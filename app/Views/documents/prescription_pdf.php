<?php

use App\Helpers\Helper;

$request = is_array($request ?? null) ? $request : [];
$prescriptions = is_array($prescriptions ?? null) ? $prescriptions : [];
$logoSrc = (string) ($logoSrc ?? '');
$signatureSrc = (string) ($signatureSrc ?? '');
$signatureWidth = (int) ($signatureWidth ?? 0);
$signatureHeight = (int) ($signatureHeight ?? 0);

$patientName = trim((string) ($request['patient_name'] ?? ''));
$patientAddress = trim((string) ($request['patient_address'] ?? ''));
$doctorName = trim((string) ($request['doctor_name'] ?? ''));
$doctorTitle = trim((string) ($request['doctor_title'] ?? ''));
$specialization = trim((string) ($request['specialization'] ?? ''));
$doctorClinic = trim((string) ($request['doctor_clinic_address'] ?? ''));

$issuedDate = '';
if ($prescriptions !== [] && is_array($prescriptions[0])) {
    $issuedDate = (string) ($prescriptions[0]['issued_date'] ?? '');
}
$issuedDateLabel = Helper::formatDate(
    $issuedDate !== '' ? $issuedDate : (string) ($request['consultation_date'] ?? ''),
    'd M Y',
    'Not recorded'
);

$doctorLine = trim(implode(' · ', array_filter([
    $doctorTitle !== '' ? $doctorTitle : null,
    $specialization !== '' ? $specialization : null,
], static fn ($value) => $value !== null)));

$pdfValue = static function (string $value, string $empty = 'Not recorded'): string {
    $value = trim($value);
    if ($value === '') {
        return Helper::escape($empty);
    }

    return nl2br(Helper::escape($value), false);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MBPHA TeleHealth Prescription</title>
  <style>
    @page {
      size: A4 portrait;
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
    .rx-mark {
      text-align: right;
      font-size: 22pt;
      font-weight: 700;
      color: #0A6FB6;
      font-style: italic;
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
    .med {
      margin: 0 0 10px;
      padding: 8px 10px;
      background: #F5F7FA;
      border: 1px solid #D9E2EC;
      border-left: 3px solid #0A6FB6;
      page-break-inside: avoid;
    }
    .med-name {
      margin: 0 0 6px;
      font-size: 11.5pt;
      font-weight: 700;
      color: #0A6FB6;
      word-wrap: break-word;
      overflow-wrap: anywhere;
    }
    .med-strength {
      font-weight: 400;
      color: #102A43;
    }
    .med-meta {
      width: 100%;
    }
    .med-meta td {
      width: 33%;
      vertical-align: top;
      padding: 0 8px 0 0;
    }
    .med-notes {
      margin: 6px 0 0;
      font-size: 9.5pt;
      color: #102A43;
      white-space: pre-wrap;
      word-wrap: break-word;
      overflow-wrap: anywhere;
    }
    .signature-block {
      margin-top: 14px;
      width: 220px;
    }
    .signature-label {
      display: block;
      font-size: 8pt;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: #4A5568;
      margin-bottom: 6px;
    }
    .signature-image {
      display: block;
      margin: 0 0 6px;
    }
    .signature-missing {
      min-height: 40px;
      font-size: 9pt;
      color: #4A5568;
      margin-bottom: 6px;
    }
    .signature-rule {
      display: block;
      border-top: 1px solid #102A43;
      width: 160px;
      margin: 4px 0;
    }
    .signature-name {
      display: block;
      font-size: 9pt;
      font-weight: 700;
      color: #102A43;
    }
    .notice {
      margin-top: 16px;
      font-size: 8pt;
      color: #4A5568;
    }
  </style>
</head>
<body>
  <!-- Layout tables (letterhead / two-column fields). Not data tables — DomPDF document chrome. -->
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
        <p class="doc-type">Prescription</p>
      </td>
      <td class="rx-mark" style="width:70px;">Rx</td>
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
        <span class="label">Prescription date</span>
        <div class="value"><?= Helper::escape($issuedDateLabel) ?></div>
      </td>
    </tr>
    <tr>
      <td colspan="2">
        <span class="label">Address</span>
        <div class="value"><?= $pdfValue($patientAddress, 'Not provided') ?></div>
      </td>
    </tr>
  </table>

  <h2>Medication information</h2>
  <?php foreach ($prescriptions as $line): ?>
    <?php if (!is_array($line)) { continue; } ?>
    <?php
    $medName = trim((string) ($line['medication_name'] ?? ''));
    $dosage = trim((string) ($line['dosage'] ?? ''));
    $frequency = trim((string) ($line['frequency'] ?? ''));
    $duration = trim((string) ($line['duration'] ?? ''));
    $quantity = trim((string) ($line['quantity'] ?? ''));
    $notes = trim((string) ($line['additional_notes'] ?? ''));
    ?>
    <div class="med">
      <p class="med-name">
        <?= $pdfValue($medName) ?>
        <?php if ($dosage !== ''): ?>
          <span class="med-strength"> — <?= Helper::escape($dosage) ?></span>
        <?php endif; ?>
      </p>
      <table class="med-meta">
        <tr>
          <td>
            <span class="label">Directions / frequency</span>
            <div class="value"><?= $pdfValue($frequency) ?></div>
          </td>
          <td>
            <span class="label">Duration</span>
            <div class="value"><?= Helper::escape($duration !== '' ? $duration : '—') ?></div>
          </td>
          <td>
            <span class="label">Quantity</span>
            <div class="value"><?= Helper::escape($quantity !== '' ? $quantity : '—') ?></div>
          </td>
        </tr>
      </table>
      <?php if ($notes !== ''): ?>
        <p class="med-notes"><span class="label">Additional notes</span><?= $pdfValue($notes) ?></p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <h2>Doctor information</h2>
  <table class="grid">
    <tr>
      <td>
        <span class="label">Prescribing doctor</span>
        <div class="value"><?= $pdfValue($doctorName) ?></div>
        <?php if ($doctorLine !== ''): ?>
          <span class="label" style="margin-top:8px;">Professional details</span>
          <div class="value"><?= Helper::escape($doctorLine) ?></div>
        <?php endif; ?>
      </td>
      <td>
        <?php if ($doctorClinic !== ''): ?>
          <span class="label">Clinic</span>
          <div class="value"><?= $pdfValue($doctorClinic, 'Not provided') ?></div>
        <?php endif; ?>
        <span class="label" style="<?= $doctorClinic !== '' ? 'margin-top:8px;' : '' ?>">Date</span>
        <div class="value"><?= Helper::escape($issuedDateLabel) ?></div>
      </td>
    </tr>
  </table>

  <div class="signature-block">
    <span class="signature-label">Doctor's signature</span>
    <?php if ($signatureSrc !== '' && $signatureWidth > 0 && $signatureHeight > 0): ?>
      <img
        src="<?= $signatureSrc ?>"
        width="<?= $signatureWidth ?>"
        height="<?= $signatureHeight ?>"
        style="width:<?= $signatureWidth ?>px;height:<?= $signatureHeight ?>px;"
        alt="Signature of <?= Helper::escape($doctorName !== '' ? $doctorName : 'the prescribing doctor') ?>"
        class="signature-image"
      >
    <?php else: ?>
      <div class="signature-missing">Signature not on file</div>
    <?php endif; ?>
    <span class="signature-rule"></span>
    <span class="signature-name"><?= Helper::escape($doctorName !== '' ? $doctorName : 'Prescribing doctor') ?></span>
  </div>

  <p class="notice">This document is an export of the issued prescription stored in MBPHA TeleHealth. It does not create or change the prescription record.</p>
</body>
</html>
