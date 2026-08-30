<?php
$invalidLink = (bool) ($invalidLink ?? false);
$resetToken = (string) ($resetToken ?? '');
$csrfToken = $csrfToken ?? '';
$errors = $errors ?? [];
$fieldErrors = $fieldErrors ?? [];
$passwordInvalid = isset($fieldErrors['password']);
$confirmInvalid = isset($fieldErrors['confirm_password']) || isset($fieldErrors['password_confirmation']);
?>
<?php require __DIR__ . '/../partials/public/navbar.php'; ?>

<main class="auth-page auth-page-login auth-page-setup">
  <?php require __DIR__ . '/../partials/auth/atmosphere.php'; ?>

  <div class="auth-login-shell auth-container">
    <article class="auth-login-card auth-card" aria-labelledby="resetPasswordHeading">
      <div class="auth-glass-brand">
        <?php require __DIR__ . '/../partials/auth/brand.php'; ?>
      </div>

      <header class="auth-login-form-header">
        <h1 id="resetPasswordHeading" class="auth-login-title auth-title">Reset password</h1>
        <?php if ($invalidLink): ?>
          <p class="auth-login-subtitle">This reset link can no longer be used. Request a new one from the sign-in page.</p>
        <?php else: ?>
          <p class="auth-login-subtitle">Choose a new password that is at least 8 characters and includes uppercase, lowercase, number, and symbol.</p>
        <?php endif; ?>
      </header>

      <?php if ($invalidLink): ?>
        <div class="alert alert-warning" role="alert">
          This password reset link is invalid or has expired.
        </div>
        <a href="<?= \App\Helpers\Helper::url('/login') ?>" class="btn auth-login-submit">
          Return to sign in
        </a>
      <?php else: ?>
        <?php require __DIR__ . '/../partials/shared/alerts.php'; ?>

        <form method="POST" action="<?= \App\Helpers\Helper::url('/reset-password') ?>" class="auth-login-form auth-form needs-validation" novalidate>
          <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">
          <input type="hidden" name="token" value="<?= \App\Helpers\Helper::escape($resetToken) ?>">

          <div class="auth-login-field auth-field auth-field--pass">
            <div class="auth-field__control">
              <input
                type="password"
                class="form-control<?= $passwordInvalid ? ' is-invalid' : '' ?>"
                id="resetPassword"
                name="password"
                placeholder=" "
                autocomplete="new-password"
                minlength="8"
                data-password-strength
                required
              >
              <label for="resetPassword" class="form-label auth-field__label">New password <span class="text-danger">*</span></label>
              <button
                type="button"
                class="auth-password-toggle"
                data-password-toggle="#resetPassword"
                aria-label="Show password"
                aria-pressed="false"
              >
                <i class="bi bi-eye" aria-hidden="true"></i>
              </button>
            </div>
            <div class="invalid-feedback">
              <?= \App\Helpers\Helper::escape($fieldErrors['password'] ?? 'Use at least 8 characters with uppercase, lowercase, number, and symbol.') ?>
            </div>
          </div>

          <div class="auth-login-field auth-field auth-field--pass">
            <div class="auth-field__control">
              <input
                type="password"
                class="form-control<?= $confirmInvalid ? ' is-invalid' : '' ?>"
                id="resetConfirmPassword"
                name="confirm_password"
                placeholder=" "
                autocomplete="new-password"
                data-confirm-password="#resetPassword"
                required
              >
              <label for="resetConfirmPassword" class="form-label auth-field__label">Confirm password <span class="text-danger">*</span></label>
              <button
                type="button"
                class="auth-password-toggle"
                data-password-toggle="#resetConfirmPassword"
                aria-label="Show confirm password"
                aria-pressed="false"
              >
                <i class="bi bi-eye" aria-hidden="true"></i>
              </button>
            </div>
            <div class="invalid-feedback">
              <?= \App\Helpers\Helper::escape($fieldErrors['confirm_password'] ?? $fieldErrors['password_confirmation'] ?? 'Please confirm your password. Both passwords must match.') ?>
            </div>
          </div>

          <div class="auth-register-strength">
            <div
              class="auth-password-meter"
              data-password-strength-meter
              role="meter"
              aria-label="Password strength"
              aria-valuemin="0"
              aria-valuemax="4"
              aria-valuenow="0"
            >
              <span></span>
              <span></span>
              <span></span>
              <span></span>
            </div>
            <p class="auth-password-hint" data-password-requirements>
              <span data-req="length">8+ characters</span> with
              <span data-req="upper">uppercase</span>,
              <span data-req="lower">lowercase</span>,
              <span data-req="number">number</span>,
              and
              <span data-req="symbol">symbol</span>.
            </p>
          </div>

          <div class="auth-login-actions auth-actions">
            <button type="submit" class="btn auth-login-submit">Save new password</button>
            <p class="auth-register-signin auth-help">
              Return to
              <a href="<?= \App\Helpers\Helper::url('/login') ?>">sign in</a>
            </p>
          </div>
        </form>
      <?php endif; ?>
    </article>
  </div>
</main>
