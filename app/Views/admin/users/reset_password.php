<?php
use App\Helpers\Helper;
use App\Helpers\Status;

$managedUser = is_array($managedUser ?? null) ? $managedUser : [];
$errors = is_array($errors ?? null) ? $errors : [];
$fieldErrors = is_array($fieldErrors ?? null) ? $fieldErrors : [];
$csrfToken = (string) ($csrfToken ?? '');
$userId = (int) ($managedUser['id'] ?? 0);
$userName = (string) ($managedUser['full_name'] ?? 'User');
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-page-header d-flex flex-column flex-lg-row justify-content-between gap-3 mb-0">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= Helper::url('/admin/dashboard') ?>">Dashboard</a></li>
        <li><a href="<?= Helper::url('/admin/users') ?>">Users</a></li>
        <li><a href="<?= Helper::url('/admin/users/' . $userId) ?>"><?= Helper::escape($userName) ?></a></li>
        <li class="active">Reset password</li>
      </ol>
      <h2 class="ux-page-header__title">Reset password</h2>
      <p class="ux-page-header__subtitle">Issue a secure replacement password. The current password is never displayed.</p>
    </div>
    <div class="ux-page-header__right">
      <a href="<?= Helper::url('/admin/users/' . $userId) ?>" class="btn btn-outline-primary btn-sm">Back to user</a>
    </div>
  </div>
</section>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="ux-card">
      <div class="card-body p-4">
        <h3 class="h6 mb-3">Selected account</h3>
        <div class="user-detail-grid">
          <div class="user-detail-item">
            <span class="user-detail-label">Name</span>
            <strong><?= Helper::escape($userName) ?></strong>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Email</span>
            <strong><?= Helper::escape((string) ($managedUser['email'] ?? '')) ?></strong>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Role</span>
            <strong><?= Helper::escape(ucfirst((string) ($managedUser['role_name'] ?? ''))) ?></strong>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Status</span>
            <strong><?= Helper::escape(Status::label((string) ($managedUser['status'] ?? ''), Status::DOMAIN_USER)) ?></strong>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="ux-card">
      <div class="card-body p-4">
        <?php if ($errors !== []): ?>
          <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
              <div><?= Helper::escape((string) $error) ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <form method="POST" action="<?= Helper::url('/admin/users/' . $userId . '/reset-password') ?>" novalidate>
          <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
          <div class="mb-3">
            <label for="password" class="form-label">New password</label>
            <input type="password" class="form-control <?= isset($fieldErrors['password']) ? 'is-invalid' : '' ?>" id="password" name="password" autocomplete="new-password" required>
            <div class="invalid-feedback"><?= Helper::escape($fieldErrors['password'] ?? 'A strong password is required.') ?></div>
          </div>
          <div class="mb-3">
            <label for="confirm_password" class="form-label">Confirm new password</label>
            <input type="password" class="form-control <?= isset($fieldErrors['confirm_password']) ? 'is-invalid' : '' ?>" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
            <div class="invalid-feedback"><?= Helper::escape($fieldErrors['confirm_password'] ?? 'Please confirm the new password.') ?></div>
          </div>
          <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" value="1" id="force_password_reset" name="force_password_reset" checked>
            <label class="form-check-label" for="force_password_reset">Require this user to choose a new password at next login</label>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Reset password</button>
        </form>
      </div>
    </div>
  </div>
</div>
