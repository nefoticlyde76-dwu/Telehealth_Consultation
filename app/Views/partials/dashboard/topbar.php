<header class="dashboard-topbar">
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
    <div class="d-flex align-items-start gap-3">
      <button class="btn btn-outline-primary d-lg-none rounded-circle topbar-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#dashboardSidebar" aria-controls="dashboardSidebar" aria-label="Open dashboard navigation">
        <i class="bi bi-list"></i>
      </button>

      <div>
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-2">
            <li class="breadcrumb-item"><a href="<?= \App\Helpers\Helper::url('/') ?>">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= \App\Helpers\Helper::escape($dashboardRoleLabel ?? 'Dashboard') ?></li>
          </ol>
        </nav>
        <h1 class="h3 mb-1"><?= \App\Helpers\Helper::escape($dashboardTitle ?? 'Dashboard') ?></h1>
        <p class="text-muted mb-0"><?= \App\Helpers\Helper::escape($dashboardDescription ?? 'Overview') ?></p>
      </div>
    </div>

    <div class="d-flex align-items-center gap-3">
      <div class="topbar-chip" aria-label="Notifications placeholder">
        <i class="bi bi-bell"></i>
        <span>Notifications</span>
        <span class="badge rounded-pill badge-soft-info">0</span>
      </div>

      <div class="dropdown">
        <button class="btn profile-trigger dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
          <?php
          $avatarPath = $user->profile_photo_path ?? null;
          $fullName = $user->full_name ?? 'User';
          $avatarClass = 'user-avatar user-avatar--xs';
          require __DIR__ . '/../shared/user_avatar.php';
          ?>
          <span class="text-start">
            <strong class="d-block"><?= \App\Helpers\Helper::escape($user->full_name ?? 'User') ?></strong>
            <small class="text-muted"><?= \App\Helpers\Helper::escape(ucfirst($dashboardRole ?? 'account')) ?></small>
          </span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-4 p-2">
          <li><span class="dropdown-item-text text-muted small"><?= \App\Helpers\Helper::escape($user->email ?? '') ?></span></li>
          <li><hr class="dropdown-divider"></li>
          <?php if (($dashboardRole ?? '') === 'admin'): ?>
            <li>
              <a href="<?= \App\Helpers\Helper::url('/admin/profile') ?>" class="dropdown-item rounded-3">
                <i class="bi bi-person-gear me-2"></i>
                Profile Settings
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
          <?php elseif (($dashboardRole ?? '') === 'doctor'): ?>
            <li>
              <a href="<?= \App\Helpers\Helper::url('/doctor/profile') ?>" class="dropdown-item rounded-3">
                <i class="bi bi-person-vcard me-2"></i>
                My Profile
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
          <?php elseif (($dashboardRole ?? '') === 'patient'): ?>
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
</header>
