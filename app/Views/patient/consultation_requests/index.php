<?php

$requests = $requests ?? [];
$summary = $summary ?? [];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0];

require __DIR__ . '/../../partials/shared/status_helper.php';

$totalItems = (int) ($pagination['total_items'] ?? 0);
$buildPageUrl = static function (int $page): string {
    $queryString = http_build_query(['page' => $page]);
    return \App\Helpers\Helper::url('/patient/consultation-requests') . ($queryString !== '' ? '?' . $queryString : '');
};
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/patient/dashboard') ?>">Dashboard</a></li>
        <li class="active">Consultation History</li>
      </ol>
      <h2 class="ux-page-header__title">Consultation History</h2>
      <p class="ux-page-header__subtitle">Join an approved consultation when it is open, or open a completed consultation record and prescription from here.</p>
    </div>
    <div class="ux-page-header__right">
      <span class="ux-chip ux-badge--dotless">
        <i class="bi bi-clipboard2-check-fill"></i>
        <span><?= $totalItems ?> requests visible</span>
      </span>
      <a href="<?= \App\Helpers\Helper::url('/patient/doctors') ?>" class="btn btn-primary btn-primary-xl">
        <i class="bi bi-person-badge me-2"></i>
        Book a Slot
      </a>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--amber">
          <i class="bi bi-clock-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['pending_requests'] ?? 0) ?></div>
          <div class="ux-stat__label">Pending</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--success">
          <i class="bi bi-check-circle-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['approved_requests'] ?? 0) ?></div>
          <div class="ux-stat__label">Approved</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--cyan">
          <i class="bi bi-calendar3-event-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['upcoming_appointments'] ?? 0) ?></div>
          <div class="ux-stat__label">Upcoming</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--navy">
          <i class="bi bi-clipboard2-data-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['completed_requests'] ?? 0) ?></div>
          <div class="ux-stat__label">Completed</div>
        </div>
      </div>
    </div>
  </div>

  <div class="ux-card">
    <div class="ux-table-wrapper border-0">
      <div class="table-responsive">
        <table class="ux-table">
          <caption class="visually-hidden">Consultation history with doctor, date, specialization, status, prescription, and view actions.</caption>
          <thead>
            <tr>
              <th scope="col">Consultation date</th>
              <th scope="col">Doctor</th>
              <th scope="col">Type</th>
              <th scope="col">Status</th>
              <th scope="col">Prescription</th>
              <th scope="col" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($requests === []): ?>
              <tr>
                <td colspan="6" class="ux-table__empty-state">
                  <div class="ux-empty">
                    <div class="ux-empty__icon">
                      <i class="bi bi-clipboard2-x"></i>
                    </div>
                    <h4 class="ux-empty__title">No consultations in your history yet</h4>
                    <p class="ux-empty__text">Book an available slot to start a consultation. Completed records and prescriptions will appear here.</p>
                    <div class="ux-empty__action">
                      <a href="<?= \App\Helpers\Helper::url('/patient/doctors') ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-person-badge me-1"></i>
                        Browse Doctors
                      </a>
                    </div>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($requests as $request): ?>
                <?php
                $status = (string) ($request['status'] ?? 'Pending');
                $statusBadge = ux_status_badge_class($status);
                $requestId = (int) ($request['id'] ?? 0);
                $isCompleted = $status === 'Completed';
                $hasPrescription = (int) ($request['has_prescription'] ?? 0) === 1;
                $detailUrl = \App\Helpers\Helper::url('/patient/consultation-requests/' . (string) $requestId);
                $timeLabel = substr((string) ($request['start_time'] ?? ''), 0, 5) . ' - ' . substr((string) ($request['end_time'] ?? ''), 0, 5);
                $videoJoin = $request['videoJoin'] ?? null;
                $joinUrl = is_array($videoJoin) ? (string) ($videoJoin['joinUrl'] ?? '') : '';
                $canJoinNow = is_array($videoJoin) ? (bool) ($videoJoin['canJoin'] ?? false) : false;
                $joinStatus = is_array($videoJoin) ? (string) ($videoJoin['status'] ?? 'unavailable') : 'unavailable';
                $joinReason = is_array($videoJoin) ? (string) ($videoJoin['reason'] ?? '') : '';
                $showJoin = $joinUrl !== '' && in_array($joinStatus, ['open', 'early', 'ended'], true);
                $joinLabel = 'Join Consultation';
                if ($joinStatus === 'early') {
                    $joinLabel = 'Not Yet Open';
                }
                if ($joinStatus === 'ended') {
                    $joinLabel = 'Consultation Ended';
                }
                ?>
                <tr>
                  <td>
                    <div class="d-flex flex-column">
                      <strong><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($request['consultation_date'] ?? ''), 'd M Y', 'Not available')) ?></strong>
                      <span class="text-muted small"><?= \App\Helpers\Helper::escape($timeLabel) ?></span>
                    </div>
                  </td>
                  <td>
                    <div class="d-flex flex-column">
                      <strong><?= \App\Helpers\Helper::escape((string) ($request['doctor_name'] ?? 'Doctor')) ?></strong>
                      <span class="text-muted small"><?= \App\Helpers\Helper::escape((string) ($request['doctor_title'] ?? 'Medical Practitioner')) ?></span>
                    </div>
                  </td>
                  <td>
                    <span class="ux-badge ux-badge--neutral">
                      <?= \App\Helpers\Helper::escape((string) ($request['specialization'] ?? 'General Practice')) ?>
                    </span>
                  </td>
                  <td>
                    <span class="ux-badge <?= $statusBadge ?>">
                      <?= \App\Helpers\Helper::escape($status) ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($hasPrescription): ?>
                      <span class="ux-badge ux-badge--approved">Issued</span>
                    <?php elseif ($isCompleted): ?>
                      <span class="text-muted small">Not issued</span>
                    <?php else: ?>
                      <span class="text-muted small">—</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end">
                    <div class="ux-table__actions">
                      <?php if ($isCompleted): ?>
                        <a href="<?= \App\Helpers\Helper::escape($detailUrl) ?>" class="btn btn-outline-primary btn-sm" aria-label="View consultation record">
                          View Record
                        </a>
                        <?php if ($hasPrescription): ?>
                          <a href="<?= \App\Helpers\Helper::escape($detailUrl . '#prescription') ?>" class="btn btn-outline-primary btn-sm" aria-label="View prescription for this consultation">
                            View Prescription
                          </a>
                        <?php endif; ?>
                      <?php elseif ($showJoin): ?>
                        <?php if ($canJoinNow): ?>
                          <a href="<?= \App\Helpers\Helper::escape($joinUrl) ?>"
                             class="btn btn-primary btn-sm"
                             aria-label="Join the video consultation">
                            <i class="bi bi-camera-video-fill me-1"></i>
                            Join Consultation
                          </a>
                        <?php else: ?>
                          <button type="button"
                                  class="btn btn-primary btn-sm"
                                  disabled
                                  aria-disabled="true"
                                  <?php if ($joinReason !== ''): ?>
                                    title="<?= \App\Helpers\Helper::escape($joinReason) ?>"
                                  <?php endif; ?>>
                            <i class="bi bi-camera-video me-1"></i>
                            <?= \App\Helpers\Helper::escape($joinLabel) ?>
                          </button>
                        <?php endif; ?>
                      <?php else: ?>
                        <a href="<?= \App\Helpers\Helper::escape($detailUrl) ?>" class="btn btn-outline-primary btn-sm" aria-label="View consultation details">
                          View Details
                        </a>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
      <div class="card-footer border-0 bg-transparent pt-4 pb-0">
        <nav class="admin-pagination d-flex justify-content-end align-items-center gap-2 flex-wrap" aria-label="Patient consultation history pagination">
          <a class="btn btn-outline-primary btn-sm <?= ($pagination['current_page'] ?? 1) <= 1 ? 'disabled' : '' ?>" href="<?= ($pagination['current_page'] ?? 1) <= 1 ? '#' : $buildPageUrl((int) $pagination['current_page'] - 1) ?>">
            <i class="bi bi-chevron-left me-1"></i>
            Previous
          </a>
          <span class="small text-muted">
            Page <?= (int) ($pagination['current_page'] ?? 1) ?> of <?= (int) ($pagination['total_pages'] ?? 1) ?>
          </span>
          <a class="btn btn-outline-primary btn-sm <?= ($pagination['current_page'] ?? 1) >= ($pagination['total_pages'] ?? 1) ? 'disabled' : '' ?>" href="<?= ($pagination['current_page'] ?? 1) >= ($pagination['total_pages'] ?? 1) ? '#' : $buildPageUrl((int) $pagination['current_page'] + 1) ?>">
            Next
            <i class="bi bi-chevron-right ms-1"></i>
          </a>
        </nav>
      </div>
    <?php endif; ?>
  </div>
</section>
