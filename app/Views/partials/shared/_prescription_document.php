<?php

$request = is_array($request ?? null) ? $request : [];
$prescriptions = is_array($prescriptions ?? null) ? $prescriptions : [];
$canCreate = (bool) ($canCreate ?? false);
$editable = $canCreate && (($rxEditable ?? false) === true);

$patientName = trim((string) ($request['patient_name'] ?? ''));
$patientAddress = trim((string) ($request['patient_address'] ?? ''));
$doctorName = trim((string) ($request['doctor_name'] ?? ''));
$doctorTitle = trim((string) ($request['doctor_title'] ?? ''));
$doctorSpecialization = trim((string) ($request['specialization'] ?? ''));
$doctorClinic = trim((string) ($request['doctor_clinic_address'] ?? ''));
$signature = \App\Services\PrescriptionPdfService::embedSignatureImage(
    (string) ($request['doctor_signature_path'] ?? ''),
    (int) ($request['doctor_id'] ?? 0)
);
$signatureUrl = (string) ($signature['src'] ?? '');
$signatureWidth = (int) ($signature['width'] ?? 0);
$signatureHeight = (int) ($signature['height'] ?? 0);

$issuedDate = '';
if ($prescriptions !== []) {
    $issuedDate = (string) ($prescriptions[0]['issued_date'] ?? '');
}
if ($issuedDate === '') {
    $issuedDate = date('Y-m-d H:i:s');
}
$issuedDateLabel = \App\Helpers\Helper::formatDate($issuedDate, 'd M Y', date('d M Y'));

$doctorLine = trim(implode(' · ', array_filter([
    $doctorTitle !== '' ? $doctorTitle : null,
    $doctorSpecialization !== '' ? $doctorSpecialization : null,
], static fn ($value) => $value !== null)));
?>

