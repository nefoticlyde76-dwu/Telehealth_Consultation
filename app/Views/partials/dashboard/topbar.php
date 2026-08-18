<?php
$dashboardRole = (string) ($dashboardRole ?? '');
$showRightbar = (bool) ($showRightbar ?? false);
$topbarQuickAction = $topbarQuickAction ?? null;

$searchConfig = [
    'admin' => [
        'url' => '/admin/consultation-requests',
        'placeholder' => 'Search users, consultations, or doctors',
    ],
    'doctor' => [
        'url' => '/doctor/consultations',
        'placeholder' => 'Search patients, appointments, or consultations',
    ],
    'patient' => [
        'url' => '/patient/consultation-requests',
        'placeholder' => 'Search doctors, slots, or consultations',
    ],
];

$topbarSearch = $searchConfig[$dashboardRole] ?? [
    'url' => '/',
    'placeholder' => (string) ($topbarSearchPlaceholder ?? 'Search'),
];
$topbarSearchPlaceholder = (string) ($topbarSearchPlaceholder ?? $topbarSearch['placeholder']);
$topbarSearchQuery = trim((string) ($_GET['search'] ?? ''));
$currentPath = \App\Helpers\Helper::currentPath();
$searchablePrefixes = [
    'admin' => ['/admin/consultation-requests', '/admin/users', '/admin/doctors', '/admin/patients'],
    'doctor' => ['/doctor/consultations', '/doctor/availability'],
    'patient' => ['/patient/consultation-requests', '/patient/doctors', '/patient/available-slots'],
];
$showTopbarSearch = false;
foreach ($searchablePrefixes[$dashboardRole] ?? [] as $prefix) {
    if ($currentPath === $prefix || str_starts_with($currentPath, $prefix . '/')) {
        $showTopbarSearch = true;
        break;
    }
}

$roleChip = match ($dashboardRole) {
    'admin' => 'Administrator',
    'doctor' => 'Doctor',
    'patient' => 'Patient',
    default => 'Account',
};

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
    <div class="topbar-left">
      <button class="btn btn-outline-primary d-lg-none topbar-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#dashboardSidebar" aria-controls="dashboardSidebar" aria-label="Open dashboard navigation">
        <i class="bi bi-list"></i>
      </button>

      <?php if ($showRightbar): ?>
        <button class="btn btn-outline-primary d-xl-none topbar-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#dashboardRightbar" aria-controls="dashboardRightbar" aria-label="Open dashboard overview panel">
          <i class="bi bi-layout-sidebar-inset-reverse"></i>
        </button>
      <?php endif; ?>

      <?php if ($showTopbarSearch): ?>
      <form class="topbar-search d-none d-md-block" action="<?= \App\Helpers\Helper::url((string) $topbarSearch['url']) ?>" method="GET" role="search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input
          type="search"
          name="search"
          value="<?= \App\Helpers\Helper::escape($topbarSearchQuery) ?>"
          placeholder="<?= \App\Helpers\Helper::escape($topbarSearchPlaceholder) ?>"
          aria-label="<?= \App\Helpers\Helper::escape($topbarSearchPlaceholder) ?>"
        >
      </form>
      <?php endif; ?>
    </div>

      <div class="topbar-right">
      <?php if (is_array($topbarQuickAction)): ?>
        <a href="<?= \App\Helpers\Helper::url((string) ($topbarQuickAction['url'] ?? '#')) ?>" class="btn btn-primary btn-sm topbar-quick-action d-none d-sm-inline-flex">
          <i class="bi <?= \App\Helpers\Helper::escape((string) ($topbarQuickAction['icon'] ?? 'bi-lightning-charge')) ?>"></i>
          <?= \App\Helpers\Helper::escape((string) ($topbarQuickAction['label'] ?? 'Action')) ?>
        </a>
      <?php endif; ?>

      <div class="topbar-chip d-none d-lg-inline-flex" aria-label="Current date and time">
        <i class="bi bi-calendar3"></i>
        <span data-dashboard-datetime="full"><?= \App\Helpers\Helper::escape(date('D, d M Y g:i A')) ?></span>
      </div>

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
          <?php if ($dashboardRole === 'admin'): ?>
            <li>
              <a href="<?= \App\Helpers\Helper::url('/admin/profile') ?>" class="dropdown-item">
                <i class="bi bi-person me-2"></i>
                Profile
              </a>
            </li>
            <li>
              <a href="<?= \App\Helpers\Helper::url('/admin/profile') ?>" class="dropdown-item">
                <i class="bi bi-gear me-2"></i>
                Settings
              </a>
            </li>
          <?php elseif ($dashboardRole === 'doctor'): ?>
            <li>
              <a href="<?= \App\Helpers\Helper::url('/doctor/profile') ?>" class="dropdown-item">
                <i class="bi bi-person me-2"></i>
                Profile
              </a>
            </li>
            <li>
              <a href="<?= \App\Helpers\Helper::url('/doctor/profile/edit') ?>" class="dropdown-item">
                <i class="bi bi-gear me-2"></i>
                Settings
              </a>
            </li>
          <?php elseif ($dashboardRole === 'patient'): ?>
            <li>
              <a href="<?= \App\Helpers\Helper::url('/patient/profile') ?>" class="dropdown-item">
                <i class="bi bi-person me-2"></i>
                Profile
              </a>
            </li>
            <li>
              <a href="<?= \App\Helpers\Helper::url('/patient/profile/edit') ?>" class="dropdown-item">
                <i class="bi bi-gear me-2"></i>
                Settings
              </a>
            </li>
          <?php endif; ?>
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
