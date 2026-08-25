<?php

use App\Helpers\Helper;
use App\Services\NotificationService;

$headerNotifications = is_array($headerNotifications ?? null) ? $headerNotifications : [];
$toastPath = Helper::currentPath();
$toastBlocked = $toastPath === '/notifications'
    || str_contains($toastPath, '/room');

$headerRecent = is_array($headerNotifications['recent'] ?? null)
    ? $headerNotifications['recent']
    : [];

$headerToasts = [];
if (!$toastBlocked) {
    foreach ($headerRecent as $toastItem) {
        if (empty($toastItem['unread'])) {
            continue;
        }
        $headerToasts[] = $toastItem;
        if (count($headerToasts) >= NotificationService::TOAST_LIMIT) {
            break;
        }
    }
}
?>

<?php if ($headerToasts !== []): ?>
<div
  class="notification-toast-stack"
  data-notification-toasts
  role="log"
  aria-live="polite"
  aria-label="Notifications"
>
  <?php foreach ($headerToasts as $index => $item): ?>
    <?php
    $itemId = (int) ($item['id'] ?? 0);
    $itemTitle = (string) ($item['title'] ?? 'Notification');
    $itemMessage = (string) ($item['message'] ?? '');
    $itemIcon = (string) ($item['icon'] ?? 'bi-bell');
    $itemTone = (string) ($item['tone'] ?? 'info');
    $itemAction = (string) ($item['action_label'] ?? 'View');
    $itemUrl = Helper::url((string) ($item['open_url'] ?? '/notifications'));
    $delay = number_format(0.2 + ($index * 0.9), 1, '.', '');
    ?>
    <div
      class="notification-toast"
      data-notification-toast
      data-toast-id="<?= (int) $itemId ?>"
      data-tone="<?= Helper::escape($itemTone) ?>"
      style="--d:<?= Helper::escape($delay) ?>s"
    >
      <span class="notification-toast__ic" aria-hidden="true">
        <i class="bi <?= Helper::escape($itemIcon) ?>"></i>
      </span>
      <div class="notification-toast__tx">
        <b><?= Helper::escape($itemTitle) ?></b>
        <?php if ($itemMessage !== ''): ?>
          <em><?= Helper::escape($itemMessage) ?></em>
        <?php endif; ?>
      </div>
      <a href="<?= $itemUrl ?>" class="notification-toast__action"><?= Helper::escape($itemAction) ?></a>
      <span class="notification-toast__bar" aria-hidden="true"></span>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
