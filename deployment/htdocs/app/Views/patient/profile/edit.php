<?php

$profile = $profile ?? [];
$errors = $errors ?? [];
$fieldErrors = $fieldErrors ?? [];
$statusMessage = $statusMessage ?? null;
$csrfToken = $csrfToken ?? '';
$formData = is_array($formData ?? null) ? $formData : [];
?>

<?php
require __DIR__ . '/../../partials/profile/_helpers.php';
$userName = (string) ($profile['full_name'] ?? 'Patient');
$profilePage = [
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'url' => '/patient/dashboard'],
        ['label' => 'Profile', 'url' => '/patient/profile'],
        ['label' => 'Edit'],
    ],
    'title' => $userName,
    'subtitle' => 'Edit permitted personal details and your profile picture. Clinical records are not managed here.',
    'header_links' => [
        ['label' => 'Security', 'url' => '/account/security'],
    ],
    'back' => ['label' => 'Back to profile', 'url' => '/patient/profile'],
];
?>
<div class="user-profile-page">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>
  <?php require __DIR__ . '/../../partials/profile/header.php'; ?>

<div class="user-profile-layout">
  <div>
    <form method="POST" action="<?= \App\Helpers\Helper::url('/patient/profile/edit') ?>" class="needs-validation mb-4" novalidate>
      <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken) ?>">
      <input type="hidden" name="form_action" value="details">
      <div class="user-profile-card">
          <h3 class="user-profile-card__title">Personal details</h3>
          <div class="row g-3">
            <div class="col-md-6">
              <label for="dob" class="form-label">Date of birth</label>
              <input type="date" class="form-control <?= isset($fieldErrors['dob']) ? 'is-invalid' : '' ?>" id="dob" name="dob" value="<?= \App\Helpers\Helper::escape((string) ($formData['dob'] ?? $profile['dob'] ?? '')) ?>">
              <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['dob'] ?? 'Enter a valid date of birth.') ?></div>
            </div>
            <div class="col-md-6">
              <label for="gender" class="form-label">Gender</label>
              <select class="form-select <?= isset($fieldErrors['gender']) ? 'is-invalid' : '' ?>" id="gender" name="gender">
                <?php $genderValue = (string) ($formData['gender'] ?? $profile['gender'] ?? ''); ?>
                <option value="">Not specified</option>
                <option value="male" <?= $genderValue === 'male' ? 'selected' : '' ?>>Male</option>
                <option value="female" <?= $genderValue === 'female' ? 'selected' : '' ?>>Female</option>
                <option value="other" <?= $genderValue === 'other' ? 'selected' : '' ?>>Other</option>
              </select>
              <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['gender'] ?? 'Select a valid option.') ?></div>
            </div>
            <div class="col-md-6">
              <label for="phone" class="form-label">Phone</label>
              <input type="text" class="form-control <?= isset($fieldErrors['phone']) ? 'is-invalid' : '' ?>" id="phone" name="phone" value="<?= \App\Helpers\Helper::escape((string) ($formData['phone'] ?? $profile['phone'] ?? '')) ?>" maxlength="30">
              <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['phone'] ?? 'Enter a valid phone number.') ?></div>
            </div>
            <div class="col-12">
              <label for="address" class="form-label">Address</label>
              <textarea class="form-control <?= isset($fieldErrors['address']) ? 'is-invalid' : '' ?>" id="address" name="address" rows="3" maxlength="1000"><?= \App\Helpers\Helper::escape((string) ($formData['address'] ?? $profile['address'] ?? '')) ?></textarea>
              <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['address'] ?? 'Address is too long.') ?></div>
            </div>
          </div>
          <div class="user-profile-form-actions">
            <button type="submit" class="btn btn-primary btn-sm">Save details</button>
          </div>
      </div>
    </form>

    <form method="POST" action="<?= \App\Helpers\Helper::url('/patient/profile/edit') ?>" enctype="multipart/form-data" class="needs-validation" novalidate>
      <input type="hidden" name="form_action" value="photo">
      <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken) ?>">

      <div class="user-profile-card h-100">
          <h3 class="user-profile-card__title">Profile picture</h3>
          <p class="user-profile-card__lede">The final saved image appears immediately across your authenticated dashboard experience.</p>

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

          <div class="user-profile-form-actions">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-save me-2"></i>
              Save Profile Picture
            </button>
          </div>
      </div>
    </form>
  </div>

  <div>
    <div class="user-profile-card">
      <h3 class="user-profile-card__title">Profile rules</h3>
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
