<?php require __DIR__ . '/../partials/public/navbar.php'; ?>

<main class="auth-page auth-page-agh py-4 py-lg-5">
  <div class="container auth-shell-container">
    <div class="auth-shell row g-0 overflow-hidden">
      <div class="col-lg-5 order-2 order-lg-1">
        <?php
        $authVisualBadge = 'MBPHA TeleHealth';
        $authVisualTitle = 'Sign in to MBPHA TeleHealth.';
        $authVisualCopy = 'Patients, doctors, and administrators use this portal to manage teleconsultations for Milne Bay Provincial Health Authority.';
        $authVisualQuote = 'Connecting communities across Milne Bay with specialist care.';
        $authVisualPoints = [
            'One sign-in for patients, doctors, and administrators.',
            'Consultation booking and video appointments in one place.',
            'Managed by MBPHA for Alotau General Hospital and referring facilities.',
        ];
        require __DIR__ . '/../partials/shared/auth_visual_panel.php';
        ?>
      </div>

      <div class="col-lg-7 order-1 order-lg-2">
        <div class="auth-form-panel h-100">
          <div class="auth-form-card card border-0 h-100 reveal-on-scroll reveal-slide-up">
            <div class="card-body p-4 p-lg-5">
            <div class="mb-4">
              <h2 class="h3 mb-1">Sign in</h2>
              <p class="text-muted mb-0">Enter the email and password for your account.</p>
            </div>

            <?php require __DIR__ . '/../partials/shared/alerts.php'; ?>

            <form action="<?= \App\Helpers\Helper::url('/login') ?>" method="POST" class="needs-validation" novalidate>
              <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken ?? '') ?>">

              <div class="mb-3">
                <label for="loginEmail" class="form-label">Email Address</label>
                <input
                  type="email"
                  class="form-control form-control-lg"
                  id="loginEmail"
                  name="email"
                  value="<?= \App\Helpers\Helper::escape($oldInput['email'] ?? '') ?>"
                  required
                >
                <div class="invalid-feedback">Please enter a valid email address.</div>
              </div>

              <div class="mb-3">
                <label for="loginPassword" class="form-label">Password</label>
                <input
                  type="password"
                  class="form-control form-control-lg"
                  id="loginPassword"
                  name="password"
                  required
                >
                <div class="invalid-feedback">Please provide your password.</div>
              </div>

              <div class="d-grid gap-3 mt-4">
                <button type="submit" class="btn btn-primary btn-lg">Sign in</button>
                <a href="<?= \App\Helpers\Helper::url('/register') ?>" class="btn btn-outline-primary btn-lg">
                  Register as a patient
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
