<?php

$request = is_array($request ?? null) ? $request : [];
$clinicalRecord = is_array($clinicalRecord ?? null) ? $clinicalRecord : null;
$prescriptions = is_array($prescriptions ?? null) ? $prescriptions : [];
$viewerRole = (string) ($viewerRole ?? 'patient');
$printType = (string) ($printType ?? '');
$requestStatus = (string) ($request['status'] ?? 'Pending');
$requestId = (int) ($request['id'] ?? 0);

$isCompleted = $requestStatus === 'Completed';
$hasFinalRecord = is_array($clinicalRecord) && (string) ($clinicalRecord['record_status'] ?? '') === \App\Models\ConsultationRecord::STATUS_FINAL;
$showClinical = $printType !== 'prescription';
$showPrescriptionBlock = $printType !== 'record';

$completedAt = (string) ($request['completed_at'] ?? ($clinicalRecord['finalized_at'] ?? ''));
$patientName = trim((string) ($request['patient_name'] ?? ''));
$patientAddress = trim((string) ($request['patient_address'] ?? ''));
$patientDob = (string) ($request['patient_dob'] ?? '');
$patientGender = trim((string) ($request['patient_gender'] ?? ''));
$hasPrescription = $prescriptions !== [];

$summaryPartyLabel = (string) ($summaryPartyLabel ?? ($viewerRole === 'doctor' ? 'Patient' : 'Doctor'));
$summaryPartyName = (string) ($summaryPartyName ?? ($viewerRole === 'doctor'
    ? (string) ($request['patient_name'] ?? 'Patient')
    : (string) ($request['doctor_name'] ?? 'Doctor')));
$summaryPartyMeta = (string) ($summaryPartyMeta ?? (string) ($request['specialization'] ?? 'General Practice'));

$statusPillClass = 'cr-pill--neutral';
if ($requestStatus === 'Completed' || $requestStatus === 'Approved') {
    $statusPillClass = '';
} elseif ($requestStatus === 'Pending') {
    $statusPillClass = 'cr-pill--warning';
} elseif (in_array($requestStatus, ['Rejected', 'Cancelled'], true)) {
    $statusPillClass = 'cr-pill--danger';
}

$dateLabel = \App\Helpers\Helper::formatDate((string) ($request['consultation_date'] ?? ''), 'd M Y', 'Not available');
$timeLabel = trim(substr((string) ($request['start_time'] ?? ''), 0, 5) . ' - ' . substr((string) ($request['end_time'] ?? ''), 0, 5), ' -');
$clinicalFindings = trim((string) ($clinicalRecord['symptoms'] ?? '') . "\n" . (string) ($clinicalRecord['clinical_findings'] ?? ''));
$additionalNotes = trim((string) ($clinicalRecord['additional_notes'] ?? ''));

$clinicalMetaParts = [];
if ($hasFinalRecord) {
    $clinicalMetaParts[] = 'Finalized by ' . (string) ($request['doctor_name'] ?? 'Doctor');
    $clinicalMetaParts[] = \App\Helpers\Helper::formatDate((string) ($clinicalRecord['consultation_date'] ?? ($request['consultation_date'] ?? '')), 'd M Y', '');
    if ($completedAt !== '') {
        $clinicalMetaParts[] = 'Completed ' . \App\Helpers\Helper::formatDate($completedAt, 'd M Y H:i', '');
    }
}
$clinicalMeta = implode(' · ', array_filter($clinicalMetaParts, static fn (string $part): bool => trim($part) !== ''));
$screenOnlyClass = $printType === 'record' ? '' : ' cr-print-hide';
?>

