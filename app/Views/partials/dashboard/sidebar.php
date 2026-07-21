<?php

$currentPath = \App\Helpers\Helper::currentPath();
$sidebarItems = $sidebarItems ?? [];
?>

<aside class="dashboard-sidebar d-none d-lg-flex flex-column">
  <div class="dashboard-brand px-4 py-4">
    <a href="<?= \App\Helpers\Helper::url('/') ?>" class="text-decoration-none d-flex align-items-center gap-3">
      <span class="brand-mark">
        <i class="bi bi-heart-pulse-fill"></i>
      </span>
      <div>
        <span class="brand-title text-white">TeleHealth PNG</span>
        <span class="brand-subtitle d-block text-white-50"><?= \App\Helpers\Helper::escape($dashboardRoleLabel ?? 'Dashboard') ?></span>
      </div>
    </a>
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
      <h2 class="h6 text-white mb-2">Week 2 Access Layer</h2>
      <p class="text-white-50 small mb-0">Core dashboards are live and ready for upcoming module integration.</p>
    </div>
  </div>
</aside>

<div class="offcanvas offcanvas-start dashboard-offcanvas" tabindex="-1" id="dashboardSidebar" aria-labelledby="dashboardSidebarLabel">
  <div class="offcanvas-header border-bottom">
    <h2 class="offcanvas-title h5 mb-0" id="dashboardSidebarLabel">TeleHealth PNG</h2>
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
