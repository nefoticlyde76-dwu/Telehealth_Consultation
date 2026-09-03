<?php
use App\Helpers\Helper;
use App\Helpers\Status;

$csrfToken = (string) ($csrfToken ?? '');
$errors = is_array($errors ?? null) ? $errors : [];
$fieldErrors = is_array($fieldErrors ?? null) ? $fieldErrors : [];
$sessions = is_array($sessions ?? null) ? $sessions : [];
$forcePasswordReset = !empty($forcePasswordReset);
$lastLoginAt = $lastLoginAt ?? null;
$accountStatus = (string) ($accountStatus ?? '');
$dashboardRole = (string) ($dashboardRole ?? '');
$dashboardHome = \App\Services\AuthService::getRoleRedirectUrl($dashboardRole);
?>

<section class="mb-4">
  <?php require __DIR__ . '/../partials/shared/alerts.php'; ?>

  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= Helper::url($dashboardHome) ?>">Dashboard</a></li>
        <li class="active">Security</li>
      </ol>
      <h2 class="ux-page-header__title">Security</h2>
      <p class="ux-page-header__subtitle">Change your password and sign out other devices. Passwords are stored as hashes only.</p>
    </div>
  </div>
</section>

<?php if ($forcePasswordReset): ?>
  <div class="alert alert-warning">Your administrator requires you to choose a new password before continuing.</div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="ux-card h-100">
      <div class="card-body p-4">
        <h3 class="h6 mb-1">Account</h3>
        <p class="text-muted small mb-4">Status: <?= Helper::escape(Status::label($accountStatus, Status::DOMAIN_USER)) ?><?= $lastLoginAt ? ' · Last login ' . Helper::escape(Helper::formatDate((string) $lastLoginAt, 'd M Y, g:i A', '')) : '' ?></p>

        <?php if ($errors !== []): ?>
          <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
              <div><?= Helper::escape((string) $error) ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="<?= Helper::url('/account/password') ?>" novalidate>
          <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
          <div class="mb-3">
            <label for="current_password" class="form-label">Current password</label>
            <input type="password" class="form-control <?= isset($fieldErrors['current_password']) ? 'is-invalid' : '' ?>" id="current_password" name="current_password" autocomplete="current-password" required>
            <div class="invalid-feedback"><?= Helper::escape($fieldErrors['current_password'] ?? 'Current password is required.') ?></div>
          </div>
          <div class="mb-3">
            <label for="password" class="form-label">New password</label>
            <input type="password" class="form-control <?= isset($fieldErrors['password']) ? 'is-invalid' : '' ?>" id="password" name="password" autocomplete="new-password" required>
            <div class="invalid-feedback"><?= Helper::escape($fieldErrors['password'] ?? 'A strong password is required.') ?></div>
          </div>
          <div class="mb-4">
            <label for="confirm_password" class="form-label">Confirm new password</label>
            <input type="password" class="form-control <?= isset($fieldErrors['confirm_password']) ? 'is-invalid' : '' ?>" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
            <div class="invalid-feedback"><?= Helper::escape($fieldErrors['confirm_password'] ?? 'Please confirm the new password.') ?></div>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Update password</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="ux-card h-100">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
          <div>
            <h3 class="h6 mb-1">Signed-in devices</h3>
            <p class="text-muted small mb-0">Session identifiers are stored as hashes. Secrets are not shown.</p>
          </div>
          <form method="POST" action="<?= Helper::url('/account/sessions/logout-others') ?>" data-confirm-title="Sign out other devices?" data-confirm-body="This device stays signed in. Other sessions will need to sign in again.">
            <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
            <button type="submit" class="btn btn-outline-primary btn-sm">Log out of all other devices</button>
          </form>
        </div>

        <?php if ($sessions === []): ?>
          <p class="text-muted mb-0">No additional session records are available yet.</p>
        <?php else: ?>
          <ul class="security-session-list">
            <?php foreach ($sessions as $session): ?>
              <li class="security-session-list__item">
                <div>
                  <strong><?= Helper::escape((string) ($session['device_label'] ?? 'Device')) ?></strong>
                  <?php if (!empty($session['is_current'])): ?>
                    <span class="ux-badge ux-badge--approved">This device</span>
                  <?php endif; ?>
                  <div class="small text-muted">
                    <?= Helper::escape((string) ($session['ip_address'] ?? 'Unknown IP')) ?>
                    · Last seen <?= Helper::escape(Helper::formatDate((string) ($session['last_seen_at'] ?? ''), 'd M Y, g:i A', '—')) ?>
                  </div>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
