<?php

use App\Helpers\Helper;

$profile = is_array($profile ?? null) ? $profile : [];
$formData = is_array($formData ?? null) ? $formData : [];
$errors = is_array($errors ?? null) ? $errors : [];
$fieldErrors = is_array($fieldErrors ?? null) ? $fieldErrors : [];
$passwordErrors = is_array($passwordErrors ?? null) ? $passwordErrors : [];
$passwordFieldErrors = is_array($passwordFieldErrors ?? null) ? $passwordFieldErrors : [];
$statusMessage = $statusMessage ?? null;
$csrfToken = (string) ($csrfToken ?? '');

require __DIR__ . '/../../partials/profile/_helpers.php';

$userName = (string) ($profile['full_name'] ?? $formData['full_name'] ?? 'Administrator');
$roleLabel = user_profile_role_label('admin');

ob_start();
?>
<form method="POST" action="<?= Helper::url('/admin/profile') ?>" enctype="multipart/form-data" class="needs-validation" novalidate>
  <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
  <input type="hidden" name="form_action" value="profile">

  <h3 class="user-profile-card__title mb-2">Profile information</h3>
  <p class="user-profile-card__lede">Keep your administrator identity, email address, and employee details accurate.</p>

  <div class="profile-photo-editor-card mb-3">
    <label for="admin_profile_photo" class="form-label">Change profile picture <span class="text-muted">(JPG/JPEG/PNG/WEBP, max 5MB)</span></label>
    <input
      type="file"
      class="form-control <?= isset($fieldErrors['profile_photo']) ? 'is-invalid' : '' ?>"
      id="admin_profile_photo"
      name="profile_photo"
      accept="image/png,image/jpeg,image/webp"
    >
    <div class="invalid-feedback"><?= Helper::escape($fieldErrors['profile_photo'] ?? 'Choose a valid profile picture to upload.') ?></div>
  </div>

  <div class="row g-3">
    <div class="col-md-6">
      <label for="full_name" class="form-label">Full Name</label>
      <input
        type="text"
        class="form-control <?= isset($fieldErrors['full_name']) ? 'is-invalid' : '' ?>"
        id="full_name"
        name="full_name"
        value="<?= Helper::escape((string) ($formData['full_name'] ?? '')) ?>"
        maxlength="255"
        autocomplete="name"
        required
      >
      <div class="invalid-feedback"><?= Helper::escape($fieldErrors['full_name'] ?? 'Full name is required.') ?></div>
    </div>

    <div class="col-md-6">
      <label for="email" class="form-label">Email Address</label>
      <input
        type="email"
        class="form-control <?= isset($fieldErrors['email']) ? 'is-invalid' : '' ?>"
        id="email"
        name="email"
        value="<?= Helper::escape((string) ($formData['email'] ?? '')) ?>"
        maxlength="255"
        autocomplete="email"
        required
      >
      <div class="invalid-feedback"><?= Helper::escape($fieldErrors['email'] ?? 'Please provide a valid email address.') ?></div>
    </div>

    <div class="col-md-6">
      <label for="employee_id" class="form-label">Employee ID</label>
      <input
        type="text"
        class="form-control <?= isset($fieldErrors['employee_id']) ? 'is-invalid' : '' ?>"
        id="employee_id"
        name="employee_id"
        value="<?= Helper::escape((string) ($formData['employee_id'] ?? '')) ?>"
        maxlength="100"
        autocomplete="off"
        required
      >
      <div class="invalid-feedback"><?= Helper::escape($fieldErrors['employee_id'] ?? 'Employee ID is required.') ?></div>
    </div>
  </div>

  <div class="user-profile-form-actions">
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="bi bi-save me-2"></i>
      Update Profile
    </button>
  </div>
</form>
<?php
$mainExtraHtml = ob_get_clean();
$profileErrors = $errors;
$flashStatus = $statusMessage;

ob_start();
?>
<?php if ($passwordErrors !== []): ?>
  <?php
  $errors = $passwordErrors;
  $statusMessage = null;
  require __DIR__ . '/../../partials/shared/alerts.php';
  ?>
<?php endif; ?>

<form method="POST" action="<?= Helper::url('/admin/profile') ?>" class="needs-validation" novalidate>
  <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
  <input type="hidden" name="form_action" value="password">

  <h3 class="user-profile-card__title">Change password</h3>
  <p class="user-profile-card__lede">Confirm your current password, then provide a strong replacement credential.</p>

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
      <div class="invalid-feedback"><?= Helper::escape($passwordFieldErrors['current_password'] ?? 'Current password is required.') ?></div>
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
      <div class="invalid-feedback"><?= Helper::escape($passwordFieldErrors['password'] ?? 'Please provide a strong new password.') ?></div>
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
      <div class="invalid-feedback"><?= Helper::escape($passwordFieldErrors['confirm_password'] ?? 'Please confirm the password.') ?></div>
    </div>
  </div>

  <div class="user-profile-form-actions">
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="bi bi-key-fill me-2"></i>
      Change Password
    </button>
  </div>
</form>
<?php
$manageExtraHtml = ob_get_clean();
$errors = $profileErrors;
$statusMessage = $flashStatus;

$profilePage = [
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Profile'],
    ],
    'title' => $userName,
    'subtitle' => 'Update your profile information and change your password without affecting other administrative workflows.',
    'back' => ['label' => 'Back to dashboard', 'url' => '/admin/dashboard'],
    'photo' => $profile['profile_photo_path'] ?? null,
    'photo_edit_url' => '/admin/profile#admin_profile_photo',
    'name' => $userName,
    'email' => (string) ($profile['email'] ?? $formData['email'] ?? ''),
    'role_label' => $roleLabel,
    'status' => (string) ($profile['status'] ?? 'active'),
    'account_tiles' => user_profile_account_tiles($profile, $roleLabel),
    'actions' => [
        [
            'key' => 'security',
            'label' => 'Security',
            'url' => '/account/security',
            'method' => 'GET',
            'tone' => 'neutral',
        ],
    ],
    'main_extra_html' => $mainExtraHtml,
    'manage_extra_html' => $manageExtraHtml,
    'role_tiles' => [
        [
            'label' => 'Employee ID',
            'value' => user_profile_value($formData['employee_id'] ?? $profile['employee_id'] ?? null),
            'icon' => 'bi-person-badge',
        ],
    ],
    'role_permissions' => [
        'show' => true,
        'text' => 'Access level and permissions granted to this role.',
    ],
    'role_extra_html' => '<p class="user-profile-card__lede mb-0 mt-3">Email addresses and employee IDs remain unique across administrator accounts. Password changes require the current password.</p>',
    'protected_history' => ['show' => false],
];

require __DIR__ . '/../../partials/profile/layout.php';
