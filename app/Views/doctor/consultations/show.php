<?php

$request = $request ?? [];
$requestStatus = (string) ($request['status'] ?? 'Pending');
$requestId = (int) ($request['id'] ?? 0);
$isCompleted = $requestStatus === 'Completed';
$hasPrescription = is_array($prescriptions ?? null) && $prescriptions !== [];

require __DIR__ . '/../../partials/shared/status_helper.php';

$viewerRole = 'doctor';
$roomUrl = \App\Helpers\Helper::url('/doctor/consultations/' . $requestId . '/room');
$prescriptionUrl = \App\Helpers\Helper::url('/doctor/consultations/' . $requestId . '/prescription');
$summaryPartyLabel = 'Patient';
$summaryPartyName = (string) ($request['patient_name'] ?? 'Patient');
$summaryPartyMeta = trim((string) ($request['patient_gender'] ?? ''));
?>

<div class="cr-page">
  <div class="cr-print-hide">
    <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

    <header class="cr-header">
      <div class="cr-header__copy">
        <ol class="cr-breadcrumb">
          <li><a href="<?= \App\Helpers\Helper::url('/doctor/dashboard') ?>">Dashboard</a></li>
          <li><a href="<?= \App\Helpers\Helper::url('/doctor/consultations') ?>">Consultations</a></li>
          <li class="active"><?= $isCompleted ? 'Consultation Record' : 'Consultation Details' ?></li>
        </ol>
        <h1 class="cr-title"><?= $isCompleted ? 'Consultation Record' : 'Consultation Details' ?></h1>
        <p class="cr-description">
          <?= $isCompleted
            ? 'Completed historical record. Clinical notes and prescriptions are read-only.'
            : 'Review this consultation. Use Join Consultation only for the live video session.' ?>
        </p>
      </div>
      <div class="cr-header__actions">
        <a href="<?= \App\Helpers\Helper::url('/doctor/consultations') ?>" class="cr-btn">
          Consultations
        </a>
        <?php if ($isCompleted && is_array($clinicalRecord ?? null) && (string) (($clinicalRecord['record_status'] ?? '')) === \App\Models\ConsultationRecord::STATUS_FINAL): ?>
          <a href="<?= \App\Helpers\Helper::url('/doctor/consultations/' . $requestId . '/download-record') ?>" class="cr-btn">
            <i class="bi bi-download me-1"></i>
            Download Consultation Record
          </a>
        <?php endif; ?>
        <?php if ($isCompleted): ?>
          <a href="<?= \App\Helpers\Helper::escape($prescriptionUrl) ?>" class="cr-btn">
            <?= $hasPrescription ? 'View Prescription' : 'Create Prescription' ?>
          </a>
        <?php endif; ?>
        <?php if ($isCompleted && $hasPrescription): ?>
          <a href="<?= \App\Helpers\Helper::url('/doctor/consultations/' . $requestId . '/download-prescription') ?>" class="cr-btn cr-btn--primary">
            <i class="bi bi-download me-1"></i>
            Download Prescription
          </a>
        <?php endif; ?>
        <?php
          $videoJoin = $videoJoin ?? null;
          if (!$isCompleted && is_array($videoJoin)) {
              $canJoinNow  = (bool) ($videoJoin['canJoin'] ?? false);
              $status      = (string) ($videoJoin['status'] ?? 'unavailable');
              $reason      = (string) ($videoJoin['reason'] ?? '');
              $joinUrl     = (string) ($videoJoin['joinUrl'] ?? '');
              $hasJoinUrl  = $joinUrl !== '';

              if ($hasJoinUrl):
        ?>
          <?php if ($canJoinNow): ?>
            <a href="<?= \App\Helpers\Helper::escape($joinUrl) ?>"
               class="cr-btn cr-btn--primary"
               aria-label="Join the video consultation room now">
              <i class="bi bi-camera-video me-1"></i>
              Join Consultation
            </a>
          <?php else: ?>
            <button type="button"
                    class="cr-btn cr-btn--primary"
                    disabled
                    aria-disabled="true"
                    <?php if ($reason !== ''): ?>
                      title="<?= \App\Helpers\Helper::escape($reason) ?>"
                    <?php endif; ?>>
              <i class="bi bi-camera-video me-1"></i>
              <?= $status === 'ended' ? 'Room Ended' : ($status === 'early' ? 'Join Soon' : 'Join Consultation') ?>
            </button>
          <?php endif; ?>
        <?php
              endif;
          }
          if ($requestStatus === 'Approved'):
        ?>
          <a href="<?= \App\Helpers\Helper::escape($roomUrl) ?>" class="cr-btn">
            Review &amp; complete
          </a>
        <?php endif; ?>
      </div>
    </header>
  </div>

  <?php require __DIR__ . '/../../partials/shared/_consultation_record_details.php'; ?>
</div>
