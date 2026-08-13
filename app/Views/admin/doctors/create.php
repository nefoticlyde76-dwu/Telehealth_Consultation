<?php

$errors = $errors ?? [];
$fieldErrors = $fieldErrors ?? [];
$formData = $formData ?? [];
$genderOptions = $genderOptions ?? [];
$statusOptions = $statusOptions ?? [];
$csrfToken = $csrfToken ?? '';
$statusMessage = $statusMessage ?? null;
$showPasswordFields = true;
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-page-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-start align-items-lg-center gap-3 mb-0">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/admin/dashboard') ?>">Dashboard</a></li>
        <li><a href="<?= \App\Helpers\Helper::url('/admin/doctors') ?>">Doctors</a></li>
        <li class="active">Create</li>
      </ol>
      <span class="section-badge mb-3">
        <i class="bi bi-person-plus-fill"></i>
        Doctor Account Provisioning
      </span>
      <h2 class="ux-page-header__title h4 mb-2">Create a new doctor account</h2>
      <p class="ux-page-header__subtitle text-muted mb-0">Provision a clinician login securely so the doctor can access the shared MBPHA TeleHealth platform.</p>
    </div>
    <div class="ux-page-header__right">
      <a href="<?= \App\Helpers\Helper::url('/admin/doctors') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-arrow-left me-2"></i>
        Back to Doctor Accounts
      </a>
    </div>
  </div>
</section>

<form method="POST" action="<?= \App\Helpers\Helper::url('/admin/doctors/create') ?>" class="needs-validation" novalidate>
  <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken) ?>">
  <?php require __DIR__ . '/_form.php'; ?>

  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= \App\Helpers\Helper::url('/admin/doctors') ?>" class="btn btn-outline-primary btn-sm">Cancel</a>
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="bi bi-person-plus-fill me-2"></i>
      Create Doctor Account
    </button>
  </div>
</form>
