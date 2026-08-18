<?php

$filters = $filters ?? ['status' => '', 'search' => ''];
$consultations = $consultations ?? [];
$historyGroups = $historyGroups ?? ['active' => [], 'completed' => [], 'closed' => []];
$grouped = (bool) ($grouped ?? false);
$summary = $summary ?? [];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0];
$statusOptions = $statusOptions ?? [];
$csrfToken = $csrfToken ?? '';

require __DIR__ . '/../../partials/shared/status_helper.php';

$activeRows = is_array($historyGroups['active'] ?? null) ? $historyGroups['active'] : [];
$completedRows = is_array($historyGroups['completed'] ?? null) ? $historyGroups['completed'] : [];
$closedRows = is_array($historyGroups['closed'] ?? null) ? $historyGroups['closed'] : [];
$hasAny = $activeRows !== [] || $completedRows !== [] || $closedRows !== [];
$filterActive = trim((string) ($filters['status'] ?? '')) !== '' || trim((string) ($filters['search'] ?? '')) !== '';

$buildPageUrl = static function (int $page) use ($filters): string {
    $query = array_filter([
        'status' => $filters['status'] ?? '',
        'search' => $filters['search'] ?? '',
        'page' => $page,
    ], static fn ($value) => $value !== '');

    $queryString = http_build_query($query);

    return \App\Helpers\Helper::url('/doctor/consultations') . ($queryString !== '' ? '?' . $queryString : '');
};

$renderPagination = static function () use ($pagination, $buildPageUrl): void {
    if (($pagination['total_pages'] ?? 1) <= 1) {
        return;
    }
    ?>
    <div class="card-footer border-0 bg-transparent pt-4 pb-0">
      <nav class="admin-pagination d-flex justify-content-end align-items-center gap-2 flex-wrap" aria-label="Doctor consultations pagination">
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
    <?php
};
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/doctor/dashboard') ?>">Dashboard</a></li>
        <li class="active">Consultations</li>
      </ol>
      <h2 class="ux-page-header__title">My Consultations</h2>
      <p class="ux-page-header__subtitle">Join approved consultations when they are open, complete the clinical record after the session, and download finalized documents from completed visits.</p>
    </div>
    <div class="ux-page-header__right">
      <span class="ux-chip ux-badge--dotless">
        <i class="bi bi-clipboard2-pulse-fill"></i>
        <span><?= (int) (($summary['approved_appointments'] ?? 0) + ($summary['completed_consultations'] ?? 0)) ?> assigned consultations</span>
      </span>
      <a href="<?= \App\Helpers\Helper::url('/doctor/availability') ?>" class="btn btn-primary btn-primary-xl">
        <i class="bi bi-calendar-week me-2"></i>
        Manage Availability
      </a>
    </div>
  </div>

  <div class="ux-card ux-filter">
    <div class="card-header">
      <h3 class="h6">
        <i class="bi bi-funnel-fill ux-filter__header-icon"></i>
        Filter consultations
      </h3>
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
          <select class="form-select" id="status" name="status" aria-label="Filter consultations by status">
            <option value="">All statuses</option>
            <?php foreach ($statusOptions as $statusOption): ?>
              <option value="<?= \App\Helpers\Helper::escape((string) $statusOption) ?>" <?= ($filters['status'] ?? '') === $statusOption ? 'selected' : '' ?>>
                <?= \App\Helpers\Helper::escape((string) $statusOption) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <div class="ux-filter__actions">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-funnel-fill me-1"></i>
              Apply
            </button>
            <a href="<?= \App\Helpers\Helper::url('/doctor/consultations') ?>" class="btn btn-outline-primary btn-sm">
              Reset
            </a>
          </div>
        </div>
      </div>
    </form>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-sm-4">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--navy">
          <i class="bi bi-calendar2-check-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['approved_appointments'] ?? 0) ?></div>
          <div class="ux-stat__label">Approved Appointments</div>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--cyan">
          <i class="bi bi-calendar3-event-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['upcoming_consultations'] ?? 0) ?></div>
          <div class="ux-stat__label">Upcoming</div>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--success">
          <i class="bi bi-check2-circle"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['completed_consultations'] ?? 0) ?></div>
          <div class="ux-stat__label">Completed</div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($grouped): ?>
    <?php if (!$hasAny): ?>
      <div class="ux-card">
        <?php
        $historyRows = [];
        $emptyTitle = 'No consultations matched the current filters';
        $emptyText = 'Approved and completed consultations will appear here.';
        $showResetAction = $filterActive;
        require __DIR__ . '/_history_table.php';
        ?>
      </div>
    <?php else: ?>
      <div class="ux-card ux-history-card mb-4">
        <div class="ux-card__header d-flex justify-content-between align-items-baseline gap-3">
          <div>
            <h3 class="h6 mb-1">Upcoming and active</h3>
            <p class="text-muted small mb-0">Approved consultations use Join Consultation when the session is open. Review and complete stays in the consultation room.</p>
          </div>
          <span class="text-muted small"><?= count($activeRows) ?></span>
        </div>
        <?php
        $historyRows = $activeRows;
        $emptyTitle = 'No upcoming or active consultations';
        $emptyText = 'Approved consultations ready to join will appear here.';
        $showResetAction = false;
        require __DIR__ . '/_history_table.php';
        ?>
      </div>

      <div class="ux-card ux-history-card mb-4">
        <div class="ux-card__header d-flex justify-content-between align-items-baseline gap-3">
          <div>
            <h3 class="h6 mb-1">Completed consultations</h3>
            <p class="text-muted small mb-0">Newest first. View Record opens the historical clinical record. PDF actions appear only when the document exists.</p>
          </div>
          <span class="text-muted small"><?= count($completedRows) ?></span>
        </div>
        <?php
        $historyRows = $completedRows;
        $emptyTitle = 'No completed consultations yet';
        $emptyText = 'Completed consultations, finalized records, and issued prescriptions will appear here.';
        $showResetAction = false;
        require __DIR__ . '/_history_table.php';
        ?>
        <?php $renderPagination(); ?>
      </div>

      <?php if ($closedRows !== []): ?>
        <div class="ux-card ux-history-card">
          <div class="ux-card__header d-flex justify-content-between align-items-baseline gap-3">
            <div>
              <h3 class="h6 mb-1">Closed consultations</h3>
              <p class="text-muted small mb-0">Rejected or cancelled consultations remain listed for reference.</p>
            </div>
            <span class="text-muted small"><?= count($closedRows) ?></span>
          </div>
          <?php
          $historyRows = $closedRows;
          $emptyTitle = 'No closed consultations';
          $emptyText = '';
          $showResetAction = false;
          require __DIR__ . '/_history_table.php';
          ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  <?php else: ?>
    <div class="ux-card">
      <?php
      $historyRows = $consultations;
      $emptyTitle = 'No consultations matched the current filters';
      $emptyText = 'Approved and completed consultations will appear here.';
      $showResetAction = true;
      require __DIR__ . '/_history_table.php';
      ?>
      <?php $renderPagination(); ?>
    </div>
  <?php endif; ?>
</section>
