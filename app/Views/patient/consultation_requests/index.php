<?php

$requests = $requests ?? [];
$historyGroups = $historyGroups ?? ['active' => [], 'completed' => [], 'closed' => []];
$summary = $summary ?? [];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0];

require __DIR__ . '/../../partials/shared/status_helper.php';

$totalItems = (int) ($pagination['total_items'] ?? 0);
$activeRows = is_array($historyGroups['active'] ?? null) ? $historyGroups['active'] : [];
$completedRows = is_array($historyGroups['completed'] ?? null) ? $historyGroups['completed'] : [];
$closedRows = is_array($historyGroups['closed'] ?? null) ? $historyGroups['closed'] : [];
$hasAny = $activeRows !== [] || $completedRows !== [] || $closedRows !== [];
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
        <span><?= $totalItems ?> requests visible</span>
      </span>
      <a href="<?= \App\Helpers\Helper::url('/patient/doctors') ?>" class="btn btn-primary btn-primary-xl">
        <i class="bi bi-person-badge me-2"></i>
        Book a Slot
      </a>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--amber">
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

  <?php if (!$hasAny): ?>
    <div class="ux-card">
      <?php
      $historyRows = [];
      $emptyTitle = 'No consultations in your history yet';
      $emptyText = 'Book an available slot to start a consultation. Completed records and prescriptions will appear here.';
      $showEmptyAction = true;
      require __DIR__ . '/_history_table.php';
      ?>
    </div>
  <?php else: ?>
    <div class="ux-card ux-history-card mb-4">
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

    <div class="ux-card ux-history-card mb-4">
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
      $emptyText = 'Completed consultations, finalized records, and issued prescriptions will appear here.';
      $showEmptyAction = false;
      require __DIR__ . '/_history_table.php';
      ?>
    </div>

    <?php if ($closedRows !== []): ?>
      <div class="ux-card ux-history-card">
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
</section>
