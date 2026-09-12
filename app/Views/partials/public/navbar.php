<?php

$currentUser = \App\Services\AuthService::getUser();
$currentRole = \App\Services\AuthService::getUserRole();
$currentPath = \App\Helpers\Helper::currentPath();
$dashboardUrl = $currentRole ? \App\Services\AuthService::getRoleRedirectUrl($currentRole) : '/';

$publicNavigationItems = [
    ['label' => 'Home',         'path' => '/',              'icon' => 'bi-house-door'],
    ['label' => 'About',        'path' => '/about',         'icon' => 'bi-info-circle'],
    ['label' => 'How It Works', 'path' => '/how-it-works',  'icon' => 'bi-diagram-3'],
    ['label' => 'Contact',      'path' => '/contact',       'icon' => 'bi-envelope'],
];

$isNavActive = static function (string $path) use ($currentPath): bool {
    if ($path === '/') {
        return $currentPath === '/';
    }

    return $currentPath === $path;
};

$loginActive = $currentPath === '/login';
$registerActive = $currentPath === '/register';
?>
<div class="public-agh-layer" aria-hidden="true"></div>
<nav class="navbar navbar-expand-xl navbar-light public-navbar" aria-label="MBPHA TeleHealth public navigation">
  <div class="container">
    <?php
    $brandVariant = 'navbar';
    $brandSubtitle = '';
    $brandShowTitle = false;
    $brandShowWordmark = false;
    $brandLink = \App\Helpers\Helper::url('/');
    require __DIR__ . '/../shared/brand_logo.php';
    ?>

    <ul class="navbar-nav public-navbar-links d-none d-xl-flex">
      <?php foreach ($publicNavigationItems as $item): ?>
        <?php $active = $isNavActive($item['path']); ?>
        <li class="nav-item">
          <a
            class="nav-link<?= $active ? ' active' : '' ?>"
            href="<?= \App\Helpers\Helper::url($item['path']) ?>"
            <?php if ($active): ?>aria-current="page"<?php endif; ?>
          >
            <?= \App\Helpers\Helper::escape($item['label']) ?>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>

    <div class="public-navbar-actions d-none d-xl-flex">
      <?php if ($currentUser && $currentRole): ?>
        <a class="btn btn-outline-primary" href="<?= \App\Helpers\Helper::url($dashboardUrl) ?>">Dashboard</a>
        <form action="<?= \App\Helpers\Helper::url('/logout') ?>" method="POST">
          <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape(\App\Core\Csrf::generate()) ?>">
          <button type="submit" class="btn btn-primary">Logout</button>
        </form>
      <?php else: ?>
        <a
          class="btn btn-outline-primary<?= $loginActive ? ' is-active' : '' ?>"
          href="<?= \App\Helpers\Helper::url('/login') ?>"
          <?php if ($loginActive): ?>aria-current="page"<?php endif; ?>
        >Login</a>
        <a
          class="btn btn-primary<?= $registerActive ? ' is-active' : '' ?>"
          href="<?= \App\Helpers\Helper::url('/register') ?>"
          <?php if ($registerActive): ?>aria-current="page"<?php endif; ?>
        >Register</a>
      <?php endif; ?>
    </div>

    <div class="public-navbar-actions public-navbar-actions--compact d-flex d-xl-none">
      <?php if ($currentUser && $currentRole): ?>
        <a class="btn btn-primary public-navbar-cta" href="<?= \App\Helpers\Helper::url($dashboardUrl) ?>">Dashboard</a>
      <?php else: ?>
        <a
          class="btn btn-outline-primary public-navbar-cta public-navbar-cta--login<?= $loginActive ? ' is-active' : '' ?>"
          href="<?= \App\Helpers\Helper::url('/login') ?>"
          <?php if ($loginActive): ?>aria-current="page"<?php endif; ?>
        >Login</a>
        <a
          class="btn btn-primary public-navbar-cta<?= $registerActive ? ' is-active' : '' ?>"
          href="<?= \App\Helpers\Helper::url('/register') ?>"
          <?php if ($registerActive): ?>aria-current="page"<?php endif; ?>
        >Register</a>
      <?php endif; ?>
    </div>

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
      $brandShowWordmark = false;
      $brandRoleLabel = '';
      $brandLink = \App\Helpers\Helper::url('/');
      require __DIR__ . '/../shared/brand_logo.php';
      ?>
      <div class="public-nav-offcanvas-divider"></div>
    </div>
    <button type="button" class="btn-close text-reset shadow-none ms-3" data-bs-dismiss="offcanvas" aria-label="Close navigation"></button>
  </div>

  <div class="offcanvas-body d-flex flex-column">
    <nav class="nav flex-column gap-2 public-nav-drawer-links" aria-label="Mobile public navigation">
      <?php foreach ($publicNavigationItems as $item): ?>
        <?php $active = $isNavActive($item['path']); ?>
        <a
          href="<?= \App\Helpers\Helper::url($item['path']) ?>"
          class="public-drawer-link<?= $active ? ' active' : '' ?>"
          <?php if ($active): ?>aria-current="page"<?php endif; ?>
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
        <a class="btn btn-primary w-100 mb-2" href="<?= \App\Helpers\Helper::url($dashboardUrl) ?>">Dashboard</a>
        <form action="<?= \App\Helpers\Helper::url('/logout') ?>" method="POST">
          <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape(\App\Core\Csrf::generate()) ?>">
          <button type="submit" class="btn btn-outline-primary public-drawer-secondary w-100">Logout</button>
        </form>
      <?php else: ?>
        <a
          class="btn btn-outline-primary w-100 mb-2<?= $loginActive ? ' is-active' : '' ?>"
          href="<?= \App\Helpers\Helper::url('/login') ?>"
        >Login</a>
        <a
          class="btn btn-primary w-100<?= $registerActive ? ' is-active' : '' ?>"
          href="<?= \App\Helpers\Helper::url('/register') ?>"
        >Register</a>
      <?php endif; ?>
    </div>
  </div>
</div>
