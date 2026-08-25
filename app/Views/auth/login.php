<?php require __DIR__ . '/../partials/public/navbar.php'; ?>

<main class="auth-page auth-page-login">
  <div class="auth-login-shell">
    <article class="auth-login-card">
      <aside class="auth-login-visual">
        <img
          class="auth-login-visual__photo"
          src="<?= \App\Helpers\Helper::asset('images/AGH.png') ?>"
          alt=""
          aria-hidden="true"
        >
        <span class="auth-login-visual__tint" aria-hidden="true"></span>

        <div class="auth-login-visual__content">
          <div class="auth-login-visual__brand">
            <?php
            $brandVariant = 'auth';
            $brandSubtitle = '';
            $brandShowTitle = false;
            $brandLink = \App\Helpers\Helper::url('/');
            require __DIR__ . '/../partials/shared/brand_logo.php';
            ?>
          </div>

          <span class="auth-login-badge">
            <i class="bi bi-shield-fill" aria-hidden="true"></i>
            Milne Bay Provincial Health Authority
          </span>

          <h2 class="auth-login-visual__title">
            Sign in to MBPHA <span>TeleHealth.</span>
          </h2>
          <p class="auth-login-visual__copy">
            Patients, doctors, and administrators use this portal to manage teleconsultations for Milne Bay Provincial Health Authority.
          </p>

          <div class="auth-login-feature">
            <div class="auth-login-feature__head">
              <span class="auth-login-feature__icon" aria-hidden="true">
                <i class="bi bi-hospital"></i>
              </span>
              <div>
                <strong>Alotau General Hospital</strong>
                <p>Connecting communities across Milne Bay with specialist care.</p>
              </div>
            </div>
            <ul class="auth-login-feature__list">
              <li>
                <span class="auth-login-check" aria-hidden="true"><i class="bi bi-check2"></i></span>
                One sign-in for patients, doctors, and administrators.
              </li>
              <li>
                <span class="auth-login-check" aria-hidden="true"><i class="bi bi-check2"></i></span>
                Consultation booking and video appointments in one place.
              </li>
              <li>
                <span class="auth-login-check" aria-hidden="true"><i class="bi bi-check2"></i></span>
                Managed by MBPHA for Alotau General Hospital and referring facilities.
              </li>
            </ul>
          </div>
        </div>
      </aside>

      <section class="auth-login-form-panel" aria-labelledby="authLoginHeading">
        <div class="auth-login-form-wrap">
          <header class="auth-login-form-header">
            <span class="auth-login-lock" aria-hidden="true">
              <i class="bi bi-lock"></i>
            </span>
            <h1 id="authLoginHeading" class="auth-login-title">Sign in</h1>
            <p class="auth-login-subtitle">Use your MBPHA TeleHealth email and password to open your account.</p>
          </header>

          <?php
          $googleClientId = trim((string) (\App\Config\App::getConfig()['google']['client_id'] ?? ''));
          ?>

          <?php require __DIR__ . '/../partials/shared/alerts.php'; ?>

          <div
            id="googleSignInAlert"
            class="alert alert-danger d-none"
            role="alert"
            aria-live="polite"
            data-google-auth-alert
          ></div>

          <form action="<?= \App\Helpers\Helper::url('/login') ?>" method="POST" class="auth-login-form needs-validation" novalidate>
            <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken ?? '') ?>">

            <div class="auth-login-field">
              <label for="loginEmail" class="form-label">Email Address <span class="text-danger">*</span></label>
              <div class="auth-login-input">
                <i class="bi bi-envelope auth-login-input__icon" aria-hidden="true"></i>
                <input
                  type="email"
                  class="form-control"
                  id="loginEmail"
                  name="email"
                  value="<?= \App\Helpers\Helper::escape($oldInput['email'] ?? '') ?>"
                  placeholder="Enter your email address"
                  autocomplete="username"
                  required
                >
              </div>
              <div class="invalid-feedback">Please enter a valid email address.</div>
            </div>

            <div class="auth-login-field">
              <label for="loginPassword" class="form-label">Password <span class="text-danger">*</span></label>
              <div class="auth-login-input">
                <i class="bi bi-lock auth-login-input__icon" aria-hidden="true"></i>
                <input
                  type="password"
                  class="form-control"
                  id="loginPassword"
                  name="password"
                  placeholder="Enter your password"
                  autocomplete="current-password"
                  required
                >
                <button
                  type="button"
                  class="auth-password-toggle"
                  data-password-toggle="#loginPassword"
                  aria-label="Show password"
                  aria-pressed="false"
                >
                  <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
              </div>
              <div class="invalid-feedback">Please provide your password.</div>
            </div>

            <div class="auth-login-actions">
              <button type="submit" class="btn auth-login-submit">Sign in</button>
              <div class="auth-login-or" role="separator" aria-label="or">OR</div>
              <?php if ($googleClientId !== ''): ?>
              <div
                id="mbphaGoogleSignIn"
                class="auth-login-google"
                data-google-auth
                data-client-id="<?= \App\Helpers\Helper::escape($googleClientId) ?>"
                data-auth-url="<?= \App\Helpers\Helper::escape(\App\Helpers\Helper::url('/auth/google')) ?>"
                data-error-generic="Google sign-in could not be completed."
                data-error-unavailable="Google sign-in is unavailable right now. Please sign in with email and password."
                data-busy-label="Signing in…"
              >
                <div
                  id="mbphaGoogleSignInButton"
                  class="auth-login-google__button"
                  data-google-auth-button
                  role="group"
                  aria-label="Continue with Google"
                ></div>
                <p class="auth-login-google-status" data-google-auth-status aria-live="polite"></p>
                <p class="auth-login-google-hint">Patients can continue with Google. Doctors and administrators sign in with email and password.</p>
              </div>
              <?php endif; ?>
              <a href="<?= \App\Helpers\Helper::url('/register') ?>" class="btn auth-login-register">
                <i class="bi bi-person-plus me-2" aria-hidden="true"></i>
                Register as a patient
              </a>
            </div>
          </form>
        </div>
      </section>
    </article>
  </div>
</main>

<?php if ($googleClientId !== ''): ?>
<script src="<?= \App\Helpers\Helper::asset('js/google-auth.js') ?>"></script>
<script src="https://accounts.google.com/gsi/client" async defer onload="window.mbphaGoogleGisLoaded && window.mbphaGoogleGisLoaded()"></script>
<?php endif; ?>

<?php require __DIR__ . '/../partials/public/footer.php'; ?>
