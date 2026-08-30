<?php

/**
 * Compact notification table for role dashboards.
 *
 * @var list<array<string,mixed>> $notificationItems
 */
use App\Helpers\Helper;

$notificationItems = is_array($notificationItems ?? null) ? $notificationItems : [];
$emptyTitle = (string) ($activityEmptyTitle ?? "You're all caught up");
$emptyText = (string) ($activityEmptyText ?? "You don't have any notifications yet.");
$emptyIcon = (string) ($activityEmptyIcon ?? 'bi-bell');
?>

<?php if ($notificationItems === []): ?>
  <?php
  $emptyActions = '';
  $emptyCompact = true;
  require __DIR__ . '/empty_state.php';
  ?>
<?php else: ?>
  <div class="ux-table-wrapper">
    <table class="ux-table notification-table align-middle mb-0">
      <caption class="visually-hidden">Recent notifications</caption>
      <thead>
        <tr>
          <th scope="col">Notification</th>
          <th scope="col">Status</th>
          <th scope="col">When</th>
          <th scope="col" class="text-end">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($notificationItems as $item): ?>
          <?php
          $itemUnread = !empty($item['unread']);
          $itemTitle = (string) ($item['title'] ?? 'Notification');
          $itemDescription = (string) ($item['description'] ?? '');
          $itemMeta = (string) ($item['meta'] ?? '');
          $itemUrl = trim((string) ($item['url'] ?? ''));
          ?>
          <tr<?= $itemUnread ? ' class="is-unread"' : '' ?>>
            <td>
              <strong class="d-block"><?= Helper::escape($itemTitle) ?></strong>
              <?php if ($itemDescription !== ''): ?>
                <span class="small text-muted"><?= Helper::escape($itemDescription) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <span class="ux-badge <?= $itemUnread ? 'ux-badge--info' : 'ux-badge--neutral' ?>">
                <?= $itemUnread ? 'Unread' : 'Read' ?>
              </span>
            </td>
            <td class="text-muted small text-nowrap"><?= Helper::escape($itemMeta) ?></td>
            <td class="text-end">
              <?php if ($itemUrl !== ''): ?>
                <a href="<?= Helper::escape(Helper::url($itemUrl)) ?>" class="btn btn-outline-primary btn-sm">
                  <i class="bi bi-eye me-1" aria-hidden="true"></i>View
                </a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
