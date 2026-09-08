<?php

$avatarPath = $avatarPath ?? null;
$pathUserId = \App\Services\ProfilePhotoService::userIdFromPath(
    is_string($avatarPath) || is_numeric($avatarPath) ? (string) $avatarPath : ''
);
$avatarUserId = $pathUserId > 0 ? $pathUserId : (int) ($avatarUserId ?? 0);
$fullName = (string) ($fullName ?? 'User');
$avatarClass = trim((string) ($avatarClass ?? 'user-avatar user-avatar--md'));
$imageClass = trim((string) ($imageClass ?? 'user-avatar-image'));
$fallbackClass = trim((string) ($fallbackClass ?? 'user-avatar-fallback'));
$fallbackLabel = trim((string) ($fallbackLabel ?? 'Default profile avatar'));
$avatarSrc = \App\Services\ProfilePhotoService::url(
    is_string($avatarPath) || is_numeric($avatarPath) ? (string) $avatarPath : '',
    $avatarUserId > 0 ? $avatarUserId : null
);
?>

<span class="<?= \App\Helpers\Helper::escape($avatarClass) ?>">
  <?php if ($avatarSrc !== ''): ?>
    <img
      class="<?= \App\Helpers\Helper::escape($imageClass) ?>"
      src="<?= \App\Helpers\Helper::escape($avatarSrc) ?>"
      alt="<?= \App\Helpers\Helper::escape($fullName) ?> profile photo"
    >
  <?php else: ?>
    <span class="<?= \App\Helpers\Helper::escape($fallbackClass) ?>" aria-hidden="true">
      <?= \App\Helpers\Helper::escape(\App\Helpers\Helper::initials($fullName)) ?>
    </span>
    <span class="visually-hidden"><?= \App\Helpers\Helper::escape($fallbackLabel) ?></span>
  <?php endif; ?>
</span>
<?php
unset($avatarPath, $avatarUserId, $avatarSrc, $pathUserId);
