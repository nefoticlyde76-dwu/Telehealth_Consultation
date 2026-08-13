<?php

$filters = $filters ?? [
    'search' => '',
    'status' => '',
    'patient_id' => 0,
    'doctor_id' => 0,
    'consultation_date' => '',
];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0, 'per_page' => 10];
$summary = $summary ?? [];
$requests = $requests ?? [];
$statusOptions = $statusOptions ?? [];
$patientOptions = $patientOptions ?? [];
$doctorOptions = $doctorOptions ?? [];

require __DIR__ . '/../../partials/shared/status_helper.php';

$totalItems = (int) ($pagination['total_items'] ?? 0);
$buildPageUrl = static function (int $page) use ($filters): string {
    $query = array_filter([
        'search' => $filters['search'] ?? '',
        'status' => $filters['status'] ?? '',
        'patient_id' => (int) ($filters['patient_id'] ?? 0) > 0 ? (int) ($filters['patient_id'] ?? 0) : '',
        'doctor_id' => (int) ($filters['doctor_id'] ?? 0) > 0 ? (int) ($filters['doctor_id'] ?? 0) : '',
        'consultation_date' => $filters['consultation_date'] ?? '',
        'page' => $page,
    ], static fn ($value) => $value !== '');

    $queryString = http_build_query($query);

    return \App\Helpers\Helper::url('/admin/consultation-requests') . ($queryString !== '' ? '?' . $queryString : '');
};
?>

