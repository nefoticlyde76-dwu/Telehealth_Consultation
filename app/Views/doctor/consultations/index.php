<?php

$filters = $filters ?? ['status' => '', 'search' => ''];
$consultations = $consultations ?? [];
$summary = $summary ?? [];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0];
$statusOptions = $statusOptions ?? [];
$csrfToken = $csrfToken ?? '';

$statusBadgeMap = [
    'Pending' => 'badge-soft-warning',
    'Approved' => 'badge-soft-success',
    'Rejected' => 'badge-soft-danger',
    'Cancelled' => 'badge-soft-danger',
    'Completed' => 'badge-soft-success',
];

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

  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
          <span class="section-badge mb-3">
            <i class="bi bi-clipboard2-pulse"></i>
            Doctor Consultations
          </span>
          <h2 class="h4 mb-2">Review approved and completed consultations</h2>
          <p class="text-muted mb-0">Track your upcoming consultations, review chief complaints, and mark consultations as completed when appropriate.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?= \App\Helpers\Helper::url('/doctor/dashboard') ?>" class="btn btn-outline-primary rounded-pill px-4">
            <i class="bi bi-arrow-left me-2"></i>
            Dashboard
          </a>
          <a href="<?= \App\Helpers\Helper::url('/doctor/availability') ?>" class="btn btn-primary rounded-pill px-4">
            <i class="bi bi-calendar-week me-2"></i>
            Manage Availability
          </a>
        </div>
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
            <select class="form-select" id="status" name="status">
              <option value="">All statuses</option>
              <?php foreach ($statusOptions as $statusOption): ?>
                <option value="<?= \App\Helpers\Helper::escape((string) $statusOption) ?>" <?= ($filters['status'] ?? '') === $statusOption ? 'selected' : '' ?>>
                  <?= \App\Helpers\Helper::escape((string) $statusOption) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2 d-grid gap-2">
            <button type="submit" class="btn btn-primary rounded-pill">Apply</button>
            <a href="<?= \App\Helpers\Helper::url('/doctor/consultations') ?>" class="btn btn-outline-primary rounded-pill">Reset</a>
          </div>
        </div>
      </form>
    </div>
  </div>
</section>

<section class="mb-4">
  <div class="row g-4">
    <div class="col-sm-4">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Approved Appointments</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ((int) ($summary['approved_appointments'] ?? 0))) ?></strong>
        <span class="admin-summary-meta">Approved consultations across your workflow.</span>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Upcoming Consultations</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ((int) ($summary['upcoming_consultations'] ?? 0))) ?></strong>
        <span class="admin-summary-meta">Approved appointments scheduled from today onward.</span>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Completed Consultations</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ((int) ($summary['completed_consultations'] ?? 0))) ?></strong>
        <span class="admin-summary-meta">Consultations marked complete by the doctor.</span>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
          <h3 class="h5 mb-1">Consultation Directory</h3>
          <p class="text-muted mb-0">Review patient consultations and update the workflow after appointments are completed.</p>
        </div>
        <span class="badge badge-soft-info rounded-pill px-3 py-2">
          Page <?= \App\Helpers\Helper::escape((string) ($pagination['current_page'] ?? 1)) ?> of <?= \App\Helpers\Helper::escape((string) ($pagination['total_pages'] ?? 1)) ?>
        </span>
      </div>

      <div class="table-responsive">
        <table class="table admin-user-table align-middle mb-0">
          <caption class="visually-hidden">Doctor consultation listing with patient, schedule, chief complaint, status, and completion action.</caption>
          <thead>
            <tr>
              <th scope="col">Patient</th>
              <th scope="col">Consultation Date</th>
              <th scope="col">Time</th>
              <th scope="col">Chief Complaint</th>
              <th scope="col">Status</th>
              <th scope="col" class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($consultations === []): ?>
              <tr>
                <td colspan="6">
                  <div class="admin-table-empty text-center py-5">
                    <div class="empty-state-icon mx-auto mb-3">
                      <i class="bi bi-clipboard2-x"></i>
                    </div>
                    <h4 class="h5 mb-2">No consultations matched the current filters</h4>
                    <p class="text-muted mb-0">Approved and completed consultations will appear here as the Week 5 workflow progresses.</p>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($consultations as $consultation): ?>
                <?php
                $status = (string) ($consultation['status'] ?? 'Pending');
                $badgeClass = $statusBadgeMap[$status] ?? 'badge-soft-neutral';
                $dateLabel = \App\Helpers\Helper::formatDate((string) ($consultation['consultation_date'] ?? ''), 'd M Y', 'Not available');
                $timeLabel = substr((string) ($consultation['start_time'] ?? ''), 0, 5) . ' - ' . substr((string) ($consultation['end_time'] ?? ''), 0, 5);
                $canComplete = $status === 'Approved' && (string) ($consultation['consultation_date'] ?? '') !== '' && (string) ($consultation['consultation_date'] ?? '') <= date('Y-m-d');
                ?>
                <tr>
                  <td>
                    <strong class="d-block"><?= \App\Helpers\Helper::escape((string) ($consultation['patient_name'] ?? 'Patient')) ?></strong>
                    <span class="text-muted small"><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($consultation['request_date'] ?? ''), 'd M Y', '')) ?></span>
                  </td>
                  <td><strong><?= \App\Helpers\Helper::escape((string) $dateLabel) ?></strong></td>
                  <td><span class="text-muted small"><?= \App\Helpers\Helper::escape((string) $timeLabel) ?></span></td>
                  <td>
                    <span class="text-muted small"><?= \App\Helpers\Helper::escape(mb_strimwidth((string) ($consultation['reason'] ?? ''), 0, 70, '...')) ?></span>
                  </td>
                  <td>
                    <span class="badge <?= \App\Helpers\Helper::escape($badgeClass) ?> rounded-pill px-3 py-2"><?= \App\Helpers\Helper::escape($status) ?></span>
                  </td>
                  <td class="text-end">
                    <?php if ($canComplete): ?>
                      <form method="POST" action="<?= \App\Helpers\Helper::url('/doctor/consultations/' . (string) ((int) ($consultation['id'] ?? 0)) . '/complete') ?>">
                        <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">
                        <button type="submit" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                          Mark Completed
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="text-muted small">No action</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
        <nav class="mt-4" aria-label="Doctor consultations pagination">
          <ul class="pagination admin-pagination justify-content-end mb-0">
            <li class="page-item <?= ($pagination['current_page'] ?? 1) <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= ($pagination['current_page'] ?? 1) <= 1 ? '#' : $buildPageUrl((int) ($pagination['current_page'] ?? 1) - 1) ?>">Previous</a>
            </li>
            <?php for ($page = 1; $page <= (int) ($pagination['total_pages'] ?? 1); $page++): ?>
              <li class="page-item <?= $page === (int) ($pagination['current_page'] ?? 1) ? 'active' : '' ?>">
                <a class="page-link" href="<?= $buildPageUrl($page) ?>"><?= \App\Helpers\Helper::escape((string) $page) ?></a>
              </li>
            <?php endfor; ?>
            <li class="page-item <?= ($pagination['current_page'] ?? 1) >= ($pagination['total_pages'] ?? 1) ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= ($pagination['current_page'] ?? 1) >= ($pagination['total_pages'] ?? 1) ? '#' : $buildPageUrl((int) ($pagination['current_page'] ?? 1) + 1) ?>">Next</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</section>

