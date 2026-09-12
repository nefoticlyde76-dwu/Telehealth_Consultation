<?php
use App\Helpers\Helper;
use App\Helpers\ListFilter;

$filters = is_array($filters ?? null) ? $filters : [];
$logs = is_array($logs ?? null) ? $logs : [];
$pagination = is_array($pagination ?? null) ? $pagination : [];
$roleOptions = is_array($roleOptions ?? null) ? $roleOptions : [];
$dateOptions = is_array($dateOptions ?? null) ? $dateOptions : [];
$summary = is_array($summary ?? null) ? $summary : [];
$filterActive = (bool) ($filterActive ?? false);
$search = (string) ($filters['search'] ?? '');
$datePreset = (string) ($filters['date'] ?? '');

$filterTabs = array_merge(
    [['value' => '', 'label' => 'All']],
    ListFilter::selectOptions($roleOptions)
);
$filterTabCurrent = (string) ($filters['role'] ?? '');
$filterTabAria = 'Filter activity by role';
$filterTabUrl = static function (string $value) use ($filters): string {
    return ListFilter::url('/admin/audit-logs', $filters, ['role' => $value, 'page' => 1]);
};

$filterForm = [
    'id' => 'tf-audit',
    'action' => Helper::url('/admin/audit-logs'),
    'clear_url' => Helper::url('/admin/audit-logs'),
    'active' => $filterActive,
    'hidden' => array_filter([
        'role' => (string) ($filters['role'] ?? ''),
        'date' => $datePreset,
    ]),
    'search' => [
        'name' => 'search',
        'value' => $search,
        'placeholder' => 'Search logs by user, action, or details...',
        'label' => 'Search',
    ],
    'toolbar' => [
        [
            'name' => 'date',
            'label' => 'Date range',
            'value' => $datePreset,
            'empty_label' => 'Date range',
            'options' => $dateOptions,
        ],
    ],
];
$tableFilterId = 'tf-audit';
$exportUrl = ListFilter::url('/admin/audit-logs', $filters, ['export' => 'csv']);
?>

