<?php

$profile = $profile ?? [];
$statusMessage = $statusMessage ?? null;
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-page-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-start align-items-lg-center gap-3 mb-0">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/patient/dashboard') ?>">Dashboard</a></li>
        <li class="active">Profile</li>
      </ol>
      <span class="section-badge mb-3">
        <i class="bi bi-person-circle"></i>
        Patient Profile
      </span>
      <h2 class="ux-page-header__title h4 mb-2">View your patient profile</h2>
      <p class="ux-page-header__subtitle text-muted mb-0">Review your account details and how your profile picture appears across the system.</p>
    </div>
    <div class="ux-page-header__right d-flex flex-wrap gap-2">
      <a href="<?= \App\Helpers\Helper::url('/patient/profile/edit') ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-camera me-2"></i>
        Change Profile Picture
      </a>
      <a href="<?= \App\Helpers\Helper::url('/patient/dashboard') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-arrow-left me-2"></i>
        Back to Dashboard
      </a>
    </div>
  </div>
</section>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm rounded-4 h-100">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
          <h3 class="h5 mb-0">Profile Picture</h3>
          <span class="ux-chip ux-badge--dotless ux-badge--neutral">
            <i class="bi bi-person-bounding-box"></i>
            <span>Live avatar</span>
          </span>
        </div>

        <div class="doctor-profile-photo-shell">
          <?php
          $avatarPath = $profile['profile_photo_path'] ?? null;
          $fullName = $profile['full_name'] ?? 'Patient';
          $avatarClass = 'user-avatar user-avatar--profile';
          require __DIR__ . '/../../partials/shared/user_avatar.php';
          ?>
        </div>

        <div class="admin-foundation-list mt-4">
          <div class="admin-foundation-item">
            <i class="bi bi-crop"></i>
            <span>Your saved profile picture is stored securely and displayed consistently across your dashboard experience.</span>
          </div>
          <div class="admin-foundation-item">
            <i class="bi bi-phone"></i>
            <span>The updated avatar appears in the sidebar, top navigation, and dashboard welcome area after save.</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4 h-100">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
          <div>
            <h3 class="h5 mb-1">Account Details</h3>
            <p class="text-muted mb-0">These details remain linked to your secure patient account.</p>
          </div>
          <span class="ux-badge ux-badge--neutral"><?= \App\Helpers\Helper::escape(ucfirst((string) ($profile['status'] ?? 'active'))) ?></span>
        </div>

        <div class="user-detail-grid">
          <div class="user-detail-item">
            <span class="user-detail-label">Full Name</span>
            <strong><?= \App\Helpers\Helper::escape((string) ($profile['full_name'] ?? 'Not available')) ?></strong>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Email</span>
            <strong><?= \App\Helpers\Helper::escape((string) ($profile['email'] ?? 'Not available')) ?></strong>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Date of Birth</span>
            <strong><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate($profile['dob'] ?? null, 'd M Y', 'Not provided')) ?></strong>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Gender</span>
            <strong><?= \App\Helpers\Helper::escape(ucfirst((string) ($profile['gender'] ?? 'Not provided'))) ?></strong>
          </div>
          <div class="user-detail-item user-detail-item-full">
            <span class="user-detail-label">Address</span>
            <strong><?= \App\Helpers\Helper::escape((string) ($profile['address'] ?? 'Not provided')) ?></strong>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
