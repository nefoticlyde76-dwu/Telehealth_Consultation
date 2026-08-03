<?php

$filters = $filters ?? ['search' => '', 'filter_date' => '', 'status' => ''];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0, 'per_page' => 10];
$summary = $summary ?? [];
$availability = $availability ?? [];
$statusOptions = $statusOptions ?? [];
$statusMessage = $statusMessage ?? null;
$csrfToken = $csrfToken ?? '';

$buildPageUrl = static function (int $page) use ($filters): string {
    $query = array_filter([
        'search' => $filters['search'] ?? '',
        'filter_date' => $filters['filter_date'] ?? '',
        'status' => $filters['status'] ?? '',
        'page' => $page,
    ], static fn ($value) => $value !== '');

    $queryString = http_build_query($query);

    return \App\Helpers\Helper::url('/doctor/availability') . ($queryString !== '' ? '?' . $queryString : '');
};
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-4">
        <div>
          <span class="section-badge mb-3">
            <i class="bi bi-calendar-week"></i>
            Doctor Availability Scheduling
          </span>
          <h2 class="h4 mb-2">Manage consultation slots professionally</h2>
          <p class="text-muted mb-0">Create, review, edit, and delete your availability without exposing patient booking workflows.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <span class="badge badge-soft-neutral rounded-pill border px-3 py-2"><?= \App\Helpers\Helper::escape((string) ($pagination['total_items'] ?? 0)) ?> slots visible</span>
          <a href="<?= \App\Helpers\Helper::url('/doctor/availability/create') ?>" class="btn btn-primary rounded-pill px-4">
            <i class="bi bi-calendar-plus me-2"></i>
            Create Availability
          </a>
        </div>
      </div>

      <form method="GET" action="<?= \App\Helpers\Helper::url('/doctor/availability') ?>" class="user-filter-form">
        <div class="row g-3 align-items-end">
          <div class="col-lg-6">
            <label for="search" class="form-label">Search availability</label>
            <input
              type="text"
              class="form-control"
              id="search"
              name="search"
              value="<?= \App\Helpers\Helper::escape((string) ($filters['search'] ?? '')) ?>"
              placeholder="Search by date, time, or notes"
            >
          </div>

          <div class="col-md-4 col-lg-2">
            <label for="filter_date" class="form-label">Filter by date</label>
            <input
              type="date"
              class="form-control"
              id="filter_date"
              name="filter_date"
              value="<?= \App\Helpers\Helper::escape((string) ($filters['filter_date'] ?? '')) ?>"
            >
          </div>

          <div class="col-md-4 col-lg-2">
            <label for="status" class="form-label">Filter by status</label>
            <select class="form-select" id="status" name="status">
              <option value="">All statuses</option>
              <?php foreach ($statusOptions as $statusOption): ?>
                <option value="<?= \App\Helpers\Helper::escape($statusOption) ?>" <?= ($filters['status'] ?? '') === $statusOption ? 'selected' : '' ?>>
                  <?= \App\Helpers\Helper::escape($statusOption) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4 col-lg-2 d-grid gap-2">
            <button type="submit" class="btn btn-primary rounded-pill">
              <i class="bi bi-funnel me-2"></i>
              Apply Filters
            </button>
            <a href="<?= \App\Helpers\Helper::url('/doctor/availability') ?>" class="btn btn-outline-primary rounded-pill">Reset</a>
          </div>
        </div>
      </form>
    </div>
  </div>
</section>

