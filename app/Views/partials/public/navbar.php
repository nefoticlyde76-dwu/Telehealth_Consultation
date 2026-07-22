<?php

$currentUser = \App\Services\AuthService::getUser();
$currentRole = \App\Services\AuthService::getUserRole();
$dashboardUrl = $currentRole ? \App\Services\AuthService::getRoleRedirectUrl($currentRole) : '/';
?>

<nav class="navbar navbar-expand-xl navbar-dark sticky-top public-navbar">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-3" href="<?= \App\Helpers\Helper::url('/') ?>">
      <span class="brand-mark">
        <i class="bi bi-heart-pulse-fill"></i>
      </span>
      <span>
        <span class="brand-title">TeleHealth PNG</span>
        <span class="brand-subtitle d-block">Milne Bay Provincial Health Authority</span>
      </span>
    </a>

    <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavbar" aria-controls="publicNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="publicNavbar">
      <ul class="navbar-nav mx-auto align-items-xl-center gap-xl-2">
        <li class="nav-item"><a class="nav-link" href="<?= \App\Helpers\Helper::url('/#home') ?>">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= \App\Helpers\Helper::url('/#about') ?>">About</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= \App\Helpers\Helper::url('/#services') ?>">Services</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= \App\Helpers\Helper::url('/#features') ?>">Features</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= \App\Helpers\Helper::url('/#how-it-works') ?>">How It Works</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= \App\Helpers\Helper::url('/#faq') ?>">FAQ</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= \App\Helpers\Helper::url('/#contact') ?>">Contact</a></li>
      </ul>

      <div class="d-flex flex-column flex-xl-row gap-2 mt-3 mt-xl-0">
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
