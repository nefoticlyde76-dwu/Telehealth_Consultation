<?php require __DIR__ . '/../partials/public/navbar.php'; ?>

<main class="auth-page py-5">
  <div class="container">
    <div class="row justify-content-center align-items-stretch g-4 content-row">
      <div class="col-lg-5">
        <div class="auth-panel auth-panel-accent h-100">
          <div class="mb-4">
            <?php
            $brandVariant = 'auth';
            $brandSubtitle = '';
            $brandShowTitle = false;
            $brandLink = \App\Helpers\Helper::url('/');
            require __DIR__ . '/../partials/shared/brand_logo.php';
            ?>
          </div>
          <span class="section-badge mb-3">
            <i class="bi bi-box-arrow-in-right"></i>
            Secure Access
          </span>
          <h1 class="display-6 fw-bold mb-3">Sign in to continue your MBPHA TeleHealth workflow.</h1>
          <p class="text-muted mb-4">
            Patients, doctors, and administrators use the same secure login point and are redirected
            automatically to the correct dashboard after authentication.
          </p>

          <div class="auth-highlight-list">
            <div class="auth-highlight-item">
              <i class="bi bi-shield-lock"></i>
              <div>
                <strong>Protected sessions</strong>
                <span>Session regeneration and CSRF verification are enforced.</span>
              </div>
            </div>
            <div class="auth-highlight-item">
              <i class="bi bi-signpost-split"></i>
              <div>
                <strong>Role-based redirects</strong>
                <span>Each authenticated user is directed to the proper dashboard.</span>
              </div>
            </div>
            <div class="auth-highlight-item">
              <i class="bi bi-phone"></i>
              <div>
                <strong>Responsive sign-in</strong>
                <span>Built for desktop and mobile access with accessible form patterns.</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card border-0 shadow-sm rounded-4 h-100">
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
                <button type="submit" class="btn btn-primary btn-lg rounded-pill">Login Securely</button>
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
</main>

<?php require __DIR__ . '/../partials/public/footer.php'; ?>
