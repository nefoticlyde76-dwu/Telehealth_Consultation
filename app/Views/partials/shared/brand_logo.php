<?php

$brandVariant = $brandVariant ?? 'default';
$brandSubtitle = $brandSubtitle ?? '';
$brandRoleLabel = $brandRoleLabel ?? '';
$brandLink = $brandLink ?? \App\Helpers\Helper::url('/');
$brandAlt = $brandAlt ?? 'MBPHA TeleHealth logo';
$brandImageClass = $brandImageClass ?? '';
$brandShowTitle = $brandShowTitle ?? false;
$brandShowWordmark = $brandShowWordmark ?? false;
?>

<a href="<?= \App\Helpers\Helper::escape($brandLink) ?>" class="brand-lockup brand-lockup-<?= \App\Helpers\Helper::escape($brandVariant) ?> text-decoration-none d-inline-flex align-items-center gap-3">
  <span class="brand-logo-shell brand-logo-shell-<?= \App\Helpers\Helper::escape($brandVariant) ?>">
    <img
      src="<?= \App\Helpers\Helper::asset('images/LOGOS.png') ?>"
      alt="<?= \App\Helpers\Helper::escape($brandAlt) ?>"
      class="brand-logo-image <?= \App\Helpers\Helper::escape($brandImageClass) ?>"
    >
  </span>

  <?php if ($brandShowWordmark): ?>
            <span class="brand-wordmark">
      <span class="brand-wordmark__primary"><?= \App\Helpers\Helper::escape($brandWordmarkPrimary ?? 'TeleHealth PNG') ?></span>
      <span class="brand-wordmark__secondary"><?= \App\Helpers\Helper::escape($brandWordmarkSecondary ?? 'MBPHA TELEHEALTH') ?></span>
    </span>
  <?php elseif ($brandShowTitle || $brandSubtitle !== '' || $brandRoleLabel !== ''): ?>
    <span class="brand-copy">
      <?php if ($brandShowTitle): ?>
        <span class="brand-title d-block">MBPHA TeleHealth</span>
      <?php endif; ?>
      <?php if ($brandSubtitle !== ''): ?>
        <span class="brand-subtitle d-block"><?= \App\Helpers\Helper::escape($brandSubtitle) ?></span>
      <?php endif; ?>
      <?php if ($brandRoleLabel !== ''): ?>
        <span class="brand-role-label d-block"><?= \App\Helpers\Helper::escape($brandRoleLabel) ?></span>
      <?php endif; ?>
    </span>
  <?php endif; ?>
</a>
