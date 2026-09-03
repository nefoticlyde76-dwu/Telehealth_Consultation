<?php
use App\Helpers\Helper;
use App\Services\NotificationService;

$csrfToken = (string) ($csrfToken ?? '');
$dashboardRole = (string) ($dashboardRole ?? '');
$notifications = is_array($notifications ?? null) ? $notifications : [];
$unreadItems = is_array($unreadItems ?? null) ? $unreadItems : [];
$readItems = is_array($readItems ?? null) ? $readItems : [];
$filters = is_array($filters ?? null) ? $filters : [];
$pagination = is_array($pagination ?? null) ? $pagination : [];
$readStateOptions = is_array($readStateOptions ?? null) ? $readStateOptions : [];
$unreadCount = (int) ($unreadCount ?? 0);
$totalCount = (int) ($totalCount ?? 0);
$filterActive = (bool) ($filterActive ?? false);
$readState = (string) ($filters['read_state'] ?? '');
$search = (string) ($filters['search'] ?? '');
$dashboardHome = \App\Services\AuthService::getRoleRedirectUrl($dashboardRole);

$emptyTitle = 'You\'re all caught up';
$emptyText = 'You have no notifications to review.';
if ($notifications === [] && $filterActive) {
    $emptyTitle = 'No matching notifications';
    $emptyText = 'Try changing the search or filters.';
}

$filterForm = [
    'id' => 'tf-notifications',
    'action' => Helper::url('/notifications'),
    'clear_url' => Helper::url('/notifications'),
    'active' => $filterActive,
    'hidden' => $readState !== '' ? ['read_state' => $readState] : [],
    'search' => [
        'name' => 'search',
        'value' => $search,
        'placeholder' => 'Search title or message',
        'label' => 'Search',
    ],
];
$tableFilterId = 'tf-notifications';

$buildNotificationUrl = static function (array $overrides = []) use ($filters): string {
    $merged = array_merge($filters, $overrides);
    $query = [];
    foreach (['search', 'type', 'read_state'] as $key) {
        $value = trim((string) ($merged[$key] ?? ''));
        if ($value !== '') {
            $query[$key] = $value;
        }
    }

    $path = '/notifications';
    return Helper::url($query === [] ? $path : $path . '?' . http_build_query($query));
};

$readStateTabs = array_merge(
    [['value' => '', 'label' => 'All']],
    $readStateOptions
);

$renderRow = static function (array $item) use ($csrfToken): void {
    $itemId = (int) ($item['id'] ?? 0);
    $itemUnread = !empty($item['unread']);
    $itemTitle = (string) ($item['title'] ?? 'Notification');
    $itemMessage = (string) ($item['message'] ?? '');
    $itemTime = (string) ($item['relative_time'] ?? '');
    $itemIcon = (string) ($item['icon'] ?? 'bi-bell');
    $itemUrl = Helper::url((string) ($item['open_url'] ?? '/notifications'));
    $itemType = (string) ($item['type'] ?? '');
    $itemTypeLabel = $itemType !== '' ? NotificationService::typeTitle($itemType) : $itemTitle;
    ?>
    <tr<?= $itemUnread ? ' class="is-unread"' : '' ?>>
      <td class="ux-table__chk">
        <label class="notification-table__select">
          <input type="checkbox" name="notification_ids[]" value="<?= $itemId ?>" form="notificationBulkForm" <?= $itemId > 0 ? '' : 'disabled' ?>>
          <span class="visually-hidden">Select <?= Helper::escape($itemTitle) ?></span>
        </label>
      </td>
      <td>
        <strong class="d-block"><?= Helper::escape($itemTitle) ?></strong>
        <?php if ($itemMessage !== ''): ?>
          <span class="small text-muted"><?= Helper::escape($itemMessage) ?></span>
        <?php endif; ?>
      </td>
      <td>
        <span class="notification-table__type">
          <i class="bi <?= Helper::escape($itemIcon) ?>" aria-hidden="true"></i>
          <?= Helper::escape($itemTypeLabel) ?>
        </span>
      </td>
      <td>
        <span class="ux-badge <?= $itemUnread ? 'ux-badge--info' : 'ux-badge--neutral' ?>">
          <?= $itemUnread ? 'Unread' : 'Read' ?>
        </span>
      </td>
      <td class="text-muted small text-nowrap"><?= Helper::escape($itemTime) ?></td>
      <td class="text-end">
        <div class="ux-table__actions">
          <a href="<?= $itemUrl ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-eye me-1" aria-hidden="true"></i>View
          </a>
          <?php if ($itemId > 0): ?>
            <form method="POST" action="<?= Helper::url('/notifications/' . $itemId . '/' . ($itemUnread ? 'read' : 'unread')) ?>">
              <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
              <input type="hidden" name="return_to" value="<?= Helper::escape(Helper::currentRequestPath()) ?>">
              <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="bi <?= $itemUnread ? 'bi-envelope-open' : 'bi-envelope' ?> me-1" aria-hidden="true"></i>
                <?= $itemUnread ? 'Mark read' : 'Mark unread' ?>
              </button>
            </form>
            <form method="POST" action="<?= Helper::url('/notifications/' . $itemId . '/delete') ?>" data-confirm-title="Delete notification?" data-confirm-body="This removes the notice from your inbox. System audit records are not affected.">
              <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
              <input type="hidden" name="return_to" value="<?= Helper::escape(Helper::currentRequestPath()) ?>">
              <button type="submit" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-trash me-1" aria-hidden="true"></i>Delete
              </button>
            </form>
          <?php endif; ?>
        </div>
      </td>
    </tr>
    <?php
};

