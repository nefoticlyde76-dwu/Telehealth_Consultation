<?php
$emailInvalid = isset($fieldErrors['email']);
?>
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
            Reset your MBPHA <span>TeleHealth</span> password.
          </h2>
          <p class="auth-login-visual__copy">
            Enter your email address and, if an account exists, we will send password reset instructions.
          </p>
        </div>
      </aside>

      <section class="auth-login-form-panel" aria-labelledby="forgotPasswordHeading">
        <div class="auth-login-form-wrap">
          <header class="auth-login-form-header">
            <span class="auth-login-lock" aria-hidden="true">
              <i class="bi bi-envelope"></i>
            </span>
            <h1 id="forgotPasswordHeading" class="auth-login-title">Forgot password</h1>
            <p class="auth-login-subtitle">
              Enter your email address and, if an account exists, we will send password reset instructions.
            </p>
          </header>

          <?php require __DIR__ . '/../partials/shared/alerts.php'; ?>

          <form action="<?= \App\Helpers\Helper::url('/forgot-password') ?>" method="POST" class="auth-login-form needs-validation" novalidate>
            <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken ?? '') ?>">

            <div class="auth-login-field">
              <label for="forgotEmail" class="form-label">Email Address <span class="text-danger">*</span></label>
              <div class="auth-login-input">
                <i class="bi bi-envelope auth-login-input__icon" aria-hidden="true"></i>
                <input
                  type="email"
                  class="form-control<?= $emailInvalid ? ' is-invalid' : '' ?>"
                  id="forgotEmail"
                  name="email"
                  value="<?= \App\Helpers\Helper::escape($oldInput['email'] ?? '') ?>"
                  placeholder="Enter your email address"
                  autocomplete="username"
                  required
                >
              </div>
              <div class="invalid-feedback">
                <?= \App\Helpers\Helper::escape($fieldErrors['email'] ?? 'Please enter a valid email address.') ?>
              </div>
            </div>

            <div class="auth-login-actions">
              <button type="submit" class="btn auth-login-submit">Send reset instructions</button>
              <p class="auth-register-signin">
                Remembered your password?
                <a href="<?= \App\Helpers\Helper::url('/login') ?>">Sign in</a>
              </p>
            </div>
          </form>
        </div>
      </section>
    </article>
  </div>
</main>

<?php require __DIR__ . '/../partials/public/footer.php'; ?>
