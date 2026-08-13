<?php

$filters = $filters ?? ['search' => '', 'filter_date' => '', 'status' => ''];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0, 'per_page' => 10];
$summary = $summary ?? [];
$availability = $availability ?? [];
$statusOptions = $statusOptions ?? [];
$statusMessage = $statusMessage ?? null;
$csrfToken = $csrfToken ?? '';

require __DIR__ . '/../../partials/shared/status_helper.php';

$totalItems = (int) ($pagination['total_items'] ?? 0);
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
  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/doctor/dashboard') ?>">Dashboard</a></li>
        <li class="active">Availability</li>
      </ol>
      <h2 class="ux-page-header__title">My Availability Schedule</h2>
      <p class="ux-page-header__subtitle">Manage consultation slots patients can book. Edit future windows and remove or lock booked appointments.</p>
    </div>
    <div class="ux-page-header__right">
      <span class="ux-chip ux-badge--dotless">
        <i class="bi bi-calendar-week"></i>
        <span><?= $totalItems ?> slots visible</span>
      </span>
      <a href="<?= \App\Helpers\Helper::url('/doctor/availability/create') ?>" class="btn btn-primary btn-primary-xl">
        <i class="bi bi-calendar-plus me-2"></i>
        Create Availability
      </a>
    </div>
  </div>

  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-card ux-filter">
    <div class="card-header">
      <h3 class="h6">
        <i class="bi bi-funnel-fill ux-filter__header-icon"></i>
        Filter slots
      </h3>
    </div>
    <form method="GET" action="<?= \App\Helpers\Helper::url('/doctor/availability') ?>" novalidate>
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

        <div class="col-lg-2">
          <label for="filter_date" class="form-label">Date</label>
          <input
            type="date"
            class="form-control"
            id="filter_date"
            name="filter_date"
            value="<?= \App\Helpers\Helper::escape((string) ($filters['filter_date'] ?? '')) ?>"
          >
        </div>

        <div class="col-lg-2">
          <label for="status" class="form-label">Status</label>
          <select class="form-select" id="status" name="status" aria-label="Filter slots by status">
            <option value="">All statuses</option>
            <?php foreach ($statusOptions as $statusOption): ?>
              <option value="<?= \App\Helpers\Helper::escape($statusOption) ?>" <?= ($filters['status'] ?? '') === $statusOption ? 'selected' : '' ?>>
                <?= \App\Helpers\Helper::escape($statusOption) ?>
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
            <a href="<?= \App\Helpers\Helper::url('/doctor/availability') ?>" class="btn btn-outline-primary btn-sm">
              Reset
            </a>
          </div>
        </div>
      </div>
    </form>
  </div>

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

  <div class="ux-card">
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
                    <h4 class="ux-empty__title">No availability slots matched the current filters</h4>
                    <p class="ux-empty__text">Adjust the search or filters, or create a new consultation slot.</p>
                    <div class="ux-empty__action">
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
                $editAriaLabel = 'Edit availability slot on ' . ($slotDate !== '' ? \App\Helpers\Helper::formatDate($slotDate, 'd M Y', 'selected date') : 'selected date');
                $deleteAriaLabel = 'Delete availability slot on ' . ($slotDate !== '' ? \App\Helpers\Helper::formatDate($slotDate, 'd M Y', 'selected date') : 'selected date');
                ?>
                <tr>
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
                      <i class="bi <?= $iconClass ?> me-1"></i>
                      <?= \App\Helpers\Helper::escape($slotStatus) ?>
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
    <?php if (is_array($pagination) && ($pagination['total_pages'] ?? 0) > 1): ?>
      <?php
      $page = (int) ($pagination['current_page'] ?? 1);
      $totalPages = (int) ($pagination['total_pages'] ?? 1);
      ?>
      <div class="card-footer border-0 bg-transparent pt-4 pb-0">
        <nav class="d-flex justify-content-end align-items-center gap-2 flex-wrap" aria-label="Doctor availability pagination">
          <?php if ($page > 1): ?>
            <a href="<?= $buildPageUrl($page - 1) ?>" class="btn btn-outline-primary btn-sm">
              <i class="bi bi-chevron-left me-1"></i>
              Previous
            </a>
          <?php endif; ?>
          <span class="small text-muted">
            Page <?= $page ?> of <?= $totalPages ?>
          </span>
          <?php if ($page < $totalPages): ?>
            <a href="<?= $buildPageUrl($page + 1) ?>" class="btn btn-outline-primary btn-sm">
              Next
              <i class="bi bi-chevron-right ms-1"></i>
            </a>
          <?php endif; ?>
        </nav>
      </div>
    <?php endif; ?>
  </div>
</section>
