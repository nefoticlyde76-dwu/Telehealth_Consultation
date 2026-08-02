<?php

$profile = $profile ?? [];
$errors = $errors ?? [];
$fieldErrors = $fieldErrors ?? [];
$statusMessage = $statusMessage ?? null;
$csrfToken = $csrfToken ?? '';
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
    <div>
      <span class="section-badge mb-3">
        <i class="bi bi-camera"></i>
        Patient Profile Picture
      </span>
      <h2 class="h4 mb-2">Crop and update your profile picture</h2>
      <p class="text-muted mb-0">Select an image, crop it to a perfect square, and save the optimized version only.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="<?= \App\Helpers\Helper::url('/patient/profile') ?>" class="btn btn-outline-primary rounded-pill px-4">
        <i class="bi bi-person-circle me-2"></i>
        View Profile
      </a>
      <a href="<?= \App\Helpers\Helper::url('/patient/dashboard') ?>" class="btn btn-outline-primary rounded-pill px-4">
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

    <form method="POST" action="<?= \App\Helpers\Helper::url('/patient/profile/edit') ?>" enctype="multipart/form-data" class="needs-validation" novalidate data-profile-photo-form>
      <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken) ?>">

      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
              <h3 class="h5 mb-1">Profile Picture</h3>
              <p class="text-muted mb-0">The final saved image appears immediately across your authenticated dashboard experience.</p>
            </div>
            <span class="badge badge-soft-info rounded-pill px-3 py-2">Patient account</span>
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
                <p class="text-muted small mb-0">Use a clear headshot or professional profile image. The saved output is limited to 300 x 300 pixels.</p>
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
                data-profile-crop-input
                data-profile-crop-label="patient profile picture"
                required
              >
              <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['profile_photo'] ?? 'Choose an image to crop before saving.') ?></div>
              <div class="profile-crop-feedback mt-2 d-none" data-profile-crop-feedback></div>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="submit" class="btn btn-primary rounded-pill px-4" data-profile-submit-button>
              <span class="button-label">
                <i class="bi bi-save me-2"></i>
                Save Profile Picture
              </span>
              <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
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
            <i class="bi bi-crop"></i>
            <span>The cropper produces a 1:1 square image so only the processed version is saved.</span>
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
