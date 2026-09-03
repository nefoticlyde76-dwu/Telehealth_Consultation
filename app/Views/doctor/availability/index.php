<?php

$filters = $filters ?? ['search' => '', 'date' => '', 'status' => '', 'sort' => 'earliest', 'per_page' => 10];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0, 'per_page' => 10, 'from' => 0, 'to' => 0];
$summary = $summary ?? [];
$availability = $availability ?? [];
$statusOptions = $statusOptions ?? [];
$dateOptions = $dateOptions ?? [];
$sortOptions = $sortOptions ?? [];
$statusMessage = $statusMessage ?? null;
$csrfToken = $csrfToken ?? '';
$filterActive = (bool) ($filterActive ?? false);

require __DIR__ . '/../../partials/shared/status_helper.php';

$totalItems = (int) ($pagination['total_items'] ?? 0);

$statusFieldOptions = \App\Helpers\Status::filterOptions(
    \App\Helpers\Status::DOMAIN_SLOT,
    $statusOptions
);

$filterForm = [
    'action' => \App\Helpers\Helper::url('/doctor/availability'),
    'title' => 'Filter slots',
    'clear_url' => \App\Helpers\Helper::url('/doctor/availability?view=list'),
    'hidden' => ['view' => 'list'],
    'search' => [
        'label' => 'Search',
        'placeholder' => 'Search by date, time, or notes',
        'value' => (string) ($filters['search'] ?? ''),
    ],
    'fields' => [
        [
            'type' => 'select',
            'name' => 'status',
            'label' => 'Status',
            'value' => (string) ($filters['status'] ?? ''),
            'empty_label' => 'All statuses',
            'options' => $statusFieldOptions,
        ],
        [
            'type' => 'date_preset',
            'name' => 'date',
            'label' => 'Date',
            'value' => (string) ($filters['date'] ?? ''),
            'empty_label' => 'All dates',
            'options' => $dateOptions,
            'from_value' => (string) ($filters['date_from'] ?? ''),
            'to_value' => (string) ($filters['date_to'] ?? ''),
        ],
        [
            'type' => 'select',
            'name' => 'sort',
            'label' => 'Sort',
            'value' => (string) ($filters['sort'] ?? 'earliest'),
            'include_empty' => false,
            'options' => $sortOptions,
        ],
        [
            'type' => 'select',
            'name' => 'per_page',
            'label' => 'Per page',
            'value' => (string) ((int) ($filters['per_page'] ?? 10)),
            'include_empty' => false,
            'options' => [
                ['value' => '10', 'label' => '10'],
                ['value' => '25', 'label' => '25'],
                ['value' => '50', 'label' => '50'],
            ],
        ],
    ],
];
$filterTabs = array_merge(
    [['value' => '', 'label' => 'All']],
    $statusFieldOptions
);
$filterTabCurrent = (string) ($filters['status'] ?? '');
$filterTabAria = 'Filter slots by status';
$filterTabUrl = static function (string $value) use ($filters): string {
    return \App\Helpers\ListFilter::url('/doctor/availability', $filters + ['view' => 'list'], ['status' => $value, 'page' => 1]);
};
?>

