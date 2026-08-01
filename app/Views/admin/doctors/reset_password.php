<?php

$doctor = $doctor ?? [];
$errors = $errors ?? [];
$fieldErrors = $fieldErrors ?? [];
$csrfToken = $csrfToken ?? '';
$statusMessage = $statusMessage ?? null;
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
    <div>
      <span class="section-badge mb-3">
        <i class="bi bi-key-fill"></i>
        Doctor Password Management
      </span>
      <h2 class="h4 mb-2">Reset doctor password</h2>
      <p class="text-muted mb-0">Issue a secure replacement password for the selected clinician account.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="<?= \App\Helpers\Helper::url('/admin/doctors/' . (int) ($doctor['id'] ?? 0) . '/edit') ?>" class="btn btn-outline-secondary rounded-pill px-4">
        <i class="bi bi-pencil-square me-2"></i>
        Edit Doctor
      </a>
      <a href="<?= \App\Helpers\Helper::url('/admin/doctors') ?>" class="btn btn-outline-primary rounded-pill px-4">
        <i class="bi bi-arrow-left me-2"></i>
        Back to Doctor Accounts
      </a>
    </div>
  </div>
</section>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm rounded-4 h-100">
      <div class="card-body p-4">
        <h3 class="h5 mb-3">Selected Doctor</h3>

        <div class="user-detail-grid">
          <div class="user-detail-item">
            <span class="user-detail-label">Full Name</span>
            <strong><?= \App\Helpers\Helper::escape((string) ($doctor['full_name'] ?? 'Not available')) ?></strong>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Email</span>
            <strong><?= \App\Helpers\Helper::escape((string) ($doctor['email'] ?? 'Not available')) ?></strong>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Professional Title</span>
            <strong><?= \App\Helpers\Helper::escape((string) ($doctor['professional_title'] ?? 'Not available')) ?></strong>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Specialization</span>
            <strong><?= \App\Helpers\Helper::escape((string) ($doctor['specialization'] ?? 'Not available')) ?></strong>
          </div>
        </div>

        <div class="admin-foundation-list mt-4">
          <div class="admin-foundation-item">
            <i class="bi bi-shield-lock"></i>
            <span>Only administrators can reset clinician passwords.</span>
          </div>
          <div class="admin-foundation-item">
            <i class="bi bi-key"></i>
            <span>The new password is stored as a secure password hash.</span>
          </div>
          <div class="admin-foundation-item">
            <i class="bi bi-person-check"></i>
            <span>Password reset does not change the doctor role, profile, or account status.</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4 h-100">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
          <div>
            <h3 class="h5 mb-1">New Password</h3>
            <p class="text-muted mb-0">Provide a strong replacement password and confirm it before saving.</p>
          </div>
          <span class="badge badge-soft-info rounded-pill px-3 py-2">Secure reset flow</span>
        </div>

        <form method="POST" action="<?= \App\Helpers\Helper::url('/admin/doctors/' . (int) ($doctor['id'] ?? 0) . '/reset-password') ?>" class="needs-validation" novalidate>
          <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken) ?>">

          <div class="row g-3">
            <div class="col-md-6">
              <label for="password" class="form-label">New Password</label>
              <input
                type="password"
                class="form-control <?= isset($fieldErrors['password']) ? 'is-invalid' : '' ?>"
                id="password"
                name="password"
                data-password-strength
                required
              >
              <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['password'] ?? 'Please provide a strong new password.') ?></div>
              <div class="password-strength mt-2" aria-live="polite">
                <div class="password-strength-bar" data-password-strength-bar></div>
              </div>
              <p class="small text-muted mt-2 mb-0">Use at least 8 characters with uppercase, lowercase, number, and symbol.</p>
            </div>

            <div class="col-md-6">
              <label for="confirm_password" class="form-label">Confirm Password</label>
              <input
                type="password"
                class="form-control <?= isset($fieldErrors['confirm_password']) ? 'is-invalid' : '' ?>"
                id="confirm_password"
                name="confirm_password"
                data-confirm-password="#password"
                required
              >
              <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['confirm_password'] ?? 'Please confirm the password.') ?></div>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="<?= \App\Helpers\Helper::url('/admin/doctors') ?>" class="btn btn-outline-primary rounded-pill px-4">Cancel</a>
            <button type="submit" class="btn btn-primary rounded-pill px-4">
              <i class="bi bi-key-fill me-2"></i>
              Reset Password
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
