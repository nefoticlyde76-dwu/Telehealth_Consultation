<?php

$request = is_array($request ?? null) ? $request : [];
$prescriptions = is_array($prescriptions ?? null) ? $prescriptions : [];
$canCreate = (bool) ($canCreate ?? false);
$editable = $canCreate && (($rxEditable ?? false) === true);

$patientName = trim((string) ($request['patient_name'] ?? ''));
$patientAddress = trim((string) ($request['patient_address'] ?? ''));
$patientPhone = trim((string) ($request['patient_phone'] ?? ''));
$patientEmail = trim((string) ($request['patient_email'] ?? ''));
$patientGender = trim((string) ($request['patient_gender'] ?? ''));
$patientDob = trim((string) ($request['patient_dob'] ?? ''));
$patientId = (int) ($request['patient_id'] ?? $request['patient_user_id'] ?? 0);
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

$patientContact = $patientPhone !== '' ? $patientPhone : $patientEmail;
if ($patientGender !== '') {
    $patientGender = ucfirst(strtolower($patientGender));
}

$patientAgeLabel = '';
if (preg_match('/^\d{4}-\d{2}-\d{2}/', $patientDob) === 1) {
    try {
        $dob = new DateTimeImmutable(substr($patientDob, 0, 10));
        $years = $dob->diff(new DateTimeImmutable('today'))->y;
        if ($years >= 0 && $years < 150) {
            $patientAgeLabel = $years === 1 ? '1 year' : $years . ' years';
        }
    } catch (Exception $e) {
        $patientAgeLabel = '';
    }
}

$patientFacts = [];
if ($patientId > 0) {
    $patientFacts[] = ['label' => 'Patient ID', 'value' => (string) $patientId];
}
if ($patientAgeLabel !== '') {
    $patientFacts[] = ['label' => 'Age', 'value' => $patientAgeLabel];
}
if ($patientGender !== '') {
    $patientFacts[] = ['label' => 'Gender', 'value' => $patientGender];
}
if ($patientContact !== '') {
    $patientFacts[] = ['label' => 'Contact', 'value' => $patientContact];
}
?>

