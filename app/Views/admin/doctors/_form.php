<?php

$formData = $formData ?? [];
$fieldErrors = $fieldErrors ?? [];
$genderOptions = $genderOptions ?? [];
$statusOptions = $statusOptions ?? [];
$showPasswordFields = $showPasswordFields ?? false;
?>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4 h-100">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
          <div>
            <h3 class="h5 mb-1">Doctor Account Information</h3>
            <p class="text-muted mb-0">Capture the clinician identity, contact details, and professional profile required for secure access.</p>
          </div>
          <span class="badge badge-soft-info rounded-pill px-3 py-2">Administrator-managed</span>
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
              autocomplete="name"
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
              autocomplete="email"
              required
            >
            <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['email'] ?? 'Please provide a valid email address.') ?></div>
          </div>

          <div class="col-md-6">
            <label for="phone" class="form-label">Phone</label>
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
            <label for="gender" class="form-label">Gender</label>
            <select class="form-select <?= isset($fieldErrors['gender']) ? 'is-invalid' : '' ?>" id="gender" name="gender" required>
              <option value="">Select gender</option>
              <?php foreach ($genderOptions as $genderOption): ?>
                <option value="<?= \App\Helpers\Helper::escape($genderOption) ?>" <?= ($formData['gender'] ?? '') === $genderOption ? 'selected' : '' ?>>
                  <?= \App\Helpers\Helper::escape(ucfirst($genderOption)) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['gender'] ?? 'Please select a gender option.') ?></div>
          </div>

          <div class="col-md-6">
            <label for="professional_title" class="form-label">Professional Title</label>
            <input
              type="text"
              class="form-control <?= isset($fieldErrors['professional_title']) ? 'is-invalid' : '' ?>"
              id="professional_title"
              name="professional_title"
              value="<?= \App\Helpers\Helper::escape((string) ($formData['professional_title'] ?? '')) ?>"
              maxlength="150"
              placeholder="e.g. Dr., Consultant Physician"
              required
            >
            <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['professional_title'] ?? 'Professional title is required.') ?></div>
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
              placeholder="e.g. General Medicine"
              required
            >
            <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['specialization'] ?? 'Specialization is required.') ?></div>
          </div>

          <div class="col-md-6">
            <label for="employee_id" class="form-label">Employee ID <span class="text-muted">(Optional)</span></label>
            <input
              type="text"
              class="form-control <?= isset($fieldErrors['employee_id']) ? 'is-invalid' : '' ?>"
              id="employee_id"
              name="employee_id"
              value="<?= \App\Helpers\Helper::escape((string) ($formData['employee_id'] ?? '')) ?>"
              maxlength="100"
              autocomplete="off"
            >
            <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['employee_id'] ?? 'Please provide a valid employee ID or leave it blank.') ?></div>
          </div>

          <div class="col-md-6">
            <label for="status" class="form-label">Account Status</label>
            <select class="form-select <?= isset($fieldErrors['status']) ? 'is-invalid' : '' ?>" id="status" name="status" required>
              <?php foreach ($statusOptions as $statusOption): ?>
                <option value="<?= \App\Helpers\Helper::escape($statusOption) ?>" <?= ($formData['status'] ?? 'active') === $statusOption ? 'selected' : '' ?>>
                  <?= \App\Helpers\Helper::escape(ucfirst($statusOption)) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['status'] ?? 'Please select an account status.') ?></div>
          </div>

          <?php if ($showPasswordFields): ?>
            <div class="col-md-6">
              <label for="password" class="form-label">Initial Password</label>
              <input
                type="password"
                class="form-control <?= isset($fieldErrors['password']) ? 'is-invalid' : '' ?>"
                id="password"
                name="password"
                data-password-strength
                autocomplete="new-password"
                required
              >
              <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['password'] ?? 'Please provide a strong initial password.') ?></div>
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
                autocomplete="new-password"
                required
              >
              <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['confirm_password'] ?? 'Please confirm the password.') ?></div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm rounded-4 h-100 doctor-side-card">
      <div class="card-body p-4">
        <h3 class="h5 mb-3">Doctor Account Rules</h3>
        <div class="admin-foundation-list">
          <div class="admin-foundation-item">
            <i class="bi bi-shield-lock"></i>
            <span>Doctors cannot self-register. Every clinician account is created by an administrator.</span>
          </div>
          <div class="admin-foundation-item">
            <i class="bi bi-link-45deg"></i>
            <span>Submitting this form creates or updates the linked user and doctor records together.</span>
          </div>
          <div class="admin-foundation-item">
            <i class="bi bi-key"></i>
            <span>Passwords are stored using password hashing and are never saved in plain text.</span>
          </div>
          <div class="admin-foundation-item">
            <i class="bi bi-person-check"></i>
            <span>Status controls determine whether the doctor can authenticate through the shared login page.</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
