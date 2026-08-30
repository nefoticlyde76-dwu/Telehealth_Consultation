<?php
$emailInvalid = isset($fieldErrors['email']);
?>
<?php require __DIR__ . '/../partials/public/navbar.php'; ?>

<main class="auth-page auth-page-login">
  <?php require __DIR__ . '/../partials/auth/atmosphere.php'; ?>

  <div class="auth-login-shell auth-container">
    <article class="auth-login-card auth-card" aria-labelledby="forgotPasswordHeading">
      <div class="auth-glass-brand">
        <?php require __DIR__ . '/../partials/auth/brand.php'; ?>
      </div>

      <header class="auth-login-form-header">
        <h1 id="forgotPasswordHeading" class="auth-login-title auth-title">Forgot password</h1>
        <p class="auth-login-subtitle auth-subtitle">
          Enter your email address and, if an account exists, we will send password reset instructions.
        </p>
      </header>

      <?php require __DIR__ . '/../partials/shared/alerts.php'; ?>

      <form action="<?= \App\Helpers\Helper::url('/forgot-password') ?>" method="POST" class="auth-login-form auth-form needs-validation" novalidate>
        <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken ?? '') ?>">

        <div class="auth-login-field auth-field">
          <div class="auth-field__control">
            <input
              type="email"
              class="form-control<?= $emailInvalid ? ' is-invalid' : '' ?>"
              id="forgotEmail"
              name="email"
              value="<?= \App\Helpers\Helper::escape($oldInput['email'] ?? '') ?>"
              placeholder=" "
              autocomplete="username"
              required
            >
            <label for="forgotEmail" class="form-label auth-field__label">Email Address <span class="text-danger">*</span></label>
          </div>
          <div class="invalid-feedback">
            <?= \App\Helpers\Helper::escape($fieldErrors['email'] ?? 'Please enter a valid email address.') ?>
          </div>
        </div>

        <div class="auth-login-actions auth-actions">
          <button type="submit" class="btn auth-login-submit">Send reset instructions</button>
          <p class="auth-register-signin auth-help">
            Remembered your password?
            <a href="<?= \App\Helpers\Helper::url('/login') ?>">Sign in</a>
          </p>
        </div>
      </form>
    </article>
  </div>
</main>
