<?php

$currentPath = \App\Helpers\Helper::currentPath();
$sidebarItems = $sidebarItems ?? [];

$hasGroups = false;
foreach ($sidebarItems as $item) {
    if (isset($item['group']) && $item['group'] !== '') {
        $hasGroups = true;
        break;
    }
}

$groupedItems = [];
$groupOrder = [];

if ($hasGroups) {
    foreach ($sidebarItems as $item) {
        $groupName = (string) ($item['group'] ?? 'General');
        if (!isset($groupedItems[$groupName])) {
            $groupedItems[$groupName] = [];
            $groupOrder[] = $groupName;
        }
        $groupedItems[$groupName][] = $item;
    }
} else {
    $groupedItems['Navigation'] = $sidebarItems;
    $groupOrder = ['Navigation'];
}

$renderSidebarNav = static function (array $groupedItems, array $groupOrder, string $currentPath, bool $forOffcanvas = false): void {
    foreach ($groupOrder as $groupName):
        ?>
        <div class="sidebar-nav-group">
            <?php if (!$forOffcanvas || true): ?>
                <p class="sidebar-caption"><?= \App\Helpers\Helper::escape($groupName) ?></p>
            <?php endif; ?>
            <nav class="nav flex-column gap-2">
                <?php foreach ($groupedItems[$groupName] as $item): ?>
                    <?php
                    $itemPath = (string) ($item['path'] ?? '#');
                    $isActive = $currentPath === $itemPath || ($itemPath !== '/' && str_starts_with($currentPath, $itemPath . '/'));
                    ?>
                    <a
                        href="<?= \App\Helpers\Helper::url($itemPath) ?>"
                        class="sidebar-link <?= $isActive ? 'active' : '' ?>"
                    >
                        <span class="sidebar-link-icon"><i class="bi <?= \App\Helpers\Helper::escape($item['icon'] ?? 'bi-grid') ?>"></i></span>
                        <span><?= \App\Helpers\Helper::escape($item['label'] ?? 'Link') ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    <?php
    endforeach;
};
?>

<aside class="dashboard-sidebar d-none d-lg-flex flex-column">
  <div class="dashboard-brand px-4 py-4">
    <?php
    $brandVariant = 'sidebar';
    $brandSubtitle = '';
    $brandRoleLabel = $dashboardRoleLabel ?? 'Dashboard';
    $brandShowTitle = false;
    $brandLink = \App\Helpers\Helper::url('/');
    require __DIR__ . '/../shared/brand_logo.php';
    ?>
  </div>

  <div class="px-4 pb-4">
    <div class="sidebar-workspace-badge mb-3">
      <span class="sidebar-workspace-dot"></span>
      <span><?= \App\Helpers\Helper::escape($dashboardRoleLabel ?? 'Dashboard Workspace') ?></span>
    </div>
    <div class="sidebar-user-card">
      <?php
      $avatarPath = $user->profile_photo_path ?? null;
      $fullName = $user->full_name ?? 'User';
      $avatarClass = 'user-avatar user-avatar--sm';
      require __DIR__ . '/../shared/user_avatar.php';
      ?>
      <div>
        <strong class="d-block text-white"><?= \App\Helpers\Helper::escape($user->full_name ?? 'User') ?></strong>
        <span class="sidebar-user-role"><?= \App\Helpers\Helper::escape($dashboardRoleLabel ?? 'Dashboard') ?></span>
        <span class="sidebar-user-meta"><?= \App\Helpers\Helper::escape($user->email ?? '') ?></span>
      </div>
    </div>
  </div>

  <div class="px-3 pb-4 flex-grow-1">
    <?php $renderSidebarNav($groupedItems, $groupOrder, $currentPath, false); ?>
  </div>

  <div class="sidebar-copyright">
    &copy; MBPHA &middot; TeleHealth
  </div>
</aside>

<div class="offcanvas offcanvas-start dashboard-offcanvas" tabindex="-1" id="dashboardSidebar" aria-labelledby="dashboardSidebarLabel">
  <div class="offcanvas-header border-bottom">
    <div id="dashboardSidebarLabel">
      <?php
      $brandVariant = 'offcanvas';
      $brandSubtitle = '';
      $brandRoleLabel = $dashboardRoleLabel ?? 'Dashboard';
      $brandShowTitle = false;
      $brandLink = \App\Helpers\Helper::url('/');
      require __DIR__ . '/../shared/brand_logo.php';
      ?>
    </div>
    <button type="button" class="btn-close text-reset shadow-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <div class="sidebar-user-card sidebar-user-card-offcanvas mb-4">
      <?php
      $avatarPath = $user->profile_photo_path ?? null;
      $fullName = $user->full_name ?? 'User';
      $avatarClass = 'user-avatar user-avatar--sm';
      require __DIR__ . '/../shared/user_avatar.php';
      ?>
      <div>
        <strong class="d-block text-white"><?= \App\Helpers\Helper::escape($user->full_name ?? 'User') ?></strong>
        <span class="sidebar-user-role"><?= \App\Helpers\Helper::escape($dashboardRoleLabel ?? 'Dashboard') ?></span>
      </div>
    </div>

    <?php $renderSidebarNav($groupedItems, $groupOrder, $currentPath, true); ?>
  </div>
</div>
