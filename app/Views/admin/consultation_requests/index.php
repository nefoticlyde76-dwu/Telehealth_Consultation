<?php

$filters = $filters ?? [
    'search' => '',
    'status' => 'Pending',
    'doctor_id' => 0,
    'consultation_date' => '',
];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0, 'per_page' => 20];
$summary = $summary ?? [];
$requests = $requests ?? [];
$statusOptions = $statusOptions ?? [];
$doctorOptions = $doctorOptions ?? [];
$selectedId = (int) ($selectedId ?? 0);
$selectedRequest = is_array($selectedRequest ?? null) ? $selectedRequest : null;
$queuePosition = (int) ($queuePosition ?? 0);
$csrfToken = $csrfToken ?? '';

require_once __DIR__ . '/../../partials/shared/status_helper.php';

$totalItems = (int) ($pagination['total_items'] ?? 0);
$buildQueueUrl = static function (array $overrides = []) use ($filters, $pagination): string {
    $page = (int) ($overrides['page'] ?? ($pagination['current_page'] ?? 1));
    $selected = array_key_exists('selected', $overrides) ? (int) $overrides['selected'] : null;
    $merged = array_merge($filters, $overrides);
    unset($merged['page'], $merged['selected']);

    return \App\Helpers\Helper::url(\App\Services\AdminConsultationService::workspacePath($merged, $selected, $page));
};
?>