$tableRows = [];
if ($unreadItems !== [] && $readState !== 'read') {
    $tableRows = array_merge($tableRows, $unreadItems);
}
if ($readItems !== [] && $readState !== 'unread') {
    $tableRows = array_merge($tableRows, $readItems);
}
?>

<section class="mb-4">
  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= Helper::url($dashboardHome) ?>">Dashboard</a></li>
        <li class="active">Notifications</li>
      </ol>
      <h2 class="ux-page-header__title">Notifications</h2>
      <p class="ux-page-header__subtitle">Your personal notices. Deleting them does not change consultation records or audit history.</p>
    </div>
    <div class="ux-page-header__right d-flex flex-wrap gap-2">
      <?php if ($unreadCount > 0): ?>
        <form method="POST" action="<?= Helper::url('/notifications/mark-all-read') ?>">
          <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
          <input type="hidden" name="return_to" value="<?= Helper::escape(Helper::currentRequestPath()) ?>">
          <button type="submit" class="btn btn-outline-primary btn-sm">Mark all as read</button>
        </form>
      <?php endif; ?>
      <?php if ($totalCount > 0 || $notifications !== []): ?>
        <form method="POST" action="<?= Helper::url('/notifications/clear-all') ?>" data-confirm-title="Delete all your notifications?" data-confirm-body="This clears every notice in your inbox. Audit records are not deleted.">
          <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
          <button type="submit" class="btn btn-outline-danger btn-sm">Clear all</button>
        </form>
      <?php endif; ?>
      <a href="<?= Helper::url('/account/notifications/preferences') ?>" class="btn btn-outline-secondary btn-sm">Preferences</a>
    </div>
  </div>

  <?php require __DIR__ . '/../partials/shared/alerts.php'; ?>

  <form method="POST" action="<?= Helper::url('/notifications/delete-selected') ?>" id="notificationBulkForm" data-confirm-title="Delete selected notifications?" data-confirm-body="Only the notices you selected will be removed from your inbox.">
    <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
    <input type="hidden" name="return_to" value="<?= Helper::escape(Helper::currentRequestPath()) ?>">

    <div class="ux-card ux-data-card notification-page" data-table-filter="tf-notifications">
      <?php require __DIR__ . '/../partials/shared/table_filter_form.php'; ?>
      <div class="ux-card__header">
        <h2 class="ux-data-card__title">Notifications</h2>
        <div class="d-flex flex-wrap align-items-center gap-2">
          <?php require __DIR__ . '/../partials/shared/table_toolbar.php'; ?>
          <div class="ux-table-filters" role="group" aria-label="Filter by read state">
            <?php foreach ($readStateTabs as $tab): ?>
              <?php
              $tabValue = (string) ($tab['value'] ?? '');
              $tabLabel = (string) ($tab['label'] ?? 'All');
              $tabActive = $readState === $tabValue;
              ?>
              <a
                href="<?= Helper::escape($buildNotificationUrl(['read_state' => $tabValue])) ?>"
                class="<?= $tabActive ? 'is-active' : '' ?>"
                <?= $tabActive ? 'aria-current="page"' : '' ?>
              ><?= Helper::escape($tabLabel) ?></a>
            <?php endforeach; ?>
          </div>
          <?php if ($notifications !== []): ?>
            <button type="submit" class="btn btn-outline-danger btn-sm" data-bulk-submit disabled>
              <i class="bi bi-trash me-1" aria-hidden="true"></i>Delete selected
            </button>
          <?php endif; ?>
        </div>
      </div>
      <div class="ux-card__body">
        <div class="ux-table-wrapper">
            <table class="ux-table notification-table align-middle mb-0">
              <caption class="visually-hidden">Inbox notifications with status and actions</caption>
              <thead>
                <tr>
                  <th scope="col" class="ux-table__chk"><span class="visually-hidden">Select</span></th>
                  <th scope="col"><?php $colLabel = 'Notification'; $colFilter = null; require __DIR__ . '/../partials/shared/table_col_filter.php'; ?></th>
                  <th scope="col"><?php $colLabel = 'Type'; $colFilter = null; require __DIR__ . '/../partials/shared/table_col_filter.php'; ?></th>
                  <th scope="col"><?php $colLabel = 'Status'; $colFilter = null; require __DIR__ . '/../partials/shared/table_col_filter.php'; ?></th>
                  <th scope="col"><?php $colLabel = 'When'; $colFilter = null; require __DIR__ . '/../partials/shared/table_col_filter.php'; ?></th>
                  <th scope="col" class="text-end"><?php $colLabel = 'Actions'; $colFilter = null; require __DIR__ . '/../partials/shared/table_col_filter.php'; ?></th>
                </tr>
              </thead>
              <tbody>
                <?php if ($tableRows === []): ?>
                  <tr>
                    <td colspan="6" class="ux-table__empty-state">
                      <?php
                      $emptyIcon = $filterActive ? 'bi-funnel' : 'bi-bell';
                      $emptyActions = '';
                      $emptyCompact = true;
                      $emptyPositive = !$filterActive;
                      require __DIR__ . '/../partials/shared/empty_state.php';
                      ?>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($tableRows as $item) { $renderRow($item); } ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
      </div>
      <div class="card-footer border-0 bg-transparent">
        <?php
        $paginationPath = '/notifications';
        $paginationFilters = $filters;
        $paginationLabel = 'notifications';
        $paginationAria = 'Notification pagination';
        $paginationShowCount = true;
        require __DIR__ . '/../partials/shared/list_pagination.php';
        ?>
      </div>
    </div>
  </form>
</section>
