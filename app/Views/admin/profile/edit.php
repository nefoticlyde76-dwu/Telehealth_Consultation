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
  <?php if (is_array($statusMessage) && !empty($statusMessage['message'])): ?>
    <div class="alert alert-<?= \App\Helpers\Helper::escape($statusMessage['type'] ?? 'info') ?> border-0 shadow-sm rounded-4 mb-4" role="alert">
      <div class="d-flex align-items-center gap-3">
        <i class="bi bi-info-circle fs-4"></i>
        <div><?= \App\Helpers\Helper::escape($statusMessage['message']) ?></div>
      </div>
    </div>
  <?php endif; ?>

  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
    <div>
      <span class="section-badge mb-3">
        <i class="bi bi-person-gear"></i>
        Administrator Profile Management
      </span>
      <h2 class="h4 mb-2">Maintain administrator profile</h2>
      <p class="text-muted mb-0">Update your profile information and change your password without affecting other administrative workflows.</p>
    </div>
    <a href="<?= \App\Helpers\Helper::url('/admin/dashboard') ?>" class="btn btn-outline-primary rounded-pill px-4">
      <i class="bi bi-arrow-left me-2"></i>
      Back to Dashboard
    </a>
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

    <form method="POST" action="<?= \App\Helpers\Helper::url('/admin/profile') ?>" class="needs-validation" novalidate>
      <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken) ?>">
      <input type="hidden" name="form_action" value="profile">

      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
              <h3 class="h5 mb-1">Profile Information</h3>
              <p class="text-muted mb-0">Keep your administrator identity, email address, and employee details accurate.</p>
            </div>
            <span class="badge badge-soft-info rounded-pill px-3 py-2">Administrator account</span>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label for="full_name" class="form-label">Full Name</label>
              <input
                type="text"
                class="form-control <?= isset($fieldErrors['full_name']) ? 'is-invalid' : '' ?>"
                id="full_name"
                name="full_name"
                value="<?= \App\Helpers\Helper::escape((string) ($formData['full_name'] ?? '')) ?>"
                maxlength="255"
                required
              >
              <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['full_name'] ?? 'Full name is required.') ?></div>
            </div>

            <div class="col-md-6">
              <label for="email" class="form-label">Email Address</label>
              <input
                type="email"
                class="form-control <?= isset($fieldErrors['email']) ? 'is-invalid' : '' ?>"
                id="email"
                name="email"
                value="<?= \App\Helpers\Helper::escape((string) ($formData['email'] ?? '')) ?>"
                maxlength="255"
                required
              >
              <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['email'] ?? 'Please provide a valid email address.') ?></div>
            </div>

            <div class="col-md-6">
              <label for="employee_id" class="form-label">Employee ID</label>
              <input
                type="text"
                class="form-control <?= isset($fieldErrors['employee_id']) ? 'is-invalid' : '' ?>"
                id="employee_id"
                name="employee_id"
                value="<?= \App\Helpers\Helper::escape((string) ($formData['employee_id'] ?? '')) ?>"
                maxlength="100"
                required
              >
              <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['employee_id'] ?? 'Employee ID is required.') ?></div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Account Status</label>
              <input
                type="text"
                class="form-control"
                value="<?= \App\Helpers\Helper::escape(ucfirst((string) ($profile['status'] ?? 'active'))) ?>"
                disabled
              >
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="submit" class="btn btn-primary rounded-pill px-4">
              <i class="bi bi-save me-2"></i>
              Update Profile
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>

  <div class="col-lg-5">
    <?php if ($passwordErrors !== []): ?>
      <div class="mb-4">
        <?php
        $errors = $passwordErrors;
        $statusMessage = null;
        require __DIR__ . '/../../partials/shared/alerts.php';
        ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= \App\Helpers\Helper::url('/admin/profile') ?>" class="needs-validation" novalidate>
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
        <h3 class="h5 mb-3">Profile Rules</h3>
        <div class="admin-foundation-list">
          <div class="admin-foundation-item">
            <i class="bi bi-shield-lock"></i>
            <span>Administrator profile changes continue using the existing authenticated session and role middleware.</span>
          </div>
          <div class="admin-foundation-item">
            <i class="bi bi-envelope-check"></i>
            <span>Email addresses and employee IDs remain unique across administrator accounts.</span>
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
