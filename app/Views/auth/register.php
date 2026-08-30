<?php
$today = date('Y-m-d');
$oldTerms = !empty($oldInput['terms']);
$errorText = implode(' ', $errors ?? []);
$emailInvalid = $errorText !== '' && stripos($errorText, 'email') !== false;
$nameInvalid = $errorText !== '' && stripos($errorText, 'full name') !== false;
$passwordInvalid = $errorText !== '' && stripos($errorText, 'password') !== false && stripos($errorText, 'confirm') === false;
$confirmInvalid = $errorText !== '' && (stripos($errorText, 'confirm') !== false || stripos($errorText, 'do not match') !== false);
$dobInvalid = $errorText !== '' && stripos($errorText, 'date of birth') !== false;
$genderInvalid = $errorText !== '' && stripos($errorText, 'gender') !== false;
$termsInvalid = $errorText !== '' && (stripos($errorText, 'terms') !== false || stripos($errorText, 'privacy') !== false);
$optionalOpen = $dobInvalid
    || $genderInvalid
    || trim((string) ($oldInput['dob'] ?? '')) !== ''
    || trim((string) ($oldInput['gender'] ?? '')) !== ''
    || trim((string) ($oldInput['address'] ?? '')) !== '';
?>
<?php require __DIR__ . '/../partials/public/navbar.php'; ?>

