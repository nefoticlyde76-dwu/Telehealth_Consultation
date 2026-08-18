<?php
$dashboardRole = (string) ($dashboardRole ?? '');
$showRightbar = (bool) ($showRightbar ?? false);
$topbarQuickAction = $topbarQuickAction ?? null;

$roleChip = match ($dashboardRole) {
    'admin' => 'Administrator',
    'doctor' => 'Doctor',
    'patient' => 'Patient',
    default => 'Account',
};

$profilePath = match ($dashboardRole) {
    'admin' => '/admin/profile',
    'doctor' => '/doctor/profile',
    'patient' => '/patient/profile',
    default => '#',
};

$settingsPath = match ($dashboardRole) {
    'admin' => '/admin/profile',
    'doctor' => '/doctor/profile/edit',
    'patient' => '/patient/profile/edit',
    default => '#',
};

if (!is_array($topbarQuickAction)) {
    if ($dashboardRole === 'patient') {
        $topbarQuickAction = ['label' => 'Book Consultation', 'url' => '/patient/available-slots', 'icon' => 'bi-calendar2-plus'];
    } elseif ($dashboardRole === 'doctor') {
        $topbarQuickAction = ['label' => 'Create Slot', 'url' => '/doctor/availability/create', 'icon' => 'bi-plus-lg'];
    } elseif ($dashboardRole === 'admin') {
        $topbarQuickAction = ['label' => 'Review Requests', 'url' => '/admin/consultation-requests', 'icon' => 'bi-clipboard2-check'];
    } else {
        $topbarQuickAction = null;
    }
}

$topbarPageTitle = trim((string) ($dashboardTitle ?? $pageTitle ?? ''));
if ($topbarPageTitle === '') {
    $documentTitle = trim((string) ($title ?? 'Dashboard'));
    $topbarPageTitle = trim(explode('|', $documentTitle)[0]);
}
?>

<header class="dashboard-topbar">
  <div class="dashboard-topbar-row">
    <div class="topbar-left">
      <button class="btn topbar-toggle d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#dashboardSidebar" aria-controls="dashboardSidebar" aria-label="Open dashboard navigation">
        <i class="bi bi-list"></i>
      </button>
      <button class="btn topbar-toggle d-none d-lg-inline-flex" type="button" data-desktop-sidebar-toggle aria-expanded="true" aria-label="Collapse dashboard navigation">
        <i class="bi bi-list"></i>
      </button>

      <?php if ($showRightbar): ?>
        <button class="btn topbar-toggle d-xl-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#dashboardRightbar" aria-controls="dashboardRightbar" aria-label="Open calendar and overview">
          <i class="bi bi-calendar3"></i>
        </button>
      <?php endif; ?>

      <h1 class="topbar-page-title"><?= \App\Helpers\Helper::escape($topbarPageTitle) ?></h1>
    </div>

    <div class="topbar-right">
      <?php if (is_array($topbarQuickAction)): ?>
        <a href="<?= \App\Helpers\Helper::url((string) ($topbarQuickAction['url'] ?? '#')) ?>" class="btn btn-primary btn-sm topbar-quick-action d-none d-sm-inline-flex">
          <i class="bi <?= \App\Helpers\Helper::escape((string) ($topbarQuickAction['icon'] ?? 'bi-plus-lg')) ?>"></i>
          <span><?= \App\Helpers\Helper::escape((string) ($topbarQuickAction['label'] ?? 'Action')) ?></span>
        </a>
      <?php endif; ?>

      <div class="dropdown">
        <button class="btn profile-trigger dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu">
          <?php
          $avatarPath = $user->profile_photo_path ?? null;
          $fullName = $user->full_name ?? 'User';
          $avatarClass = 'user-avatar user-avatar--xs';
          require __DIR__ . '/../shared/user_avatar.php';
          ?>
          <span class="text-start topbar-profile-meta d-none d-md-block">
            <strong class="d-block"><?= \App\Helpers\Helper::escape($user->full_name ?? 'User') ?></strong>
            <small><?= \App\Helpers\Helper::escape($roleChip) ?></small>
          </span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end p-2">
          <li><span class="dropdown-item-text text-muted small"><?= \App\Helpers\Helper::escape($user->email ?? '') ?></span></li>
          <li><hr class="dropdown-divider"></li>
          <li>
            <a href="<?= \App\Helpers\Helper::url($profilePath) ?>" class="dropdown-item">
              <i class="bi bi-person me-2"></i>
              Profile
            </a>
          </li>
          <li>
            <a href="<?= \App\Helpers\Helper::url($settingsPath) ?>" class="dropdown-item">
              <i class="bi bi-gear me-2"></i>
              Settings
            </a>
          </li>
          <li><hr class="dropdown-divider"></li>
          <li>
            <form action="<?= \App\Helpers\Helper::url('/logout') ?>" method="POST">
              <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape(\App\Core\Csrf::generate()) ?>">
              <button type="submit" class="dropdown-item">
                <i class="bi bi-box-arrow-left me-2"></i>
                Logout
              </button>
            </form>
          </li>
        </ul>
      </div>
    </div>
  </div>
</header>
