<?php

$formData = $formData ?? [];
$fieldErrors = $fieldErrors ?? [];
$genderOptions = $genderOptions ?? [];
$statusOptions = $statusOptions ?? [];
?>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4 h-100">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
          <div>
            <h3 class="h5 mb-1">Patient Account Information</h3>
            <p class="text-muted mb-0">Maintain patient identity, demographic details, and secure access status without changing consultation workflows.</p>
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
            <label for="dob" class="form-label">Date of Birth</label>
            <input
              type="date"
              class="form-control <?= isset($fieldErrors['dob']) ? 'is-invalid' : '' ?>"
              id="dob"
              name="dob"
              value="<?= \App\Helpers\Helper::escape((string) ($formData['dob'] ?? '')) ?>"
            >
            <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['dob'] ?? 'Please provide a valid date of birth.') ?></div>
          </div>

          <div class="col-md-6">
            <label for="gender" class="form-label">Gender</label>
            <select class="form-select <?= isset($fieldErrors['gender']) ? 'is-invalid' : '' ?>" id="gender" name="gender">
              <option value="">Select gender</option>
              <?php foreach ($genderOptions as $genderOption): ?>
                <option value="<?= \App\Helpers\Helper::escape($genderOption) ?>" <?= ($formData['gender'] ?? '') === $genderOption ? 'selected' : '' ?>>
                  <?= \App\Helpers\Helper::escape(ucfirst($genderOption)) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['gender'] ?? 'Please select a valid gender option.') ?></div>
          </div>

          <div class="col-12">
            <label for="address" class="form-label">Address</label>
            <textarea
              class="form-control <?= isset($fieldErrors['address']) ? 'is-invalid' : '' ?>"
              id="address"
              name="address"
              rows="4"
            ><?= \App\Helpers\Helper::escape((string) ($formData['address'] ?? '')) ?></textarea>
            <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['address'] ?? 'Please provide a valid address.') ?></div>
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
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm rounded-4 h-100">
      <div class="card-body p-4">
        <h3 class="h5 mb-3">Patient Management Rules</h3>
        <div class="admin-foundation-list">
          <div class="admin-foundation-item">
            <i class="bi bi-person-lines-fill"></i>
            <span>Patient management focuses on reviewing and maintaining registered patient accounts only.</span>
          </div>
          <div class="admin-foundation-item">
            <i class="bi bi-shield-lock"></i>
            <span>Status changes control whether the patient can sign in through the shared authentication flow.</span>
          </div>
          <div class="admin-foundation-item">
            <i class="bi bi-pencil-square"></i>
            <span>Editing this form updates the linked user and patient records together.</span>
          </div>
          <div class="admin-foundation-item">
            <i class="bi bi-info-circle"></i>
            <span>Consultation requests and clinical availability remain outside today’s scope.</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