<section class="mb-4">
  <div class="row g-4">
    <div class="col-sm-6 col-xl-3">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Total Slots</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($summary['total_slots'] ?? 0)) ?></strong>
        <span class="admin-summary-meta">All consultation slots currently stored for your account.</span>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Available</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($summary['available_slots'] ?? 0)) ?></strong>
        <span class="admin-summary-meta">Slots ready for future booking workflows.</span>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Booked</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($summary['booked_slots'] ?? 0)) ?></strong>
        <span class="admin-summary-meta">Reserved status supported by the existing schema.</span>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Upcoming</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($summary['upcoming_slots'] ?? 0)) ?></strong>
        <span class="admin-summary-meta">Future-dated availability visible from today onward.</span>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
          <h3 class="h5 mb-1">Availability Table</h3>
          <p class="text-muted mb-0">Responsive slot listing with secure edit and delete actions.</p>
        </div>
        <span class="badge badge-soft-info rounded-pill px-3 py-2">
          Page <?= \App\Helpers\Helper::escape((string) ($pagination['current_page'] ?? 1)) ?> of <?= \App\Helpers\Helper::escape((string) ($pagination['total_pages'] ?? 1)) ?>
        </span>
      </div>

      <div class="table-responsive">
        <table class="table admin-user-table align-middle mb-0">
          <caption class="visually-hidden">Doctor availability schedule showing consultation date, start time, end time, notes, status, and management actions.</caption>
          <thead>
            <tr>
              <th scope="col">Date</th>
              <th scope="col">Time</th>
              <th scope="col">Notes</th>
              <th scope="col">Status</th>
              <th scope="col" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($availability === []): ?>
              <tr>
                <td colspan="5">
                  <div class="admin-table-empty text-center py-5">
                    <div class="empty-state-icon mx-auto mb-3">
                      <i class="bi bi-calendar-x"></i>
                    </div>
                    <h4 class="h5 mb-2">No availability slots matched the current filters</h4>
                    <p class="text-muted mb-0">Adjust the search or filters, or create a new consultation slot.</p>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($availability as $slot): ?>
                <?php $slotId = (int) ($slot['id'] ?? 0); ?>
                <tr>
                  <td>
                    <strong><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($slot['consultation_date'] ?? ''), 'd M Y', 'Not available')) ?></strong>
                    <span class="d-block text-muted small"><?= \App\Helpers\Helper::escape((string) ($slot['consultation_date'] ?? '')) ?></span>
                  </td>
                  <td>
                    <div class="d-flex flex-column">
                      <strong><?= \App\Helpers\Helper::escape(substr((string) ($slot['start_time'] ?? ''), 0, 5)) ?> - <?= \App\Helpers\Helper::escape(substr((string) ($slot['end_time'] ?? ''), 0, 5)) ?></strong>
                      <span class="text-muted small">Consultation window</span>
                    </div>
                  </td>
                  <td>
                    <span class="text-muted small d-block"><?= \App\Helpers\Helper::escape((string) ($slot['notes'] ?? 'No notes provided')) ?></span>
                  </td>
                  <td>
                    <?php $isAvailable = ($slot['status'] ?? '') === 'Available'; ?>
                    <span class="badge <?= $isAvailable ? 'badge-soft-success' : 'badge-soft-warning' ?> rounded-pill">
                      <?= \App\Helpers\Helper::escape((string) ($slot['status'] ?? 'Available')) ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <div class="admin-action-group">
                      <?php if ($isAvailable): ?>
                        <a href="<?= \App\Helpers\Helper::url('/doctor/availability/' . $slotId . '/edit') ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3" aria-label="Edit availability slot on <?= \App\Helpers\Helper::escape((string) ($slot['consultation_date'] ?? 'selected date')) ?>">
                          <i class="bi bi-pencil-square me-2"></i>
                          Edit
                        </a>
                        <form method="POST" action="<?= \App\Helpers\Helper::url('/doctor/availability/' . $slotId . '/delete') ?>" class="d-inline">
                          <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken) ?>">
                          <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3" aria-label="Delete availability slot on <?= \App\Helpers\Helper::escape((string) ($slot['consultation_date'] ?? 'selected date')) ?>">
                            <i class="bi bi-trash me-2"></i>
                            Delete
                          </button>
                        </form>
                      <?php else: ?>
                        <span class="btn btn-outline-secondary btn-sm rounded-pill px-3 disabled" aria-disabled="true">
                          <i class="bi bi-lock-fill me-2"></i>
                          Edit
                        </span>
                        <span class="btn btn-outline-secondary btn-sm rounded-pill px-3 disabled" aria-disabled="true">
                          <i class="bi bi-lock-fill me-2"></i>
                          Delete
                        </span>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
        <nav class="mt-4" aria-label="Doctor availability pagination">
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
