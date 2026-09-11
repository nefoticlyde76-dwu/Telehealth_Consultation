<?php
use App\Helpers\Helper;
use App\Helpers\ListFilter;

$filters = is_array($filters ?? null) ? $filters : [];
$logs = is_array($logs ?? null) ? $logs : [];
$pagination = is_array($pagination ?? null) ? $pagination : [];
$roleOptions = is_array($roleOptions ?? null) ? $roleOptions : [];

$filterTabs = array_merge(
    [['value' => '', 'label' => 'All']],
    ListFilter::selectOptions($roleOptions)
);
$filterTabCurrent = (string) ($filters['role'] ?? '');
$filterTabAria = 'Filter activity by role';
$filterTabUrl = static function (string $value) use ($filters): string {
    return ListFilter::url('/admin/audit-logs', $filters, ['role' => $value, 'page' => 1]);
};
?>

<section class="mb-4">
  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= Helper::url('/admin/dashboard') ?>">Dashboard</a></li>
        <li class="active">Activity &amp; Audit Logs</li>
      </ol>
      <h2 class="ux-page-header__title">Activity &amp; Audit Logs</h2>
      <p class="ux-page-header__subtitle">Chronological records of important security and workflow events.</p>
    </div>
    <div class="ux-page-header__right">
      <span class="ux-chip ux-badge--dotless">
        <i class="bi bi-shield-check" aria-hidden="true"></i>
        Security record
      </span>
    </div>
  </div>

  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-card ux-data-card">
    <div class="ux-card__header">
      <h2 class="ux-data-card__title">Activity log</h2>
      <?php require __DIR__ . '/../../partials/shared/table_filter_tabs.php'; ?>
    </div>
    <div class="ux-table-wrapper border-0">
      <div class="table-responsive">
        <table class="ux-table">
          <thead>
          <tr>
            <th>When</th>
            <th>Actor</th>
            <th>Action</th>
            <th>Entity</th>
            <th>Outcome</th>
            <th class="text-end">View</th>
          </tr>
          </thead>
          <tbody>
          <?php if ($logs === []): ?>
            <tr>
              <td colspan="6" class="ux-table__empty-state">
                <div class="ux-empty">
                  <div class="ux-empty__icon"><i class="bi bi-journal-x"></i></div>
                  <h4 class="ux-empty__title">No activity found</h4>
                  <p class="ux-empty__text">There are no activity records in this view.</p>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($logs as $log): ?>
              <?php
              $entityType = trim((string) ($log['entity_type'] ?? ''));
              $entityId = (int) ($log['entity_id'] ?? 0);
              $entityLabel = $entityType !== '' ? ucfirst(str_replace('_', ' ', $entityType)) . ($entityId > 0 ? ' #' . $entityId : '') : '—';
              ?>
              <tr>
                <td>
                  <div><?= Helper::formatDate((string) ($log['created_at'] ?? ''), 'd M Y', '—') ?></div>
                  <small class="text-muted"><?= Helper::formatDate((string) ($log['created_at'] ?? ''), 'g:i A', '') ?></small>
                </td>
                <td>
                  <div><?= Helper::escape((string) ($log['actor_name'] ?? 'System')) ?></div>
                  <small class="text-muted"><?= Helper::escape((string) ($log['actor_role'] ?? '')) ?></small>
                </td>
                <td><?= Helper::escape((string) ($log['event_label'] ?? 'Event')) ?></td>
                <td><?= Helper::escape($entityLabel) ?></td>
                <td>
                  <span class="ux-badge <?= (string) ($log['outcome'] ?? '') === 'success' ? 'ux-badge--success' : 'ux-badge--danger' ?>">
                    <?= Helper::escape(ucfirst((string) ($log['outcome'] ?? 'success'))) ?>
                  </span>
                </td>
                <td class="text-end">
                  <a class="btn btn-outline-primary btn-sm" href="<?= Helper::url('/admin/audit-logs/' . (int) ($log['id'] ?? 0)) ?>">
                    <i class="bi bi-eye"></i>
                    Details
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
