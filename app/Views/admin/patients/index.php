<?php

$filters = $filters ?? ['search' => '', 'status' => ''];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0, 'per_page' => 10];
$summary = $summary ?? [];
$patients = $patients ?? [];
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

    return \App\Helpers\Helper::url('/admin/patients') . ($queryString !== '' ? '?' . $queryString : '');
};
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/admin/dashboard') ?>">Dashboard</a></li>
        <li class="active">Patients</li>
      </ol>
      <h2 class="ux-page-header__title">Patient Account Management</h2>
      <p class="ux-page-header__subtitle">Manage patient access securely — review profiles, update demographics, and control account status.</p>
    </div>
    <div class="ux-page-header__right">
      <span class="ux-chip ux-badge--dotless">
        <i class="bi bi-people-fill"></i>
        <span><?= $totalItems ?> patients visible</span>
      </span>
    </div>
  </div>

  <div class="ux-card ux-filter">
    <div class="card-header">
      <h3 class="h6">
        <i class="bi bi-funnel-fill ux-filter__header-icon"></i>
        Filter patients
      </h3>
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

        <div class="col-lg-2">
          <label for="status" class="form-label">Status</label>
          <select class="form-select" id="status" name="status" aria-label="Filter patients by status">
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
            <a href="<?= \App\Helpers\Helper::url('/admin/patients') ?>" class="btn btn-outline-primary btn-sm">
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
          <i class="bi bi-people-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['total_patients'] ?? 0) ?></div>
          <div class="ux-stat__label">Total Patients</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--success">
          <i class="bi bi-check-circle-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['active_patients'] ?? 0) ?></div>
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
          <div class="ux-stat__value"><?= (int) ($summary['inactive_patients'] ?? 0) ?></div>
          <div class="ux-stat__label">Inactive</div>
        </div>
      </div>
    </div>
  </div>

  <div class="ux-card">
    <div class="ux-table-wrapper border-0">
      <div class="table-responsive">
        <table class="ux-table">
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
                <td colspan="5" class="ux-table__empty-state">
                  <div class="ux-empty">
                    <div class="ux-empty__icon">
                      <i class="bi bi-person-bounding-box"></i>
                    </div>
                    <h4 class="ux-empty__title">No patient accounts matched the current filters</h4>
                    <p class="ux-empty__text">Adjust the search or status filter to broaden the patient listing.</p>
                    <div class="ux-empty__action">
                      <a href="<?= \App\Helpers\Helper::url('/admin/patients') ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-arrow-clockwise me-1"></i>
                        Reset Filters
                      </a>
                    </div>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($patients as $patient): ?>
                <?php
                $patientId = (int) ($patient['id'] ?? 0);
                $isActive = ($patient['status'] ?? '') === 'active';
                $fullName = trim((string) ($patient['full_name'] ?? 'Patient'));
                $statusBadge = ux_active_badge_class($patient['status'] ?? 'inactive');
                ?>
                <tr>
                  <td>
                    <div class="d-flex flex-column">
                      <strong><?= \App\Helpers\Helper::escape($fullName) ?></strong>
                      <span class="text-muted small"><?= \App\Helpers\Helper::escape((string) ($patient['email'] ?? '')) ?></span>
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
                    <span class="ux-badge <?= $statusBadge ?>">
                      <?= \App\Helpers\Helper::escape(ucfirst((string) ($patient['status'] ?? 'unknown'))) ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <div class="ux-table__actions">
                      <a href="<?= \App\Helpers\Helper::url('/admin/patients/' . $patientId . '/edit') ?>" class="btn btn-outline-primary btn-sm" aria-label="Edit patient account for <?= \App\Helpers\Helper::escape($fullName) ?>">
                        <i class="bi bi-pencil-square me-1"></i>
                        Edit
                      </a>
                      <form method="POST" action="<?= \App\Helpers\Helper::url('/admin/patients/' . $patientId . '/' . ($isActive ? 'deactivate' : 'activate')) ?>" class="d-inline">
                        <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">
                        <button type="submit" class="btn <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?> btn-sm" aria-label="<?= ($isActive ? 'Deactivate' : 'Activate') . ' patient account for ' . \App\Helpers\Helper::escape($fullName) ?>">
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
        <nav class="admin-pagination d-flex justify-content-end align-items-center gap-2 flex-wrap" aria-label="Patient management pagination">
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
