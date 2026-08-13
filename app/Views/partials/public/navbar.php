<?php

$currentUser = \App\Services\AuthService::getUser();
$currentRole = \App\Services\AuthService::getUserRole();
$currentPath = \App\Helpers\Helper::currentPath();
$publicNavigationItems = [
    ['label' => 'Home',          'path' => '/#home',          'icon' => 'bi-house-door'],
    ['label' => 'About',         'path' => '/#about',         'icon' => 'bi-info-circle'],
    ['label' => 'How It Works',  'path' => '/#how-it-works',  'icon' => 'bi-diagram-3'],
    ['label' => 'Contact',       'path' => '/#contact',       'icon' => 'bi-envelope'],
];
$dashboardUrl = $currentRole ? \App\Services\AuthService::getRoleRedirectUrl($currentRole) : '/';
?>
<nav class="navbar navbar-expand-xl navbar-dark sticky-top public-navbar">
  <div class="container">
    <?php
    $brandVariant = 'navbar';
    $brandSubtitle = '';
    $brandShowTitle = false;
    $brandLink = \App\Helpers\Helper::url('/');
    require __DIR__ . '/../shared/brand_logo.php';
    ?>

    <button
      class="navbar-toggler border-0 shadow-none d-xl-none"
      type="button"
      data-bs-toggle="offcanvas"
      data-bs-target="#publicNavigationDrawer"
      aria-controls="publicNavigationDrawer"
      aria-label="Open navigation menu"
    >
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="d-none d-xl-flex align-items-center flex-grow-1">
      <ul class="navbar-nav mx-auto align-items-xl-center gap-xl-2">
        <?php foreach ($publicNavigationItems as $item): ?>
          <?php $isHomeActive = $item['label'] === 'Home' && $currentPath === '/'; ?>
          <li class="nav-item">
            <a class="nav-link <?= $isHomeActive ? 'active' : '' ?>" href="<?= \App\Helpers\Helper::url($item['path']) ?>">
              <?= \App\Helpers\Helper::escape($item['label']) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>

      <div class="d-flex gap-2">
        <?php if ($currentUser && $currentRole): ?>
          <a class="btn btn-outline-primary rounded-pill px-4" href="<?= \App\Helpers\Helper::url($dashboardUrl) ?>">Dashboard</a>
          <form action="<?= \App\Helpers\Helper::url('/logout') ?>" method="POST">
            <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape(\App\Core\Csrf::generate()) ?>">
            <button type="submit" class="btn btn-primary rounded-pill px-4">Logout</button>
          </form>
        <?php else: ?>
          <a class="btn btn-outline-primary rounded-pill px-4" href="<?= \App\Helpers\Helper::url('/login') ?>">Login</a>
          <a class="btn btn-primary rounded-pill px-4" href="<?= \App\Helpers\Helper::url('/register') ?>">Register</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<div
  class="offcanvas offcanvas-start public-nav-offcanvas"
  tabindex="-1"
  id="publicNavigationDrawer"
  aria-labelledby="publicNavigationDrawerLabel"
>
  <div class="offcanvas-header align-items-start">
    <div id="publicNavigationDrawerLabel" class="w-100">
      <?php
      $brandVariant = 'offcanvas';
      $brandSubtitle = 'Connecting Care, Improving Lives';
      $brandShowTitle = true;
      $brandRoleLabel = '';
      $brandLink = \App\Helpers\Helper::url('/');
      require __DIR__ . '/../shared/brand_logo.php';
      ?>
      <div class="public-nav-offcanvas-divider"></div>
    </div>
    <button type="button" class="btn-close text-reset shadow-none ms-3" data-bs-dismiss="offcanvas" aria-label="Close navigation"></button>
  </div>

  <div class="offcanvas-body d-flex flex-column">
    <nav class="nav flex-column gap-2 public-nav-drawer-links">
      <?php foreach ($publicNavigationItems as $item): ?>
        <?php $isHomeActive = $item['label'] === 'Home' && $currentPath === '/'; ?>
        <a
          href="<?= \App\Helpers\Helper::url($item['path']) ?>"
          class="public-drawer-link <?= $isHomeActive ? 'active' : '' ?>"
          data-bs-dismiss="offcanvas"
        >
          <span class="public-drawer-icon">
            <i class="bi <?= \App\Helpers\Helper::escape($item['icon']) ?>"></i>
          </span>
          <span><?= \App\Helpers\Helper::escape($item['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="public-nav-drawer-actions mt-auto pt-4">
      <?php if ($currentUser && $currentRole): ?>
        <a class="btn btn-light public-drawer-primary w-100 rounded-pill" href="<?= \App\Helpers\Helper::url($dashboardUrl) ?>" data-bs-dismiss="offcanvas">Dashboard</a>
        <form action="<?= \App\Helpers\Helper::url('/logout') ?>" method="POST" class="mt-3">
          <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape(\App\Core\Csrf::generate()) ?>">
          <button type="submit" class="btn btn-outline-light public-drawer-secondary w-100 rounded-pill">Secure Logout</button>
        </form>
      <?php else: ?>
        <a class="btn btn-light public-drawer-primary w-100 rounded-pill" href="<?= \App\Helpers\Helper::url('/login') ?>" data-bs-dismiss="offcanvas">Secure Login</a>
        <a class="btn btn-outline-light public-drawer-secondary w-100 rounded-pill mt-3" href="<?= \App\Helpers\Helper::url('/register') ?>" data-bs-dismiss="offcanvas">Register as Patient</a>
      <?php endif; ?>
    </div>
  </div>
</div>
