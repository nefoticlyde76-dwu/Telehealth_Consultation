<?php

$patient = $patient ?? [];
$errors = $errors ?? [];
$fieldErrors = $fieldErrors ?? [];
$formData = $formData ?? [];
$genderOptions = $genderOptions ?? [];
$statusOptions = $statusOptions ?? [];
$csrfToken = $csrfToken ?? '';
$statusMessage = $statusMessage ?? null;
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-page-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-start align-items-lg-center gap-3 mb-0">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/admin/dashboard') ?>">Dashboard</a></li>
        <li><a href="<?= \App\Helpers\Helper::url('/admin/patients') ?>">Patients</a></li>
        <li class="active">Edit</li>
      </ol>
      <span class="section-badge mb-3">
        <i class="bi bi-person-gear"></i>
        Patient Account Maintenance
      </span>
      <h2 class="ux-page-header__title h4 mb-2">Edit patient account</h2>
      <p class="ux-page-header__subtitle text-muted mb-0">Update patient identity, demographics, and access status while preserving the existing registration foundation.</p>
    </div>
    <div class="ux-page-header__right">
      <a href="<?= \App\Helpers\Helper::url('/admin/patients') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-arrow-left me-2"></i>
        Back to Patient Management
      </a>
    </div>
  </div>
</section>

<form method="POST" action="<?= \App\Helpers\Helper::url('/admin/patients/' . (int) ($patient['id'] ?? 0) . '/edit') ?>" class="needs-validation" novalidate>
  <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken) ?>">
  <?php require __DIR__ . '/_form.php'; ?>

  <div class="d-flex justify-content-end gap-2 mt-4">
    <a href="<?= \App\Helpers\Helper::url('/admin/patients') ?>" class="btn btn-outline-primary btn-sm">Cancel</a>
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="bi bi-save me-2"></i>
      Save Patient Changes
    </button>
  </div>
</form>
