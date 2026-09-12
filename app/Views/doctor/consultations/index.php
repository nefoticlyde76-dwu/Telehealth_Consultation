<?php

$filters = $filters ?? ['status' => '', 'search' => '', 'date' => '', 'sort' => '', 'per_page' => 10];
$consultations = $consultations ?? [];
$historyGroups = $historyGroups ?? ['active' => [], 'completed' => [], 'closed' => []];
$grouped = (bool) ($grouped ?? false);
$summary = $summary ?? [];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0, 'from' => 0, 'to' => 0];
$statusOptions = $statusOptions ?? [];
$csrfToken = $csrfToken ?? '';
$filterActive = (bool) ($filterActive ?? false);

require __DIR__ . '/../../partials/shared/status_helper.php';

$activeRows = is_array($historyGroups['active'] ?? null) ? $historyGroups['active'] : [];
$completedRows = is_array($historyGroups['completed'] ?? null) ? $historyGroups['completed'] : [];
$closedRows = is_array($historyGroups['closed'] ?? null) ? $historyGroups['closed'] : [];
$hasAny = $activeRows !== [] || $completedRows !== [] || $closedRows !== [];
if (!$filterActive) {
    $filterActive = trim((string) ($filters['status'] ?? '')) !== ''
        || trim((string) ($filters['search'] ?? '')) !== ''
        || trim((string) ($filters['date'] ?? '')) !== '';
}

$statusFieldOptions = \App\Helpers\Status::filterOptions(
    \App\Helpers\Status::DOMAIN_CONSULTATION,
    $statusOptions
);

$filterTabs = array_merge(
    [['value' => '', 'label' => 'All']],
    $statusFieldOptions
);
$filterTabCurrent = (string) ($filters['status'] ?? '');
$filterTabAria = 'Filter consultations by status';
$filterTabUrl = static function (string $value) use ($filters): string {
    return \App\Helpers\ListFilter::url('/doctor/consultations', $filters, ['status' => $value, 'page' => 1]);
};

$paginationPath = '/doctor/consultations';
$paginationFilters = $filters;
$paginationLabel = 'consultations';
$paginationAria = 'Doctor consultations pagination';
$paginationShowCount = true;
$paginationBuildUrl = null;

