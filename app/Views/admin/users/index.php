<?php

$filters = $filters ?? ['search' => '', 'role' => '', 'status' => ''];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0, 'per_page' => 10];
$summary = $summary ?? [];
$users = $users ?? [];
$roleOptions = $roleOptions ?? [];
$statusOptions = $statusOptions ?? [];
$statusMessage = $statusMessage ?? null;

$buildPageUrl = static function (int $page) use ($filters): string {
    $query = array_filter([
        'search' => $filters['search'] ?? '',
        'role' => $filters['role'] ?? '',
        'status' => $filters['status'] ?? '',
        'page' => $page,
    ], static fn ($value) => $value !== '');

    $queryString = http_build_query($query);

    return \App\Helpers\Helper::url('/admin/users') . ($queryString !== '' ? '?' . $queryString : '');
};
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
          <span class="section-badge mb-3">
            <i class="bi bi-people-fill"></i>
            Administrator User Management
          </span>
          <h2 class="h4 mb-2">Review all platform accounts</h2>
          <p class="text-muted mb-0">Search by name or email, filter by role or account status, and open a detailed user profile view.</p>
        </div>
        <div class="text-lg-end">
          <span class="badge badge-soft-neutral rounded-pill border px-3 py-2"><?= \App\Helpers\Helper::escape((string) ($pagination['total_items'] ?? 0)) ?> users visible</span>
        </div>
      </div>

      <form method="GET" action="<?= \App\Helpers\Helper::url('/admin/users') ?>" class="user-filter-form">
        <div class="row g-3 align-items-end">
          <div class="col-lg-5">
            <label for="search" class="form-label">Search users</label>
            <input
              type="text"
              class="form-control"
              id="search"
              name="search"
              value="<?= \App\Helpers\Helper::escape((string) ($filters['search'] ?? '')) ?>"
              placeholder="Search by full name or email"
            >
          </div>

          <div class="col-md-4 col-lg-3">
            <label for="role" class="form-label">Filter by role</label>
            <select class="form-select" id="role" name="role">
              <option value="">All roles</option>
              <?php foreach ($roleOptions as $roleOption): ?>
                <option value="<?= \App\Helpers\Helper::escape($roleOption) ?>" <?= ($filters['role'] ?? '') === $roleOption ? 'selected' : '' ?>>
                  <?= \App\Helpers\Helper::escape(ucfirst($roleOption)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4 col-lg-2">
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

          <div class="col-md-4 col-lg-2 d-grid gap-2">
            <button type="submit" class="btn btn-primary rounded-pill">
              <i class="bi bi-funnel me-2"></i>
              Apply Filters
            </button>
            <a href="<?= \App\Helpers\Helper::url('/admin/users') ?>" class="btn btn-outline-primary rounded-pill">
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
    <div class="col-sm-6 col-xl-3">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Total Users</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($summary['total_users'] ?? 0)) ?></strong>
        <span class="admin-summary-meta">All roles combined in the current environment</span>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Active Accounts</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($summary['active_users'] ?? 0)) ?></strong>
        <span class="admin-summary-meta">Users permitted to authenticate today</span>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Doctors</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($summary['doctor_users'] ?? 0)) ?></strong>
        <span class="admin-summary-meta">Clinician accounts visible for future workflow management</span>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Patients</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($summary['patient_users'] ?? 0)) ?></strong>
        <span class="admin-summary-meta">Registered patient accounts ready for oversight</span>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
          <h3 class="h5 mb-1">User Directory</h3>
          <p class="text-muted mb-0">Responsive account listing with role, status, and detail access.</p>
        </div>
        <span class="badge badge-soft-info rounded-pill px-3 py-2">
          Page <?= \App\Helpers\Helper::escape((string) ($pagination['current_page'] ?? 1)) ?> of <?= \App\Helpers\Helper::escape((string) ($pagination['total_pages'] ?? 1)) ?>
        </span>
      </div>

      <div class="table-responsive">
        <table class="table admin-user-table align-middle mb-0">
          <caption class="visually-hidden">Administrator user directory showing account identity, role, status, creation date, and detail access.</caption>
          <thead>
            <tr>
              <th scope="col">User</th>
              <th scope="col">Role</th>
              <th scope="col">Status</th>
              <th scope="col">Created</th>
              <th scope="col" class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($users === []): ?>
              <tr>
                <td colspan="5">
                  <div class="admin-table-empty text-center py-5">
                    <div class="empty-state-icon mx-auto mb-3">
                      <i class="bi bi-search"></i>
                    </div>
                    <h4 class="h5 mb-2">No users matched the current filters</h4>
                    <p class="text-muted mb-0">Adjust the search, role, or status filters to broaden the current user listing.</p>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($users as $managedUser): ?>
                <tr>
                  <td>
                    <div class="d-flex flex-column">
                      <strong><?= \App\Helpers\Helper::escape($managedUser['full_name'] ?? 'User') ?></strong>
                      <span class="text-muted small"><?= \App\Helpers\Helper::escape($managedUser['email'] ?? '') ?></span>
                    </div>
                  </td>
                  <td>
                    <span class="badge badge-soft-neutral rounded-pill border">
                      <?= \App\Helpers\Helper::escape(ucfirst((string) ($managedUser['role_name'] ?? 'user'))) ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge <?= ($managedUser['status'] ?? '') === 'active' ? 'badge-soft-success' : 'badge-soft-warning' ?> rounded-pill">
                      <?= \App\Helpers\Helper::escape(ucfirst((string) ($managedUser['status'] ?? 'unknown'))) ?>
                    </span>
                  </td>
                  <td class="text-muted small">
                    <?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate($managedUser['created_at'] ?? null)) ?>
                  </td>
                  <td class="text-end">
                    <a href="<?= \App\Helpers\Helper::url('/admin/users/' . (int) ($managedUser['id'] ?? 0)) ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3" aria-label="View details for <?= \App\Helpers\Helper::escape((string) ($managedUser['full_name'] ?? 'this user')) ?>">
                      <i class="bi bi-eye me-2"></i>
                      View Details
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
        <nav class="mt-4" aria-label="User management pagination">
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
