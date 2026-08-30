<?php

/**
 * Shared activity / notification-style list.
 *
 * @var list<array<string,mixed>> $activityItems
 */
use App\Helpers\Helper;

$activityItems = is_array($activityItems ?? null) ? $activityItems : [];
$activityEmptyTitle = (string) ($activityEmptyTitle ?? 'No recent activity');
$activityEmptyText = (string) ($activityEmptyText ?? 'Updates will appear here as they happen.');
$activityEmptyIcon = (string) ($activityEmptyIcon ?? 'bi-clock-history');
?>

<?php if ($activityItems === []): ?>
  <?php
  $emptyIcon = $activityEmptyIcon;
  $emptyTitle = $activityEmptyTitle;
  $emptyText = $activityEmptyText;
  $emptyActions = '';
  $emptyCompact = true;
  require __DIR__ . '/empty_state.php';
  ?>
<?php else: ?>
  <ul class="ux-activity">
    <?php foreach ($activityItems as $activity): ?>
      <?php
      $activityTitle = (string) ($activity['title'] ?? '');
      $activityDescription = (string) ($activity['description'] ?? '');
      $activityMeta = (string) ($activity['meta'] ?? '');
      $activityIcon = (string) ($activity['icon'] ?? 'bi-dot');
      $activityUrl = trim((string) ($activity['url'] ?? ''));
      $activityUnread = !empty($activity['unread']);
      $activityTag = $activityUrl !== '' ? 'a' : 'div';
      $activityHref = $activityUrl !== '' ? ' href="' . Helper::escape(Helper::url($activityUrl)) . '"' : '';
      ?>
      <li>
        <<?= $activityTag ?> class="ux-activity__item<?= $activityUnread ? ' is-unread' : '' ?>"<?= $activityHref ?>>
          <span class="ux-activity__icon" aria-hidden="true">
            <i class="bi <?= Helper::escape($activityIcon) ?>"></i>
          </span>
          <span class="ux-activity__body">
            <span class="ux-activity__title"><?= Helper::escape($activityTitle) ?></span>
            <?php if ($activityDescription !== ''): ?>
              <span class="ux-activity__description"><?= Helper::escape($activityDescription) ?></span>
            <?php endif; ?>
            <?php if ($activityMeta !== ''): ?>
              <span class="ux-activity__meta"><?= Helper::escape($activityMeta) ?></span>
            <?php endif; ?>
          </span>
        </<?= $activityTag ?>>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>
