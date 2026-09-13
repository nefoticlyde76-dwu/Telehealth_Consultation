<?php

$request = is_array($request ?? null) ? $request : [];
$record = is_array($record ?? null) ? $record : [];
$prescriptions = is_array($prescriptions ?? null) ? $prescriptions : [];
$canCreate = (bool) ($canCreate ?? false);
$hasSignature = (bool) ($hasSignature ?? false);
$csrfToken = (string) ($csrfToken ?? '');
$requestId = (int) ($request['id'] ?? 0);
$status = (string) ($request['status'] ?? '');
$recordStatus = (string) ($record['record_status'] ?? '');
$isIssued = $prescriptions !== [];
$patientName = (string) ($request['patient_name'] ?? 'Patient');
$rxEditable = $canCreate;
$consultationUrl = \App\Helpers\Helper::url('/doctor/consultations/' . $requestId);
?>

<section class="rx-page mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <header class="ux-page-header rx-header">
    <div class="ux-page-header__left">
      <nav aria-label="Breadcrumb">
        <ol class="ux-breadcrumb">
          <li><a href="<?= \App\Helpers\Helper::url('/doctor/dashboard') ?>">Dashboard</a></li>
          <li><a href="<?= \App\Helpers\Helper::url('/doctor/consultations') ?>">Consultations</a></li>
          <li class="active">Prescription</li>
        </ol>
      </nav>
      <p class="rx-page__eyebrow">Prescription</p>
      <h1 class="ux-page-header__title">Prescription</h1>
      <p class="ux-page-header__subtitle">
        <?= $isIssued
          ? 'Issued prescription for ' . \App\Helpers\Helper::escape($patientName) . '.'
          : 'Create and issue a prescription for this patient.' ?>
      </p>
    </div>
    <div class="ux-page-header__right rx-header__actions">
      <a href="<?= $consultationUrl ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
        Back to Consultation
      </a>
      <a href="<?= $consultationUrl ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-clipboard2-pulse me-1" aria-hidden="true"></i>
        Clinical record
      </a>
      <?php if ($isIssued): ?>
        <a href="<?= \App\Helpers\Helper::url('/doctor/consultations/' . $requestId . '/download-prescription') ?>" class="btn btn-primary btn-sm">
          <i class="bi bi-download me-1" aria-hidden="true"></i>
          Download Prescription
        </a>
      <?php endif; ?>
    </div>
  </header>

  <?php if ($status !== 'Completed' || $recordStatus !== \App\Models\ConsultationRecord::STATUS_FINAL): ?>
    <div class="alert alert-warning" role="alert">
      Complete the consultation and finalize the clinical record before issuing a prescription.
    </div>
  <?php elseif (!$isIssued && !$hasSignature): ?>
    <div class="alert alert-warning" role="alert">
      Upload your signature in
      <a href="<?= \App\Helpers\Helper::url('/doctor/profile/edit') ?>" class="alert-link">My Profile</a>
      before issuing a prescription. The signature on file is attached automatically and cannot be selected from another doctor.
    </div>
  <?php elseif ($isIssued): ?>
    <div class="alert alert-info" role="status">
      This prescription is linked to the completed consultation and cannot be edited.
    </div>
  <?php endif; ?>

  <?php if ($canCreate): ?>
    <form
      method="POST"
      action="<?= \App\Helpers\Helper::url('/doctor/consultations/' . $requestId . '/prescription') ?>"
      id="rx-create-form"
      class="rx-page-form"
      data-rx-patient-name="<?= \App\Helpers\Helper::escape($patientName) ?>"
      novalidate
    >
      <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken) ?>">
      <?php require __DIR__ . '/../../partials/shared/_prescription_document.php'; ?>

      <section class="rx-review-card rx-screen-only" aria-labelledby="rx-review-heading">
        <div class="rx-card__head">
          <h2 class="rx-card__title" id="rx-review-heading">Review</h2>
          <p class="rx-card__hint">Confirm the patient and medication details before saving.</p>
        </div>
        <div id="rx-review-summary" class="rx-review__body">
          <p class="rx-review__empty">Enter a medication to see a concise summary here.</p>
        </div>
      </section>

      <div class="rx-actions rx-print-hide">
        <a href="<?= \App\Helpers\Helper::url('/doctor/consultations') ?>" class="btn btn-outline-secondary rx-actions__skip">
          Skip — no medication required
        </a>
        <a href="<?= $consultationUrl ?>" class="btn btn-outline-secondary rx-actions__cancel">
          Cancel
        </a>
        <button type="button"
                class="btn btn-primary rx-actions__submit"
                id="rx-finalize-btn"
                data-bs-toggle="modal"
                data-bs-target="#rx-save-modal">
          <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>
          Save Prescription
        </button>
      </div>
    </form>

    <div class="modal fade" id="rx-save-modal" tabindex="-1" aria-labelledby="rx-save-modal-label" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content rounded-3">
          <div class="modal-header border-bottom">
            <h2 class="modal-title h5" id="rx-save-modal-label">Save this prescription?</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p class="mb-3">Review the medication details before saving. Patient and doctor identity are taken from the completed consultation and cannot be changed.</p>
            <div id="rx-modal-review" class="rx-review__body rx-review__body--modal"></div>
            <p class="mb-0 text-muted small">Saving links this prescription to the finalized consultation. It cannot be edited afterwards.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Review prescription</button>
            <button type="submit" class="btn btn-primary" id="rx-save-confirm-btn" form="rx-create-form">
              Save Prescription
            </button>
          </div>
        </div>
      </div>
    </div>
  <?php else: ?>
    <?php require __DIR__ . '/../../partials/shared/_prescription_document.php'; ?>
  <?php endif; ?>
</section>
