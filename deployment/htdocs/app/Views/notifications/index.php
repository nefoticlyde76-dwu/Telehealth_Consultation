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
$typeOptions = is_array($typeOptions ?? null) ? $typeOptions : [];
$readStateOptions = is_array($readStateOptions ?? null) ? $readStateOptions : [];
$unreadCount = (int) ($unreadCount ?? 0);
$totalCount = (int) ($totalCount ?? 0);
$filterActive = (bool) ($filterActive ?? false);
$readState = (string) ($filters['read_state'] ?? '');
$typeFilter = (string) ($filters['type'] ?? '');
$search = (string) ($filters['search'] ?? '');
$dashboardHome = match ($dashboardRole) {
    'admin' => '/admin/dashboard',
    'doctor' => '/doctor/dashboard',
    'patient' => '/patient/dashboard',
    default => '/',
};

$emptyTitle = 'You\'re all caught up';
$emptyText = 'You have no notifications to review.';
if ($notifications === [] && $filterActive) {
    $emptyTitle = 'No matching notifications';
    $emptyText = 'Try changing the search or filters.';
}

$filterForm = [
    'action' => Helper::url('/notifications'),
    'title' => 'Filter notifications',
    'search' => [
        'name' => 'search',
        'value' => $search,
        'placeholder' => 'Search title or message',
        'label' => 'Search',
    ],
    'clear_url' => Helper::url('/notifications'),
    'fields' => [
        [
            'type' => 'select',
            'name' => 'read_state',
            'id' => 'read_state',
            'label' => 'Read state',
            'value' => $readState,
            'empty_label' => 'All',
            'options' => $readStateOptions,
        ],
        [
            'type' => 'select',
            'name' => 'type',
            'id' => 'notification_type',
            'label' => 'Type',
            'value' => $typeFilter,
            'empty_label' => 'All types',
            'options' => $typeOptions,
        ],
    ],
];

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
    <li class="notification-inbox__item<?= $itemUnread ? ' is-unread' : '' ?>">
      <label class="notification-inbox__select">
        <input type="checkbox" name="notification_ids[]" value="<?= $itemId ?>" form="notificationBulkForm" <?= $itemId > 0 ? '' : 'disabled' ?>>
        <span class="visually-hidden">Select <?= Helper::escape($itemTitle) ?></span>
      </label>
      <span class="notification-inbox__icon" aria-hidden="true"><i class="bi <?= Helper::escape($itemIcon) ?>"></i></span>
      <div class="notification-inbox__body">
        <div class="d-flex flex-wrap align-items-center gap-2">
          <strong><?= Helper::escape($itemTitle) ?></strong>
          <span class="ux-badge <?= $itemUnread ? 'ux-badge--info' : 'ux-badge--neutral' ?>"><?= $itemUnread ? 'Unread' : 'Read' ?></span>
          <span class="small text-muted"><?= Helper::escape($itemTypeLabel) ?></span>
        </div>
        <?php if ($itemMessage !== ''): ?>
          <p class="text-muted small mb-1"><?= Helper::escape($itemMessage) ?></p>
        <?php endif; ?>
        <span class="small text-muted"><?= Helper::escape($itemTime !== '' ? $itemTime : '') ?></span>
      </div>
      <div class="notification-inbox__actions">
        <a href="<?= $itemUrl ?>" class="btn btn-outline-primary btn-sm">View</a>
        <?php if ($itemId > 0): ?>
          <form method="POST" action="<?= Helper::url('/notifications/' . $itemId . '/' . ($itemUnread ? 'read' : 'unread')) ?>">
            <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
            <input type="hidden" name="return_to" value="<?= Helper::escape(Helper::currentRequestPath()) ?>">
            <button type="submit" class="btn btn-outline-secondary btn-sm"><?= $itemUnread ? 'Mark read' : 'Mark unread' ?></button>
          </form>
          <form method="POST" action="<?= Helper::url('/notifications/' . $itemId . '/delete') ?>" data-confirm-title="Delete notification?" data-confirm-body="This removes the notice from your inbox. System audit records are not affected.">
            <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
            <input type="hidden" name="return_to" value="<?= Helper::escape(Helper::currentRequestPath()) ?>">
            <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
          </form>
        <?php endif; ?>
      </div>
    </li>
    <?php
};
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
  <?php require __DIR__ . '/../partials/shared/list_filter.php'; ?>

  <form method="POST" action="<?= Helper::url('/notifications/delete-selected') ?>" id="notificationBulkForm" data-confirm-title="Delete selected notifications?" data-confirm-body="Only the notices you selected will be removed from your inbox.">
    <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
    <input type="hidden" name="return_to" value="<?= Helper::escape(Helper::currentRequestPath()) ?>">

    <div class="ux-card notification-page">
      <?php if ($notifications === []): ?>
        <?php
        $emptyIcon = $filterActive ? 'bi-funnel' : 'bi-bell';
        $emptyTitle = $emptyTitle;
        $emptyText = $emptyText;
        $emptyActions = '';
        $emptyCompact = false;
        $emptyPositive = !$filterActive;
        require __DIR__ . '/../partials/shared/empty_state.php';
        ?>
      <?php else: ?>
        <?php if ($unreadItems !== [] && $readState !== 'read'): ?>
          <h3 class="notification-inbox__heading">Unread</h3>
          <ul class="notification-inbox">
            <?php foreach ($unreadItems as $item) { $renderRow($item); } ?>
          </ul>
        <?php endif; ?>
        <?php if ($readItems !== [] && $readState !== 'unread'): ?>
          <h3 class="notification-inbox__heading">Read</h3>
          <ul class="notification-inbox">
            <?php foreach ($readItems as $item) { $renderRow($item); } ?>
          </ul>
        <?php endif; ?>
        <div class="notification-inbox__bulk">
          <button type="submit" class="btn btn-outline-danger btn-sm" data-bulk-submit disabled>Delete selected</button>
        </div>
      <?php endif; ?>
    </div>
  </form>

  <?php
  $paginationPath = '/notifications';
  $paginationFilters = $filters;
  $paginationLabel = 'notifications';
  $paginationAria = 'Notification pagination';
  $paginationShowCount = true;
  require __DIR__ . '/../partials/shared/list_pagination.php';
  ?>
</section>