$renderPagination = static function () use ($pagination, $paginationPath, $paginationFilters, $paginationLabel, $paginationAria, $paginationShowCount): void {
    ?>
    <div class="card-footer border-0 bg-transparent pt-4 pb-0">
      <?php require __DIR__ . '/../../partials/shared/list_pagination.php'; ?>
    </div>
    <?php
};
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <?php
  $pageHeaderTitle = 'My Consultations';
  $pageHeaderSubtitle = 'Join approved consultations when they are open, complete the clinical record after the session, and download finalized documents from completed visits.';
  $pageHeaderHeadingTag = 'h2';
  $pageHeaderBreadcrumbs = [
      ['label' => 'Dashboard', 'url' => '/doctor/dashboard'],
      ['label' => 'Consultations', 'active' => true],
  ];
  ob_start();
  ?>
  <span class="ux-chip ux-badge--dotless">
    <i class="bi bi-people"></i>
    <span><?= (int) (($summary['approved_appointments'] ?? 0) + ($summary['completed_consultations'] ?? 0)) ?> assigned consultations</span>
  </span>
  <a href="<?= \App\Helpers\Helper::url('/doctor/availability') ?>" class="btn btn-primary">
    <i class="bi bi-calendar-week me-2"></i>
    Manage Availability
  </a>
  <?php
  $pageHeaderActions = ob_get_clean();
  require __DIR__ . '/../../partials/dashboard/page_header.php';

  $summaryStats = [
      [
          'label' => 'Approved Appointments',
          'value' => (int) ($summary['approved_appointments'] ?? 0),
          'icon' => 'bi-calendar2-check',
          'tone' => 'success',
          'url' => \App\Helpers\Status::filteredListUrl('/doctor/consultations', \App\Helpers\Status::APPROVED),
      ],
      [
          'label' => 'Upcoming',
          'value' => (int) ($summary['upcoming_consultations'] ?? 0),
          'icon' => 'bi-clock',
          'tone' => 'cyan',
          'url' => \App\Helpers\Status::filteredListUrl('/doctor/consultations', \App\Helpers\Status::APPROVED),
      ],
      [
          'label' => 'Completed',
          'value' => (int) ($summary['completed_consultations'] ?? 0),
          'icon' => 'bi-check2-circle',
          'tone' => 'info',
          'url' => \App\Helpers\Status::filteredListUrl('/doctor/consultations', \App\Helpers\Status::COMPLETED),
      ],
      [
          'label' => 'No-Show',
          'value' => (int) ($summary['no_show_consultations'] ?? 0),
          'icon' => 'bi-person',
          'tone' => 'warning',
          'url' => \App\Helpers\Status::filteredListUrl('/doctor/consultations', \App\Helpers\Status::NO_SHOW),
      ],
  ];
  $summaryStatsCompact = true;
  require __DIR__ . '/../../partials/dashboard/summary_stats.php';
  ?>

  <?php if ($grouped): ?>
    <?php if (!$hasAny): ?>
      <div class="ux-card ux-data-card">
        <div class="ux-card__header">
          <h2 class="ux-data-card__title">Consultations</h2>
          <?php require __DIR__ . '/../../partials/shared/table_filter_tabs.php'; ?>
        </div>
        <?php
        $historyRows = [];
        $emptyTitle = $filterActive ? 'No matching consultations' : 'No consultations yet';
        $emptyText = $filterActive
            ? 'There are no consultations in this view.'
            : 'Approved and completed consultations will appear here.';
        $showResetAction = $filterActive;
        require __DIR__ . '/_history_table.php';
        ?>
      </div>
    <?php else: ?>
      <div class="ux-card ux-data-card ux-history-card mb-4">
        <div class="ux-card__header">
          <div>
            <h3 class="ux-data-card__title mb-1">
              <i class="bi bi-calendar2 me-1" aria-hidden="true"></i>
              Upcoming and active
            </h3>
            <p class="text-muted small mb-0">Approved consultations use Join Consultation when the session is open. Review and complete stays in the consultation room.</p>
          </div>
          <?php require __DIR__ . '/../../partials/shared/table_filter_tabs.php'; ?>
        </div>
        <?php
        $historyRows = $activeRows;
        $emptyTitle = 'No upcoming or active consultations';
        $emptyText = 'Approved consultations ready to join will appear here.';
        $emptyClass = 'ux-empty--panel';
        $emptyIcon = 'bi-calendar2';
        $showResetAction = false;
        require __DIR__ . '/_history_table.php';
        ?>
      </div>

      <div class="ux-card ux-data-card ux-history-card mb-4">
        <div class="ux-card__header d-flex justify-content-between align-items-baseline gap-3">
          <div>
            <h3 class="h6 mb-1">
              <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>
              Completed consultations
            </h3>
            <p class="text-muted small mb-0">Newest first. View Record opens the historical clinical record. PDF actions appear only when the document exists.</p>
          </div>
          <span class="ux-history-card__count"><?= count($completedRows) ?></span>
        </div>
        <?php
        $historyRows = $completedRows;
        $emptyTitle = 'No completed consultations yet';
        $emptyText = 'Completed consultations, finalized records, and issued prescriptions will appear here.';
        $showResetAction = false;
        require __DIR__ . '/_history_table.php';
        ?>
        <?php if ((int) ($pagination['total_items'] ?? 0) > 0): ?>
          <?php $renderPagination(); ?>
        <?php endif; ?>
      </div>

      <?php if ($closedRows !== []): ?>
        <div class="ux-card ux-data-card ux-history-card">
          <div class="ux-card__header d-flex justify-content-between align-items-baseline gap-3">
            <div>
              <h3 class="h6 mb-1">Closed consultations</h3>
              <p class="text-muted small mb-0">Rejected, cancelled, or no-show consultations remain listed for reference.</p>
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
    <div class="ux-card ux-data-card">
      <div class="ux-card__header">
        <h2 class="ux-data-card__title">Consultations</h2>
        <?php require __DIR__ . '/../../partials/shared/table_filter_tabs.php'; ?>
      </div>
      <?php
      $historyRows = $consultations;
      $emptyTitle = $filterActive ? 'No matching consultations' : 'No consultations yet';
      $emptyText = $filterActive
          ? 'There are no consultations in this view.'
          : 'Approved and completed consultations will appear here.';
      $showResetAction = true;
      require __DIR__ . '/_history_table.php';
      ?>
      <?php $renderPagination(); ?>
    </div>
  <?php endif; ?>
</section>