<section class="mb-4">
  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/doctor/dashboard') ?>">Dashboard</a></li>
        <li class="active">Availability</li>
      </ol>
      <h2 class="ux-page-header__title">My Availability Schedule</h2>
      <p class="ux-page-header__subtitle">Review saved slots, booked appointments, and one-off entries. Use the weekly schedule to set 30-minute times in bulk.</p>
    </div>
    <div class="ux-page-header__right">
      <span class="ux-chip ux-badge--dotless">
        <i class="bi bi-calendar-week"></i>
        <span><?= $totalItems ?> slots visible</span>
      </span>
      <a href="<?= \App\Helpers\Helper::url('/doctor/availability') ?>" class="btn btn-outline-primary">
        <i class="bi bi-calendar3 me-2"></i>
        Weekly Schedule
      </a>
      <a href="<?= \App\Helpers\Helper::url('/doctor/availability/create') ?>" class="btn btn-primary btn-primary-xl">
        <i class="bi bi-calendar-plus me-2"></i>
        Create Availability
      </a>
    </div>
  </div>

  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>
  <?php require __DIR__ . '/../../partials/shared/list_filter.php'; ?>

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--navy">
          <i class="bi bi-calendar3"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['total_slots'] ?? 0) ?></div>
          <div class="ux-stat__label">Total Slots</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--success">
          <i class="bi bi-calendar2-check-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['available_slots'] ?? 0) ?></div>
          <div class="ux-stat__label">Available</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--cyan">
          <i class="bi bi-calendar2-event-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['booked_slots'] ?? 0) ?></div>
          <div class="ux-stat__label">Booked</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--purple">
          <i class="bi bi-calendar-event-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['upcoming_slots'] ?? 0) ?></div>
          <div class="ux-stat__label">Upcoming</div>
        </div>
      </div>
    </div>
  </div>

  <div class="ux-card ux-data-card">
    <div class="ux-card__header">
      <h2 class="ux-data-card__title">Availability slots</h2>
      <?php require __DIR__ . '/../../partials/shared/table_filter_tabs.php'; ?>
    </div>
    <div class="ux-table-wrapper border-0">
      <div class="table-responsive">
        <table class="ux-table">
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
                <td colspan="5" class="ux-table__empty-state">
                  <div class="ux-empty">
                    <div class="ux-empty__icon">
                      <i class="bi bi-calendar-x"></i>
                    </div>
                    <h4 class="ux-empty__title"><?= $filterActive ? 'No matching availability slots' : 'No availability slots yet' ?></h4>
                    <p class="ux-empty__text"><?= $filterActive ? 'Try changing your search or filters, or create a new consultation slot.' : 'Create a consultation slot so patients can book an appointment.' ?></p>
                    <div class="ux-empty__action">
                      <?php if ($filterActive): ?>
                        <a href="<?= \App\Helpers\Helper::url('/doctor/availability') ?>" class="btn btn-outline-primary btn-sm">
                          Clear Filters
                        </a>
                      <?php endif; ?>
                      <a href="<?= \App\Helpers\Helper::url('/doctor/availability/create') ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-calendar-plus me-1"></i>
                        Create Slot
                      </a>
                    </div>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($availability as $slot): ?>
                <?php
                $slotId = (int) ($slot['id'] ?? 0);
                $slotStatus = (string) ($slot['status'] ?? 'Available');
                $isAvailable = $slotStatus === 'Available';
                $badgeClass = ux_slot_status_badge_class($slotStatus, $isAvailable ? false : true);
                $iconClass = ux_status_icon_class($slotStatus, $isAvailable ? 'bi-calendar2-check-fill' : 'bi-calendar2-event-fill');
                $slotDate = (string) ($slot['consultation_date'] ?? '');
                $slotExpiresAt = $isAvailable
                    ? \App\Helpers\Helper::combineDateTimeIso($slotDate, (string) ($slot['end_time'] ?? ''))
                    : '';
                $editAriaLabel = 'Edit availability slot on ' . ($slotDate !== '' ? \App\Helpers\Helper::formatDate($slotDate, 'd M Y', 'selected date') : 'selected date');
                $deleteAriaLabel = 'Delete availability slot on ' . ($slotDate !== '' ? \App\Helpers\Helper::formatDate($slotDate, 'd M Y', 'selected date') : 'selected date');
                ?>
                <tr<?= $slotExpiresAt !== '' ? ' data-slot-expires-at="' . \App\Helpers\Helper::escape($slotExpiresAt) . '"' : '' ?>>
                  <td>
                    <div class="d-flex flex-column">
                      <strong><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate($slotDate, 'd M Y', 'Not available')) ?></strong>
                      <span class="text-muted small"><?= \App\Helpers\Helper::escape($slotDate) ?></span>
                    </div>
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
                    <span class="ux-badge <?= $badgeClass ?>">
                      <i class="bi <?= $iconClass ?> me-1" aria-hidden="true"></i>
                      <?= \App\Helpers\Helper::escape(ux_status_label($slotStatus, \App\Helpers\Status::DOMAIN_SLOT)) ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <div class="ux-table__actions">
                      <?php if ($isAvailable && $slotId > 0): ?>
                        <a
                          href="<?= \App\Helpers\Helper::url('/doctor/availability/' . $slotId . '/edit') ?>"
                          class="btn btn-outline-primary btn-sm"
                          aria-label="<?= \App\Helpers\Helper::escape($editAriaLabel) ?>"
                        >
                          <i class="bi bi-pencil-square me-1"></i>
                          Edit
                        </a>
                        <form method="POST" action="<?= \App\Helpers\Helper::url('/doctor/availability/' . $slotId . '/delete') ?>" class="d-inline">
                          <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">
                          <button
                            type="submit"
                            class="btn btn-outline-danger btn-sm"
                            aria-label="<?= \App\Helpers\Helper::escape($deleteAriaLabel) ?>"
                          >
                            <i class="bi bi-trash me-1"></i>
                            Delete
                          </button>
                        </form>
                      <?php else: ?>
                        <span class="btn btn-outline-secondary btn-sm disabled" aria-disabled="true">
                          <i class="bi bi-lock-fill me-1"></i>
                          Edit
                        </span>
                        <span class="btn btn-outline-secondary btn-sm disabled" aria-disabled="true">
                          <i class="bi bi-lock-fill me-1"></i>
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
    </div>
    <div class="card-footer border-0 bg-transparent pt-4 pb-0">
      <?php
      $paginationPath = '/doctor/availability';
      $paginationFilters = $filters + ['view' => 'list'];
      $paginationLabel = 'slots';
      $paginationAria = 'Doctor availability pagination';
      $paginationShowCount = true;
      $paginationBuildUrl = null;
      require __DIR__ . '/../../partials/shared/list_pagination.php';
      ?>
    </div>
  </div>
</section>
