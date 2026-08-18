<?php require __DIR__ . '/../partials/public/navbar.php'; ?>

<main class="auth-page auth-page-agh py-4 py-lg-5">
  <div class="container auth-shell-container">
    <div class="auth-shell row g-0 overflow-hidden">
      <div class="col-lg-5 order-2 order-lg-1">
        <?php
        $authVisualBadge = 'Patient registration';
        $authVisualTitle = 'Create a patient account.';
        $authVisualCopy = 'Register to book teleconsultations with MBPHA clinicians.';
        $authVisualQuote = 'Registration is for patients. Doctors and administrators are issued accounts by MBPHA.';
        $authVisualPoints = [
            'Use a valid email address you can access.',
            'Choose a strong password of at least 8 characters.',
            'You can sign in and book a consultation as soon as registration is complete.',
        ];
        require __DIR__ . '/../partials/shared/auth_visual_panel.php';
        ?>
      </div>

      <div class="col-lg-7 order-1 order-lg-2">
        <div class="auth-form-panel h-100">
          <div class="auth-form-card card border-0 h-100 reveal-on-scroll reveal-slide-up">
            <div class="card-body p-4 p-lg-5">
            <div class="mb-4">
              <h2 class="h3 mb-1">Patient registration</h2>
              <p class="text-muted mb-0">Complete the required fields to create your account.</p>
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
                <label for="registerDob" class="form-label">Date of birth <span class="text-muted fw-normal">(optional)</span></label>
                <input
                  type="date"
                  class="form-control form-control-lg"
                  id="registerDob"
                  name="dob"
                  value="<?= \App\Helpers\Helper::escape($oldInput['dob'] ?? '') ?>"
                >
              </div>

              <div class="col-md-6">
                <label for="registerGender" class="form-label">Gender <span class="text-muted fw-normal">(optional)</span></label>
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
                    I understand that my information will be used to provide telehealth services through MBPHA.
                  </label>
                  <div class="invalid-feedback">Please confirm you understand how your information will be used.</div>
                </div>
              </div>

              <div class="col-12 d-grid gap-3 mt-2">
                <button type="submit" class="btn btn-primary btn-lg">Create patient account</button>
                <a href="<?= \App\Helpers\Helper::url('/login') ?>" class="btn btn-outline-primary btn-lg">
                  Already have an account? Sign in
                </a>
              </div>
            </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<?php require __DIR__ . '/../partials/public/footer.php'; ?>