<div class="rx-page-stage">
  <article class="rx-document" aria-label="MBPHA TeleHealth prescription">
    <header class="rx-document__header">
      <div class="rx-document__brand">
        <img src="<?= \App\Helpers\Helper::asset('images/LOGOS.png') ?>" alt="MBPHA TeleHealth logo" class="rx-document__logo">
        <div>
          <p class="rx-document__authority">Milne Bay Provincial Health Authority</p>
          <h2 class="rx-document__title">MBPHA TeleHealth</h2>
          <p class="rx-document__subtitle">Prescription</p>
        </div>
      </div>
    </header>

    <section class="rx-document__section rx-document__section--patient">
      <h3 class="rx-document__heading">Patient information</h3>
      <p class="rx-document__screen-only">Taken from the patient's profile. This cannot be typed or changed on the prescription.</p>
      <dl class="rx-document__identity">
        <div class="rx-document__identity-row">
          <dt>Full name</dt>
          <dd><?= \App\Helpers\Helper::escape($patientName !== '' ? $patientName : 'Not recorded') ?></dd>
        </div>
        <div class="rx-document__identity-row">
          <dt>Address</dt>
          <dd><?= $patientAddress !== ''
            ? nl2br(\App\Helpers\Helper::escape($patientAddress))
            : '<span class="rx-document__empty">Address not recorded on the patient profile</span>' ?></dd>
        </div>
        <div class="rx-document__identity-row">
          <dt>Date</dt>
          <dd><?= \App\Helpers\Helper::escape($issuedDateLabel) ?></dd>
        </div>
      </dl>
    </section>

    <section class="rx-document__section rx-document__section--medication">
      <div class="rx-document__medication-head">
        <h3 class="rx-document__heading">Prescription / medication</h3>
        <span class="rx-document__rx-mark" aria-hidden="true">Rx</span>
      </div>

      <?php if ($editable): ?>
        <p class="rx-document__screen-only">Enter the medications you are prescribing. Patient and doctor identity are taken from the linked consultation.</p>
        <div id="rx-medication-rows">
          <?php
          $blankLine = [
              'medication_name' => '',
              'dosage' => '',
              'frequency' => '',
              'duration' => '',
              'quantity' => '',
          ];
          $rows = [$blankLine];
          foreach ($rows as $index => $line) {
              require __DIR__ . '/_prescription_medication_row.php';
          }
          ?>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm rx-document__screen-only" id="rx-add-medication">
          <i class="bi bi-plus-lg me-1"></i>
          Add another medication
        </button>
      <?php elseif ($prescriptions === []): ?>
        <p class="rx-document__empty">No medication has been issued for this consultation.</p>
      <?php else: ?>
        <ol class="rx-document__med-list">
          <?php foreach ($prescriptions as $line): ?>
            <li class="rx-document__med-item">
              <p class="rx-document__med-name">
                <?= \App\Helpers\Helper::escape((string) ($line['medication_name'] ?? '')) ?>
                <span class="rx-document__med-strength"><?= \App\Helpers\Helper::escape((string) ($line['dosage'] ?? '')) ?></span>
              </p>
              <dl class="rx-document__med-meta">
                <div>
                  <dt>Directions</dt>
                  <dd><?= \App\Helpers\Helper::escape((string) ($line['frequency'] ?? '')) ?></dd>
                </div>
                <div>
                  <dt>Duration</dt>
                  <dd><?= \App\Helpers\Helper::escape(trim((string) ($line['duration'] ?? '')) !== '' ? (string) $line['duration'] : '—') ?></dd>
                </div>
                <div>
                  <dt>Quantity</dt>
                  <dd><?= \App\Helpers\Helper::escape(trim((string) ($line['quantity'] ?? '')) !== '' ? (string) $line['quantity'] : '—') ?></dd>
                </div>
              </dl>
              <?php if (trim((string) ($line['additional_notes'] ?? '')) !== ''): ?>
                <p class="rx-document__med-notes"><?= nl2br(\App\Helpers\Helper::escape((string) $line['additional_notes'])) ?></p>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </section>

    <section class="rx-document__section rx-document__section--doctor">
      <h3 class="rx-document__heading">Doctor information</h3>
      <p class="rx-document__screen-only">Taken from the assigned doctor's profile and signature on file.</p>
      <div class="rx-document__doctor">
        <dl class="rx-document__identity">
          <div class="rx-document__identity-row">
            <dt>Prescribing doctor</dt>
            <dd><?= \App\Helpers\Helper::escape($doctorName !== '' ? $doctorName : 'Not recorded') ?></dd>
          </div>
          <?php if ($doctorLine !== ''): ?>
            <div class="rx-document__identity-row">
              <dt>Professional details</dt>
              <dd><?= \App\Helpers\Helper::escape($doctorLine) ?></dd>
            </div>
          <?php endif; ?>
          <?php if ($doctorClinic !== ''): ?>
            <div class="rx-document__identity-row">
              <dt>Clinic</dt>
              <dd><?= nl2br(\App\Helpers\Helper::escape($doctorClinic)) ?></dd>
            </div>
          <?php endif; ?>
          <div class="rx-document__identity-row">
            <dt>Date</dt>
            <dd><?= \App\Helpers\Helper::escape($issuedDateLabel) ?></dd>
          </div>
        </dl>
        <div class="rx-document__signature">
          <span class="rx-document__signature-label">Doctor's signature</span>
          <?php if ($signatureUrl !== '' && $signatureWidth > 0 && $signatureHeight > 0): ?>
            <img
              src="<?= \App\Helpers\Helper::escape($signatureUrl) ?>"
              width="<?= $signatureWidth ?>"
              height="<?= $signatureHeight ?>"
              alt="Signature of <?= \App\Helpers\Helper::escape($doctorName !== '' ? $doctorName : 'the prescribing doctor') ?>"
              class="rx-document__signature-image"
            >
          <?php else: ?>
            <div class="rx-document__signature-missing">Signature not on file</div>
          <?php endif; ?>
          <span class="rx-document__signature-rule"></span>
          <span class="rx-document__signature-name"><?= \App\Helpers\Helper::escape($doctorName) ?></span>
        </div>
      </div>
    </section>

    <footer class="rx-document__footer">
      MBPHA TeleHealth PNG · Milne Bay Provincial Health Authority
    </footer>
  </article>
</div>
