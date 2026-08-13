<?php

$request = $request ?? [];
$status = (string) ($request['status'] ?? 'Pending');
$requestId = (int) ($request['id'] ?? 0);

require __DIR__ . '/../../partials/shared/status_helper.php';

$badgeClass = ux_status_badge_class($status, 'ux-badge--neutral');
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-page-header d-flex flex-column flex-xl-row justify-content-between align-items-xl-start align-items-xl-center gap-3 mb-4">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/patient/dashboard') ?>">Dashboard</a></li>
        <li><a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests') ?>">History</a></li>
        <li class="active">Request #<?= \App\Helpers\Helper::escape((string) $requestId) ?></li>
      </ol>
      <span class="section-badge mb-3">
        <i class="bi bi-clipboard2-data"></i>
        Consultation Details
      </span>
      <h2 class="ux-page-header__title h4 mb-2">Request #<?= \App\Helpers\Helper::escape((string) $requestId) ?></h2>
      <p class="ux-page-header__subtitle text-muted mb-0">Review your consultation booking request, selected slot, and current status.</p>
    </div>
    <div class="ux-page-header__right d-flex flex-wrap gap-2">
      <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-clipboard2-check me-2"></i>
        Back to History
      </a>
      <a href="<?= \App\Helpers\Helper::url('/patient/dashboard') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-arrow-left me-2"></i>
        Dashboard
      </a>
      <?php
        $videoJoin = $videoJoin ?? null;
        if (is_array($videoJoin)) {
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
             class="btn btn-primary btn-sm d-inline-flex align-items-center justify-content-center"
             aria-label="Join the video consultation room now">
            <i class="bi bi-camera-video-fill me-2"></i>
            Join Consultation
          </a>
        <?php else: ?>
          <button type="button"
                  class="btn btn-primary btn-sm d-inline-flex align-items-center justify-content-center"
                  <?= $hasJoinUrl ? '' : 'disabled' ?>
                  <?= $hasJoinUrl ? '' : 'aria-disabled="true"' ?>
                  <?php if ($reason !== ''): ?>
                    title="<?= \App\Helpers\Helper::escape($reason) ?>"
                  <?php endif; ?>>
            <i class="bi bi-camera-video me-2"></i>
            <?= $status === 'ended' ? 'Consultation Ended' : ($status === 'early' ? 'Not Yet Open' : 'Join Consultation') ?>
          </button>
        <?php endif; ?>
        <?php if ($reason !== '' && $status !== 'unavailable'): ?>
          <small class="d-flex align-items-center text-muted mt-1 mt-lg-0">
            <i class="bi bi-info-circle me-1"></i>
            <?= \App\Helpers\Helper::escape($reason) ?>
          </small>
        <?php endif; ?>
      <?php
            endif;
        }
      ?>
    </div>
  </div>

  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="row g-4">
        <div class="col-xl-4">
          <div class="admin-summary-card h-100">
            <span class="admin-summary-label">Current Status</span>
            <strong class="admin-summary-value">
              <span class="ux-badge <?= \App\Helpers\Helper::escape($badgeClass) ?>"><?= \App\Helpers\Helper::escape($status) ?></span>
            </strong>
            <span class="admin-summary-meta">Status updates reflect the clinical workflow progression.</span>
          </div>
        </div>
        <div class="col-xl-4">
          <div class="admin-summary-card h-100">
            <span class="admin-summary-label">Consultation Slot</span>
            <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($request['consultation_date'] ?? ''), 'd M Y', 'Not available')) ?></strong>
            <span class="admin-summary-meta">
              <?= \App\Helpers\Helper::escape(substr((string) ($request['start_time'] ?? ''), 0, 5)) ?> - <?= \App\Helpers\Helper::escape(substr((string) ($request['end_time'] ?? ''), 0, 5)) ?>
            </span>
          </div>
        </div>
        <div class="col-xl-4">
          <div class="admin-summary-card h-100">
            <span class="admin-summary-label">Doctor</span>
            <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($request['doctor_name'] ?? 'Doctor')) ?></strong>
            <span class="admin-summary-meta"><?= \App\Helpers\Helper::escape((string) ($request['doctor_title'] ?? 'Medical Practitioner')) ?> · <?= \App\Helpers\Helper::escape((string) ($request['specialization'] ?? 'General Practice')) ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <h3 class="h5 mb-3">Chief Complaint</h3>
      <div class="dashboard-inline-callout">
        <p class="text-muted mb-0"><?= nl2br(\App\Helpers\Helper::escape((string) ($request['reason'] ?? ''))) ?></p>
      </div>
    </div>
  </div>
</section>
