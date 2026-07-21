<?php require __DIR__ . '/../partials/public/navbar.php'; ?>

<main class="auth-page py-5">
  <div class="container">
    <div class="row justify-content-center align-items-stretch g-4">
      <div class="col-lg-5">
        <div class="auth-panel h-100">
          <span class="section-badge mb-3">
            <i class="bi bi-person-plus"></i>
            Patient Registration
          </span>
          <h1 class="display-6 fw-bold mb-3">Create a secure patient account in a few guided steps.</h1>
          <p class="text-muted mb-4">
            Registration is designed for patients only and uses layered validation to protect account quality,
            privacy, and long-term maintainability.
          </p>

          <div class="auth-highlight-list">
            <div class="auth-highlight-item">
              <i class="bi bi-check2-circle"></i>
              <div>
                <strong>Server-side validation</strong>
                <span>Validates names, email, password rules, duplicate accounts, and trusted date values.</span>
              </div>
            </div>
            <div class="auth-highlight-item">
              <i class="bi bi-key"></i>
              <div>
                <strong>Password strength guidance</strong>
                <span>Live password strength and confirmation feedback support better patient account security.</span>
              </div>
            </div>
            <div class="auth-highlight-item">
              <i class="bi bi-database-check"></i>
              <div>
                <strong>Transactional registration</strong>
                <span>User and patient records are written together to preserve data integrity.</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="card border-0 shadow-sm rounded-4 h-100">
          <div class="card-body p-4 p-lg-5">
            <div class="mb-4">
              <p class="text-primary fw-semibold mb-2">Create account</p>
              <h2 class="h3 mb-1">Patient registration form</h2>
              <p class="text-muted mb-0">Complete the required information below to access the patient dashboard.</p>
            </div>

            <?php require __DIR__ . '/../partials/shared/alerts.php'; ?>

            <form action="<?= \App\Helpers\Helper::url('/register') ?>" method="POST" class="row g-3 needs-validation" novalidate>
              <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken ?? '') ?>">

              <div class="col-md-6">
                <label for="registerFullName" class="form-label">Full Name</label>
                <input
                  type="text"
                  class="form-control form-control-lg"
                  id="registerFullName"
                  name="full_name"
                  value="<?= \App\Helpers\Helper::escape($oldInput['full_name'] ?? '') ?>"
                  required
                >
                <div class="invalid-feedback">Please enter your full name.</div>
              </div>

              <div class="col-md-6">
                <label for="registerEmail" class="form-label">Email Address</label>
                <input
                  type="email"
                  class="form-control form-control-lg"
                  id="registerEmail"
                  name="email"
                  value="<?= \App\Helpers\Helper::escape($oldInput['email'] ?? '') ?>"
                  required
                >
                <div class="invalid-feedback">Please enter a valid email address.</div>
              </div>

              <div class="col-md-6">
                <label for="registerDob" class="form-label">Date of Birth</label>
                <input
                  type="date"
                  class="form-control form-control-lg"
                  id="registerDob"
                  name="dob"
                  value="<?= \App\Helpers\Helper::escape($oldInput['dob'] ?? '') ?>"
                >
              </div>

              <div class="col-md-6">
                <label for="registerGender" class="form-label">Gender</label>
                <select class="form-select form-select-lg" id="registerGender" name="gender">
                  <option value="">Select gender</option>
                  <option value="male" <?= ($oldInput['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                  <option value="female" <?= ($oldInput['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                  <option value="other" <?= ($oldInput['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
              </div>

              <div class="col-12">
                <label for="registerAddress" class="form-label">Address</label>
                <textarea class="form-control" id="registerAddress" name="address" rows="3"><?= \App\Helpers\Helper::escape($oldInput['address'] ?? '') ?></textarea>
              </div>

              <div class="col-md-6">
                <label for="registerPassword" class="form-label">Password</label>
                <input
                  type="password"
                  class="form-control form-control-lg"
                  id="registerPassword"
                  name="password"
                  data-password-strength
                  required
                >
                <div class="invalid-feedback">Please provide a strong password.</div>
                <div class="password-strength mt-2" aria-live="polite">
                  <div class="password-strength-bar" data-password-strength-bar></div>
                </div>
                <p class="small text-muted mt-2 mb-0">Use at least 8 characters with uppercase, lowercase, number, and symbol.</p>
              </div>

              <div class="col-md-6">
                <label for="registerConfirmPassword" class="form-label">Confirm Password</label>
                <input
                  type="password"
                  class="form-control form-control-lg"
                  id="registerConfirmPassword"
                  name="confirm_password"
                  data-confirm-password="#registerPassword"
                  required
                >
                <div class="invalid-feedback">Please confirm your password.</div>
              </div>

              <div class="col-12">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" value="1" id="acceptTerms" name="terms" required>
                  <label class="form-check-label" for="acceptTerms">
                    I agree to the platform terms and acknowledge the privacy and security requirements.
                  </label>
                  <div class="invalid-feedback">You must accept the terms to continue.</div>
                </div>
              </div>

              <div class="col-12 d-grid gap-3 mt-2">
                <button type="submit" class="btn btn-primary btn-lg rounded-pill">Create Patient Account</button>
                <a href="<?= \App\Helpers\Helper::url('/login') ?>" class="btn btn-outline-primary rounded-pill">
                  Already have an account? Login
                </a>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<?php require __DIR__ . '/../partials/public/footer.php'; ?>
