<?php

$linkValid = (bool) ($linkValid ?? false);
$csrfFailed = (bool) ($csrfFailed ?? false);
$doctorName = trim((string) ($doctorName ?? ''));
$setupToken = (string) ($setupToken ?? '');
$csrfToken = $csrfToken ?? '';
$errors = $errors ?? [];
$fieldErrors = $fieldErrors ?? [];
$invalidMessage = (string) ($invalidMessage ?? 'This invitation link is invalid or has expired. Ask an MBPHA administrator to send a new invitation.');
$csrfMessage = (string) ($csrfMessage ?? 'Unable to verify the request. Please refresh the page and try again.');
$passwordInvalid = isset($fieldErrors['password']);
$confirmInvalid = isset($fieldErrors['confirm_password']);
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
            Set up your MBPHA <span>TeleHealth</span> password.
          </h2>
          <p class="auth-login-visual__copy">
            An MBPHA administrator created this doctor account. Choose a password, then sign in through the shared login page.
          </p>
        </div>
      </aside>

      <section class="auth-login-form-panel" aria-labelledby="setupPasswordHeading">
        <div class="auth-login-form-wrap">
          <header class="auth-login-form-header">
            <span class="auth-login-lock" aria-hidden="true">
              <i class="bi bi-key"></i>
            </span>
            <h1 id="setupPasswordHeading" class="auth-login-title">Set Up Your MBPHA TeleHealth Password</h1>
            <?php if ($linkValid): ?>
              <p class="auth-login-subtitle">
                <?php if ($doctorName !== ''): ?>
                  Hello <?= \App\Helpers\Helper::escape($doctorName) ?>.
                <?php endif; ?>
                Your doctor account was created by an MBPHA administrator. Set a password before signing in.
              </p>
            <?php else: ?>
              <p class="auth-login-subtitle">Use a current invitation link from your MBPHA administrator to continue.</p>
            <?php endif; ?>
          </header>

          <?php if ($csrfFailed): ?>
            <div class="alert alert-warning" role="alert">
              <?= \App\Helpers\Helper::escape($csrfMessage) ?>
            </div>
            <a href="<?= \App\Helpers\Helper::url('/login') ?>" class="btn btn-primary w-100">
              Return to sign in
            </a>
          <?php elseif (!$linkValid): ?>
            <div class="alert alert-warning" role="alert">
              <?= \App\Helpers\Helper::escape($invalidMessage) ?>
            </div>
            <a href="<?= \App\Helpers\Helper::url('/login') ?>" class="btn btn-primary w-100">
              Return to sign in
            </a>
          <?php else: ?>
            <?php require __DIR__ . '/../partials/shared/alerts.php'; ?>

            <form method="POST" action="<?= \App\Helpers\Helper::url('/doctor/setup-password') ?>" class="auth-login-form needs-validation" novalidate>
              <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">
              <input type="hidden" name="token" value="<?= \App\Helpers\Helper::escape($setupToken) ?>">

              <div class="auth-login-field">
                <label for="setupPassword" class="form-label">New password <span class="text-danger">*</span></label>
                <div class="auth-login-input">
                  <i class="bi bi-lock auth-login-input__icon" aria-hidden="true"></i>
                  <input
                    type="password"
                    class="form-control<?= $passwordInvalid ? ' is-invalid' : '' ?>"
                    id="setupPassword"
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
                    data-password-toggle="#setupPassword"
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
                <label for="setupConfirmPassword" class="form-label">Confirm password <span class="text-danger">*</span></label>
                <div class="auth-login-input">
                  <i class="bi bi-lock auth-login-input__icon" aria-hidden="true"></i>
                  <input
                    type="password"
                    class="form-control<?= $confirmInvalid ? ' is-invalid' : '' ?>"
                    id="setupConfirmPassword"
                    name="confirm_password"
                    placeholder="Re-enter your password"
                    autocomplete="new-password"
                    data-confirm-password="#setupPassword"
                    required
                  >
                  <button
                    type="button"
                    class="auth-password-toggle"
                    data-password-toggle="#setupConfirmPassword"
                    aria-label="Show confirm password"
                    aria-pressed="false"
                  >
                    <i class="bi bi-eye" aria-hidden="true"></i>
                  </button>
                </div>
                <div class="invalid-feedback">
                  <?= \App\Helpers\Helper::escape($fieldErrors['confirm_password'] ?? 'Please confirm your password. Both passwords must match.') ?>
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

              <button type="submit" class="btn btn-primary w-100">Set Password</button>
            </form>
          <?php endif; ?>

          <p class="text-muted small mt-4 mb-0 text-center">
            After setup, sign in at
            <a href="<?= \App\Helpers\Helper::url('/login') ?>">/login</a>.
          </p>
        </div>
      </section>
    </article>
  </div>
</main>
<?php require __DIR__ . '/../partials/public/footer.php'; ?>
