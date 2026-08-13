<?php

$profile = $profile ?? [];
$errors = $errors ?? [];
$fieldErrors = $fieldErrors ?? [];
$statusMessage = $statusMessage ?? null;
$csrfToken = $csrfToken ?? '';
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-page-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-start align-items-lg-center gap-3 mb-0">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/patient/dashboard') ?>">Dashboard</a></li>
        <li class="active">Edit Profile</li>
      </ol>
      <span class="section-badge mb-3">
        <i class="bi bi-camera"></i>
        Patient Profile Picture
      </span>
      <h2 class="ux-page-header__title h4 mb-2">Update your profile picture</h2>
      <p class="ux-page-header__subtitle text-muted mb-0">Select a professional image and upload it securely. Your avatar updates across the dashboard after saving.</p>
    </div>
    <div class="ux-page-header__right d-flex flex-wrap gap-2">
      <a href="<?= \App\Helpers\Helper::url('/patient/profile') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-person-circle me-2"></i>
        View Profile
      </a>
      <a href="<?= \App\Helpers\Helper::url('/patient/dashboard') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-arrow-left me-2"></i>
        Back to Dashboard
      </a>
    </div>
  </div>
</section>

<div class="row g-4">
  <div class="col-lg-7">
    <?php if ($errors !== []): ?>
      <div class="mb-4">
        <?php
        $statusMessage = null;
        require __DIR__ . '/../../partials/shared/alerts.php';
        ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= \App\Helpers\Helper::url('/patient/profile/edit') ?>" enctype="multipart/form-data" class="needs-validation" novalidate>
      <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken) ?>">

      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
              <h3 class="h5 mb-1">Profile Picture</h3>
              <p class="text-muted mb-0">The final saved image appears immediately across your authenticated dashboard experience.</p>
            </div>
            <span class="ux-chip ux-badge--dotless ux-badge--neutral">
              <i class="bi bi-person-circle"></i>
              <span>Patient account</span>
            </span>
          </div>

          <div class="profile-photo-editor-card">
            <div class="d-flex align-items-center gap-3 flex-wrap">
              <?php
              $avatarPath = $profile['profile_photo_path'] ?? null;
              $fullName = $profile['full_name'] ?? 'Patient';
              $avatarClass = 'user-avatar user-avatar--xl';
              require __DIR__ . '/../../partials/shared/user_avatar.php';
              ?>
              <div>
                <strong class="d-block">Current profile picture</strong>
                <p class="text-muted small mb-0">Use a clear headshot or professional profile image. The updated avatar will appear across your dashboard after saving.</p>
              </div>
            </div>

            <div class="mt-3">
              <label for="patient_profile_photo" class="form-label">Change Profile Picture <span class="text-muted">(JPG/JPEG/PNG/WEBP, max 5MB)</span></label>
              <input
                type="file"
                class="form-control <?= isset($fieldErrors['profile_photo']) ? 'is-invalid' : '' ?>"
                id="patient_profile_photo"
                name="profile_photo"
                accept="image/png,image/jpeg,image/webp"
                required
              >
              <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['profile_photo'] ?? 'Choose a valid profile picture to upload.') ?></div>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-save me-2"></i>
              Save Profile Picture
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <h3 class="h5 mb-3">Profile Rules</h3>
        <div class="admin-foundation-list">
          <div class="admin-foundation-item">
            <i class="bi bi-shield-lock"></i>
            <span>Profile picture updates continue using the existing authenticated patient session and CSRF protection.</span>
          </div>
          <div class="admin-foundation-item">
            <i class="bi bi-image"></i>
            <span>Only JPG, JPEG, PNG, and WEBP files up to 5 MB are accepted.</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
