<?php

use App\Helpers\DashboardNav;
use App\Helpers\Helper;
use App\Core\Csrf;

$currentPath = Helper::currentPath();
$dashboardRole = (string) ($dashboardRole ?? '');
$navConfig = DashboardNav::forRole($dashboardRole);
$roleFooterLabel = (string) ($navConfig['roleLabel'] ?? 'Dashboard');
$navGroups = is_array($navConfig['groups'] ?? null) ? $navConfig['groups'] : [];
$accountItems = is_array($navConfig['account'] ?? null) ? $navConfig['account'] : [];
$allNavItems = DashboardNav::allItems($dashboardRole);
$homePath = (string) ($navConfig['home'] ?? '/');

$renderNavGroup = static function (array $items, string $currentPath, array $allNavItems): void {
    foreach ($items as $item):
        $itemPath = (string) ($item['path'] ?? '#');
        $itemLabel = (string) ($item['label'] ?? 'Link');
        $isActive = empty($item['skipActive']) && DashboardNav::isActive($itemPath, $currentPath, $allNavItems);
        ?>
        <a href="<?= Helper::url($itemPath) ?>"
           class="sidebar-link<?= $isActive ? ' active' : '' ?>"
           data-label="<?= Helper::escape($itemLabel) ?>"
           data-tooltip="<?= Helper::escape($itemLabel) ?>"
           <?= $isActive ? ' aria-current="page"' : '' ?>>
            <span class="sidebar-link-icon" aria-hidden="true"><i class="bi <?= Helper::escape($item['icon'] ?? 'bi-grid') ?>"></i></span>
            <span class="sidebar-link-label"><?= Helper::escape($itemLabel) ?></span>
        </a>
        <?php
    endforeach;
};

$renderSidebarBody = static function (
    array $navGroups,
    array $accountItems,
    array $allNavItems,
    string $currentPath,
    $user,
    string $roleFooterLabel,
    string $homePath,
    bool $forOffcanvas = false
) use ($renderNavGroup): void {
    $idPrefix = $forOffcanvas ? 'offcanvas-' : 'rail-';
    ?>
    <div class="sidebar-rail-top<?= $forOffcanvas ? ' sidebar-rail-top--offcanvas' : '' ?>">
      <div class="dashboard-brand">
        <?php
        $brandVariant = $forOffcanvas ? 'offcanvas' : 'sidebar';
        $brandSubtitle = '';
        $brandRoleLabel = '';
        $brandShowTitle = false;
        $brandShowWordmark = false;
        $brandLink = Helper::url($homePath !== '' ? $homePath : '/');
        require __DIR__ . '/../shared/brand_logo.php';
        ?>
      </div>
    </div>

    <div class="sidebar-nav-scroll">
      <?php foreach ($navGroups as $group): ?>
        <?php
        $groupCaption = (string) ($group['caption'] ?? '');
        $groupItems = is_array($group['items'] ?? null) ? $group['items'] : [];
        if ($groupItems === []) {
            continue;
        }
        $groupSlug = $idPrefix . strtolower(preg_replace('/[^a-z0-9]+/i', '-', $groupCaption) ?: 'nav');
        ?>
        <div class="sidebar-nav-group">
          <?php if ($groupCaption !== ''): ?>
            <p class="sidebar-caption" id="sidebar-<?= \App\Helpers\Helper::escape($groupSlug) ?>"><?= \App\Helpers\Helper::escape($groupCaption) ?></p>
          <?php endif; ?>
          <nav class="nav flex-column" <?= $groupCaption !== '' ? 'aria-labelledby="sidebar-' . \App\Helpers\Helper::escape($groupSlug) . '"' : 'aria-label="Dashboard navigation"' ?>>
            <?php $renderNavGroup($groupItems, $currentPath, $allNavItems); ?>
          </nav>
        </div>
      <?php endforeach; ?>

      <div class="sidebar-nav-group">
        <p class="sidebar-caption" id="<?= $idPrefix ?>account">Account</p>
        <nav class="nav flex-column" aria-labelledby="<?= $idPrefix ?>account">
          <?php $renderNavGroup($accountItems, $currentPath, $allNavItems); ?>
          <form action="<?= \App\Helpers\Helper::url('/logout') ?>" method="POST">
            <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape(Csrf::generate()) ?>">
            <button type="submit" class="sidebar-link sidebar-logout-btn" data-label="Logout" data-tooltip="Logout">
              <span class="sidebar-link-icon" aria-hidden="true"><i class="bi bi-box-arrow-left"></i></span>
              <span class="sidebar-link-label">Logout</span>
            </button>
          </form>
        </nav>
      </div>
    </div>

    <div class="sidebar-user-footer" data-label="<?= \App\Helpers\Helper::escape($user->full_name ?? 'User') ?>" data-tooltip="<?= \App\Helpers\Helper::escape($user->full_name ?? 'User') ?>">
      <div class="sidebar-avatar-wrap">
        <div class="sidebar-user-avatar">
          <?php
          $avatarPath = $user->profile_photo_path ?? null;
          $fullName = $user->full_name ?? 'User';
          $avatarClass = 'user-avatar user-avatar--sm';
          require __DIR__ . '/../shared/user_avatar.php';
          ?>
          <span class="sidebar-online-dot" title="Signed in" aria-hidden="true"></span>
        </div>
      </div>
      <div class="sidebar-user-copy">
        <strong><?= \App\Helpers\Helper::escape($user->full_name ?? 'User') ?></strong>
        <span class="sidebar-user-role"><?= \App\Helpers\Helper::escape($roleFooterLabel) ?></span>
      </div>
    </div>
    <?php
};
?>

<aside class="dashboard-sidebar d-none d-lg-flex flex-column" id="dashboardDesktopNav" aria-label="<?= \App\Helpers\Helper::escape($roleFooterLabel) ?> navigation">
  <?php $renderSidebarBody($navGroups, $accountItems, $allNavItems, $currentPath, $user, $roleFooterLabel, $homePath, false); ?>
</aside>

<div class="offcanvas offcanvas-start dashboard-offcanvas" tabindex="-1" id="dashboardSidebar" aria-labelledby="dashboardSidebarLabel">
  <div class="offcanvas-header border-0 pb-0">
    <span class="visually-hidden" id="dashboardSidebarLabel">Dashboard navigation</span>
    <button type="button" class="btn-close ms-auto" data-bs-dismiss="offcanvas" aria-label="Close navigation"></button>
  </div>
  <div class="offcanvas-body d-flex flex-column p-0">
    <?php $renderSidebarBody($navGroups, $accountItems, $allNavItems, $currentPath, $user, $roleFooterLabel, $homePath, true); ?>
  </div>
</div>
