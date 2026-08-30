<?php

$avatarPath = $avatarPath ?? null;
$fullName = (string) ($fullName ?? 'User');
$avatarClass = trim((string) ($avatarClass ?? 'user-avatar user-avatar--md'));
$imageClass = trim((string) ($imageClass ?? 'user-avatar-image'));
$fallbackClass = trim((string) ($fallbackClass ?? 'user-avatar-fallback'));
$fallbackLabel = trim((string) ($fallbackLabel ?? 'Default profile avatar'));
?>

<span class="<?= \App\Helpers\Helper::escape($avatarClass) ?>">
  <?php if (!empty($avatarPath)): ?>
    <img
      class="<?= \App\Helpers\Helper::escape($imageClass) ?>"
      src="<?= \App\Helpers\Helper::asset((string) $avatarPath) ?>"
      alt="<?= \App\Helpers\Helper::escape($fullName) ?> profile photo"
    >
  <?php else: ?>
    <span class="<?= \App\Helpers\Helper::escape($fallbackClass) ?>" aria-hidden="true">
      <?= \App\Helpers\Helper::escape(\App\Helpers\Helper::initials($fullName)) ?>
    </span>
    <span class="visually-hidden"><?= \App\Helpers\Helper::escape($fallbackLabel) ?></span>
  <?php endif; ?>
</span>
