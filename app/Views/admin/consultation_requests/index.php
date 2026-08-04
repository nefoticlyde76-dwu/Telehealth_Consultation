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

$statusBadgeMap = [
    'Pending' => 'badge-soft-warning',
    'Assigned' => 'badge-soft-info',
    'Approved' => 'badge-soft-success',
    'Rejected' => 'badge-soft-danger',
    'Cancelled' => 'badge-soft-danger',
    'Completed' => 'badge-soft-success',
];
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
          <span class="section-badge mb-3">
            <i class="bi bi-clipboard2-check"></i>
            Administrator Consultation Requests
          </span>
          <h2 class="h4 mb-2">Review and approve booked consultation requests</h2>
          <p class="text-muted mb-0">Search and filter consultation requests, open full request details, and apply approval or rejection securely.</p>
        </div>
        <div class="text-lg-end">
          <span class="badge badge-soft-neutral rounded-pill border px-3 py-2"><?= \App\Helpers\Helper::escape((string) ($pagination['total_items'] ?? 0)) ?> requests visible</span>
        </div>
      </div>

      <form method="GET" action="<?= \App\Helpers\Helper::url('/admin/consultation-requests') ?>" class="user-filter-form">
        <div class="row g-3 align-items-end">
          <div class="col-lg-4">
            <label for="search" class="form-label">Search consultation requests</label>
            <input
              type="text"
              class="form-control"
              id="search"
              name="search"
              value="<?= \App\Helpers\Helper::escape((string) ($filters['search'] ?? '')) ?>"
              placeholder="Search by patient, doctor, request id, or complaint"
            >
          </div>

          <div class="col-md-4 col-lg-2">
            <label for="status" class="form-label">Status</label>
            <select class="form-select" id="status" name="status">
              <option value="">All statuses</option>
              <?php foreach ($statusOptions as $statusOption): ?>
                <option value="<?= \App\Helpers\Helper::escape((string) $statusOption) ?>" <?= ($filters['status'] ?? '') === $statusOption ? 'selected' : '' ?>>
                  <?= \App\Helpers\Helper::escape((string) $statusOption) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4 col-lg-2">
            <label for="patient_id" class="form-label">Patient</label>
            <select class="form-select" id="patient_id" name="patient_id">
              <option value="">All patients</option>
              <?php foreach ($patientOptions as $patientOption): ?>
                <?php $patientId = (int) ($patientOption['id'] ?? 0); ?>
                <option value="<?= \App\Helpers\Helper::escape((string) $patientId) ?>" <?= (int) ($filters['patient_id'] ?? 0) === $patientId ? 'selected' : '' ?>>
                  <?= \App\Helpers\Helper::escape((string) ($patientOption['full_name'] ?? 'Patient')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4 col-lg-2">
            <label for="doctor_id" class="form-label">Doctor</label>
            <select class="form-select" id="doctor_id" name="doctor_id">
              <option value="">All doctors</option>
              <?php foreach ($doctorOptions as $doctorOption): ?>
                <?php $doctorId = (int) ($doctorOption['id'] ?? 0); ?>
                <option value="<?= \App\Helpers\Helper::escape((string) $doctorId) ?>" <?= (int) ($filters['doctor_id'] ?? 0) === $doctorId ? 'selected' : '' ?>>
                  <?= \App\Helpers\Helper::escape((string) ($doctorOption['full_name'] ?? 'Doctor')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4 col-lg-2">
            <label for="consultation_date" class="form-label">Consultation Date</label>
            <input
              type="date"
              class="form-control"
              id="consultation_date"
              name="consultation_date"
              value="<?= \App\Helpers\Helper::escape((string) ($filters['consultation_date'] ?? '')) ?>"
            >
          </div>

          <div class="col-12 d-flex flex-wrap gap-2 justify-content-end">
            <button type="submit" class="btn btn-primary rounded-pill px-4">
              <i class="bi bi-funnel me-2"></i>
              Apply Filters
            </button>
            <a href="<?= \App\Helpers\Helper::url('/admin/consultation-requests') ?>" class="btn btn-outline-primary rounded-pill px-4">
              Reset
            </a>
          </div>
        </div>
      </form>
    </div>
  </div>
</section>

<section class="mb-4">
  <div class="row g-4">
    <div class="col-sm-6 col-xl-4">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Pending Requests</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ((int) ($summary['pending_requests'] ?? 0))) ?></strong>
        <span class="admin-summary-meta">Awaiting administrator action</span>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Approved Consultations</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ((int) ($summary['approved_requests'] ?? 0))) ?></strong>
        <span class="admin-summary-meta">Reserved consultation appointments</span>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Rejected Requests</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ((int) ($summary['rejected_requests'] ?? 0))) ?></strong>
        <span class="admin-summary-meta">Rejected consultation requests</span>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
          <h3 class="h5 mb-1">Consultation Request Directory</h3>
          <p class="text-muted mb-0">Review patient and doctor matching, slot details, and current approval status.</p>
        </div>
        <span class="badge badge-soft-info rounded-pill px-3 py-2">
          Page <?= \App\Helpers\Helper::escape((string) ($pagination['current_page'] ?? 1)) ?> of <?= \App\Helpers\Helper::escape((string) ($pagination['total_pages'] ?? 1)) ?>
        </span>
      </div>

      <div class="table-responsive">
        <table class="table admin-user-table align-middle mb-0">
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
                <td colspan="7">
                  <div class="admin-table-empty text-center py-5">
                    <div class="empty-state-icon mx-auto mb-3">
                      <i class="bi bi-search"></i>
                    </div>
                    <h4 class="h5 mb-2">No consultation requests matched the current filters</h4>
                    <p class="text-muted mb-0">Adjust the filters to review other consultation booking requests.</p>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($requests as $request): ?>
                <?php
                $status = (string) ($request['status'] ?? 'Pending');
                $badgeClass = $statusBadgeMap[$status] ?? 'badge-soft-neutral';
                $dateLabel = \App\Helpers\Helper::formatDate((string) ($request['consultation_date'] ?? ''), 'd M Y', 'Not available');
                $timeLabel = substr((string) ($request['start_time'] ?? ''), 0, 5) . ' - ' . substr((string) ($request['end_time'] ?? ''), 0, 5);
                ?>
                <tr>
                  <td>
                    <strong class="d-block"><?= \App\Helpers\Helper::escape((string) ($request['patient_name'] ?? 'Patient')) ?></strong>
                    <span class="text-muted small">ID #<?= \App\Helpers\Helper::escape((string) ((int) ($request['patient_id'] ?? 0))) ?></span>
                  </td>
                  <td>
                    <strong class="d-block"><?= \App\Helpers\Helper::escape((string) ($request['doctor_name'] ?? 'Doctor')) ?></strong>
                    <span class="text-muted small">ID #<?= \App\Helpers\Helper::escape((string) ((int) ($request['doctor_id'] ?? 0))) ?></span>
                  </td>
                  <td>
                    <strong><?= \App\Helpers\Helper::escape((string) $dateLabel) ?></strong>
                  </td>
                  <td>
                    <span class="text-muted small"><?= \App\Helpers\Helper::escape((string) $timeLabel) ?></span>
                  </td>
                  <td>
                    <span class="badge <?= \App\Helpers\Helper::escape($badgeClass) ?> rounded-pill px-3 py-2"><?= \App\Helpers\Helper::escape($status) ?></span>
                  </td>
                  <td class="text-muted small">
                    <?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($request['request_date'] ?? ''), 'd M Y', '')) ?>
                  </td>
                  <td class="text-end">
                    <a href="<?= \App\Helpers\Helper::url('/admin/consultation-requests/' . (string) ((int) ($request['id'] ?? 0))) ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                      <i class="bi bi-eye me-2"></i>
                      View
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
        <nav class="mt-4" aria-label="Consultation request management pagination">
          <ul class="pagination admin-pagination justify-content-end mb-0">
            <li class="page-item <?= ($pagination['current_page'] ?? 1) <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= ($pagination['current_page'] ?? 1) <= 1 ? '#' : $buildPageUrl((int) $pagination['current_page'] - 1) ?>">Previous</a>
            </li>

            <?php for ($page = 1; $page <= (int) ($pagination['total_pages'] ?? 1); $page++): ?>
              <li class="page-item <?= $page === (int) ($pagination['current_page'] ?? 1) ? 'active' : '' ?>">
                <a class="page-link" href="<?= $buildPageUrl($page) ?>"><?= \App\Helpers\Helper::escape((string) $page) ?></a>
              </li>
            <?php endfor; ?>

            <li class="page-item <?= ($pagination['current_page'] ?? 1) >= ($pagination['total_pages'] ?? 1) ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= ($pagination['current_page'] ?? 1) >= ($pagination['total_pages'] ?? 1) ? '#' : $buildPageUrl((int) $pagination['current_page'] + 1) ?>">Next</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</section>

