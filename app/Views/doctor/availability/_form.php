<?php

$formData = $formData ?? [];
$fieldErrors = $fieldErrors ?? [];
$statusOptions = $statusOptions ?? [];
?>

<div class="row g-3">
  <div class="col-md-6">
    <label for="consultation_date" class="form-label">Consultation Date</label>
    <input
      type="date"
      class="form-control <?= isset($fieldErrors['consultation_date']) ? 'is-invalid' : '' ?>"
      id="consultation_date"
      name="consultation_date"
      value="<?= \App\Helpers\Helper::escape((string) ($formData['consultation_date'] ?? '')) ?>"
      required
    >
    <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['consultation_date'] ?? 'Consultation date is required.') ?></div>
  </div>

  <div class="col-md-3">
    <label for="start_time" class="form-label">Start Time</label>
    <input
      type="time"
      class="form-control <?= isset($fieldErrors['start_time']) ? 'is-invalid' : '' ?>"
      id="start_time"
      name="start_time"
      value="<?= \App\Helpers\Helper::escape((string) ($formData['start_time'] ?? '')) ?>"
      required
    >
    <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['start_time'] ?? 'Start time is required.') ?></div>
  </div>

  <div class="col-md-3">
    <label for="end_time" class="form-label">End Time</label>
    <input
      type="time"
      class="form-control <?= isset($fieldErrors['end_time']) ? 'is-invalid' : '' ?>"
      id="end_time"
      name="end_time"
      value="<?= \App\Helpers\Helper::escape((string) ($formData['end_time'] ?? '')) ?>"
      required
    >
    <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['end_time'] ?? 'End time is required.') ?></div>
  </div>

  <div class="col-md-4">
    <label for="status" class="form-label">Status</label>
    <select class="form-select <?= isset($fieldErrors['status']) ? 'is-invalid' : '' ?>" id="status" name="status">
      <?php foreach ($statusOptions as $statusOption): ?>
        <option value="<?= \App\Helpers\Helper::escape($statusOption) ?>" <?= ($formData['status'] ?? 'Available') === $statusOption ? 'selected' : '' ?>>
          <?= \App\Helpers\Helper::escape($statusOption) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['status'] ?? 'Please select a valid availability status.') ?></div>
  </div>

  <div class="col-12">
    <label for="notes" class="form-label">Optional Notes</label>
    <textarea
      class="form-control <?= isset($fieldErrors['notes']) ? 'is-invalid' : '' ?>"
      id="notes"
      name="notes"
      rows="4"
      maxlength="1000"
      placeholder="Add optional notes for this availability slot"
    ><?= \App\Helpers\Helper::escape((string) ($formData['notes'] ?? '')) ?></textarea>
    <div class="invalid-feedback"><?= \App\Helpers\Helper::escape($fieldErrors['notes'] ?? 'Optional notes must be 1000 characters or fewer.') ?></div>
  </div>
</div>
