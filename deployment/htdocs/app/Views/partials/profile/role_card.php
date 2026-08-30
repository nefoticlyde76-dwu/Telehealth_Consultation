<?php

use App\Helpers\Helper;

$profilePage = is_array($profilePage ?? null) ? $profilePage : [];
$roleTiles = is_array($profilePage['role_tiles'] ?? null) ? $profilePage['role_tiles'] : [];
$permissions = is_array($profilePage['role_permissions'] ?? null) ? $profilePage['role_permissions'] : [];
$roleExtraHtml = (string) ($profilePage['role_extra_html'] ?? '');
$emptyMessage = (string) ($profilePage['role_empty'] ?? 'No additional profile fields for this role.');
?>

<section class="user-profile-card user-profile-card--watermark user-profile-role">
  <h3 class="user-profile-card__title">Role profile</h3>

  <?php if ($roleTiles !== []): ?>
    <?php
    $tiles = $roleTiles;
    require __DIR__ . '/info_grid.php';
    ?>
  <?php else: ?>
    <p class="user-profile-card__lede mb-0"><?= Helper::escape($emptyMessage) ?></p>
  <?php endif; ?>

  <?php if ($roleExtraHtml !== ''): ?>
    <?= $roleExtraHtml ?>
  <?php endif; ?>

  <?php if (!empty($permissions['show'])): ?>
    <div class="user-profile-permissions">
      <h4 class="user-profile-permissions__title">Role permissions</h4>
      <p class="user-profile-permissions__text">
        <?= Helper::escape((string) ($permissions['text'] ?? 'Access level and permissions granted to this role.')) ?>
      </p>
      <?php if (!empty($permissions['details_url'])): ?>
        <a class="user-profile-permissions__link" href="<?= Helper::url((string) $permissions['details_url']) ?>">
          <?= Helper::escape((string) ($permissions['details_label'] ?? 'View role details')) ?>
          <span aria-hidden="true">→</span>
        </a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</section>
