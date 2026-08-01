<?php

$filters = $filters ?? ['search' => '', 'status' => ''];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0, 'per_page' => 10];
$summary = $summary ?? [];
$doctors = $doctors ?? [];
$statusOptions = $statusOptions ?? [];
$statusMessage = $statusMessage ?? null;
$csrfToken = $csrfToken ?? '';

$buildPageUrl = static function (int $page) use ($filters): string {
    $query = array_filter([
        'search' => $filters['search'] ?? '',
        'status' => $filters['status'] ?? '',
        'page' => $page,
    ], static fn ($value) => $value !== '');

    $queryString = http_build_query($query);

    return \App\Helpers\Helper::url('/admin/doctors') . ($queryString !== '' ? '?' . $queryString : '');
};
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-4">
        <div>
          <span class="section-badge mb-3">
            <i class="bi bi-person-badge-fill"></i>
            Administrator Doctor Account Management
          </span>
          <h2 class="h4 mb-2">Manage clinician access securely</h2>
          <p class="text-muted mb-0">Create doctor accounts, update clinician profiles, control account status, and reset passwords without exposing sensitive credentials.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <span class="badge badge-soft-neutral rounded-pill border px-3 py-2"><?= \App\Helpers\Helper::escape((string) ($pagination['total_items'] ?? 0)) ?> doctors visible</span>
          <a href="<?= \App\Helpers\Helper::url('/admin/doctors/create') ?>" class="btn btn-primary rounded-pill px-4">
            <i class="bi bi-person-plus-fill me-2"></i>
            Create Doctor Account
          </a>
        </div>
      </div>

      <form method="GET" action="<?= \App\Helpers\Helper::url('/admin/doctors') ?>" class="user-filter-form">
        <div class="row g-3 align-items-end">
          <div class="col-lg-8">
            <label for="search" class="form-label">Search doctors</label>
            <input
              type="text"
              class="form-control"
              id="search"
              name="search"
              value="<?= \App\Helpers\Helper::escape((string) ($filters['search'] ?? '')) ?>"
              placeholder="Search by full name, email, specialization, title, or employee ID"
            >
          </div>

          <div class="col-md-5 col-lg-2">
            <label for="status" class="form-label">Filter by status</label>
            <select class="form-select" id="status" name="status">
              <option value="">All statuses</option>
              <?php foreach ($statusOptions as $statusOption): ?>
                <option value="<?= \App\Helpers\Helper::escape($statusOption) ?>" <?= ($filters['status'] ?? '') === $statusOption ? 'selected' : '' ?>>
                  <?= \App\Helpers\Helper::escape(ucfirst($statusOption)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-7 col-lg-2 d-grid gap-2">
            <button type="submit" class="btn btn-primary rounded-pill">
              <i class="bi bi-funnel me-2"></i>
              Apply Filters
            </button>
            <a href="<?= \App\Helpers\Helper::url('/admin/doctors') ?>" class="btn btn-outline-primary rounded-pill">
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
        <span class="admin-summary-label">Total Doctors</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($summary['total_doctors'] ?? 0)) ?></strong>
        <span class="admin-summary-meta">Clinician accounts currently linked to the platform.</span>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Active Doctors</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($summary['active_doctors'] ?? 0)) ?></strong>
        <span class="admin-summary-meta">Doctor accounts currently permitted to sign in.</span>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Inactive Doctors</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($summary['inactive_doctors'] ?? 0)) ?></strong>
        <span class="admin-summary-meta">Doctor accounts retained for future reactivation.</span>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
          <h3 class="h5 mb-1">Doctor Directory</h3>
          <p class="text-muted mb-0">Responsive clinician listing with identity, professional profile, and secure account actions.</p>
        </div>
        <span class="badge badge-soft-info rounded-pill px-3 py-2">
          Page <?= \App\Helpers\Helper::escape((string) ($pagination['current_page'] ?? 1)) ?> of <?= \App\Helpers\Helper::escape((string) ($pagination['total_pages'] ?? 1)) ?>
        </span>
      </div>

      <div class="table-responsive">
        <table class="table admin-user-table align-middle mb-0">
          <caption class="visually-hidden">Doctor directory showing clinician identity, specialization, contact details, account status, and administrator actions.</caption>
          <thead>
            <tr>
              <th scope="col">Doctor</th>
              <th scope="col">Specialization</th>
              <th scope="col">Contact</th>
              <th scope="col">Status</th>
              <th scope="col" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($doctors === []): ?>
              <tr>
                <td colspan="5">
                  <div class="admin-table-empty text-center py-5">
                    <div class="empty-state-icon mx-auto mb-3">
                      <i class="bi bi-person-bounding-box"></i>
                    </div>
                    <h4 class="h5 mb-2">No doctor accounts matched the current filters</h4>
                    <p class="text-muted mb-0">Adjust the search or status filter to broaden the clinician listing.</p>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($doctors as $doctor): ?>
                <?php
                $doctorId = (int) ($doctor['id'] ?? 0);
                $isActive = ($doctor['status'] ?? '') === 'active';
                ?>
                <tr>
                  <td>
                    <div class="d-flex flex-column">
                      <strong><?= \App\Helpers\Helper::escape($doctor['full_name'] ?? 'Doctor') ?></strong>
                      <span class="text-muted small"><?= \App\Helpers\Helper::escape($doctor['professional_title'] ?? 'Professional title not assigned') ?></span>
                      <span class="text-muted small"><?= \App\Helpers\Helper::escape($doctor['email'] ?? '') ?></span>
                    </div>
                  </td>
                  <td>
                    <div class="d-flex flex-column">
                      <strong><?= \App\Helpers\Helper::escape($doctor['specialization'] ?? 'Not assigned') ?></strong>
                      <span class="text-muted small">
                        Employee ID:
                        <?= \App\Helpers\Helper::escape($doctor['employee_id'] !== '' ? ($doctor['employee_id'] ?? 'Not assigned') : 'Not assigned') ?>
                      </span>
                    </div>
                  </td>
                  <td>
                    <div class="d-flex flex-column">
                      <span><?= \App\Helpers\Helper::escape($doctor['phone'] ?? 'Not available') ?></span>
                      <span class="text-muted small"><?= \App\Helpers\Helper::escape(ucfirst((string) ($doctor['gender'] ?? ''))) ?></span>
                    </div>
                  </td>
                  <td>
                    <span class="badge <?= $isActive ? 'badge-soft-success' : 'badge-soft-warning' ?> rounded-pill">
                      <?= \App\Helpers\Helper::escape(ucfirst((string) ($doctor['status'] ?? 'unknown'))) ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <div class="admin-action-group">
                      <a href="<?= \App\Helpers\Helper::url('/admin/doctors/' . $doctorId . '/edit') ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3" aria-label="Edit doctor account for <?= \App\Helpers\Helper::escape((string) ($doctor['full_name'] ?? 'this doctor')) ?>">
                        <i class="bi bi-pencil-square me-2"></i>
                        Edit
                      </a>
                      <a href="<?= \App\Helpers\Helper::url('/admin/doctors/' . $doctorId . '/reset-password') ?>" class="btn btn-outline-secondary btn-sm rounded-pill px-3" aria-label="Reset password for <?= \App\Helpers\Helper::escape((string) ($doctor['full_name'] ?? 'this doctor')) ?>">
                        <i class="bi bi-key me-2"></i>
                        Reset Password
                      </a>
                      <form method="POST" action="<?= \App\Helpers\Helper::url('/admin/doctors/' . $doctorId . '/' . ($isActive ? 'deactivate' : 'activate')) ?>" class="d-inline">
                        <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken) ?>">
                        <button type="submit" class="btn <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?> btn-sm rounded-pill px-3" aria-label="<?= ($isActive ? 'Deactivate' : 'Activate') . ' doctor account for ' . \App\Helpers\Helper::escape((string) ($doctor['full_name'] ?? 'this doctor')) ?>">
                          <i class="bi <?= $isActive ? 'bi-person-dash' : 'bi-person-check' ?> me-2"></i>
                          <?= $isActive ? 'Deactivate' : 'Activate' ?>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
        <nav class="mt-4" aria-label="Doctor management pagination">
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
