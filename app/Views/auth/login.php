<?php require __DIR__ . '/../partials/public/navbar.php'; ?>

<main class="auth-page auth-page-login">
  <?php require __DIR__ . '/../partials/auth/atmosphere.php'; ?>

  <div class="auth-login-shell">
    <article class="auth-login-card" aria-labelledby="authLoginHeading">
      <div class="auth-glass-brand">
        <?php require __DIR__ . '/../partials/auth/brand.php'; ?>
      </div>

      <?php
      $authTab = 'login';
      require __DIR__ . '/../partials/auth/tabs.php';
      ?>

      <header class="auth-login-form-header">
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

        <div class="auth-login-field auth-field auth-field--icon">
          <div class="auth-field__control">
            <span class="auth-field__icon" aria-hidden="true"><i class="bi bi-envelope"></i></span>
            <input
              type="email"
              class="form-control"
              id="loginEmail"
              name="email"
              value="<?= \App\Helpers\Helper::escape($oldInput['email'] ?? '') ?>"
              placeholder=" "
              autocomplete="username"
              required
            >
            <label for="loginEmail" class="form-label auth-field__label">Email Address <span class="text-danger">*</span></label>
          </div>
          <div class="invalid-feedback">Please enter a valid email address.</div>
        </div>

        <div class="auth-login-field auth-field auth-field--pass auth-field--icon">
          <div class="auth-field__control">
            <span class="auth-field__icon" aria-hidden="true"><i class="bi bi-lock"></i></span>
            <input
              type="password"
              class="form-control"
              id="loginPassword"
              name="password"
              placeholder=" "
              autocomplete="current-password"
              required
            >
            <label for="loginPassword" class="form-label auth-field__label">Password <span class="text-danger">*</span></label>
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

        <div class="auth-login-meta">
          <a href="<?= \App\Helpers\Helper::url('/forgot-password') ?>" class="auth-login-forgot">Forgot password?</a>
        </div>

        <div class="auth-login-actions">
          <button
            type="submit"
            class="btn auth-login-submit"
            data-auth-submit
            data-loading-label="Signing in…"
          >
            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
            <span data-auth-submit-label>Sign in</span>
          </button>
          <?php if ($googleClientId !== ''): ?>
          <div class="auth-login-or" role="separator" aria-label="or">OR</div>
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
        </div>
      </form>

      <p class="auth-login-secure">
        <i class="bi bi-shield-check" aria-hidden="true"></i>
        Your information is protected with secure server-side authentication.
      </p>
    </article>

    <?php require __DIR__ . '/../partials/auth/trust.php'; ?>
  </div>
</main>

<?php if ($googleClientId !== ''): ?>
<script src="<?= \App\Helpers\Helper::asset('js/google-auth.js') ?>"></script>
<script src="https://accounts.google.com/gsi/client" async defer onload="window.mbphaGoogleGisLoaded && window.mbphaGoogleGisLoaded()"></script>
<?php endif; ?>
