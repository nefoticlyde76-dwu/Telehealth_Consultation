<?php

use App\Helpers\Helper;
use App\Helpers\ListFilter;
use App\Helpers\Status;

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

    return Helper::url('/admin/doctors') . ($queryString !== '' ? '?' . $queryString : '');
};

$filterForm = [
    'action' => Helper::url('/admin/doctors'),
    'title' => 'Filter clinicians',
    'clear_url' => Helper::url('/admin/doctors'),
    'search' => [
        'name' => 'search',
        'value' => (string) ($filters['search'] ?? ''),
        'placeholder' => 'Search by full name, email, specialization, title, or employee ID',
        'label' => 'Search doctors',
    ],
    'fields' => [
        [
            'type' => 'select',
            'name' => 'status',
            'label' => 'Status',
            'value' => (string) ($filters['status'] ?? ''),
            'empty_label' => 'All statuses',
            'options' => array_map(static fn (string $opt): array => [
                'value' => $opt,
                'label' => Status::label($opt, Status::DOMAIN_USER),
            ], $statusOptions),
        ],
    ],
];
$filterTabs = array_merge(
    [['value' => '', 'label' => 'All']],
    array_map(static fn (string $opt): array => [
        'value' => $opt,
        'label' => Status::label($opt, Status::DOMAIN_USER),
    ], $statusOptions)
);
$filterTabCurrent = (string) ($filters['status'] ?? '');
$filterTabAria = 'Filter doctors by status';
$filterTabUrl = static function (string $value) use ($filters): string {
    return ListFilter::url('/admin/doctors', $filters, ['status' => $value, 'page' => 1]);
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
      <p class="ux-page-header__subtitle">Manage clinician access securely — create profiles, review invitation status, and control established accounts.</p>
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

  <?php require __DIR__ . '/../../partials/shared/list_filter.php'; ?>

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
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
    <div class="col-sm-6 col-xl-3">
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
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--pending">
          <i class="bi bi-envelope"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['pending_doctors'] ?? 0) ?></div>
          <div class="ux-stat__label">Invitation pending</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--slate">
          <i class="bi bi-person-dash-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['inactive_suspended_doctors'] ?? 0) ?></div>
          <div class="ux-stat__label">Inactive / Suspended</div>
        </div>
      </div>
    </div>
  </div>

  <div class="ux-card ux-data-card">
    <div class="ux-card__header">
      <h2 class="ux-data-card__title">Doctors</h2>
      <?php require __DIR__ . '/../../partials/shared/table_filter_tabs.php'; ?>
    </div>
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
                $fullName = trim((string) ($doctor['full_name'] ?? 'Doctor'));
                $actions = \App\Services\AdminDoctorService::managementActions($doctor);
                ?>
                <tr>
                  <td>
                    <?php
                    $personName = $fullName;
                    $personPhoto = $doctor['profile_photo_path'] ?? null;
                    $personMeta = (string) ($doctor['professional_title'] ?? 'Professional title not assigned');
                    $personSize = 'sm';
                    require __DIR__ . '/../../partials/shared/person_row.php';
                    ?>
                    <span class="text-muted small d-block mt-1"><?= \App\Helpers\Helper::escape((string) ($doctor['email'] ?? '')) ?></span>
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
                    <?= ux_status_badge((string) ($doctor['status'] ?? ''), \App\Helpers\Status::DOMAIN_USER) ?>
                  </td>
                  <td class="text-end">
                    <div class="ux-table__actions">
                      <?php if ($actions['edit']): ?>
                      <a href="<?= \App\Helpers\Helper::url('/admin/doctors/' . $doctorId . '/edit') ?>" class="btn btn-outline-primary btn-sm" aria-label="Edit doctor account for <?= \App\Helpers\Helper::escape($fullName) ?>">
                        <i class="bi bi-pencil-square me-1"></i>
                        Edit
                      </a>
                      <?php endif; ?>
                      <?php if ($actions['resend_invitation']): ?>
                      <form
                        method="POST"
                        action="<?= \App\Helpers\Helper::url('/admin/doctors/' . $doctorId . '/resend-invitation') ?>"
                        class="d-inline"
                        data-confirm-title="Resend the password setup invitation?"
                        data-confirm-body="The previous invitation link will stop working. A new invitation will be emailed to the doctor's registered address."
                        data-confirm-hint="The doctor remains invitation pending until they set a password."
                        data-confirm-tone="primary"
                      >
                        <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">
                        <button type="submit" class="btn btn-outline-secondary btn-sm" aria-label="Resend invitation for <?= \App\Helpers\Helper::escape($fullName) ?>">
                          <i class="bi bi-envelope me-1"></i>
                          Resend Invitation
                        </button>
                      </form>
                      <?php endif; ?>
                      <?php if ($actions['reset_password']): ?>
                      <a href="<?= \App\Helpers\Helper::url('/admin/doctors/' . $doctorId . '/reset-password') ?>" class="btn btn-outline-secondary btn-sm" aria-label="Reset password for <?= \App\Helpers\Helper::escape($fullName) ?>">
                        <i class="bi bi-key me-1"></i>
                        Reset Password
                      </a>
                      <?php endif; ?>
                      <?php if ($actions['deactivate']): ?>
                      <form
                        method="POST"
                        action="<?= \App\Helpers\Helper::url('/admin/doctors/' . $doctorId . '/deactivate') ?>"
                        class="d-inline"
                        data-confirm-title="Deactivate this doctor account?"
                        data-confirm-body="The doctor will be unable to sign in. This is not permanent deletion."
                        data-confirm-hint="You can reactivate the account later if this was a mistake."
                        data-confirm-tone="danger"
                      >
                        <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm" aria-label="Deactivate doctor account for <?= \App\Helpers\Helper::escape($fullName) ?>">
                          <i class="bi bi-person-dash me-1"></i>
                          Deactivate
                        </button>
                      </form>
                      <?php elseif ($actions['activate']): ?>
                      <form
                        method="POST"
                        action="<?= \App\Helpers\Helper::url('/admin/doctors/' . $doctorId . '/activate') ?>"
                        class="d-inline"
                        data-confirm-title="Reactivate this doctor account?"
                        data-confirm-body="This doctor will be able to sign in again."
                        data-confirm-hint="You can change the status again later if needed."
                        data-confirm-tone="primary"
                      >
                        <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">
                        <button type="submit" class="btn btn-outline-success btn-sm" aria-label="Activate doctor account for <?= \App\Helpers\Helper::escape($fullName) ?>">
                          <i class="bi bi-person-check me-1"></i>
                          Activate
                        </button>
                      </form>
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
