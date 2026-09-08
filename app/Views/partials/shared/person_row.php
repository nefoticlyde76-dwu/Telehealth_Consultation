<?php

$personName = (string) ($personName ?? 'User');
$personPhoto = $personPhoto ?? null;
$personMeta = (string) ($personMeta ?? '');
$personSize = (string) ($personSize ?? 'sm');
$allowedSizes = ['xs', 'sm', 'lg'];
if (!in_array($personSize, $allowedSizes, true)) {
    $personSize = 'sm';
}

$avatarPath = $personPhoto;
$avatarUserId = \App\Services\ProfilePhotoService::userIdFromPath(is_string($personPhoto) ? $personPhoto : '');
$fullName = $personName;
$avatarClass = 'user-avatar user-avatar--' . $personSize;
?>
<div class="ux-person">
  <?php require __DIR__ . '/user_avatar.php'; ?>
  <div class="ux-person__copy">
    <strong class="ux-person__name"><?= \App\Helpers\Helper::escape($personName) ?></strong>
    <?php if ($personMeta !== ''): ?>
      <span class="ux-person__meta"><?= \App\Helpers\Helper::escape($personMeta) ?></span>
    <?php endif; ?>
  </div>
</div>
