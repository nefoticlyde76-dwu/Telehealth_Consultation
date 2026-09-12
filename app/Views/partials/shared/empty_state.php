<?php

/**
 * Shared empty state.
 *
 * @var string $emptyIcon
 * @var string $emptyTitle
 * @var string $emptyText
 * @var string $emptyActions  Optional HTML
 */
$emptyIcon = (string) ($emptyIcon ?? 'bi-inbox');
$emptyTitle = (string) ($emptyTitle ?? 'Nothing to show yet');
$emptyText = (string) ($emptyText ?? 'Records will appear here when they are available.');
$emptyActions = (string) ($emptyActions ?? '');
$emptyCompact = !empty($emptyCompact);
$emptyPositive = !empty($emptyPositive);
$emptyClass = trim((string) ($emptyClass ?? ''));
$emptyBadge = $emptyBadge ?? null;
$emptyBadgeLabel = is_scalar($emptyBadge) ? trim((string) $emptyBadge) : '';
?>

<div class="ux-empty app-empty-state<?= $emptyCompact ? ' ux-empty--compact' : '' ?><?= $emptyPositive ? ' ux-empty--positive' : '' ?><?= $emptyClass !== '' ? ' ' . \App\Helpers\Helper::escape($emptyClass) : '' ?> text-center">
  <div class="ux-empty__icon mx-auto mb-3" aria-hidden="true">
    <span class="ux-empty__sparkles">
      <span></span><span></span><span></span><span></span>
    </span>
    <i class="bi <?= \App\Helpers\Helper::escape($emptyIcon) ?>"></i>
    <?php if ($emptyBadgeLabel !== ''): ?>
      <span class="ux-empty__badge"><?= \App\Helpers\Helper::escape($emptyBadgeLabel) ?></span>
    <?php endif; ?>
  </div>
  <h3 class="ux-empty__title"><?= \App\Helpers\Helper::escape($emptyTitle) ?></h3>
  <p class="ux-empty__text"><?= \App\Helpers\Helper::escape($emptyText) ?></p>
  <?php if ($emptyActions !== ''): ?>
    <div class="ux-empty__actions d-flex flex-wrap justify-content-center gap-2">
      <?= $emptyActions ?>
    </div>
  <?php endif; ?>
</div>
<?php
$emptyPositive = false;
$emptyCompact = false;
$emptyActions = '';
$emptyClass = '';
$emptyBadge = null;
?>
