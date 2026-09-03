<?php

$formData = $formData ?? [];
$fieldErrors = $fieldErrors ?? [];
$showStatus = $showStatus ?? true;
$disabled = $disabled ?? false;
$returnWeek = (string) ($returnWeek ?? '');
?>

<?php if ($returnWeek !== ''): ?>
  <input type="hidden" name="return_week" value="<?= \App\Helpers\Helper::escape($returnWeek) ?>">
<?php endif; ?>

<div class="row g-3">
  <div class="col-md-6">
    <label for="consultation_date" class="form-label">Consultation Date <span class="text-danger">*</span></label>
    <input
      type="date"
      class="form-control <?= isset($fieldErrors['consultation_date']) ? 'is-invalid' : '' ?>"
      id="consultation_date"
      name="consultation_date"
      value="<?= \App\Helpers\Helper::escape((string) ($formData['consultation_date'] ?? '')) ?>"
      <?= $disabled ? 'disabled' : '' ?>
      required
    >
    <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['consultation_date'] ?? 'Consultation date is required.') ?></div>
  </div>

  <div class="col-md-3">
    <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
    <input
      type="time"
      class="form-control <?= isset($fieldErrors['start_time']) ? 'is-invalid' : '' ?>"
      id="start_time"
      name="start_time"
      step="60"
      value="<?= \App\Helpers\Helper::escape((string) ($formData['start_time'] ?? '')) ?>"
      <?= $disabled ? 'disabled' : '' ?>
      required
    >
    <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['start_time'] ?? 'Start time is required.') ?></div>
  </div>

  <div class="col-md-3">
    <label for="end_time" class="form-label">End Time <span class="text-danger">*</span></label>
    <input
      type="time"
      class="form-control <?= isset($fieldErrors['end_time']) ? 'is-invalid' : '' ?>"
      id="end_time"
      name="end_time"
      step="60"
      value="<?= \App\Helpers\Helper::escape((string) ($formData['end_time'] ?? '')) ?>"
      <?= $disabled ? 'disabled' : '' ?>
      required
    >
    <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['end_time'] ?? 'End time is required.') ?></div>
  </div>

  <?php if ($showStatus): ?>
    <div class="col-md-4">
      <label class="form-label">Status</label>
      <div class="form-control bg-body-tertiary">
        <?= \App\Helpers\Helper::escape((string) ($formData['status'] ?? 'Available')) ?>
      </div>
      <input type="hidden" name="status" value="<?= \App\Helpers\Helper::escape((string) ($formData['status'] ?? 'Available')) ?>">
    </div>
  <?php endif; ?>

  <div class="col-12">
    <label for="notes" class="form-label">Optional Notes</label>
    <textarea
      class="form-control <?= isset($fieldErrors['notes']) ? 'is-invalid' : '' ?>"
      id="notes"
      name="notes"
      rows="4"
      maxlength="1000"
      placeholder="Add optional notes for this availability slot"
      <?= $disabled ? 'disabled' : '' ?>
    ><?= \App\Helpers\Helper::escape((string) ($formData['notes'] ?? '')) ?></textarea>
    <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['notes'] ?? 'Optional notes must be 1000 characters or fewer.') ?></div>
  </div>
</div>
