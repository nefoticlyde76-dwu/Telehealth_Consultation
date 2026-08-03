<?php

$profile = $profile ?? [];
$formData = $formData ?? [];
$errors = $errors ?? [];
$fieldErrors = $fieldErrors ?? [];
$passwordErrors = $passwordErrors ?? [];
$passwordFieldErrors = $passwordFieldErrors ?? [];
$statusMessage = $statusMessage ?? null;
$csrfToken = $csrfToken ?? '';
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
    <div>
      <span class="section-badge mb-3">
        <i class="bi bi-person-gear"></i>
        Doctor Profile Management
      </span>
      <h2 class="h4 mb-2">Edit your clinician profile</h2>
      <p class="text-muted mb-0">Update your phone number, specialization, profile photo, signature, and password securely.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="<?= \App\Helpers\Helper::url('/doctor/profile') ?>" class="btn btn-outline-primary rounded-pill px-4">
        <i class="bi bi-person-vcard me-2"></i>
        View Profile
      </a>
      <a href="<?= \App\Helpers\Helper::url('/doctor/dashboard') ?>" class="btn btn-outline-primary rounded-pill px-4">
        <i class="bi bi-arrow-left me-2"></i>
        Back to Dashboard
      </a>
    </div>
  </div>
</section>

<div class="row g-4">
  <div class="col-xl-7">
    <?php if ($errors !== []): ?>
      <div class="mb-4">
        <?php
        $statusMessage = null;
        require __DIR__ . '/../../partials/shared/alerts.php';
        ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= \App\Helpers\Helper::url('/doctor/profile/edit') ?>" enctype="multipart/form-data" class="needs-validation" novalidate>
      <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken) ?>">
      <input type="hidden" name="form_action" value="profile">

      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
              <h3 class="h5 mb-1">Profile Information</h3>
              <p class="text-muted mb-0">Your updates apply to your linked doctor record and appear across clinician workflows.</p>
            </div>
            <span class="badge badge-soft-info rounded-pill px-3 py-2">Doctor role only</span>
          </div>

          <div class="row g-3">
            <div class="col-12">
              <div class="profile-photo-editor-card">
                <div>
                  <span class="user-detail-label mb-2">Current Profile Picture</span>
                  <div class="d-flex align-items-center gap-3 flex-wrap">
                    <?php
                    $avatarPath = $profile['profile_photo_path'] ?? null;
                    $fullName = $profile['full_name'] ?? 'Doctor';
                    $avatarClass = 'user-avatar user-avatar--xl';
                    require __DIR__ . '/../../partials/shared/user_avatar.php';
                    ?>
                    <div>
                      <strong class="d-block">Clinician avatar</strong>
                      <p class="text-muted small mb-0">Upload a professional profile picture. The updated avatar appears across your dashboard after saving.</p>
                    </div>
                  </div>
                </div>

                <div class="mt-3">
                  <label for="profile_photo" class="form-label">Change Profile Picture <span class="text-muted">(JPG/JPEG/PNG/WEBP, max 5MB)</span></label>
                  <input
                    type="file"
                    class="form-control <?= isset($fieldErrors['profile_photo']) ? 'is-invalid' : '' ?>"
                    id="profile_photo"
                    name="profile_photo"
                    accept="image/png,image/jpeg,image/webp"
                  >
                  <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['profile_photo'] ?? 'Choose a valid profile picture to upload.') ?></div>
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Full Name</label>
              <input type="text" class="form-control" value="<?= \App\Helpers\Helper::escape((string) ($profile['full_name'] ?? '')) ?>" disabled>
            </div>

            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="text" class="form-control" value="<?= \App\Helpers\Helper::escape((string) ($profile['email'] ?? '')) ?>" disabled>
            </div>

            <div class="col-md-6">
              <label for="phone" class="form-label">Phone Number</label>
              <input
                type="text"
                class="form-control <?= isset($fieldErrors['phone']) ? 'is-invalid' : '' ?>"
                id="phone"
                name="phone"
                value="<?= \App\Helpers\Helper::escape((string) ($formData['phone'] ?? '')) ?>"
                maxlength="30"
                autocomplete="tel"
                required
              >
              <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['phone'] ?? 'Phone number is required.') ?></div>
            </div>

            <div class="col-md-6">
              <label for="specialization" class="form-label">Specialization</label>
              <input
                type="text"
                class="form-control <?= isset($fieldErrors['specialization']) ? 'is-invalid' : '' ?>"
                id="specialization"
                name="specialization"
                value="<?= \App\Helpers\Helper::escape((string) ($formData['specialization'] ?? '')) ?>"
                maxlength="255"
                required
              >
              <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['specialization'] ?? 'Specialization is required.') ?></div>
            </div>

            <div class="col-md-6">
              <label for="signature" class="form-label">Digital Signature <span class="text-muted">(PNG/JPG, max 2MB)</span></label>
              <input
                type="file"
                class="form-control <?= isset($fieldErrors['signature']) ? 'is-invalid' : '' ?>"
                id="signature"
                name="signature"
                accept="image/png,image/jpeg"
              >
              <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['signature'] ?? 'Choose a valid signature image to upload.') ?></div>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="submit" class="btn btn-primary rounded-pill px-4">
              <i class="bi bi-save me-2"></i>
              Save Profile Updates
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>

  <div class="col-xl-5">
    <?php if ($passwordErrors !== []): ?>
      <div class="mb-4">
        <?php
        $errors = $passwordErrors;
        $statusMessage = null;
        require __DIR__ . '/../../partials/shared/alerts.php';
        ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= \App\Helpers\Helper::url('/doctor/profile/edit') ?>" class="needs-validation" novalidate>
      <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken) ?>">
      <input type="hidden" name="form_action" value="password">

      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
          <h3 class="h5 mb-3">Change Password</h3>
          <p class="text-muted mb-4">Confirm your current password, then provide a strong replacement credential.</p>

          <div class="row g-3">
            <div class="col-12">
              <label for="current_password" class="form-label">Current Password</label>
              <input
                type="password"
                class="form-control <?= isset($passwordFieldErrors['current_password']) ? 'is-invalid' : '' ?>"
                id="current_password"
                name="current_password"
                autocomplete="current-password"
                required
              >
              <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($passwordFieldErrors['current_password'] ?? 'Current password is required.') ?></div>
            </div>

            <div class="col-12">
              <label for="password" class="form-label">New Password</label>
              <input
                type="password"
                class="form-control <?= isset($passwordFieldErrors['password']) ? 'is-invalid' : '' ?>"
                id="password"
                name="password"
                data-password-strength
                autocomplete="new-password"
                required
              >
              <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($passwordFieldErrors['password'] ?? 'Please provide a strong new password.') ?></div>
              <div class="password-strength mt-2" aria-live="polite">
                <div class="password-strength-bar" data-password-strength-bar></div>
              </div>
              <p class="small text-muted mt-2 mb-0">Use at least 8 characters with uppercase, lowercase, number, and symbol.</p>
            </div>

            <div class="col-12">
              <label for="confirm_password" class="form-label">Confirm Password</label>
              <input
                type="password"
                class="form-control <?= isset($passwordFieldErrors['confirm_password']) ? 'is-invalid' : '' ?>"
                id="confirm_password"
                name="confirm_password"
                data-confirm-password="#password"
                autocomplete="new-password"
                required
              >
              <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($passwordFieldErrors['confirm_password'] ?? 'Please confirm the password.') ?></div>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="submit" class="btn btn-primary rounded-pill px-4">
              <i class="bi bi-key-fill me-2"></i>
              Change Password
            </button>
          </div>
        </div>
      </div>
    </form>

    <div class="card border-0 shadow-sm rounded-4 mt-4">
      <div class="card-body p-4">
        <h3 class="h5 mb-3">Security Notes</h3>
        <div class="admin-foundation-list">
          <div class="admin-foundation-item">
            <i class="bi bi-shield-lock"></i>
            <span>Profile updates require a verified CSRF token and continue using the existing authenticated session.</span>
          </div>
          <div class="admin-foundation-item">
            <i class="bi bi-upload"></i>
            <span>Uploads are restricted to images and stored under clinician-specific directories.</span>
          </div>
          <div class="admin-foundation-item">
            <i class="bi bi-key"></i>
            <span>Password changes require the current password and store the new value as a secure password hash.</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
