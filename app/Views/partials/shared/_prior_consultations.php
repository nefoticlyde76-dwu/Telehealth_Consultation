<?php

$priorConsultations = is_array($priorConsultations ?? null) ? $priorConsultations : [];
if ($priorConsultations === [] || (string) ($viewerRole ?? '') !== 'doctor') {
    return;
}

$savedRequest = is_array($request ?? null) ? $request : [];
$savedPrescriptions = is_array($prescriptions ?? null) ? $prescriptions : [];
?>

<section class="cr-card cr-panel cr-prior cr-print-hide" id="prior-consultations" aria-label="Prior consultations">
  <div class="cr-panel__header">
    <div class="cr-panel__title-group">
      <span class="cr-icon cr-icon--sm" aria-hidden="true"><i class="bi bi-clock-history"></i></span>
      <div>
        <h2 class="cr-panel__title">Prior consultations</h2>
        <p class="cr-panel__meta">Read-only finalized records from other clinicians. Draft notes are not shown.</p>
      </div>
    </div>
  </div>

  <?php foreach ($priorConsultations as $priorConsultation): ?>
    <?php
    $priorRequest = is_array($priorConsultation['request'] ?? null) ? $priorConsultation['request'] : [];
    $priorRecord = is_array($priorConsultation['record'] ?? null) ? $priorConsultation['record'] : [];
    $priorPrescriptions = is_array($priorConsultation['prescriptions'] ?? null) ? $priorConsultation['prescriptions'] : [];
    $priorDoctorName = trim((string) ($priorRequest['doctor_name'] ?? 'Another clinician'));
    $priorDateLabel = \App\Helpers\Helper::formatDate(
        (string) ($priorRecord['consultation_date'] ?? ($priorRequest['consultation_date'] ?? '')),
        'd M Y',
        ''
    );
    $priorMeta = implode(' · ', array_filter([
        $priorDoctorName !== '' ? $priorDoctorName : null,
        $priorDateLabel !== '' ? $priorDateLabel : null,
        'Final',
    ], static fn ($part) => $part !== null && $part !== ''));
    ?>
    <article class="cr-prior__item">
      <p class="cr-panel__meta"><?= \App\Helpers\Helper::escape($priorMeta) ?></p>
      <div class="cr-clinical">
        <div class="cr-clinical__row">
          <i class="bi bi-clipboard-check cr-clinical__icon cr-clinical__icon--diagnosis" aria-hidden="true"></i>
          <div>
            <span class="cr-clinical__label">Diagnosis / assessment</span>
            <p class="cr-clinical__value"><?= \App\Helpers\Helper::escape((string) ($priorRecord['diagnosis'] ?? '')) ?></p>
          </div>
        </div>
        <div class="cr-clinical__row">
          <i class="bi bi-capsule cr-clinical__icon cr-clinical__icon--treatment" aria-hidden="true"></i>
          <div>
            <span class="cr-clinical__label">Treatment / advice</span>
            <p class="cr-clinical__value"><?= \App\Helpers\Helper::escape((string) ($priorRecord['treatment_plan'] ?? '')) ?></p>
          </div>
        </div>
      </div>

      <?php if ($priorPrescriptions === []): ?>
        <p class="cr-clinical__empty">No prescription was issued for this prior consultation.</p>
      <?php else: ?>
        <?php
        $request = $priorRequest;
        $prescriptions = $priorPrescriptions;
        $canCreate = false;
        $rxEditable = false;
        require __DIR__ . '/_prescription_document.php';
        ?>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
</section>
<?php
$request = $savedRequest;
$prescriptions = $savedPrescriptions;
?>
