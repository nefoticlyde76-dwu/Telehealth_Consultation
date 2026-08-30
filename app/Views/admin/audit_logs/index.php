<?php
use App\Helpers\Helper;

$filters = is_array($filters ?? null) ? $filters : [];
$logs = is_array($logs ?? null) ? $logs : [];
$pagination = is_array($pagination ?? null) ? $pagination : [];
$actionOptions = is_array($actionOptions ?? null) ? $actionOptions : [];
$roleOptions = is_array($roleOptions ?? null) ? $roleOptions : [];
$dateOptions = is_array($dateOptions ?? null) ? $dateOptions : [];
$sortOptions = is_array($sortOptions ?? null) ? $sortOptions : [];
$userOptions = is_array($userOptions ?? null) ? $userOptions : [];

$filterForm = [
    'action' => Helper::url('/admin/audit-logs'),
    'title' => 'Search and filter activity',
    'search' => [
        'name' => 'search',
        'value' => (string) ($filters['search'] ?? ''),
        'placeholder' => 'Search activity...',
        'label' => 'Search',
    ],
    'clear_url' => Helper::url('/admin/audit-logs'),
    'apply_label' => 'Apply Filters',
    'clear_label' => 'Clear Filters',
    'fields' => [
        ['type' => 'select', 'name' => 'action', 'label' => 'Action', 'value' => (string) ($filters['action'] ?? ''), 'empty_label' => 'All actions', 'options' => $actionOptions],
        ['type' => 'select', 'name' => 'role', 'label' => 'Role', 'value' => (string) ($filters['role'] ?? ''), 'empty_label' => 'All roles', 'options' => $roleOptions],
        ['type' => 'select', 'name' => 'user_id', 'label' => 'User', 'value' => (string) ((int) ($filters['user_id'] ?? 0) ?: ''), 'empty_label' => 'All users', 'options' => array_map(static fn (array $u): array => ['value' => (string) (int) ($u['id'] ?? 0), 'label' => (string) (($u['full_name'] ?? '') . ' (' . ($u['email'] ?? '') . ')')], $userOptions)],
        ['type' => 'date_preset', 'name' => 'date', 'label' => 'Date', 'value' => (string) ($filters['date'] ?? ''), 'empty_label' => 'All dates', 'options' => $dateOptions, 'from_value' => (string) ($filters['date_from'] ?? ''), 'to_value' => (string) ($filters['date_to'] ?? '')],
        ['type' => 'select', 'name' => 'sort', 'label' => 'Sort', 'value' => (string) ($filters['sort'] ?? 'newest'), 'empty_label' => 'Newest first', 'include_empty' => false, 'options' => $sortOptions],
        ['type' => 'select', 'name' => 'per_page', 'label' => 'Per page', 'value' => (string) ($filters['per_page'] ?? 25), 'empty_label' => '25', 'include_empty' => false, 'options' => [['value' => '25', 'label' => '25'], ['value' => '50', 'label' => '50'], ['value' => '100', 'label' => '100']]],
    ],
];
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
  </div>

  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>
  <?php require __DIR__ . '/../../partials/shared/list_filter.php'; ?>

  <div class="ux-card ux-data-card">
    <div class="ux-card__header">
      <h2 class="ux-data-card__title">Activity log</h2>
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
                  <h4 class="ux-empty__title">No matching activity found</h4>
                  <p class="ux-empty__text">Try changing your search or filters.</p>
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
