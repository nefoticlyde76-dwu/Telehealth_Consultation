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
            Choose a new MBPHA <span>TeleHealth</span> password.
          </h2>
          <p class="auth-login-visual__copy">
            Use this page to set a new password, then sign in with your email and the password you choose.
          </p>
        </div>
      </aside>

      <section class="auth-login-form-panel" aria-labelledby="resetPasswordHeading">
        <div class="auth-login-form-wrap">
          <header class="auth-login-form-header">
            <span class="auth-login-lock" aria-hidden="true">
              <i class="bi bi-key"></i>
            </span>
            <h1 id="resetPasswordHeading" class="auth-login-title">Reset password</h1>
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
            <a href="<?= \App\Helpers\Helper::url('/login') ?>" class="btn btn-primary w-100">
              Return to sign in
            </a>
          <?php else: ?>
            <?php require __DIR__ . '/../partials/shared/alerts.php'; ?>

            <form method="POST" action="<?= \App\Helpers\Helper::url('/reset-password') ?>" class="auth-login-form needs-validation" novalidate>
              <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">
              <input type="hidden" name="token" value="<?= \App\Helpers\Helper::escape($resetToken) ?>">

              <div class="auth-login-field">
                <label for="resetPassword" class="form-label">New password <span class="text-danger">*</span></label>
                <div class="auth-login-input">
                  <i class="bi bi-lock auth-login-input__icon" aria-hidden="true"></i>
                  <input
                    type="password"
                    class="form-control<?= $passwordInvalid ? ' is-invalid' : '' ?>"
                    id="resetPassword"
                    name="password"
                    placeholder="Create a password"
                    autocomplete="new-password"
                    minlength="8"
                    data-password-strength
                    required
                  >
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

              <div class="auth-login-field">
                <label for="resetConfirmPassword" class="form-label">Confirm password <span class="text-danger">*</span></label>
                <div class="auth-login-input">
                  <i class="bi bi-lock auth-login-input__icon" aria-hidden="true"></i>
                  <input
                    type="password"
                    class="form-control<?= $confirmInvalid ? ' is-invalid' : '' ?>"
                    id="resetConfirmPassword"
                    name="confirm_password"
                    placeholder="Re-enter your password"
                    autocomplete="new-password"
                    data-confirm-password="#resetPassword"
                    required
                  >
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

              <div class="auth-register-strength mb-4">
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

              <div class="auth-login-actions">
                <button type="submit" class="btn auth-login-submit">Save new password</button>
                <p class="auth-register-signin">
                  Return to
                  <a href="<?= \App\Helpers\Helper::url('/login') ?>">sign in</a>
                </p>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </section>
    </article>
  </div>
</main>

<?php require __DIR__ . '/../partials/public/footer.php'; ?>
