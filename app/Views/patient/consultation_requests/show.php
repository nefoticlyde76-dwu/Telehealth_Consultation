<?php

$request = $request ?? [];
$requestStatus = (string) ($request['status'] ?? 'Pending');
$requestId = (int) ($request['id'] ?? 0);
$isCompleted = $requestStatus === 'Completed';

require __DIR__ . '/../../partials/shared/status_helper.php';

$viewerRole = 'patient';
$summaryPartyLabel = 'Doctor';
$summaryPartyName = (string) ($request['doctor_name'] ?? 'Doctor');
$summaryPartyMeta = trim(implode(' · ', array_filter([
    (string) ($request['doctor_title'] ?? ''),
    (string) ($request['specialization'] ?? 'General Practice'),
], static fn (string $value): bool => $value !== '')));
?>

<div class="cr-page">
  <div class="cr-print-hide">
    <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

    <header class="cr-header">
      <div class="cr-header__copy">
        <ol class="cr-breadcrumb">
          <li><a href="<?= \App\Helpers\Helper::url('/patient/dashboard') ?>">Dashboard</a></li>
          <li><a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests') ?>">History</a></li>
          <li class="active"><?= $isCompleted ? 'Consultation Record' : 'Consultation Details' ?></li>
        </ol>
        <h1 class="cr-title"><?= $isCompleted ? 'Consultation Record' : 'Consultation Details' ?></h1>
        <p class="cr-description">
          <?= $isCompleted
            ? 'Completed historical record. Clinical notes and prescriptions are read-only.'
            : 'Review your appointment details. Join the video consultation only when the scheduled session is open.' ?>
        </p>
      </div>
      <div class="cr-header__actions">
        <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests') ?>" class="cr-btn">
          Consultations
        </a>
        <?php
          $videoJoin = $videoJoin ?? null;
          if (!$isCompleted && is_array($videoJoin)) {
              $canJoinNow  = (bool) ($videoJoin['canJoin'] ?? false);
              $status      = (string) ($videoJoin['status'] ?? 'unavailable');
              $reason      = (string) ($videoJoin['reason'] ?? '');
              $joinUrl     = (string) ($videoJoin['joinUrl'] ?? '');
              $hasJoinUrl  = $joinUrl !== '';
              $isCancelledOrRejected = $status === 'unavailable' && in_array(strtolower(trim((string) ($request['status'] ?? ''))), ['rejected', 'cancelled'], true);
              $showButton = $hasJoinUrl && !$isCancelledOrRejected;

              if ($showButton):
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
                    <?= $hasJoinUrl ? '' : 'disabled' ?>
                    <?= $hasJoinUrl ? '' : 'aria-disabled="true"' ?>
                    <?php if ($reason !== ''): ?>
                      title="<?= \App\Helpers\Helper::escape($reason) ?>"
                    <?php endif; ?>>
              <i class="bi bi-camera-video me-1"></i>
              <?= $status === 'ended' ? 'Consultation Ended' : ($status === 'early' ? 'Not Yet Open' : 'Join Consultation') ?>
            </button>
          <?php endif; ?>
        <?php
              endif;
          }
        ?>
      </div>
    </header>
  </div>

  <?php require __DIR__ . '/../../partials/shared/_consultation_record_details.php'; ?>
</div>
