<?php

$filters = $filters ?? ['search' => '', 'status' => ''];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0, 'per_page' => 10];
$summary = $summary ?? [];
$doctors = $doctors ?? [];
$statusOptions = $statusOptions ?? [];
$statusMessage = $statusMessage ?? null;
$csrfToken = $csrfToken ?? '';

require __DIR__ . '/../../partials/shared/status_helper.php';

$totalItems = (int) ($pagination['total_items'] ?? 0);
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

  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/admin/dashboard') ?>">Dashboard</a></li>
        <li class="active">Doctors</li>
      </ol>
      <h2 class="ux-page-header__title">Doctor Account Management</h2>
      <p class="ux-page-header__subtitle">Manage clinician access securely — create profiles, control status, and reset passwords.</p>
    </div>
    <div class="ux-page-header__right">
      <span class="ux-chip ux-badge--dotless">
        <i class="bi bi-person-badge-fill"></i>
        <span><?= $totalItems ?> doctors visible</span>
      </span>
      <a href="<?= \App\Helpers\Helper::url('/admin/doctors/create') ?>" class="btn btn-primary btn-primary-xl">
        <i class="bi bi-person-plus-fill me-2"></i>
        Create Doctor
      </a>
    </div>
  </div>

  <div class="ux-card ux-filter">
    <div class="card-header">
      <h3 class="h6">
        <i class="bi bi-funnel-fill ux-filter__header-icon"></i>
        Filter clinicians
      </h3>
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

        <div class="col-lg-2">
          <label for="status" class="form-label">Status</label>
          <select class="form-select" id="status" name="status" aria-label="Filter doctors by status">
            <option value="">All statuses</option>
            <?php foreach ($statusOptions as $statusOption): ?>
              <option value="<?= \App\Helpers\Helper::escape($statusOption) ?>" <?= ($filters['status'] ?? '') === $statusOption ? 'selected' : '' ?>>
                <?= \App\Helpers\Helper::escape(ucfirst($statusOption)) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-lg-2">
          <div class="ux-filter__actions">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-funnel-fill me-1"></i>
              Apply Filters
            </button>
            <a href="<?= \App\Helpers\Helper::url('/admin/doctors') ?>" class="btn btn-outline-primary btn-sm">
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
        <div class="ux-stat__icon ux-stat__icon--navy">
          <i class="bi bi-person-badge-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['total_doctors'] ?? 0) ?></div>
          <div class="ux-stat__label">Total Doctors</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--success">
          <i class="bi bi-check-circle-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['active_doctors'] ?? 0) ?></div>
          <div class="ux-stat__label">Active</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--slate">
          <i class="bi bi-person-dash-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['inactive_doctors'] ?? 0) ?></div>
          <div class="ux-stat__label">Inactive</div>
        </div>
      </div>
    </div>
  </div>

  <div class="ux-card">
    <div class="ux-table-wrapper border-0">
      <div class="table-responsive">
        <table class="ux-table">
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
                <td colspan="5" class="ux-table__empty-state">
                  <div class="ux-empty">
                    <div class="ux-empty__icon">
                      <i class="bi bi-person-bounding-box"></i>
                    </div>
                    <h4 class="ux-empty__title">No doctor accounts matched the current filters</h4>
                    <p class="ux-empty__text">Adjust the search or status filter to broaden the clinician listing.</p>
                    <div class="ux-empty__action">
                      <a href="<?= \App\Helpers\Helper::url('/admin/doctors') ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-arrow-clockwise me-1"></i>
                        Reset Filters
                      </a>
                    </div>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($doctors as $doctor): ?>
                <?php
                $doctorId = (int) ($doctor['id'] ?? 0);
                $isActive = ($doctor['status'] ?? '') === 'active';
                $fullName = trim((string) ($doctor['full_name'] ?? 'Doctor'));
                $statusBadge = ux_active_badge_class($doctor['status'] ?? 'inactive');
                ?>
                <tr>
                  <td>
                    <div class="d-flex flex-column">
                      <strong><?= \App\Helpers\Helper::escape($fullName) ?></strong>
                      <span class="text-muted small"><?= \App\Helpers\Helper::escape((string) ($doctor['professional_title'] ?? 'Professional title not assigned')) ?></span>
                      <span class="text-muted small"><?= \App\Helpers\Helper::escape((string) ($doctor['email'] ?? '')) ?></span>
                    </div>
                  </td>
                  <td>
                    <div class="d-flex flex-column">
                      <strong><?= \App\Helpers\Helper::escape((string) ($doctor['specialization'] ?? 'Not assigned')) ?></strong>
                      <span class="text-muted small">
                        Employee ID:
                        <?= \App\Helpers\Helper::escape(($doctor['employee_id'] ?? '') !== '' ? ((string) ($doctor['employee_id'] ?? 'Not assigned')) : 'Not assigned') ?>
                      </span>
                    </div>
                  </td>
                  <td>
                    <div class="d-flex flex-column">
                      <span><?= \App\Helpers\Helper::escape((string) ($doctor['phone'] ?? 'Not available')) ?></span>
                      <span class="text-muted small"><?= \App\Helpers\Helper::escape(ucfirst((string) ($doctor['gender'] ?? ''))) ?></span>
                    </div>
                  </td>
                  <td>
                    <span class="ux-badge <?= $statusBadge ?>">
                      <?= \App\Helpers\Helper::escape(ucfirst((string) ($doctor['status'] ?? 'unknown'))) ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <div class="ux-table__actions">
                      <a href="<?= \App\Helpers\Helper::url('/admin/doctors/' . $doctorId . '/edit') ?>" class="btn btn-outline-primary btn-sm" aria-label="Edit doctor account for <?= \App\Helpers\Helper::escape($fullName) ?>">
                        <i class="bi bi-pencil-square me-1"></i>
                        Edit
                      </a>
                      <a href="<?= \App\Helpers\Helper::url('/admin/doctors/' . $doctorId . '/reset-password') ?>" class="btn btn-outline-secondary btn-sm" aria-label="Reset password for <?= \App\Helpers\Helper::escape($fullName) ?>">
                        <i class="bi bi-key me-1"></i>
                        Reset Password
                      </a>
                      <form method="POST" action="<?= \App\Helpers\Helper::url('/admin/doctors/' . $doctorId . '/' . ($isActive ? 'deactivate' : 'activate')) ?>" class="d-inline">
                        <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">
                        <button type="submit" class="btn <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?> btn-sm" aria-label="<?= ($isActive ? 'Deactivate' : 'Activate') . ' doctor account for ' . \App\Helpers\Helper::escape($fullName) ?>">
                          <i class="bi <?= $isActive ? 'bi-person-dash' : 'bi-person-check' ?> me-1"></i>
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
    </div>

    <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
      <div class="card-footer border-0 bg-transparent pt-4 pb-0">
        <nav class="admin-pagination d-flex justify-content-end align-items-center gap-2 flex-wrap" aria-label="Doctor management pagination">
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
