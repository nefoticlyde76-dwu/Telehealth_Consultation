<?php

$filters = $filters ?? ['status' => '', 'search' => ''];
$consultations = $consultations ?? [];
$summary = $summary ?? [];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0];
$statusOptions = $statusOptions ?? [];
$csrfToken = $csrfToken ?? '';

require __DIR__ . '/../../partials/shared/status_helper.php';

$totalItems = (int) ($pagination['total_items'] ?? 0);
$buildPageUrl = static function (int $page) use ($filters): string {
    $query = array_filter([
        'status' => $filters['status'] ?? '',
        'search' => $filters['search'] ?? '',
        'page' => $page,
    ], static fn ($value) => $value !== '');

    $queryString = http_build_query($query);

    return \App\Helpers\Helper::url('/doctor/consultations') . ($queryString !== '' ? '?' . $queryString : '');
};
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/doctor/dashboard') ?>">Dashboard</a></li>
        <li class="active">Consultations</li>
      </ol>
      <h2 class="ux-page-header__title">My Consultations</h2>
      <p class="ux-page-header__subtitle">Track upcoming appointments, review chief complaints, join video rooms, and complete consultations after documenting clinical notes.</p>
    </div>
    <div class="ux-page-header__right">
      <span class="ux-chip ux-badge--dotless">
        <i class="bi bi-clipboard2-pulse-fill"></i>
        <span><?= $totalItems ?> consultations visible</span>
      </span>
      <a href="<?= \App\Helpers\Helper::url('/doctor/availability') ?>" class="btn btn-primary btn-primary-xl">
        <i class="bi bi-calendar-week me-2"></i>
        Manage Availability
      </a>
    </div>
  </div>

  <div class="ux-card ux-filter">
    <div class="card-header">
      <h3 class="h6">
        <i class="bi bi-funnel-fill ux-filter__header-icon"></i>
        Filter consultations
      </h3>
    </div>
    <form method="GET" action="<?= \App\Helpers\Helper::url('/doctor/consultations') ?>" class="user-filter-form">
      <div class="row g-3 align-items-end">
        <div class="col-md-7">
          <label for="search" class="form-label">Search consultations</label>
          <input
            type="text"
            class="form-control"
            id="search"
            name="search"
            value="<?= \App\Helpers\Helper::escape((string) ($filters['search'] ?? '')) ?>"
            placeholder="Search by patient name or chief complaint"
          >
        </div>
        <div class="col-md-3">
          <label for="status" class="form-label">Status</label>
          <select class="form-select" id="status" name="status" aria-label="Filter consultations by status">
            <option value="">All statuses</option>
            <?php foreach ($statusOptions as $statusOption): ?>
              <option value="<?= \App\Helpers\Helper::escape((string) $statusOption) ?>" <?= ($filters['status'] ?? '') === $statusOption ? 'selected' : '' ?>>
                <?= \App\Helpers\Helper::escape((string) $statusOption) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <div class="ux-filter__actions">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-funnel-fill me-1"></i>
              Apply
            </button>
            <a href="<?= \App\Helpers\Helper::url('/doctor/consultations') ?>" class="btn btn-outline-primary btn-sm">
              Reset
            </a>
          </div>
        </div>
      </div>
    </form>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-sm-4">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--navy">
          <i class="bi bi-calendar2-check-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['approved_appointments'] ?? 0) ?></div>
          <div class="ux-stat__label">Approved Appointments</div>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--cyan">
          <i class="bi bi-calendar3-event-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['upcoming_consultations'] ?? 0) ?></div>
          <div class="ux-stat__label">Upcoming</div>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--success">
          <i class="bi bi-check2-circle"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['completed_consultations'] ?? 0) ?></div>
          <div class="ux-stat__label">Completed</div>
        </div>
      </div>
    </div>
  </div>

  <div class="ux-card">
    <div class="ux-table-wrapper border-0">
      <div class="table-responsive">
        <table class="ux-table">
          <caption class="visually-hidden">Doctor consultation listing with patient, schedule, chief complaint, status, video join, and completion action.</caption>
          <thead>
            <tr>
              <th scope="col">Patient</th>
              <th scope="col">Consultation Date</th>
              <th scope="col">Time</th>
              <th scope="col">Chief Complaint</th>
              <th scope="col">Status</th>
              <th scope="col">Prescription</th>
              <th scope="col" class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($consultations === []): ?>
              <tr>
                <td colspan="7" class="ux-table__empty-state">
                  <div class="ux-empty">
                    <div class="ux-empty__icon">
                      <i class="bi bi-clipboard2-x"></i>
                    </div>
                    <h4 class="ux-empty__title">No consultations matched the current filters</h4>
                    <p class="ux-empty__text">Approved and completed consultations will appear here.</p>
                    <div class="ux-empty__action">
                      <a href="<?= \App\Helpers\Helper::url('/doctor/consultations') ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-arrow-clockwise me-1"></i>
                        Reset Filters
                      </a>
                    </div>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($consultations as $consultation): ?>
                <?php
                $status = (string) ($consultation['status'] ?? 'Pending');
                $statusBadge = ux_status_badge_class($status);
                $dateLabel = \App\Helpers\Helper::formatDate((string) ($consultation['consultation_date'] ?? ''), 'd M Y', 'Not available');
                $timeLabel = substr((string) ($consultation['start_time'] ?? ''), 0, 5) . ' - ' . substr((string) ($consultation['end_time'] ?? ''), 0, 5);
                $canComplete = $status === 'Approved';
                $isCompleted = $status === 'Completed';
                $hasPrescription = (int) ($consultation['has_prescription'] ?? 0) === 1;
                $consultationId = (int) ($consultation['id'] ?? 0);
                $consultationRoomUrl = \App\Helpers\Helper::url('/doctor/consultations/' . (string) $consultationId . '/room');
                $consultationRecordUrl = \App\Helpers\Helper::url('/doctor/consultations/' . (string) $consultationId);
                $prescriptionUrl = \App\Helpers\Helper::url('/doctor/consultations/' . (string) $consultationId . '/prescription');

                $videoJoin = $consultation['videoJoin'] ?? null;
                $joinUrl    = is_array($videoJoin) ? (string) ($videoJoin['joinUrl'] ?? '') : '';
                $canJoinNow = is_array($videoJoin) ? (bool) ($videoJoin['canJoin'] ?? false) : false;
                $joinStatus = is_array($videoJoin) ? (string) ($videoJoin['status'] ?? 'unavailable') : 'unavailable';
                $joinReason = is_array($videoJoin) ? (string) ($videoJoin['reason'] ?? '') : '';
                $showJoin   = $joinUrl !== '' && in_array($joinStatus, ['open', 'early', 'ended'], true);
                $joinLabel  = 'Join Consultation';
                if ($joinStatus === 'early')   $joinLabel = 'Join Soon';
                if ($joinStatus === 'ended')   $joinLabel = 'Room Ended';
                ?>
                <tr>
                  <td>
                    <div class="d-flex flex-column">
                      <strong><?= \App\Helpers\Helper::escape((string) ($consultation['patient_name'] ?? 'Patient')) ?></strong>
                      <span class="text-muted small"><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($consultation['request_date'] ?? ''), 'd M Y', '')) ?></span>
                    </div>
                  </td>
                  <td><strong><?= \App\Helpers\Helper::escape((string) $dateLabel) ?></strong></td>
                  <td><span class="text-muted small"><?= \App\Helpers\Helper::escape((string) $timeLabel) ?></span></td>
                  <td>
                    <span class="text-muted small"><?= \App\Helpers\Helper::escape(mb_strimwidth((string) ($consultation['reason'] ?? ''), 0, 70, '...')) ?></span>
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
                      <?php if ($showJoin): ?>
                        <?php if ($canJoinNow): ?>
                          <a href="<?= \App\Helpers\Helper::escape($joinUrl) ?>"
                             class="btn btn-primary btn-sm"
                             aria-label="Join video consultation room now">
                            <i class="bi bi-camera-video-fill me-1"></i>
                            <?= \App\Helpers\Helper::escape($joinLabel) ?>
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
                          <?php if ($joinReason !== '' && $joinStatus === 'early'): ?>
                            <small class="text-muted d-block mt-1 text-end">
                              <i class="bi bi-info-circle me-1"></i>
                              <?= \App\Helpers\Helper::escape($joinReason) ?>
                            </small>
                          <?php endif; ?>
                        <?php endif; ?>
                      <?php endif; ?>

                      <?php if ($canComplete): ?>
                        <a href="<?= \App\Helpers\Helper::escape($consultationRoomUrl) ?>" class="btn btn-outline-primary btn-sm">
                          <i class="bi bi-clipboard2-pulse me-1"></i>
                          Review &amp; complete
                        </a>
                      <?php elseif ($isCompleted): ?>
                        <a href="<?= \App\Helpers\Helper::escape($consultationRecordUrl) ?>" class="btn btn-outline-primary btn-sm">
                          <i class="bi bi-clipboard2-pulse me-1"></i>
                          View Record
                        </a>
                        <a href="<?= \App\Helpers\Helper::escape($prescriptionUrl) ?>" class="btn btn-outline-primary btn-sm">
                          <i class="bi bi-capsule me-1"></i>
                          <?= $hasPrescription ? 'View prescription' : 'Create prescription' ?>
                        </a>
                      <?php elseif (!$showJoin): ?>
                        <span class="text-muted small">No action</span>
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
        <nav class="admin-pagination d-flex justify-content-end align-items-center gap-2 flex-wrap" aria-label="Doctor consultations pagination">
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