<section class="mb-4">
  <?php
  $pageHeaderTitle = 'Activity & Audit Logs';
  $pageHeaderSubtitle = 'Chronological records of important security and workflow events.';
  $pageHeaderIcon = 'bi-shield-check';
  $pageHeaderHeadingTag = 'h2';
  $pageHeaderBreadcrumbs = [
      ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
      ['label' => 'Activity & Audit Logs', 'active' => true],
  ];
  ob_start();
  ?>
  <span class="ux-chip ux-chip--secure ux-badge--dotless">
    <i class="bi bi-shield-check" aria-hidden="true"></i>
    <span>
      <strong>Secure &amp; Compliant</strong>
      <small>All actions are logged for audit and security purposes.</small>
    </span>
  </span>
  <?php
  $pageHeaderActions = ob_get_clean();
  require __DIR__ . '/../../partials/dashboard/page_header.php';
  ?>

  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <?php
  $summaryStats = [
      [
          'label' => 'Total Logs',
          'value' => (int) ($summary['total_logs'] ?? ($pagination['total_items'] ?? 0)),
          'description' => 'All activity records',
          'icon' => 'bi-journal-text',
          'tone' => 'navy',
      ],
      [
          'label' => 'Successful',
          'value' => (int) ($summary['success_logs'] ?? 0),
          'description' => 'Successful actions',
          'icon' => 'bi-person-check',
          'tone' => 'success',
      ],
      [
          'label' => 'Failed',
          'value' => (int) ($summary['failed_logs'] ?? 0),
          'description' => 'Failed or blocked events',
          'icon' => 'bi-exclamation-circle',
          'tone' => 'warning',
      ],
      [
          'label' => 'Today',
          'value' => (int) ($summary['today_logs'] ?? 0),
          'description' => 'Events recorded today',
          'icon' => 'bi-people',
          'tone' => 'info',
      ],
  ];
  $summaryStatsCompact = true;
  require __DIR__ . '/../../partials/dashboard/summary_stats.php';
  ?>

  <div class="ux-card ux-data-card" data-table-filter="tf-audit">
    <?php require __DIR__ . '/../../partials/shared/table_filter_form.php'; ?>
    <div class="ux-card__header d-flex flex-wrap justify-content-between align-items-center gap-3">
      <div class="d-flex flex-wrap align-items-center gap-3 min-w-0 flex-grow-1">
        <?php require __DIR__ . '/../../partials/shared/table_toolbar.php'; ?>
        <?php require __DIR__ . '/../../partials/shared/table_filter_tabs.php'; ?>
      </div>
      <a href="<?= Helper::escape($exportUrl) ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-download me-1" aria-hidden="true"></i>
        Export
      </a>
    </div>
    <div class="ux-table-wrapper border-0">
      <div class="table-responsive">
        <table class="ux-table">
          <thead>
          <tr>
            <th scope="col"><?php $colLabel = 'When'; $colFilter = null; require __DIR__ . '/../../partials/shared/table_col_filter.php'; ?></th>
            <th scope="col"><?php $colLabel = 'Actor'; $colFilter = null; require __DIR__ . '/../../partials/shared/table_col_filter.php'; ?></th>
            <th scope="col"><?php $colLabel = 'Action'; $colFilter = null; require __DIR__ . '/../../partials/shared/table_col_filter.php'; ?></th>
            <th scope="col"><?php $colLabel = 'Entity'; $colFilter = null; require __DIR__ . '/../../partials/shared/table_col_filter.php'; ?></th>
            <th scope="col"><?php $colLabel = 'Outcome'; $colFilter = null; require __DIR__ . '/../../partials/shared/table_col_filter.php'; ?></th>
            <th scope="col"><?php $colLabel = 'Last login'; $colFilter = null; require __DIR__ . '/../../partials/shared/table_col_filter.php'; ?></th>
            <th scope="col" class="text-end"><?php $colLabel = 'Actions'; $colFilter = null; require __DIR__ . '/../../partials/shared/table_col_filter.php'; ?></th>
          </tr>
          </thead>
          <tbody>
          <?php if ($logs === []): ?>
            <tr>
              <td colspan="7" class="ux-table__empty-state">
                <?php
                $emptyIcon = 'bi-journal-x';
                $emptyTitle = 'No activity found';
                $emptyText = 'There are no activity records in this view.';
                $emptyActions = '';
                $emptyCompact = true;
                require __DIR__ . '/../../partials/shared/empty_state.php';
                ?>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $log): ?>
              <?php
              $entityType = trim((string) ($log['entity_type'] ?? ''));
              $entityId = (int) ($log['entity_id'] ?? 0);
              $entityLabel = $entityType !== '' ? ucfirst(str_replace('_', ' ', $entityType)) . ($entityId > 0 ? ' #' . $entityId : '') : '—';
              $actorName = (string) ($log['actor_name'] ?? 'System');
              $actorRole = (string) ($log['actor_role'] ?? '');
              $lastLogin = trim((string) ($log['actor_last_login_at'] ?? ''));
              $outcomeOk = (string) ($log['outcome'] ?? '') === 'success';
              ?>
              <tr>
                <td>
                  <div><?= Helper::formatDate((string) ($log['created_at'] ?? ''), 'd M Y', '—') ?></div>
                  <small class="text-muted"><?= Helper::formatDate((string) ($log['created_at'] ?? ''), 'g:i A', '') ?></small>
                </td>
                <td>
                  <?php
                  $personName = $actorName !== '' ? $actorName : 'System';
                  $personPhoto = null;
                  $personMeta = $actorRole !== '' ? ucfirst($actorRole) : '';
                  $personSize = 'sm';
                  require __DIR__ . '/../../partials/shared/person_row.php';
                  ?>
                </td>
                <td>
                  <strong class="d-block"><?= Helper::escape((string) ($log['event_label'] ?? 'Event')) ?></strong>
                  <?php if (trim((string) ($log['description'] ?? '')) !== ''): ?>
                    <span class="small text-muted"><?= Helper::escape((string) $log['description']) ?></span>
                  <?php endif; ?>
                </td>
                <td><?= Helper::escape($entityLabel) ?></td>
                <td>
                  <span class="ux-badge <?= $outcomeOk ? 'ux-badge--success' : 'ux-badge--danger' ?>">
                    <i class="bi <?= $outcomeOk ? 'bi-check-circle' : 'bi-x-circle' ?> me-1" aria-hidden="true"></i>
                    <?= Helper::escape(ucfirst((string) ($log['outcome'] ?? 'success'))) ?>
                  </span>
                </td>
                <td>
                  <?php if ($lastLogin !== ''): ?>
                    <div><?= Helper::escape(Helper::formatDate($lastLogin, 'd M Y', '—')) ?></div>
                    <div class="small text-muted"><?= Helper::escape(Helper::formatDate($lastLogin, 'g:i A', '')) ?></div>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <a class="btn btn-outline-primary btn-sm" href="<?= Helper::url('/admin/audit-logs/' . (int) ($log['id'] ?? 0)) ?>">
                    <i class="bi bi-eye"></i>
                    View
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php
    $paginationPath = '/admin/audit-logs';
    $paginationFilters = $filters;
    $paginationLabel = 'activity records';
    $paginationAria = 'Audit logs pagination';
    $paginationShowCount = true;
    require __DIR__ . '/../../partials/shared/list_pagination.php';
    ?>
  </div>
</section>