<section class="mb-4 ux-review-workspace" data-consultation-queue-workspace>
  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/admin/dashboard') ?>">Dashboard</a></li>
        <li class="active">Consultation Requests</li>
      </ol>
      <h2 class="ux-page-header__title">Consultation Requests</h2>
      <p class="ux-page-header__subtitle">Review each request, then approve or reject without leaving this workspace.</p>
    </div>
    <div class="ux-page-header__right">
      <span class="ux-chip ux-badge--dotless">
        <i class="bi bi-list-check"></i>
        <span>
          <?php
          $queueNoun = ((string) ($filters['status'] ?? '')) === 'Pending'
              ? 'pending requests'
              : 'matching requests';
          ?>
          <?php if ($queuePosition > 0 && $totalItems > 0): ?>
            <?= (int) $queuePosition ?> of <?= (int) $totalItems ?> <?= $queueNoun ?>
          <?php else: ?>
            <?= (int) $totalItems ?> <?= $queueNoun ?>
          <?php endif; ?>
        </span>
      </span>
    </div>
  </div>

  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="row g-3 mb-4">
    <div class="col-sm-4">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--amber"><i class="bi bi-hourglass-split"></i></div>
        <div>
          <div class="ux-stat__value"><?= (int) ($summary['pending_requests'] ?? 0) ?></div>
          <div class="ux-stat__label">Pending Requests</div>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--navy"><i class="bi bi-calendar2-day"></i></div>
        <div>
          <div class="ux-stat__value"><?= (int) ($summary['today_requests'] ?? 0) ?></div>
          <div class="ux-stat__label">Today’s Requests</div>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--mint"><i class="bi bi-check2-circle"></i></div>
        <div>
          <div class="ux-stat__value"><?= (int) ($summary['approved_requests'] ?? 0) ?></div>
          <div class="ux-stat__label">Approved</div>
        </div>
      </div>
    </div>
  </div>

  <div class="ux-card ux-filter">
    <div class="card-header">
      <h3 class="h6">
        <i class="bi bi-funnel-fill ux-filter__header-icon"></i>
        Filter queue
      </h3>
    </div>
    <form method="GET" action="<?= \App\Helpers\Helper::url('/admin/consultation-requests') ?>" novalidate>
      <div class="row g-3 align-items-end">
        <div class="col-lg-3">
          <label for="search" class="form-label">Search</label>
          <input type="text" class="form-control" id="search" name="search"
                 value="<?= \App\Helpers\Helper::escape((string) ($filters['search'] ?? '')) ?>"
                 placeholder="Patient, doctor, or request ID">
        </div>
        <div class="col-lg-2">
          <label for="status" class="form-label">Status</label>
          <select class="form-select" id="status" name="status" aria-label="Filter by status">
            <option value="">All statuses</option>
            <?php foreach ($statusOptions as $statusOption): ?>
              <option value="<?= \App\Helpers\Helper::escape((string) $statusOption) ?>" <?= (string) ($filters['status'] ?? '') === (string) $statusOption ? 'selected' : '' ?>>
                <?= \App\Helpers\Helper::escape((string) $statusOption) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-3">
          <label for="doctor_id" class="form-label">Doctor</label>
          <select class="form-select" id="doctor_id" name="doctor_id" aria-label="Filter by doctor">
            <option value="">All doctors</option>
            <?php foreach ($doctorOptions as $doctorOption): ?>
              <?php $doctorId = (int) ($doctorOption['id'] ?? 0); ?>
              <option value="<?= \App\Helpers\Helper::escape((string) $doctorId) ?>" <?= (int) ($filters['doctor_id'] ?? 0) === $doctorId ? 'selected' : '' ?>>
                <?= \App\Helpers\Helper::escape((string) ($doctorOption['full_name'] ?? 'Doctor')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-lg-4">
          <label for="consultation_date" class="form-label">Consultation date</label>
          <input type="date" class="form-control" id="consultation_date" name="consultation_date"
                 value="<?= \App\Helpers\Helper::escape((string) ($filters['consultation_date'] ?? '')) ?>">
        </div>
        <div class="col-12">
          <div class="ux-filter__actions">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-funnel-fill me-1"></i>
              Apply Filters
            </button>
            <a href="<?= \App\Helpers\Helper::url('/admin/consultation-requests') ?>" class="btn btn-outline-primary btn-sm">Reset</a>
          </div>
        </div>
      </div>
    </form>
  </div>

  <div class="ux-review-workspace__grid">
    <aside class="ux-card ux-queue" aria-label="Consultation request queue">
      <div class="ux-queue__header">
        <h3 class="h6 mb-0">Queue</h3>
        <span class="text-muted small"><?= (int) $totalItems ?></span>
      </div>
      <?php if ($requests === []): ?>
        <div class="ux-empty p-4">
          <div class="ux-empty__icon"><i class="bi bi-clipboard2-check"></i></div>
          <h4 class="ux-empty__title">No matching requests</h4>
          <p class="ux-empty__text mb-0">Adjust the filters or wait for new consultation bookings.</p>
        </div>
      <?php else: ?>
        <ul class="ux-queue__list">
          <?php foreach ($requests as $request): ?>
            <?php
            $requestId = (int) ($request['id'] ?? 0);
            $isActive = $requestId === $selectedId;
            $status = (string) ($request['status'] ?? 'Pending');
            $dateLabel = \App\Helpers\Helper::formatDate((string) ($request['consultation_date'] ?? ''), 'd M Y', 'Date TBD');
            $timeLabel = substr((string) ($request['start_time'] ?? ''), 0, 5);
            ?>
            <li>
              <a class="ux-queue__item<?= $isActive ? ' is-active' : '' ?>"
                 href="<?= $buildQueueUrl(['selected' => $requestId, 'page' => (int) ($pagination['current_page'] ?? 1)]) ?>"
                 <?= $isActive ? 'aria-current="true"' : '' ?>>
                <div class="d-flex justify-content-between gap-2 align-items-start">
                  <strong class="ux-queue__name"><?= \App\Helpers\Helper::escape((string) ($request['patient_name'] ?? 'Patient')) ?></strong>
                  <span class="ux-badge <?= ux_status_badge_class($status) ?>"><?= \App\Helpers\Helper::escape($status) ?></span>
                </div>
                <div class="ux-queue__meta">
                  <?= \App\Helpers\Helper::escape((string) ($request['doctor_name'] ?? 'Doctor')) ?>
                  · <?= \App\Helpers\Helper::escape((string) ($request['specialization'] ?? 'General')) ?>
                </div>
                <div class="ux-queue__meta">
                  <?= \App\Helpers\Helper::escape($dateLabel) ?>
                  <?= $timeLabel !== '' ? ' · ' . \App\Helpers\Helper::escape($timeLabel) : '' ?>
                </div>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if (is_array($pagination) && (int) ($pagination['total_pages'] ?? 0) > 1): ?>
        <?php
        $page = (int) ($pagination['current_page'] ?? 1);
        $totalPages = (int) ($pagination['total_pages'] ?? 1);
        ?>
        <nav class="ux-queue__pager" aria-label="Queue pagination">
          <?php if ($page > 1): ?>
            <a href="<?= $buildQueueUrl(['page' => $page - 1, 'selected' => 0]) ?>" class="btn btn-outline-primary btn-sm">Previous</a>
          <?php endif; ?>
          <span class="small text-muted">Page <?= $page ?> of <?= $totalPages ?></span>
          <?php if ($page < $totalPages): ?>
            <a href="<?= $buildQueueUrl(['page' => $page + 1, 'selected' => 0]) ?>" class="btn btn-outline-primary btn-sm">Next</a>
          <?php endif; ?>
        </nav>
      <?php endif; ?>
    </aside>

    <div class="ux-review-workspace__detail">
      <?php require __DIR__ . '/_queue_detail.php'; ?>
    </div>
  </div>
</section>
