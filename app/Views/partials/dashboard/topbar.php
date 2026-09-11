<?php

use App\Helpers\DashboardNav;
use App\Helpers\Helper;
use App\Core\Csrf;

$dashboardRole = (string) ($dashboardRole ?? '');
$showRightbar = (bool) ($showRightbar ?? false);
$navConfig = DashboardNav::forRole($dashboardRole);
$roleChip = (string) ($navConfig['roleLabel'] ?? 'Account');
$profilePath = (string) ($navConfig['profile'] ?? '#');
$homePath = (string) ($navConfig['home'] ?? '/');

$topbarQuickAction = $topbarQuickAction ?? DashboardNav::quickAction($dashboardRole);
$topbarSearch = DashboardNav::searchTarget($dashboardRole);
?>

<header class="dashboard-topbar">
  <div class="dashboard-topbar-row">
    <div class="topbar-left">
      <button class="btn topbar-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#dashboardSidebar" aria-controls="dashboardSidebar" aria-label="Open dashboard navigation">
        <i class="bi bi-list" aria-hidden="true"></i>
      </button>
      <button class="btn topbar-toggle d-none d-lg-inline-flex" type="button" data-desktop-sidebar-toggle aria-expanded="true" aria-controls="dashboardDesktopNav" aria-label="Collapse dashboard navigation">
        <i class="bi bi-list" aria-hidden="true"></i>
      </button>

      <?php if ($showRightbar): ?>
        <button class="btn topbar-toggle d-xl-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#dashboardRightbar" aria-controls="dashboardRightbar" aria-label="Open calendar and overview">
          <i class="bi bi-calendar3" aria-hidden="true"></i>
        </button>
      <?php endif; ?>

      <div class="topbar-brand-wrap">
        <?php
        $brandVariant = 'navbar';
        $brandSubtitle = '';
        $brandRoleLabel = '';
        $brandShowTitle = false;
        $brandShowWordmark = true;
        $brandWordmarkPrimary = 'MBPHA';
        $brandWordmarkSecondary = 'TeleHealth';
        $brandLink = Helper::url($homePath !== '' ? $homePath : '/');
        require __DIR__ . '/../shared/brand_logo.php';
        ?>
      </div>
    </div>

    <form
      class="topbar-search"
      method="GET"
      action="<?= Helper::url((string) ($topbarSearch['url'] ?? '/')) ?>"
      role="search"
      data-topbar-search
    >
      <label class="visually-hidden" for="topbar-global-search">Search</label>
      <i class="bi bi-search" aria-hidden="true"></i>
      <input
        type="search"
        id="topbar-global-search"
        name="search"
        class="form-control"
        placeholder="<?= Helper::escape((string) ($topbarSearch['placeholder'] ?? 'Search…')) ?>"
        maxlength="100"
        autocomplete="off"
      >
    </form>

    <div class="topbar-right">
      <?php require __DIR__ . '/notifications_bell.php'; ?>

      <?php if (is_array($topbarQuickAction)): ?>
        <a href="<?= Helper::url((string) ($topbarQuickAction['url'] ?? '#')) ?>" class="btn btn-primary btn-sm topbar-quick-action d-none d-sm-inline-flex">
          <i class="bi <?= Helper::escape((string) ($topbarQuickAction['icon'] ?? 'bi-plus-lg')) ?>" aria-hidden="true"></i>
          <span><?= Helper::escape((string) ($topbarQuickAction['label'] ?? 'Action')) ?></span>
        </a>
      <?php endif; ?>

      <div class="dropdown">
        <button class="btn profile-trigger dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-haspopup="true" aria-label="Account menu">
          <?php
          $avatarPath = $user->profile_photo_path ?? null;
          $avatarUserId = (int) ($user->id ?? 0);
          $fullName = $user->full_name ?? 'User';
          $avatarClass = 'user-avatar user-avatar--xs';
          require __DIR__ . '/../shared/user_avatar.php';
          ?>
          <span class="text-start topbar-profile-meta d-none d-md-block">
            <strong class="d-block"><?= Helper::escape($user->full_name ?? 'User') ?></strong>
            <small><?= Helper::escape($roleChip) ?></small>
          </span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end p-2">
          <li>
            <span class="dropdown-item-text">
              <strong class="d-block"><?= Helper::escape($user->full_name ?? 'User') ?></strong>
              <small class="text-muted"><?= Helper::escape($roleChip) ?></small>
            </span>
          </li>
          <?php if (!empty($user->email)): ?>
            <li><span class="dropdown-item-text text-muted small"><?= Helper::escape((string) $user->email) ?></span></li>
          <?php endif; ?>
          <li><hr class="dropdown-divider"></li>
          <li>
            <a href="<?= Helper::url($profilePath) ?>" class="dropdown-item">
              <i class="bi bi-person me-2" aria-hidden="true"></i>
              Profile
            </a>
          </li>
          <li>
            <a href="<?= Helper::url('/account/security') ?>" class="dropdown-item">
              <i class="bi bi-shield-lock me-2" aria-hidden="true"></i>
              Security
            </a>
          </li>
          <li>
            <a href="<?= Helper::url('/account/notifications/preferences') ?>" class="dropdown-item">
              <i class="bi bi-sliders me-2" aria-hidden="true"></i>
              Notification Preferences
            </a>
          </li>
          <li><hr class="dropdown-divider"></li>
          <li>
            <form action="<?= Helper::url('/logout') ?>" method="POST">
              <input type="hidden" name="_token" value="<?= Helper::escape(Csrf::generate()) ?>">
              <button type="submit" class="dropdown-item">
                <i class="bi bi-box-arrow-left me-2" aria-hidden="true"></i>
                Logout
              </button>
            </form>
          </li>
        </ul>
      </div>
    </div>
  </div>
</header>
