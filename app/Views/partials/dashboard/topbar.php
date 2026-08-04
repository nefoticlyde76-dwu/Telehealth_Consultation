<?php
$dashboardRole = (string) ($dashboardRole ?? '');
$showRightbar = (bool) ($showRightbar ?? false);
$topbarSearchPlaceholder = (string) ($topbarSearchPlaceholder ?? 'Search doctors, consultations, or requests');
$topbarQuickAction = $topbarQuickAction ?? null;

if (!is_array($topbarQuickAction)) {
    if ($dashboardRole === 'patient') {
        $topbarQuickAction = ['label' => 'Book Consultation', 'url' => '/patient/available-slots', 'icon' => 'bi-calendar2-plus'];
    } elseif ($dashboardRole === 'doctor') {
        $topbarQuickAction = ['label' => 'Create Slot', 'url' => '/doctor/availability/create', 'icon' => 'bi-plus-circle'];
    } elseif ($dashboardRole === 'admin') {
        $topbarQuickAction = ['label' => 'Review Requests', 'url' => '/admin/consultation-requests', 'icon' => 'bi-clipboard2-check'];
    } else {
        $topbarQuickAction = null;
    }
}
?>

<header class="dashboard-topbar">
  <div class="dashboard-topbar-row">
    <div class="d-flex align-items-center gap-3 flex-wrap">
      <button class="btn btn-outline-primary d-lg-none rounded-circle topbar-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#dashboardSidebar" aria-controls="dashboardSidebar" aria-label="Open dashboard navigation">
        <i class="bi bi-list"></i>
      </button>

      <?php if ($showRightbar): ?>
        <button class="btn btn-outline-primary d-xl-none rounded-circle topbar-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#dashboardRightbar" aria-controls="dashboardRightbar" aria-label="Open dashboard overview panel">
          <i class="bi bi-layout-sidebar-inset-reverse"></i>
        </button>
      <?php endif; ?>

      <div class="topbar-role-pill d-none d-md-inline-flex">
        <i class="bi bi-heart-pulse"></i>
        <span><?= \App\Helpers\Helper::escape($dashboardRoleLabel ?? 'Dashboard Workspace') ?></span>
      </div>
    </div>

    <div class="topbar-search d-none d-lg-flex" role="search" aria-label="Global search">
      <i class="bi bi-search"></i>
      <input type="search" class="form-control topbar-search-input" placeholder="<?= \App\Helpers\Helper::escape($topbarSearchPlaceholder) ?>" autocomplete="off">
    </div>

    <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
      <?php if (is_array($topbarQuickAction)): ?>
        <a href="<?= \App\Helpers\Helper::url((string) ($topbarQuickAction['url'] ?? '#')) ?>" class="btn btn-primary rounded-pill px-4 topbar-quick-action d-none d-md-inline-flex">
          <i class="bi <?= \App\Helpers\Helper::escape((string) ($topbarQuickAction['icon'] ?? 'bi-lightning-charge')) ?> me-2"></i>
          <?= \App\Helpers\Helper::escape((string) ($topbarQuickAction['label'] ?? 'Action')) ?>
        </a>
      <?php endif; ?>

      <div class="topbar-chip d-none d-md-inline-flex" aria-label="Current date and time">
        <i class="bi bi-clock"></i>
        <span data-dashboard-datetime="full"><?= \App\Helpers\Helper::escape(date('D, d M Y H:i')) ?></span>
      </div>

      <button type="button" class="topbar-icon-btn" aria-label="Notifications">
        <i class="bi bi-bell"></i>
        <span class="badge rounded-pill badge-soft-info topbar-icon-badge">0</span>
      </button>

      <div class="dropdown">
        <button class="btn profile-trigger dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
          <?php
          $avatarPath = $user->profile_photo_path ?? null;
          $fullName = $user->full_name ?? 'User';
          $avatarClass = 'user-avatar user-avatar--xs';
          require __DIR__ . '/../shared/user_avatar.php';
          ?>
          <span class="text-start topbar-profile-meta d-none d-md-block">
            <strong class="d-block"><?= \App\Helpers\Helper::escape($user->full_name ?? 'User') ?></strong>
            <small class="text-muted"><?= \App\Helpers\Helper::escape(ucfirst($dashboardRole !== '' ? $dashboardRole : 'account')) ?></small>
          </span>
          <span class="badge badge-soft-neutral rounded-pill px-3 py-2 ms-2 d-none d-lg-inline-flex"><?= \App\Helpers\Helper::escape(strtoupper($dashboardRole !== '' ? $dashboardRole : 'USER')) ?></span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4 p-2">
          <li><span class="dropdown-item-text text-muted small"><?= \App\Helpers\Helper::escape($user->email ?? '') ?></span></li>
          <li><hr class="dropdown-divider"></li>
          <?php if ($dashboardRole === 'admin'): ?>
            <li>
              <a href="<?= \App\Helpers\Helper::url('/admin/profile') ?>" class="dropdown-item rounded-3">
                <i class="bi bi-person-gear me-2"></i>
                Profile Settings
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
          <?php elseif ($dashboardRole === 'doctor'): ?>
            <li>
              <a href="<?= \App\Helpers\Helper::url('/doctor/profile') ?>" class="dropdown-item rounded-3">
                <i class="bi bi-person-vcard me-2"></i>
                My Profile
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
          <?php elseif ($dashboardRole === 'patient'): ?>
            <li>
              <a href="<?= \App\Helpers\Helper::url('/patient/profile') ?>" class="dropdown-item rounded-3">
                <i class="bi bi-person-circle me-2"></i>
                My Profile
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
          <?php endif; ?>
          <li>
            <form action="<?= \App\Helpers\Helper::url('/logout') ?>" method="POST">
              <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape(\App\Core\Csrf::generate()) ?>">
              <button type="submit" class="dropdown-item rounded-3">Logout</button>
            </form>
          </li>
        </ul>
      </div>
    </div>
  </div>

  <div class="dashboard-topbar-meta">
    <nav aria-label="breadcrumb" class="dashboard-breadcrumb">
      <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="<?= \App\Helpers\Helper::url('/') ?>">Home</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= \App\Helpers\Helper::escape($dashboardRoleLabel ?? 'Dashboard') ?></li>
      </ol>
    </nav>
    <div>
      <h1 class="h3 mb-1"><?= \App\Helpers\Helper::escape($dashboardTitle ?? 'Dashboard') ?></h1>
      <p class="text-muted mb-0"><?= \App\Helpers\Helper::escape($dashboardDescription ?? 'Overview') ?></p>
    </div>
  </div>
</header>