<section class="mb-4">
  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/admin/dashboard') ?>">Dashboard</a></li>
        <li class="active">Consultation Requests</li>
      </ol>
      <h2 class="ux-page-header__title">Consultation Request Governance</h2>
      <p class="ux-page-header__subtitle">Review, approve, and reject patient consultation bookings with full audit trail.</p>
    </div>
    <div class="ux-page-header__right">
      <span class="ux-chip ux-badge--dotless">
        <i class="bi bi-clipboard2-check-fill"></i>
        <span><?= $totalItems ?> requests visible</span>
      </span>
    </div>
  </div>

  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-card ux-filter">
    <div class="card-header">
      <h3 class="h6">
        <i class="bi bi-funnel-fill ux-filter__header-icon"></i>
        Filter requests
      </h3>
    </div>
    <form method="GET" action="<?= \App\Helpers\Helper::url('/admin/consultation-requests') ?>" novalidate>
      <div class="row g-3 align-items-end">
        <div class="col-lg-4">
          <label for="search" class="form-label">Search</label>
          <input
            type="text"
            class="form-control"
            id="search"
            name="search"
            value="<?= \App\Helpers\Helper::escape((string) ($filters['search'] ?? '')) ?>"
            placeholder="Search by patient, doctor, request id, or complaint"
          >
        </div>

        <div class="col-lg-2">
          <label for="status" class="form-label">Status</label>
          <select class="form-select" id="status" name="status" aria-label="Filter requests by status">
            <option value="">All statuses</option>
            <?php foreach ($statusOptions as $statusOption): ?>
              <option value="<?= \App\Helpers\Helper::escape((string) $statusOption) ?>" <?= ($filters['status'] ?? '') === $statusOption ? 'selected' : '' ?>>
                <?= \App\Helpers\Helper::escape((string) $statusOption) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-lg-2">
          <label for="patient_id" class="form-label">Patient</label>
          <select class="form-select" id="patient_id" name="patient_id" aria-label="Filter requests by patient">
            <option value="">All patients</option>
            <?php foreach ($patientOptions as $patientOption): ?>
              <?php $patientId = (int) ($patientOption['id'] ?? 0); ?>
              <option value="<?= \App\Helpers\Helper::escape((string) $patientId) ?>" <?= (int) ($filters['patient_id'] ?? 0) === $patientId ? 'selected' : '' ?>>
                <?= \App\Helpers\Helper::escape((string) ($patientOption['full_name'] ?? 'Patient')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-lg-2">
          <label for="doctor_id" class="form-label">Doctor</label>
          <select class="form-select" id="doctor_id" name="doctor_id" aria-label="Filter requests by doctor">
            <option value="">All doctors</option>
            <?php foreach ($doctorOptions as $doctorOption): ?>
              <?php $doctorId = (int) ($doctorOption['id'] ?? 0); ?>
              <option value="<?= \App\Helpers\Helper::escape((string) $doctorId) ?>" <?= (int) ($filters['doctor_id'] ?? 0) === $doctorId ? 'selected' : '' ?>>
                <?= \App\Helpers\Helper::escape((string) ($doctorOption['full_name'] ?? 'Doctor')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-lg-2">
          <label for="consultation_date" class="form-label">Consultation Date</label>
          <input
            type="date"
            class="form-control"
            id="consultation_date"
            name="consultation_date"
            value="<?= \App\Helpers\Helper::escape((string) ($filters['consultation_date'] ?? '')) ?>"
          >
        </div>

        <div class="col-12">
          <div class="ux-filter__actions">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-funnel-fill me-1"></i>
              Apply Filters
            </button>
            <a href="<?= \App\Helpers\Helper::url('/admin/consultation-requests') ?>" class="btn btn-outline-primary btn-sm">
              Reset
            </a>
          </div>
        </div>
      </div>
    </form>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--amber">
          <i class="bi bi-clock-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['pending_requests'] ?? 0) ?></div>
          <div class="ux-stat__label">Pending Requests</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--success">
          <i class="bi bi-calendar2-check-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['approved_requests'] ?? 0) ?></div>
          <div class="ux-stat__label">Approved Consultations</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--danger">
          <i class="bi bi-x-circle-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['rejected_requests'] ?? 0) ?></div>
          <div class="ux-stat__label">Rejected Requests</div>
        </div>
      </div>
    </div>
  </div>

  <div class="ux-card">
    <div class="ux-table-wrapper border-0">
      <div class="table-responsive">
        <table class="ux-table">
          <caption class="visually-hidden">Administrator consultation request listing with patient, doctor, slot, status, and detail navigation.</caption>
          <thead>
            <tr>
              <th scope="col">Patient</th>
              <th scope="col">Doctor</th>
              <th scope="col">Consultation Date</th>
              <th scope="col">Time</th>
              <th scope="col">Status</th>
              <th scope="col">Submitted</th>
              <th scope="col" class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($requests === []): ?>
              <tr>
                <td colspan="7" class="ux-table__empty-state">
                  <div class="ux-empty">
                    <div class="ux-empty__icon">
                      <i class="bi bi-search"></i>
                    </div>
                    <h4 class="ux-empty__title">No requests matched filters</h4>
                    <p class="ux-empty__text">Adjust the filters to review other consultation booking requests.</p>
                    <div class="ux-empty__action">
                      <a href="<?= \App\Helpers\Helper::url('/admin/consultation-requests') ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-arrow-clockwise me-1"></i>
                        Reset filters
                      </a>
                    </div>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($requests as $request): ?>
                <?php
                $requestId = (int) ($request['id'] ?? 0);
                $status = (string) ($request['status'] ?? 'Pending');
                $badgeClass = ux_status_badge_class($status, 'ux-badge--pending');
                $iconClass = ux_status_icon_class($status, 'bi-circle-fill');
                $dateLabel = \App\Helpers\Helper::formatDate((string) ($request['consultation_date'] ?? ''), 'd M Y', 'Not available');
                $timeLabel = substr((string) ($request['start_time'] ?? ''), 0, 5) . ' - ' . substr((string) ($request['end_time'] ?? ''), 0, 5);
                $viewAriaLabel = 'View consultation request for ' . ($request['patient_name'] ?? 'patient');
                ?>
                <tr>
                  <td>
                    <div class="d-flex flex-column">
                      <strong><?= \App\Helpers\Helper::escape((string) ($request['patient_name'] ?? 'Patient')) ?></strong>
                      <span class="text-muted small">ID #<?= \App\Helpers\Helper::escape((string) ((int) ($request['patient_id'] ?? 0))) ?></span>
                    </div>
                  </td>
                  <td>
                    <div class="d-flex flex-column">
                      <strong><?= \App\Helpers\Helper::escape((string) ($request['doctor_name'] ?? 'Doctor')) ?></strong>
                      <span class="text-muted small">ID #<?= \App\Helpers\Helper::escape((string) ((int) ($request['doctor_id'] ?? 0))) ?></span>
                    </div>
                  </td>
                  <td>
                    <strong><?= \App\Helpers\Helper::escape((string) $dateLabel) ?></strong>
                  </td>
                  <td>
                    <span class="text-muted small"><?= \App\Helpers\Helper::escape((string) $timeLabel) ?></span>
                  </td>
                  <td>
                    <span class="ux-badge <?= $badgeClass ?>">
                      <i class="bi <?= $iconClass ?> me-1"></i>
                      <?= \App\Helpers\Helper::escape($status) ?>
                    </span>
                  </td>
                  <td>
                    <span class="text-muted small"><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($request['request_date'] ?? ''), 'd M Y', '—')) ?></span>
                  </td>
                  <td class="text-end">
                    <div class="ux-table__actions">
                      <?php if ($requestId > 0): ?>
                        <a
                          href="<?= \App\Helpers\Helper::url('/admin/consultation-requests/' . $requestId) ?>"
                          class="btn btn-outline-primary btn-sm"
                          aria-label="<?= \App\Helpers\Helper::escape($viewAriaLabel) ?>"
                        >
                          <i class="bi bi-eye-fill me-1"></i>
                          View
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
    <?php if (is_array($pagination) && ($pagination['total_pages'] ?? 0) > 1): ?>
      <?php
      $page = (int) ($pagination['current_page'] ?? 1);
      $totalPages = (int) ($pagination['total_pages'] ?? 1);
      ?>
      <div class="card-footer border-0 bg-transparent pt-4 pb-0">
        <nav class="d-flex justify-content-end align-items-center gap-2 flex-wrap" aria-label="Consultation request management pagination">
          <?php if ($page > 1): ?>
            <a href="<?= $buildPageUrl($page - 1) ?>" class="btn btn-outline-primary btn-sm">
              <i class="bi bi-chevron-left me-1"></i>
              Previous
            </a>
          <?php endif; ?>
          <span class="small text-muted">
            Page <?= $page ?> of <?= $totalPages ?>
          </span>
          <?php if ($page < $totalPages): ?>
            <a href="<?= $buildPageUrl($page + 1) ?>" class="btn btn-outline-primary btn-sm">
              Next
              <i class="bi bi-chevron-right ms-1"></i>
            </a>
          <?php endif; ?>
        </nav>
      </div>
    <?php endif; ?>
  </div>
</section>
