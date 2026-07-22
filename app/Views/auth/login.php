<?php require __DIR__ . '/../partials/public/navbar.php'; ?>

<main class="auth-page auth-page-agh py-4 py-lg-5">
  <div class="container auth-shell-container">
    <div class="auth-shell row g-0 overflow-hidden">
      <div class="col-lg-5 order-2 order-lg-1">
        <?php
        $authVisualBadge = 'Welcome to MBPHA TeleHealth';
        $authVisualTitle = 'Secure sign-in for digital healthcare coordination.';
        $authVisualCopy = 'Access a professional care platform inspired by Alotau General Hospital and built for trusted healthcare delivery.';
        $authVisualQuote = 'Connecting patients, clinicians, and administrators through a premium MBPHA experience.';
        require __DIR__ . '/../partials/shared/auth_visual_panel.php';
        ?>
      </div>

      <div class="col-lg-7 order-1 order-lg-2">
        <div class="auth-form-panel h-100">
          <div class="auth-form-card card border-0 h-100">
            <div class="card-body p-4 p-lg-5">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
              <div>
                <p class="text-primary fw-semibold mb-2">Welcome back</p>
                <h2 class="h3 mb-1">Login to your account</h2>
                <p class="text-muted mb-0">Use your registered email address and password.</p>
              </div>
              <span class="badge badge-soft-neutral rounded-pill px-3 py-2 border">Patients, Doctors, Administrators</span>
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
                <button type="submit" class="btn btn-success btn-lg rounded-pill">Login Securely</button>
                <a href="<?= \App\Helpers\Helper::url('/register') ?>" class="btn btn-outline-primary rounded-pill">
                  Create Patient Account
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
