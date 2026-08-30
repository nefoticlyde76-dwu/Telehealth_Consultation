<?php

$requests = $requests ?? [];
$historyGroups = $historyGroups ?? ['active' => [], 'completed' => [], 'closed' => []];
$summary = $summary ?? [];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0, 'from' => 0, 'to' => 0];
$filters = $filters ?? ['search' => '', 'status' => '', 'date' => '', 'sort' => '', 'documents' => '', 'per_page' => 10];
$statusOptions = $statusOptions ?? [];
$dateOptions = $dateOptions ?? [];
$sortOptions = $sortOptions ?? [];
$documentOptions = $documentOptions ?? [];
$grouped = (bool) ($grouped ?? true);
$filterActive = (bool) ($filterActive ?? false);

require __DIR__ . '/../../partials/shared/status_helper.php';

$totalItems = (int) ($pagination['total_items'] ?? 0);
$activeRows = is_array($historyGroups['active'] ?? null) ? $historyGroups['active'] : [];
$completedRows = is_array($historyGroups['completed'] ?? null) ? $historyGroups['completed'] : [];
$closedRows = is_array($historyGroups['closed'] ?? null) ? $historyGroups['closed'] : [];
$hasAny = $activeRows !== [] || $completedRows !== [] || $closedRows !== [];

$statusFieldOptions = \App\Helpers\Status::filterOptions(
    \App\Helpers\Status::DOMAIN_CONSULTATION,
    $statusOptions
);

$filterForm = [
    'action' => \App\Helpers\Helper::url('/patient/consultation-requests'),
    'title' => 'Find a consultation',
    'search' => [
        'label' => 'Search',
        'placeholder' => 'Search by doctor or medication name',
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
            'name' => 'documents',
            'label' => 'Records',
            'value' => (string) ($filters['documents'] ?? ''),
            'empty_label' => 'All records',
            'options' => $documentOptions,
        ],
        [
            'type' => 'select',
            'name' => 'sort',
            'label' => 'Sort',
            'value' => (string) ($filters['sort'] ?? ''),
            'empty_label' => 'Default',
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
    'clear_url' => \App\Helpers\Helper::url('/patient/consultation-requests'),
];

$paginationPath = '/patient/consultation-requests';
$paginationFilters = $filters;
$paginationLabel = 'consultations';
$paginationAria = 'Patient consultation history pagination';
$paginationShowCount = true;
$paginationBuildUrl = null;
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/patient/dashboard') ?>">Dashboard</a></li>
        <li class="active">My Consultations</li>
      </ol>
      <h2 class="ux-page-header__title">My Consultations</h2>
      <p class="ux-page-header__subtitle">Join an approved consultation when it is open, or open a completed consultation record and download available documents from here.</p>
    </div>
    <div class="ux-page-header__right">
      <span class="ux-chip ux-badge--dotless">
        <i class="bi bi-clipboard2-check-fill"></i>
        <span><?= $grouped ? ((int) ($summary['consultation_history'] ?? $totalItems) . ' consultations') : ($totalItems . ' matching consultations') ?></span>
      </span>
      <a href="<?= \App\Helpers\Helper::url('/patient/doctors') ?>" class="btn btn-primary btn-primary-xl">
        <i class="bi bi-person-badge me-2"></i>
        Book a Slot
      </a>
    </div>
  </div>

  <?php require __DIR__ . '/../../partials/shared/list_filter.php'; ?>

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--pending">
          <i class="bi bi-clock-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['pending_requests'] ?? 0) ?></div>
          <div class="ux-stat__label">Pending</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--success">
          <i class="bi bi-check-circle-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['approved_requests'] ?? 0) ?></div>
          <div class="ux-stat__label">Approved</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--cyan">
          <i class="bi bi-calendar3-event-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['upcoming_appointments'] ?? 0) ?></div>
          <div class="ux-stat__label">Upcoming</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--navy">
          <i class="bi bi-clipboard2-data-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['completed_requests'] ?? 0) ?></div>
          <div class="ux-stat__label">Completed</div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($grouped): ?>
    <?php if (!$hasAny): ?>
      <div class="ux-card ux-data-card">
        <div class="ux-card__header">
          <h2 class="ux-data-card__title">Consultations</h2>
        </div>
        <?php
        $historyRows = [];
        $emptyTitle = $filterActive ? 'No matching consultations' : 'No consultations in your history yet';
        $emptyText = $filterActive
            ? 'Try changing your search or filters.'
            : 'Book an available slot to start a consultation. Completed records and prescriptions will appear here.';
        $showEmptyAction = !$filterActive;
        require __DIR__ . '/_history_table.php';
        ?>
      </div>
    <?php else: ?>
      <div class="ux-card ux-data-card ux-history-card mb-4">
        <div class="ux-card__header d-flex justify-content-between align-items-baseline gap-3">
          <div>
            <h3 class="h6 mb-1">Upcoming and active</h3>
            <p class="text-muted small mb-0">Pending review or approved sessions. Join Consultation is available only while the session is open.</p>
          </div>
          <span class="text-muted small"><?= count($activeRows) ?></span>
        </div>
        <?php
        $historyRows = $activeRows;
        $emptyTitle = 'No upcoming or active consultations';
        $emptyText = 'Approved consultations will appear here when they are ready to join.';
        $showEmptyAction = false;
        require __DIR__ . '/_history_table.php';
        ?>
      </div>

      <div class="ux-card ux-data-card ux-history-card mb-4">
        <div class="ux-card__header d-flex justify-content-between align-items-baseline gap-3">
          <div>
            <h3 class="h6 mb-1">Completed consultations</h3>
            <p class="text-muted small mb-0">Newest first. View Record opens the historical clinical record. Download actions appear only when the document exists.</p>
          </div>
          <span class="text-muted small"><?= count($completedRows) ?></span>
        </div>
        <?php
        $historyRows = $completedRows;
        $emptyTitle = 'No completed consultations yet';
        $emptyText = 'Your completed consultations will appear here after your appointments are completed.';
        $showEmptyAction = false;
        require __DIR__ . '/_history_table.php';
        ?>
        <?php if ((int) ($pagination['total_items'] ?? 0) > 0): ?>
          <div class="card-footer border-0 bg-transparent pt-4 pb-0">
            <?php require __DIR__ . '/../../partials/shared/list_pagination.php'; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($closedRows !== []): ?>
        <div class="ux-card ux-data-card ux-history-card">
          <div class="ux-card__header d-flex justify-content-between align-items-baseline gap-3">
            <div>
              <h3 class="h6 mb-1">Closed requests</h3>
              <p class="text-muted small mb-0">Rejected or cancelled bookings remain in your history for reference.</p>
            </div>
            <span class="text-muted small"><?= count($closedRows) ?></span>
          </div>
          <?php
          $historyRows = $closedRows;
          $emptyTitle = 'No closed requests';
          $emptyText = '';
          $showEmptyAction = false;
          require __DIR__ . '/_history_table.php';
          ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  <?php else: ?>
    <div class="ux-card ux-data-card">
      <div class="ux-card__header">
        <h2 class="ux-data-card__title">Consultations</h2>
      </div>
      <?php
      $historyRows = $requests;
      $emptyTitle = 'No matching consultations';
      $emptyText = 'Try changing your search or filters.';
      $showEmptyAction = false;
      require __DIR__ . '/_history_table.php';
      ?>
      <div class="card-footer border-0 bg-transparent pt-4 pb-0">
        <?php require __DIR__ . '/../../partials/shared/list_pagination.php'; ?>
      </div>
    </div>
  <?php endif; ?>
</section>
