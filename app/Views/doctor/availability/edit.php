<?php

$availability = $availability ?? [];
$formData = $formData ?? [];
$errors = $errors ?? [];
$fieldErrors = $fieldErrors ?? [];
$statusOptions = $statusOptions ?? [];
$statusMessage = $statusMessage ?? null;
$csrfToken = $csrfToken ?? '';
$isBooked = ($availability['status'] ?? '') !== 'Available';
$formData['status'] = (string) ($availability['status'] ?? ($formData['status'] ?? 'Available'));
$showStatus = true;
$disabled = $isBooked;
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
    <div>
      <span class="section-badge mb-3">
        <i class="bi bi-pencil-square"></i>
        Edit Availability
      </span>
      <h2 class="h4 mb-2">Update your consultation slot</h2>
      <p class="text-muted mb-0">Adjust the selected availability slot while preserving conflict-free scheduling.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="<?= \App\Helpers\Helper::url('/doctor/availability') ?>" class="btn btn-outline-primary rounded-pill px-4">
        <i class="bi bi-calendar-week me-2"></i>
        View Availability
      </a>
      <a href="<?= \App\Helpers\Helper::url('/doctor/dashboard') ?>" class="btn btn-outline-primary rounded-pill px-4">
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
  <form method="POST" action="<?= \App\Helpers\Helper::url('/doctor/availability/' . (int) ($availability['id'] ?? 0) . '/edit') ?>" class="needs-validation" novalidate>
    <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken) ?>">

    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
          <div>
            <h3 class="h5 mb-1">Availability Details</h3>
            <p class="text-muted mb-0">Update the date, time, and notes for the selected consultation slot.</p>
          </div>
          <span class="badge badge-soft-info rounded-pill px-3 py-2">
            Slot #<?= \App\Helpers\Helper::escape((string) ($availability['id'] ?? 0)) ?>
          </span>
        </div>

        <?php if ($isBooked): ?>
          <div class="alert alert-warning border-0 shadow-sm rounded-4 mb-4" role="alert">
            <div class="d-flex align-items-center gap-3">
              <i class="bi bi-exclamation-triangle-fill fs-4"></i>
              <div>This availability slot is booked and cannot be edited.</div>
            </div>
          </div>
        <?php endif; ?>

        <?php require __DIR__ . '/_form.php'; ?>

        <div class="d-flex justify-content-end gap-2 mt-4">
          <a href="<?= \App\Helpers\Helper::url('/doctor/availability') ?>" class="btn btn-outline-primary rounded-pill px-4">Cancel</a>
          <button type="submit" class="btn btn-primary rounded-pill px-4" <?= $isBooked ? 'disabled' : '' ?>>
            <i class="bi bi-save me-2"></i>
            Update Availability
          </button>
        </div>
      </div>
    </div>
  </form>
</section>
