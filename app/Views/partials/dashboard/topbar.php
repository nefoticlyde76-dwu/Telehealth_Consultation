<?php

use App\Helpers\DashboardNav;
use App\Helpers\Helper;

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
        $brandShowWordmark = false;
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

      <?php
      $accountUser = $user ?? null;
      $accountRoleLabel = $roleChip;
      $accountRoleKey = $dashboardRole;
      $accountProfilePath = $profilePath;
      $accountShowTriggerMeta = true;
      $accountTriggerClass = 'profile-trigger';
      require __DIR__ . '/../shared/account_menu.php';
      ?>
    </div>
  </div>
</header>
