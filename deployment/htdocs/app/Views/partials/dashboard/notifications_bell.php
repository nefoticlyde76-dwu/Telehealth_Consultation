<?php

use App\Core\Csrf;
use App\Helpers\Helper;
use App\Services\AuthService;
use App\Services\NotificationService;

$headerUserId = (int) ($user->id ?? AuthService::getUserId() ?? 0);
$headerRole = (string) ($dashboardRole ?? AuthService::getUserRole() ?? '');
if (!isset($headerNotifications) || !is_array($headerNotifications)) {
    $headerNotifications = $headerUserId > 0
        ? NotificationService::getHeaderData($headerUserId, $headerRole)
        : ['unread_count' => 0, 'total_count' => 0, 'recent' => []];
}
$headerUnread = (int) ($headerNotifications['unread_count'] ?? 0);
$headerTotal = (int) ($headerNotifications['total_count'] ?? 0);
$headerRecent = is_array($headerNotifications['recent'] ?? null) ? $headerNotifications['recent'] : [];
$headerBadgeCount = $headerUnread;
$headerBadgeLabel = $headerBadgeCount > 99 ? '99+' : (string) $headerBadgeCount;
$headerHasItems = $headerRecent !== [];
$headerCsrf = Csrf::generate();
$headerReturnTo = Helper::currentRequestPath();
$headerGrouped = [];
foreach ($headerRecent as $headerItem) {
    $dayKey = (string) ($headerItem['day_group'] ?? 'Earlier');
    $headerGrouped[$dayKey][] = $headerItem;
}

if ($headerUnread > 0) {
    $headerBellLabel = $headerUnread === 1
        ? 'Notifications — 1 unread'
        : 'Notifications — ' . $headerUnread . ' unread';
} elseif ($headerTotal > 0) {
    $headerBellLabel = $headerTotal === 1
        ? 'Notifications — 1 notification'
        : 'Notifications — ' . $headerTotal . ' notifications';
} else {
    $headerBellLabel = 'Notifications — 0';
}
?>

<?php if ($headerHasItems): ?>
<div class="dropdown notification-dropdown">
  <button
    class="btn notification-bell<?= $headerUnread > 0 ? ' has-unread' : '' ?>"
    type="button"
    id="notificationBellButton"
    data-bs-toggle="dropdown"
    data-bs-display="static"
    data-bs-auto-close="outside"
    aria-expanded="false"
    aria-haspopup="true"
    aria-controls="notificationPanel"
    aria-label="<?= Helper::escape($headerBellLabel) ?>"
  >
    <i class="bi bi-bell" aria-hidden="true"></i>
    <span class="notification-bell__count<?= $headerUnread > 0 ? '' : ' is-zero' ?>" aria-hidden="true"><?= Helper::escape($headerBadgeLabel) ?></span>
  </button>
  <div
    class="dropdown-menu dropdown-menu-end notification-panel"
    id="notificationPanel"
    aria-labelledby="notificationBellButton"
    role="dialog"
    aria-label="Notification center"
  >
    <div class="notification-panel__head">
      <h2 class="notification-panel__title">Notifications</h2>
      <?php if ($headerUnread > 0): ?>
        <form method="POST" action="<?= Helper::url('/notifications/mark-all-read') ?>" class="notification-panel__clear-form">
          <input type="hidden" name="_token" value="<?= Helper::escape($headerCsrf) ?>">
          <input type="hidden" name="return_to" value="<?= Helper::escape($headerReturnTo) ?>">
          <button type="submit" class="notification-panel__clear">Mark all read</button>
        </form>
      <?php endif; ?>
    </div>

    <?php foreach ($headerGrouped as $dayLabel => $dayItems): ?>
      <div class="notification-panel__day"><?= Helper::escape((string) $dayLabel) ?></div>
      <ul class="notification-panel__list">
        <?php foreach ($dayItems as $item): ?>
          <?php
          $itemId = (int) ($item['id'] ?? 0);
          $itemUnread = !empty($item['unread']);
          $itemTitle = (string) ($item['title'] ?? 'Notification');
          $itemMessage = (string) ($item['message'] ?? '');
          $itemTime = (string) ($item['relative_time'] ?? '');
          $itemIcon = (string) ($item['icon'] ?? 'bi-bell');
          $itemTone = (string) ($item['tone'] ?? 'info');
          $itemUrl = Helper::url((string) ($item['open_url'] ?? '/notifications'));
          $itemState = $itemUnread ? 'Unread' : 'Read';
          $openAria = 'Open notification: ' . $itemTitle;
          $archiveAria = 'Archive notification: ' . $itemTitle;
          ?>
          <li class="notification-panel__row<?= $itemUnread ? ' notification-panel__row--new' : '' ?>" data-tone="<?= Helper::escape($itemTone) ?>">
            <?php if ($itemUnread): ?>
              <i class="notification-panel__dot" aria-label="Unread"></i>
            <?php endif; ?>
            <span class="notification-panel__sys" aria-hidden="true">
              <i class="bi <?= Helper::escape($itemIcon) ?>"></i>
            </span>
            <a class="notification-panel__body" href="<?= $itemUrl ?>">
              <span class="visually-hidden"><?= Helper::escape($itemState) ?>. </span>
              <b><?= Helper::escape($itemTitle) ?></b>
              <?php if ($itemMessage !== ''): ?>
                <em><?= Helper::escape($itemMessage) ?></em>
              <?php endif; ?>
              <?php if ($itemTime !== ''): ?>
                <time><?= Helper::escape($itemTime) ?></time>
              <?php endif; ?>
            </a>
            <span class="notification-panel__act">
              <a href="<?= $itemUrl ?>" aria-label="<?= Helper::escape($openAria) ?>">
                <i class="bi bi-arrow-right" aria-hidden="true"></i>
              </a>
              <?php if ($itemUnread && $itemId > 0): ?>
                <form method="POST" action="<?= Helper::url('/notifications/' . $itemId . '/read') ?>">
                  <input type="hidden" name="_token" value="<?= Helper::escape($headerCsrf) ?>">
                  <input type="hidden" name="return_to" value="<?= Helper::escape($headerReturnTo) ?>">
                  <button type="submit" aria-label="<?= Helper::escape($archiveAria) ?>">
                    <i class="bi bi-archive" aria-hidden="true"></i>
                  </button>
                </form>
              <?php endif; ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>

    <div class="notification-panel__footer">
      <a href="<?= Helper::url('/notifications') ?>" class="notification-panel__all">
        View all notifications
      </a>
    </div>
  </div>
</div>
<?php else: ?>
  <a
    href="<?= Helper::url('/notifications') ?>"
    class="btn notification-bell"
    id="notificationBellButton"
    aria-label="<?= Helper::escape($headerBellLabel) ?>"
  >
    <i class="bi bi-bell" aria-hidden="true"></i>
    <span class="notification-bell__count<?= $headerUnread > 0 ? '' : ' is-zero' ?>" aria-hidden="true"><?= Helper::escape($headerBadgeLabel) ?></span>
  </a>
<?php endif; ?>
