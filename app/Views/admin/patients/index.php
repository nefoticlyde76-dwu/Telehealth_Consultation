<?php

$filters = $filters ?? ['search' => '', 'status' => ''];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0, 'per_page' => 10];
$summary = $summary ?? [];
$patients = $patients ?? [];
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

    return \App\Helpers\Helper::url('/admin/patients') . ($queryString !== '' ? '?' . $queryString : '');
};
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-4">
        <div>
          <span class="section-badge mb-3">
            <i class="bi bi-people-fill"></i>
            Administrator Patient Management
          </span>
          <h2 class="h4 mb-2">Manage patient accounts securely</h2>
          <p class="text-muted mb-0">Review registered patients, search accounts, update profile information, and control account access status.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <span class="badge badge-soft-neutral rounded-pill border px-3 py-2"><?= \App\Helpers\Helper::escape((string) ($pagination['total_items'] ?? 0)) ?> patients visible</span>
        </div>
      </div>

      <form method="GET" action="<?= \App\Helpers\Helper::url('/admin/patients') ?>" class="user-filter-form">
        <div class="row g-3 align-items-end">
          <div class="col-lg-8">
            <label for="search" class="form-label">Search patients</label>
            <input
              type="text"
              class="form-control"
              id="search"
              name="search"
              value="<?= \App\Helpers\Helper::escape((string) ($filters['search'] ?? '')) ?>"
              placeholder="Search by full name, email, or address"
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
            <a href="<?= \App\Helpers\Helper::url('/admin/patients') ?>" class="btn btn-outline-primary rounded-pill">
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
        <span class="admin-summary-label">Total Patients</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($summary['total_patients'] ?? 0)) ?></strong>
        <span class="admin-summary-meta">Registered patient records currently visible in the platform.</span>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Active Patients</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($summary['active_patients'] ?? 0)) ?></strong>
        <span class="admin-summary-meta">Patients who can currently access the shared login workflow.</span>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Inactive Patients</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($summary['inactive_patients'] ?? 0)) ?></strong>
        <span class="admin-summary-meta">Patient accounts retained for future reactivation when needed.</span>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
          <h3 class="h5 mb-1">Patient Directory</h3>
          <p class="text-muted mb-0">Responsive patient listing with identity, demographics, and secure account actions.</p>
        </div>
        <span class="badge badge-soft-info rounded-pill px-3 py-2">
          Page <?= \App\Helpers\Helper::escape((string) ($pagination['current_page'] ?? 1)) ?> of <?= \App\Helpers\Helper::escape((string) ($pagination['total_pages'] ?? 1)) ?>
        </span>
      </div>

      <div class="table-responsive">
        <table class="table admin-user-table align-middle mb-0">
          <caption class="visually-hidden">Patient directory showing identity, demographics, address, account status, and administrator actions.</caption>
          <thead>
            <tr>
              <th scope="col">Patient</th>
              <th scope="col">Demographics</th>
              <th scope="col">Address</th>
              <th scope="col">Status</th>
              <th scope="col" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($patients === []): ?>
              <tr>
                <td colspan="5">
                  <div class="admin-table-empty text-center py-5">
                    <div class="empty-state-icon mx-auto mb-3">
                      <i class="bi bi-search"></i>
                    </div>
                    <h4 class="h5 mb-2">No patient accounts matched the current filters</h4>
                    <p class="text-muted mb-0">Adjust the search or status filter to broaden the patient listing.</p>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($patients as $patient): ?>
                <?php
                $patientId = (int) ($patient['id'] ?? 0);
                $isActive = ($patient['status'] ?? '') === 'active';
                ?>
                <tr>
                  <td>
                    <div class="d-flex flex-column">
                      <strong><?= \App\Helpers\Helper::escape($patient['full_name'] ?? 'Patient') ?></strong>
                      <span class="text-muted small"><?= \App\Helpers\Helper::escape($patient['email'] ?? '') ?></span>
                    </div>
                  </td>
                  <td>
                    <div class="d-flex flex-column">
                      <span>
                        DOB:
                        <?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate($patient['dob'] ?? null, 'd M Y', 'Not provided')) ?>
                      </span>
                      <span class="text-muted small">
                        Gender:
                        <?= \App\Helpers\Helper::escape(!empty($patient['gender']) ? ucfirst((string) $patient['gender']) : 'Not provided') ?>
                      </span>
                    </div>
                  </td>
                  <td>
                    <span class="text-muted small"><?= \App\Helpers\Helper::escape($patient['address'] ?? 'Not provided') ?></span>
                  </td>
                  <td>
                    <span class="badge <?= $isActive ? 'badge-soft-success' : 'badge-soft-warning' ?> rounded-pill">
                      <?= \App\Helpers\Helper::escape(ucfirst((string) ($patient['status'] ?? 'unknown'))) ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <div class="admin-action-group">
                      <a href="<?= \App\Helpers\Helper::url('/admin/patients/' . $patientId . '/edit') ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3" aria-label="Edit patient account for <?= \App\Helpers\Helper::escape((string) ($patient['full_name'] ?? 'this patient')) ?>">
                        <i class="bi bi-pencil-square me-2"></i>
                        Edit
                      </a>
                      <form method="POST" action="<?= \App\Helpers\Helper::url('/admin/patients/' . $patientId . '/' . ($isActive ? 'deactivate' : 'activate')) ?>" class="d-inline">
                        <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken) ?>">
                        <button type="submit" class="btn <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?> btn-sm rounded-pill px-3" aria-label="<?= ($isActive ? 'Deactivate' : 'Activate') . ' patient account for ' . \App\Helpers\Helper::escape((string) ($patient['full_name'] ?? 'this patient')) ?>">
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
        <nav class="mt-4" aria-label="Patient management pagination">
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