<main class="auth-page auth-page-login auth-page-register">
  <?php require __DIR__ . '/../partials/auth/atmosphere.php'; ?>

  <div class="auth-login-shell">
    <article class="auth-login-card" aria-labelledby="authRegisterHeading">
      <div class="auth-glass-brand">
        <?php require __DIR__ . '/../partials/auth/brand.php'; ?>
      </div>

      <?php
      $authTab = 'register';
      require __DIR__ . '/../partials/auth/tabs.php';
      ?>

      <header class="auth-register-form-header">
        <h1 id="authRegisterHeading" class="auth-register-title">Patient registration</h1>
        <p class="auth-register-subtitle">Create your account to book teleconsultations.</p>
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

      <form
        action="<?= \App\Helpers\Helper::url('/register') ?>"
        method="POST"
        class="auth-login-form auth-register-form needs-validation"
        novalidate
        data-auth-form="register"
      >
        <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken ?? '') ?>">

        <div class="auth-register-grid">
          <div class="auth-login-field auth-field">
            <div class="auth-field__control">
              <input
                type="text"
                class="form-control<?= $nameInvalid ? ' is-invalid' : '' ?>"
                id="registerFullName"
                name="full_name"
                value="<?= \App\Helpers\Helper::escape($oldInput['full_name'] ?? '') ?>"
                placeholder=" "
                autocomplete="name"
                maxlength="255"
                required
              >
              <label for="registerFullName" class="form-label auth-field__label">Full Name <span class="text-danger">*</span></label>
            </div>
            <div class="invalid-feedback">Please enter your full name.</div>
          </div>

          <div class="auth-login-field auth-field">
            <div class="auth-field__control">
              <input
                type="email"
                class="form-control<?= $emailInvalid ? ' is-invalid' : '' ?>"
                id="registerEmail"
                name="email"
                value="<?= \App\Helpers\Helper::escape($oldInput['email'] ?? '') ?>"
                placeholder=" "
                autocomplete="email"
                required
              >
              <label for="registerEmail" class="form-label auth-field__label">Email Address <span class="text-danger">*</span></label>
            </div>
            <div class="invalid-feedback">
              <?= $emailInvalid ? 'Please use a valid email that is not already registered.' : 'Please enter a valid email address.' ?>
            </div>
          </div>

          <div class="auth-login-field auth-field auth-field--pass">
            <div class="auth-field__control">
              <input
                type="password"
                class="form-control<?= $passwordInvalid ? ' is-invalid' : '' ?>"
                id="registerPassword"
                name="password"
                placeholder=" "
                autocomplete="new-password"
                minlength="8"
                data-password-strength
                required
              >
              <label for="registerPassword" class="form-label auth-field__label">Password <span class="text-danger">*</span></label>
              <button
                type="button"
                class="auth-password-toggle"
                data-password-toggle="#registerPassword"
                aria-label="Show password"
                aria-pressed="false"
              >
                <i class="bi bi-eye" aria-hidden="true"></i>
              </button>
            </div>
            <div class="invalid-feedback">Use at least 8 characters with uppercase, lowercase, number, and symbol.</div>
          </div>

          <div class="auth-login-field auth-field auth-field--pass">
            <div class="auth-field__control">
              <input
                type="password"
                class="form-control<?= $confirmInvalid ? ' is-invalid' : '' ?>"
                id="registerConfirmPassword"
                name="confirm_password"
                placeholder=" "
                autocomplete="new-password"
                data-confirm-password="#registerPassword"
                required
              >
              <label for="registerConfirmPassword" class="form-label auth-field__label">Confirm Password <span class="text-danger">*</span></label>
              <button
                type="button"
                class="auth-password-toggle"
                data-password-toggle="#registerConfirmPassword"
                aria-label="Show confirm password"
                aria-pressed="false"
              >
                <i class="bi bi-eye" aria-hidden="true"></i>
              </button>
            </div>
            <div class="invalid-feedback">Please confirm your password. Both passwords must match.</div>
          </div>

          <div class="auth-register-grid__full auth-register-strength">
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
        </div>

        <details class="auth-register-optional"<?= $optionalOpen ? ' open' : '' ?>>
          <summary>Optional details</summary>
          <div class="auth-register-grid auth-register-optional__fields">
            <div class="auth-login-field auth-field auth-field--raised">
              <div class="auth-field__control">
                <input
                  type="date"
                  class="form-control<?= $dobInvalid ? ' is-invalid' : '' ?>"
                  id="registerDob"
                  name="dob"
                  value="<?= \App\Helpers\Helper::escape($oldInput['dob'] ?? '') ?>"
                  min="1900-01-01"
                  max="<?= \App\Helpers\Helper::escape($today) ?>"
                  autocomplete="bday"
                  placeholder=" "
                >
                <label for="registerDob" class="form-label auth-field__label">Date of birth</label>
              </div>
              <div class="invalid-feedback">Date of birth cannot be in the future.</div>
            </div>

            <div class="auth-login-field auth-field auth-field--raised">
              <div class="auth-field__control">
                <select
                  class="form-select<?= $genderInvalid ? ' is-invalid' : '' ?>"
                  id="registerGender"
                  name="gender"
                  autocomplete="sex"
                >
                  <option value="">Select gender</option>
                  <option value="male" <?= ($oldInput['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                  <option value="female" <?= ($oldInput['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                  <option value="other" <?= ($oldInput['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
                <label for="registerGender" class="form-label auth-field__label">Gender</label>
              </div>
              <div class="invalid-feedback">Please select a valid gender option.</div>
            </div>

            <div class="auth-login-field auth-field auth-register-grid__full">
              <div class="auth-field__control">
                <input
                  type="text"
                  class="form-control"
                  id="registerAddress"
                  name="address"
                  value="<?= \App\Helpers\Helper::escape($oldInput['address'] ?? '') ?>"
                  maxlength="1000"
                  placeholder=" "
                  autocomplete="street-address"
                >
                <label for="registerAddress" class="form-label auth-field__label">Address</label>
              </div>
            </div>
          </div>
        </details>

        <div class="auth-login-field auth-register-consent">
          <div class="form-check">
            <input
              class="form-check-input<?= $termsInvalid ? ' is-invalid' : '' ?>"
              type="checkbox"
              value="1"
              id="acceptTerms"
              name="terms"
              <?= $oldTerms ? 'checked' : '' ?>
              required
            >
            <div class="auth-register-consent__copy">
              <label class="form-check-label" for="acceptTerms">
                I agree to the use of my information for MBPHA telehealth services.
              </label>
              <div class="invalid-feedback">Please confirm you understand how your information will be used.</div>
            </div>
          </div>
        </div>

        <div class="auth-login-actions">
          <button
            type="submit"
            class="btn auth-login-submit"
            data-auth-submit
            data-loading-label="Creating account..."
          >
            <i class="bi bi-person-plus me-2" aria-hidden="true"></i>
            <span data-auth-submit-label>Create patient account</span>
          </button>
          <?php if ($googleClientId !== ''): ?>
          <div class="auth-login-or" role="separator" aria-label="or">OR</div>
          <div
            id="mbphaGoogleSignIn"
            class="auth-login-google"
            data-google-auth
            data-client-id="<?= \App\Helpers\Helper::escape($googleClientId) ?>"
            data-auth-url="<?= \App\Helpers\Helper::escape(\App\Helpers\Helper::url('/auth/google')) ?>"
            data-error-generic="This Google account could not be used to create or access a patient account."
            data-error-unavailable="Google sign-in is unavailable right now. Please create an account with email and password."
            data-busy-label="Continuing with Google…"
          >
            <div
              id="mbphaGoogleSignInButton"
              class="auth-login-google__button"
              data-google-auth-button
              role="group"
              aria-label="Continue with Google"
            ></div>
            <p class="auth-login-google-status" data-google-auth-status aria-live="polite"></p>
          </div>
          <?php endif; ?>
        </div>
      </form>
    </article>
  </div>
</main>

<?php if ($googleClientId !== ''): ?>
<script src="<?= \App\Helpers\Helper::asset('js/google-auth.js') ?>"></script>
<script src="https://accounts.google.com/gsi/client" async defer onload="window.mbphaGoogleGisLoaded && window.mbphaGoogleGisLoaded()"></script>
<?php endif; ?>
