<?php

/**
 * Shared authenticated account menu. Visual wrapper only — routes and logout stay the same.
 *
 * @var object|array $accountUser
 * @var string $accountRoleLabel
 * @var string $accountRoleKey
 * @var string $accountProfilePath
 * @var bool $accountShowTriggerMeta
 */

use App\Core\Csrf;
use App\Helpers\Helper;

$accountUser = $accountUser ?? ($user ?? null);
$accountRoleLabel = trim((string) ($accountRoleLabel ?? 'Account'));
$accountRoleKey = strtolower(trim((string) ($accountRoleKey ?? '')));
if ($accountRoleKey === 'administrator') {
    $accountRoleKey = 'admin';
}
if (!in_array($accountRoleKey, ['admin', 'doctor', 'patient'], true)) {
    $accountRoleKey = 'patient';
}

$accountName = 'User';
$accountEmail = '';
$accountPhotoPath = null;
$accountUserId = 0;
if (is_object($accountUser)) {
    $accountName = trim((string) ($accountUser->full_name ?? '')) ?: 'User';
    $accountEmail = trim((string) ($accountUser->email ?? ''));
    $accountPhotoPath = $accountUser->profile_photo_path ?? null;
    $accountUserId = (int) ($accountUser->id ?? 0);
} elseif (is_array($accountUser)) {
    $accountName = trim((string) ($accountUser['full_name'] ?? '')) ?: 'User';
    $accountEmail = trim((string) ($accountUser['email'] ?? ''));
    $accountPhotoPath = $accountUser['profile_photo_path'] ?? null;
    $accountUserId = (int) ($accountUser['id'] ?? 0);
}

$accountProfilePath = (string) ($accountProfilePath ?? '#');
$accountShowTriggerMeta = !empty($accountShowTriggerMeta);
$accountTriggerClass = trim((string) ($accountTriggerClass ?? ''));
$currentAccountPath = Helper::currentPath();
$isAccountPath = static function (string $path) use ($currentAccountPath): bool {
    return $currentAccountPath === $path;
};
?>
<div class="dropdown account-menu">
  <button
    class="account-menu__trigger<?= $accountTriggerClass !== '' ? ' ' . Helper::escape($accountTriggerClass) : '' ?>"
    type="button"
    data-bs-toggle="dropdown"
    data-bs-offset="0,12"
    aria-expanded="false"
    aria-haspopup="true"
    aria-controls="accountMenuPanel"
    aria-label="Account menu"
  >
    <?php
    $avatarPath = $accountPhotoPath;
    $avatarUserId = $accountUserId;
    $fullName = $accountName;
    $avatarClass = 'user-avatar user-avatar--xs account-menu__trigger-avatar';
    require __DIR__ . '/user_avatar.php';
    ?>
    <?php if ($accountShowTriggerMeta): ?>
      <span class="account-menu__trigger-meta d-none d-lg-flex">
        <strong><?= Helper::escape($accountName) ?></strong>
        <small><?= Helper::escape($accountRoleLabel) ?></small>
      </span>
    <?php endif; ?>
    <i class="bi bi-chevron-down account-menu__trigger-caret" aria-hidden="true"></i>
  </button>

  <div class="dropdown-menu dropdown-menu-end account-menu__panel" id="accountMenuPanel">
    <div class="account-menu__header">
      <a
        href="<?= Helper::url($accountProfilePath) ?>"
        class="account-menu__avatar-link"
        aria-label="View profile"
      >
        <?php
        $avatarPath = $accountPhotoPath;
        $avatarUserId = $accountUserId;
        $fullName = $accountName;
        $avatarClass = 'user-avatar user-avatar--lg account-menu__avatar';
        require __DIR__ . '/user_avatar.php';
        ?>
      </a>
      <div class="account-menu__identity">
        <p class="account-menu__name"><?= Helper::escape($accountName) ?></p>
        <p class="account-menu__role account-menu__role--<?= Helper::escape($accountRoleKey) ?>"><?= Helper::escape($accountRoleLabel) ?></p>
        <?php if ($accountEmail !== ''): ?>
          <p class="account-menu__email">
            <i class="bi bi-envelope" aria-hidden="true"></i>
            <span><?= Helper::escape($accountEmail) ?></span>
          </p>
        <?php endif; ?>
      </div>
    </div>

    <div class="account-menu__list">
      <a
        href="<?= Helper::url($accountProfilePath) ?>"
        class="account-menu__item<?= $isAccountPath($accountProfilePath) ? ' is-active' : '' ?>"
      >
        <span class="account-menu__icon" aria-hidden="true"><i class="bi bi-person"></i></span>
        <span class="account-menu__label">Profile</span>
        <i class="bi bi-chevron-right account-menu__chevron" aria-hidden="true"></i>
      </a>
      <a
        href="<?= Helper::url('/account/security') ?>"
        class="account-menu__item<?= $isAccountPath('/account/security') ? ' is-active' : '' ?>"
      >
        <span class="account-menu__icon" aria-hidden="true"><i class="bi bi-shield-lock"></i></span>
        <span class="account-menu__label">Security</span>
        <i class="bi bi-chevron-right account-menu__chevron" aria-hidden="true"></i>
      </a>
      <a
        href="<?= Helper::url('/account/notifications/preferences') ?>"
        class="account-menu__item<?= $isAccountPath('/account/notifications/preferences') ? ' is-active' : '' ?>"
      >
        <span class="account-menu__icon" aria-hidden="true"><i class="bi bi-sliders"></i></span>
        <span class="account-menu__label">Notification Preferences</span>
        <i class="bi bi-chevron-right account-menu__chevron" aria-hidden="true"></i>
      </a>

      <div class="account-menu__divider" role="separator"></div>

      <form action="<?= Helper::url('/logout') ?>" method="POST" class="account-menu__form">
        <input type="hidden" name="_token" value="<?= Helper::escape(Csrf::generate()) ?>">
        <button type="submit" class="account-menu__item account-menu__item--danger">
          <span class="account-menu__icon" aria-hidden="true"><i class="bi bi-box-arrow-right"></i></span>
          <span class="account-menu__label">Logout</span>
          <i class="bi bi-chevron-right account-menu__chevron" aria-hidden="true"></i>
        </button>
      </form>
    </div>
  </div>
</div>
<?php
$accountShowTriggerMeta = false;
$accountTriggerClass = '';
?>