<?php if ($showClinical): ?>
<section class="cr-card cr-summary<?= $screenOnlyClass ?>" aria-label="Consultation summary">
  <div class="cr-summary__item">
    <span class="cr-icon <?= $requestStatus === 'Completed' ? 'cr-icon--success' : '' ?>" aria-hidden="true">
      <i class="bi <?= $requestStatus === 'Completed' ? 'bi-check-circle' : \App\Helpers\Helper::escape(ux_status_icon_class($requestStatus, 'bi-info-circle')) ?>"></i>
    </span>
    <div>
      <span class="cr-kicker">Status</span>
      <span class="cr-pill <?= $statusPillClass ?>"><?= \App\Helpers\Helper::escape($requestStatus) ?></span>
    </div>
  </div>
  <div class="cr-summary__item">
    <span class="cr-icon" aria-hidden="true"><i class="bi bi-calendar3"></i></span>
    <div>
      <span class="cr-kicker">Consultation date</span>
      <p class="cr-summary__value"><?= \App\Helpers\Helper::escape($dateLabel) ?></p>
      <?php if ($timeLabel !== ''): ?>
        <p class="cr-summary__meta"><?= \App\Helpers\Helper::escape($timeLabel) ?></p>
      <?php endif; ?>
    </div>
  </div>
  <div class="cr-summary__item">
    <div>
      <span class="cr-kicker"><?= \App\Helpers\Helper::escape($summaryPartyLabel) ?></span>
      <?php
      $summaryPartyPhoto = $summaryPartyLabel === 'Patient'
          ? ($request['patient_photo_path'] ?? null)
          : ($request['doctor_photo_path'] ?? $request['profile_photo_path'] ?? null);
      $personName = $summaryPartyName;
      $personPhoto = $summaryPartyPhoto;
      $personMeta = $summaryPartyMeta;
      $personSize = 'sm';
      require __DIR__ . '/person_row.php';
      ?>
    </div>
  </div>
</section>

<section class="cr-card cr-reason<?= $screenOnlyClass ?>">
  <span class="cr-icon" aria-hidden="true"><i class="bi bi-clipboard-check"></i></span>
  <div>
    <h2 class="cr-reason__label">Reason for visit</h2>
    <p class="cr-reason__value"><?= \App\Helpers\Helper::escape((string) ($request['reason'] ?? '')) ?></p>
  </div>
</section>

