<?php

$doctor = $doctor ?? [];
$errors = $errors ?? [];
$fieldErrors = $fieldErrors ?? [];
$formData = $formData ?? [];
$genderOptions = $genderOptions ?? [];
$statusOptions = $statusOptions ?? [];
$csrfToken = $csrfToken ?? '';
$statusMessage = $statusMessage ?? null;
$isInvitationPending = \App\Helpers\Status::isInvitationPendingUserStatus((string) ($doctor['status'] ?? ''));
$showStatusField = !$isInvitationPending;
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-page-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-start align-items-lg-center gap-3 mb-0">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/admin/dashboard') ?>">Dashboard</a></li>
        <li><a href="<?= \App\Helpers\Helper::url('/admin/doctors') ?>">Doctors</a></li>
        <li class="active">Edit</li>
      </ol>
      <span class="section-badge mb-3">
        <i class="bi bi-pencil-square"></i>
        Doctor Account Maintenance
      </span>
      <h2 class="ux-page-header__title h4 mb-2">Edit doctor account</h2>
      <p class="ux-page-header__subtitle text-muted mb-0">Update clinician identity, professional profile, and secure access status while preserving the linked doctor record.</p>
    </div>
    <div class="ux-page-header__right d-flex flex-wrap gap-2">
      <?php if (!$isInvitationPending): ?>
      <a href="<?= \App\Helpers\Helper::url('/admin/doctors/' . (int) ($doctor['id'] ?? 0) . '/reset-password') ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-key me-2"></i>
        Reset Password
      </a>
      <?php endif; ?>
      <a href="<?= \App\Helpers\Helper::url('/admin/doctors') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-arrow-left me-2"></i>
        Back to Doctor Accounts
      </a>
    </div>
  </div>
</section>

<form method="POST" action="<?= \App\Helpers\Helper::url('/admin/doctors/' . (int) ($doctor['id'] ?? 0) . '/edit') ?>" class="needs-validation" novalidate>
  <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken) ?>">
  <?php require __DIR__ . '/_form.php'; ?>

  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= \App\Helpers\Helper::url('/admin/doctors') ?>" class="btn btn-outline-primary btn-sm">Cancel</a>
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="bi bi-save me-2"></i>
      Save Doctor Changes
    </button>
  </div>
</form>
