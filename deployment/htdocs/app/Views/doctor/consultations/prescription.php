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
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-page-header d-flex flex-column flex-xl-row justify-content-between align-items-xl-start gap-3 mb-4">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/doctor/dashboard') ?>">Dashboard</a></li>
        <li><a href="<?= \App\Helpers\Helper::url('/doctor/consultations') ?>">Consultations</a></li>
        <li class="active">Prescription</li>
      </ol>
      <h2 class="ux-page-header__title">Prescription</h2>
      <div class="mt-3">
        <?php
        $personName = $patientName;
        $personPhoto = $request['patient_photo_path'] ?? null;
        $personMeta = $isIssued
          ? 'Issued prescription'
          : 'Create a prescription for this completed consultation';
        $personSize = 'sm';
        require __DIR__ . '/../../partials/shared/person_row.php';
        ?>
      </div>
      <p class="ux-page-header__subtitle mb-0 mt-2">
        <?= $isIssued
          ? 'Issued prescription for ' . \App\Helpers\Helper::escape($patientName) . '.'
          : 'Create a prescription for the completed consultation with ' . \App\Helpers\Helper::escape($patientName) . '.' ?>
      </p>
    </div>
    <div class="ux-page-header__right d-flex flex-wrap gap-2">
      <a href="<?= \App\Helpers\Helper::url('/doctor/consultations/' . $requestId) ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-clipboard2-pulse me-1"></i>
        Clinical record
      </a>
      <?php if ($isIssued): ?>
        <a href="<?= \App\Helpers\Helper::url('/doctor/consultations/' . $requestId . '/download-prescription') ?>" class="btn btn-primary btn-sm">
          <i class="bi bi-download me-1"></i>
          Download Prescription
        </a>
      <?php endif; ?>
      <a href="<?= \App\Helpers\Helper::url('/doctor/consultations') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>
        Consultations
      </a>
    </div>
  </div>

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
    <form method="POST" action="<?= \App\Helpers\Helper::url('/doctor/consultations/' . $requestId . '/prescription') ?>" id="rx-create-form" class="rx-page-form" novalidate>
      <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken) ?>">
      <?php require __DIR__ . '/../../partials/shared/_prescription_document.php'; ?>
      <div class="d-flex flex-wrap gap-2 justify-content-center mt-3 rx-print-hide">
        <a href="<?= \App\Helpers\Helper::url('/doctor/consultations') ?>" class="btn btn-outline-secondary">
          Skip — no medication required
        </a>
        <button type="button"
                class="btn btn-primary"
                id="rx-finalize-btn"
                data-bs-toggle="modal"
                data-bs-target="#rx-save-modal">
          <i class="bi bi-check2-circle me-1"></i>
          Save Prescription
        </button>
      </div>
    </form>

    <div class="modal fade" id="rx-save-modal" tabindex="-1" aria-labelledby="rx-save-modal-label" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-3">
          <div class="modal-header border-bottom">
            <h2 class="modal-title h5" id="rx-save-modal-label">Save this prescription?</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <p class="mb-2">Review the medication details before saving. Patient and doctor identity are taken from the completed consultation and cannot be changed.</p>
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
