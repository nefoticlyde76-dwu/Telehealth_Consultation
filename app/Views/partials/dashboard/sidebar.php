<?php

$currentPath = \App\Helpers\Helper::currentPath();
$dashboardRole = (string) ($dashboardRole ?? '');

$canonicalNav = [
    'admin' => [
        'roleLabel' => 'Administrator',
        'primary' => [
            ['path' => '/admin/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2'],
            ['path' => '/admin/users', 'label' => 'Users', 'icon' => 'bi-people'],
            ['path' => '/admin/doctors', 'label' => 'Doctors', 'icon' => 'bi-person-badge'],
            ['path' => '/admin/patients', 'label' => 'Patients', 'icon' => 'bi-heart-pulse'],
            ['path' => '/admin/consultation-requests', 'label' => 'Consultation Requests', 'icon' => 'bi-clipboard2-check'],
        ],
        'account' => [
            ['path' => '/admin/profile', 'label' => 'Settings', 'icon' => 'bi-gear'],
        ],
    ],
    'doctor' => [
        'roleLabel' => 'Doctor',
        'primary' => [
            ['path' => '/doctor/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2'],
            ['path' => '/doctor/availability', 'label' => 'Availability', 'icon' => 'bi-calendar-week'],
            ['path' => '/doctor/consultations', 'label' => 'Appointments', 'icon' => 'bi-calendar2-check'],
        ],
        'account' => [
            ['path' => '/doctor/profile', 'label' => 'Profile', 'icon' => 'bi-person'],
            ['path' => '/doctor/profile/edit', 'label' => 'Settings', 'icon' => 'bi-gear'],
        ],
    ],
    'patient' => [
        'roleLabel' => 'Patient',
        'primary' => [
            ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2'],
            ['path' => '/patient/available-slots', 'label' => 'Book Consultation', 'icon' => 'bi-calendar2-plus'],
            ['path' => '/patient/doctors', 'label' => 'Doctors', 'icon' => 'bi-person-badge'],
            ['path' => '/patient/consultation-requests', 'label' => 'My Appointments', 'icon' => 'bi-clipboard2-check'],
        ],
        'account' => [
            ['path' => '/patient/profile', 'label' => 'Profile', 'icon' => 'bi-person'],
            ['path' => '/patient/profile/edit', 'label' => 'Settings', 'icon' => 'bi-gear'],
        ],
    ],
];

$navConfig = $canonicalNav[$dashboardRole] ?? [
    'roleLabel' => (string) ($dashboardRoleLabel ?? 'Dashboard'),
    'primary' => is_array($sidebarItems ?? null) ? $sidebarItems : [],
    'account' => [],
];

$roleFooterLabel = (string) ($navConfig['roleLabel'] ?? 'Dashboard');
$primaryItems = is_array($navConfig['primary'] ?? null) ? $navConfig['primary'] : [];
$accountItems = is_array($navConfig['account'] ?? null) ? $navConfig['account'] : [];

$isNavActive = static function (string $itemPath, string $currentPath, array $siblings = []): bool {
    if ($itemPath === '' || $itemPath === '#') {
        return false;
    }
    if ($currentPath === $itemPath) {
        return true;
    }
    if ($itemPath !== '/' && str_starts_with($currentPath, $itemPath . '/')) {
        foreach ($siblings as $sibling) {
            $siblingPath = (string) ($sibling['path'] ?? '');
            if ($siblingPath !== '' && $siblingPath !== $itemPath && ($currentPath === $siblingPath || str_starts_with($currentPath, $siblingPath . '/'))) {
                if (strlen($siblingPath) > strlen($itemPath)) {
                    return false;
                }
            }
        }
        return true;
    }
    return false;
};

$renderNavGroup = static function (array $items, string $currentPath) use ($isNavActive): void {
    foreach ($items as $item):
        $itemPath = (string) ($item['path'] ?? '#');
        $isActive = empty($item['skipActive']) && $isNavActive($itemPath, $currentPath, $items);
        ?>
        <a href="<?= \App\Helpers\Helper::url($itemPath) ?>" class="sidebar-link <?= $isActive ? 'active' : '' ?>">
            <span class="sidebar-link-icon"><i class="bi <?= \App\Helpers\Helper::escape($item['icon'] ?? 'bi-grid') ?>"></i></span>
            <span><?= \App\Helpers\Helper::escape($item['label'] ?? 'Link') ?></span>
        </a>
        <?php
    endforeach;
};

$renderSidebarBody = static function (array $primaryItems, array $accountItems, string $currentPath, $user, string $roleFooterLabel, bool $forOffcanvas = false) use ($renderNavGroup): void {
    ?>
    <div class="dashboard-brand <?= $forOffcanvas ? 'px-0 pb-3' : 'px-3 pt-4 pb-2' ?>">
      <?php
      $brandVariant = $forOffcanvas ? 'offcanvas' : 'sidebar';
      $brandSubtitle = '';
      $brandRoleLabel = '';
      $brandShowTitle = false;
      $brandShowWordmark = true;
      $brandLink = \App\Helpers\Helper::url('/');
      require __DIR__ . '/../shared/brand_logo.php';
      ?>
    </div>

    <div class="sidebar-nav-scroll">
      <div class="sidebar-nav-group">
        <nav class="nav flex-column">
          <?php $renderNavGroup($primaryItems, $currentPath); ?>
        </nav>
      </div>

      <div class="sidebar-nav-group">
        <p class="sidebar-caption">Account</p>
        <nav class="nav flex-column">
          <?php $renderNavGroup($accountItems, $currentPath); ?>
          <form action="<?= \App\Helpers\Helper::url('/logout') ?>" method="POST">
            <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape(\App\Core\Csrf::generate()) ?>">
            <button type="submit" class="sidebar-link sidebar-logout-btn">
              <span class="sidebar-link-icon"><i class="bi bi-box-arrow-left"></i></span>
              <span>Logout</span>
            </button>
          </form>
        </nav>
      </div>
    </div>

    <div class="sidebar-user-footer">
      <?php
      $avatarPath = $user->profile_photo_path ?? null;
      $fullName = $user->full_name ?? 'User';
      $avatarClass = 'user-avatar user-avatar--sm';
      require __DIR__ . '/../shared/user_avatar.php';
      ?>
      <div>
        <strong><?= \App\Helpers\Helper::escape($user->full_name ?? 'User') ?></strong>
        <span class="sidebar-user-role">
          <?= \App\Helpers\Helper::escape($roleFooterLabel) ?>
        </span>
      </div>
    </div>
    <?php
};
?>

<aside class="dashboard-sidebar d-none d-lg-flex flex-column">
  <?php $renderSidebarBody($primaryItems, $accountItems, $currentPath, $user, $roleFooterLabel, false); ?>
</aside>

<div class="offcanvas offcanvas-start dashboard-offcanvas" tabindex="-1" id="dashboardSidebar" aria-labelledby="dashboardSidebarLabel">
  <div class="offcanvas-header border-0 pb-0">
    <span class="visually-hidden" id="dashboardSidebarLabel">Dashboard navigation</span>
    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body d-flex flex-column p-0">
    <?php $renderSidebarBody($primaryItems, $accountItems, $currentPath, $user, $roleFooterLabel, true); ?>
  </div>
</div>