<div class="cr-main<?= $screenOnlyClass ?>">
  <section class="cr-card cr-panel" id="patient-information">
    <div class="cr-panel__header">
      <div class="cr-panel__title-group">
        <span class="cr-icon cr-icon--sm" aria-hidden="true"><i class="bi bi-person"></i></span>
        <h2 class="cr-panel__title">Patient information</h2>
      </div>
    </div>
    <div class="cr-fields">
      <div class="cr-field">
        <i class="bi bi-person cr-field__icon" aria-hidden="true"></i>
        <div>
          <span class="cr-field__label">Full name</span>
          <span class="cr-field__value"><?= \App\Helpers\Helper::escape($patientName !== '' ? $patientName : 'Not recorded') ?></span>
        </div>
      </div>
      <div class="cr-field">
        <i class="bi bi-calendar3 cr-field__icon" aria-hidden="true"></i>
        <div>
          <span class="cr-field__label">Date of birth</span>
          <span class="cr-field__value"><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate($patientDob !== '' ? $patientDob : null, 'd M Y', 'Not provided')) ?></span>
        </div>
      </div>
      <div class="cr-field">
        <i class="bi bi-person cr-field__icon" aria-hidden="true"></i>
        <div>
          <span class="cr-field__label">Gender</span>
          <span class="cr-field__value"><?= \App\Helpers\Helper::escape($patientGender !== '' ? ucfirst($patientGender) : 'Not provided') ?></span>
        </div>
      </div>
      <div class="cr-field">
        <i class="bi bi-geo-alt cr-field__icon" aria-hidden="true"></i>
        <div>
          <span class="cr-field__label">Address</span>
          <span class="cr-field__value"><?= \App\Helpers\Helper::escape($patientAddress !== '' ? $patientAddress : 'Not provided') ?></span>
        </div>
      </div>
    </div>
  </section>

  <section class="cr-card cr-panel" id="consultation-record">
    <div class="cr-panel__header">
      <div class="cr-panel__title-group">
        <span class="cr-icon cr-icon--sm" aria-hidden="true"><i class="bi bi-clipboard2-data"></i></span>
        <div>
          <h2 class="cr-panel__title">Clinical consultation record</h2>
          <?php if ($clinicalMeta !== ''): ?>
            <p class="cr-panel__meta"><?= \App\Helpers\Helper::escape($clinicalMeta) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($isCompleted): ?>
        <span class="cr-pill">Completed</span>
      <?php endif; ?>
    </div>

    <?php if ($isCompleted && $hasFinalRecord): ?>
      <div class="cr-clinical">
        <div class="cr-clinical__row">
          <i class="bi bi-exclamation-circle cr-clinical__icon cr-clinical__icon--alert" aria-hidden="true"></i>
          <div>
            <span class="cr-clinical__label">Chief complaint</span>
            <p class="cr-clinical__value"><?= \App\Helpers\Helper::escape((string) ($clinicalRecord['chief_complaint'] ?? '')) ?></p>
          </div>
        </div>
        <div class="cr-clinical__row">
          <i class="bi bi-thermometer cr-clinical__icon cr-clinical__icon--findings" aria-hidden="true"></i>
          <div>
            <span class="cr-clinical__label">Clinical findings</span>
            <p class="cr-clinical__value"><?= \App\Helpers\Helper::escape($clinicalFindings) ?></p>
          </div>
        </div>
        <div class="cr-clinical__row">
          <i class="bi bi-clipboard-check cr-clinical__icon cr-clinical__icon--diagnosis" aria-hidden="true"></i>
          <div>
            <span class="cr-clinical__label">Diagnosis / assessment</span>
            <p class="cr-clinical__value"><?= \App\Helpers\Helper::escape((string) ($clinicalRecord['diagnosis'] ?? '')) ?></p>
          </div>
        </div>
        <div class="cr-clinical__row">
          <i class="bi bi-capsule cr-clinical__icon cr-clinical__icon--treatment" aria-hidden="true"></i>
          <div>
            <span class="cr-clinical__label">Treatment / advice</span>
            <p class="cr-clinical__value"><?= \App\Helpers\Helper::escape((string) ($clinicalRecord['treatment_plan'] ?? '')) ?></p>
          </div>
        </div>
        <div class="cr-clinical__row">
          <i class="bi bi-file-text cr-clinical__icon cr-clinical__icon--notes" aria-hidden="true"></i>
          <div>
            <span class="cr-clinical__label">Additional clinical notes</span>
            <p class="cr-clinical__value"><?= \App\Helpers\Helper::escape($additionalNotes) ?></p>
          </div>
        </div>
      </div>
    <?php elseif ($isCompleted): ?>
      <p class="cr-clinical__empty">
        <?= $viewerRole === 'doctor'
          ? 'This consultation is completed. The finalized clinical record will appear here once it has been saved.'
          : 'This consultation is completed. The clinical record and prescription will appear here after the doctor finalizes them.' ?>
      </p>
    <?php else: ?>
      <p class="cr-clinical__empty">The clinical record will appear here after the consultation is completed.</p>
    <?php endif; ?>
  </section>
</div>
<?php endif; ?>

<?php if ($isCompleted && $showPrescriptionBlock): ?>
<section class="cr-card cr-rx" id="prescription">
  <div class="cr-rx__header cr-print-hide">
    <div class="cr-panel__title-group">
      <span class="cr-icon cr-icon--sm" aria-hidden="true"><i class="bi bi-journal-text"></i></span>
      <h2 class="cr-panel__title">Prescription</h2>
    </div>
  </div>

  <?php if (!$hasPrescription): ?>
    <div class="cr-rx__empty cr-print-hide">
      <i class="bi bi-info-circle" aria-hidden="true"></i>
      <p>No prescription has been issued for this consultation.</p>
    </div>
  <?php else: ?>
    <?php
    $canCreate = false;
    $rxEditable = false;
    require __DIR__ . '/_prescription_document.php';
    ?>
  <?php endif; ?>
</section>
<?php endif; ?>
