<?php

$formData = $formData ?? [];
$errors = $errors ?? [];
$fieldErrors = $fieldErrors ?? [];
$statusOptions = $statusOptions ?? [];
$statusMessage = $statusMessage ?? null;
$csrfToken = $csrfToken ?? '';
$formData['status'] = 'Available';
$showStatus = true;
$disabled = false;
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-page-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-start align-items-lg-center gap-3 mb-0">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/doctor/dashboard') ?>">Dashboard</a></li>
        <li><a href="<?= \App\Helpers\Helper::url('/doctor/availability') ?>">Availability</a></li>
        <li class="active">Create</li>
      </ol>
      <span class="section-badge mb-3">
        <i class="bi bi-calendar-plus"></i>
        Create Availability
      </span>
      <h2 class="ux-page-header__title h4 mb-2">Schedule a new consultation slot</h2>
      <p class="ux-page-header__subtitle text-muted mb-0">Create a valid, conflict-free availability slot for future consultation workflows.</p>
    </div>
    <div class="ux-page-header__right d-flex flex-wrap gap-2">
      <a href="<?= \App\Helpers\Helper::url('/doctor/availability') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-calendar-week me-2"></i>
        View Availability
      </a>
      <a href="<?= \App\Helpers\Helper::url('/doctor/dashboard') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-arrow-left me-2"></i>
        Back to Dashboard
      </a>
    </div>
  </div>
</section>

<?php if ($errors !== []): ?>
  <div class="mb-4">
    <?php
    $statusMessage = null;
    require __DIR__ . '/../../partials/shared/alerts.php';
    ?>
  </div>
<?php endif; ?>

<section>
  <form method="POST" action="<?= \App\Helpers\Helper::url('/doctor/availability/create') ?>" class="needs-validation" novalidate>
    <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken) ?>">

    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
          <div>
            <h3 class="h5 mb-1">Availability Details</h3>
            <p class="text-muted mb-0">All availability slots are protected by date, time, duplicate, and overlap validation rules.</p>
          </div>
          <span class="ux-chip ux-badge--dotless ux-badge--neutral">
            <i class="bi bi-calendar2-check"></i>
            <span>Available by default</span>
          </span>
        </div>

        <?php require __DIR__ . '/_form.php'; ?>

        <div class="d-flex justify-content-end gap-2 mt-4">
          <a href="<?= \App\Helpers\Helper::url('/doctor/availability') ?>" class="btn btn-outline-primary btn-sm">Cancel</a>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="bi bi-save me-2"></i>
            Create Availability
          </button>
        </div>
      </div>
    </div>
  </form>
</section>
