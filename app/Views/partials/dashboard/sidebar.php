<?php

$currentPath = \App\Helpers\Helper::currentPath();
$sidebarItems = $sidebarItems ?? [];
$sidebarStatusTitle = $sidebarStatusTitle ?? 'Week 2 Access Layer';
$sidebarStatusDescription = $sidebarStatusDescription ?? 'Core dashboards are live and ready for upcoming module integration.';
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

  <div class="px-3 pb-4 flex-grow-1">
    <p class="sidebar-caption mb-3 px-3">Navigation</p>
    <nav class="nav flex-column gap-2">
      <?php foreach ($sidebarItems as $item): ?>
        <?php $isActive = $currentPath === ($item['path'] ?? '#'); ?>
        <a
          href="<?= \App\Helpers\Helper::url($item['path'] ?? '/') ?>"
          class="sidebar-link <?= $isActive ? 'active' : '' ?>"
        >
          <span><i class="bi <?= \App\Helpers\Helper::escape($item['icon'] ?? 'bi-grid') ?>"></i></span>
          <span><?= \App\Helpers\Helper::escape($item['label'] ?? 'Link') ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>

  <div class="px-4 pb-4">
    <div class="sidebar-support-card">
      <span class="small text-uppercase text-white-50 d-block mb-2">Platform Status</span>
      <h2 class="h6 text-white mb-2"><?= \App\Helpers\Helper::escape($sidebarStatusTitle) ?></h2>
      <p class="text-white-50 small mb-0"><?= \App\Helpers\Helper::escape($sidebarStatusDescription) ?></p>
    </div>
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
    <nav class="nav flex-column gap-2">
      <?php foreach ($sidebarItems as $item): ?>
        <?php $isActive = $currentPath === ($item['path'] ?? '#'); ?>
        <a
          href="<?= \App\Helpers\Helper::url($item['path'] ?? '/') ?>"
          class="sidebar-link <?= $isActive ? 'active' : '' ?>"
        >
          <span><i class="bi <?= \App\Helpers\Helper::escape($item['icon'] ?? 'bi-grid') ?>"></i></span>
          <span><?= \App\Helpers\Helper::escape($item['label'] ?? 'Link') ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>
</div>