<div class="rx-page-stage">
  <article class="rx-document" aria-label="MBPHA TeleHealth prescription">
    <header class="rx-document__header rx-print-only">
      <div class="rx-document__brand">
        <img src="<?= \App\Helpers\Helper::asset('images/LOGOS.png') ?>" alt="MBPHA TeleHealth logo" class="rx-document__logo">
        <div>
          <p class="rx-document__authority">Milne Bay Provincial Health Authority</p>
          <h2 class="rx-document__title">MBPHA TeleHealth</h2>
          <p class="rx-document__subtitle">Prescription</p>
        </div>
      </div>
      <dl class="rx-document__print-meta">
        <div>
          <dt>Patient</dt>
          <dd><?= \App\Helpers\Helper::escape($patientName !== '' ? $patientName : 'Not recorded') ?></dd>
        </div>
        <?php if ($patientId > 0): ?>
          <div>
            <dt>Patient ID</dt>
            <dd><?= (int) $patientId ?></dd>
          </div>
        <?php endif; ?>
        <div>
          <dt>Date</dt>
          <dd><?= \App\Helpers\Helper::escape($issuedDateLabel) ?></dd>
        </div>
        <div>
          <dt>Doctor</dt>
          <dd><?= \App\Helpers\Helper::escape($doctorName !== '' ? $doctorName : 'Not recorded') ?></dd>
        </div>
      </dl>
    </header>

    <section class="rx-patient-card" aria-labelledby="rx-patient-heading">
      <div class="rx-card__head">
        <h3 class="rx-card__title" id="rx-patient-heading">Patient information</h3>
        <p class="rx-card__hint rx-screen-only">Taken from the patient's profile. This cannot be typed or changed on the prescription.</p>
      </div>
      <div class="rx-patient-card__body">
        <div class="rx-patient-card__identity">
          <?php
          $personName = $patientName !== '' ? $patientName : 'Patient';
          $personPhoto = $request['patient_photo_path'] ?? null;
          $personMeta = $patientAgeLabel !== '' ? $patientAgeLabel : 'Patient';
          $personSize = 'sm';
          require __DIR__ . '/person_row.php';
          ?>
        </div>
        <?php if ($patientFacts !== []): ?>
          <dl class="rx-meta-grid">
            <?php foreach ($patientFacts as $fact): ?>
              <div class="rx-meta-grid__item">
                <dt><?= \App\Helpers\Helper::escape((string) $fact['label']) ?></dt>
                <dd><?= \App\Helpers\Helper::escape((string) $fact['value']) ?></dd>
              </div>
            <?php endforeach; ?>
          </dl>
        <?php endif; ?>
        <?php if ($patientAddress !== ''): ?>
          <p class="rx-patient-card__address">
            <span>Address</span>
            <?= nl2br(\App\Helpers\Helper::escape($patientAddress)) ?>
          </p>
        <?php endif; ?>
      </div>
    </section>

    <section class="rx-form-card" aria-labelledby="rx-medication-heading">
      <div class="rx-card__head">
        <div>
          <h3 class="rx-card__title" id="rx-medication-heading">Medication</h3>
          <p class="rx-card__hint rx-screen-only">
            <?= $editable
              ? 'Enter the medications you are prescribing. Patient and doctor identity are taken from the linked consultation.'
              : 'Medications issued for this consultation.' ?>
          </p>
        </div>
        <span class="rx-document__rx-mark" aria-hidden="true">Rx</span>
      </div>

      <?php if ($editable): ?>
        <div id="rx-medication-rows" class="rx-medication-list">
          <?php
          $blankLine = [
              'medication_name' => '',
              'dosage' => '',
              'frequency' => '',
              'duration' => '',
              'quantity' => '',
              'additional_notes' => '',
          ];
          $rows = [$blankLine];
          foreach ($rows as $index => $line) {
              require __DIR__ . '/_prescription_medication_row.php';
          }
          ?>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm rx-add-medication rx-screen-only" id="rx-add-medication">
          <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>
          Add another medication
        </button>
      <?php elseif ($prescriptions === []): ?>
        <p class="rx-document__empty">No medication has been issued for this consultation.</p>
      <?php else: ?>
        <ol class="rx-issued-list">
          <?php foreach ($prescriptions as $lineIndex => $line): ?>
            <li class="rx-issued-item">
              <div class="rx-issued-item__head">
                <span class="rx-issued-item__index"><?= str_pad((string) ($lineIndex + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <p class="rx-issued-item__name">
                  <?= \App\Helpers\Helper::escape((string) ($line['medication_name'] ?? '')) ?>
                  <?php if (trim((string) ($line['dosage'] ?? '')) !== ''): ?>
                    <span class="rx-issued-item__strength"><?= \App\Helpers\Helper::escape((string) $line['dosage']) ?></span>
                  <?php endif; ?>
                </p>
              </div>
              <dl class="rx-meta-grid">
                <div class="rx-meta-grid__item">
                  <dt>Frequency</dt>
                  <dd><?= \App\Helpers\Helper::escape((string) ($line['frequency'] ?? '')) ?></dd>
                </div>
                <div class="rx-meta-grid__item">
                  <dt>Duration</dt>
                  <dd><?= \App\Helpers\Helper::escape(trim((string) ($line['duration'] ?? '')) !== '' ? (string) $line['duration'] : '—') ?></dd>
                </div>
                <div class="rx-meta-grid__item">
                  <dt>Quantity</dt>
                  <dd><?= \App\Helpers\Helper::escape(trim((string) ($line['quantity'] ?? '')) !== '' ? (string) $line['quantity'] : '—') ?></dd>
                </div>
              </dl>
              <?php if (trim((string) ($line['additional_notes'] ?? '')) !== ''): ?>
                <p class="rx-issued-item__notes"><?= nl2br(\App\Helpers\Helper::escape((string) $line['additional_notes'])) ?></p>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </section>

    <section class="rx-doctor-card" aria-labelledby="rx-doctor-heading">
      <div class="rx-card__head">
        <h3 class="rx-card__title" id="rx-doctor-heading">Doctor information</h3>
        <p class="rx-card__hint rx-screen-only">Taken from the assigned doctor's profile and signature on file.</p>
      </div>
      <div class="rx-doctor-card__body">
        <dl class="rx-meta-grid">
          <div class="rx-meta-grid__item">
            <dt>Prescribing doctor</dt>
            <dd><?= \App\Helpers\Helper::escape($doctorName !== '' ? $doctorName : 'Not recorded') ?></dd>
          </div>
          <?php if ($doctorLine !== ''): ?>
            <div class="rx-meta-grid__item">
              <dt>Professional details</dt>
              <dd><?= \App\Helpers\Helper::escape($doctorLine) ?></dd>
            </div>
          <?php endif; ?>
          <?php if ($doctorClinic !== ''): ?>
            <div class="rx-meta-grid__item rx-meta-grid__item--wide">
              <dt>Clinic</dt>
              <dd><?= nl2br(\App\Helpers\Helper::escape($doctorClinic)) ?></dd>
            </div>
          <?php endif; ?>
          <div class="rx-meta-grid__item">
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

    <footer class="rx-document__footer rx-print-only">
      <p>MBPHA TeleHealth · Milne Bay Provincial Health Authority</p>
      <p>
        <?= \App\Helpers\Helper::escape($issuedDateLabel) ?>
        <?php if ($doctorName !== ''): ?>
          · <?= \App\Helpers\Helper::escape($doctorName) ?>
        <?php endif; ?>
        <?php if ($doctorClinic !== ''): ?>
          · <?= \App\Helpers\Helper::escape(preg_replace('/\s+/', ' ', $doctorClinic) ?? $doctorClinic) ?>
        <?php endif; ?>
      </p>
    </footer>
  </article>
</div>
