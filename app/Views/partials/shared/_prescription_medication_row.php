<?php

$index = (int) ($index ?? 0);
$line = is_array($line ?? null) ? $line : [];
$name = (string) ($line['medication_name'] ?? '');
$dosage = (string) ($line['dosage'] ?? '');
$frequency = (string) ($line['frequency'] ?? '');
$duration = (string) ($line['duration'] ?? '');
$quantity = (string) ($line['quantity'] ?? '');
?>

<div class="rx-med-row" data-rx-medication-row>
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label">Medication name <span class="text-danger">*</span></label>
      <input type="text" class="form-control" name="medications[<?= $index ?>][medication_name]" maxlength="255" value="<?= \App\Helpers\Helper::escape($name) ?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Strength / dosage <span class="text-danger">*</span></label>
      <input type="text" class="form-control" name="medications[<?= $index ?>][dosage]" maxlength="255" value="<?= \App\Helpers\Helper::escape($dosage) ?>" placeholder="e.g. 500 mg" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Directions / instructions <span class="text-danger">*</span></label>
      <input type="text" class="form-control" name="medications[<?= $index ?>][frequency]" maxlength="255" value="<?= \App\Helpers\Helper::escape($frequency) ?>" placeholder="e.g. 1 tablet three times daily" required>
    </div>
    <div class="col-md-3">
      <label class="form-label">Duration</label>
      <input type="text" class="form-control" name="medications[<?= $index ?>][duration]" maxlength="255" value="<?= \App\Helpers\Helper::escape($duration) ?>" placeholder="e.g. 5 days">
    </div>
    <div class="col-md-3">
      <label class="form-label">Quantity</label>
      <input type="text" class="form-control" name="medications[<?= $index ?>][quantity]" maxlength="100" value="<?= \App\Helpers\Helper::escape($quantity) ?>" placeholder="e.g. 15 tablets">
    </div>
  </div>
  <button type="button" class="btn btn-link btn-sm text-danger px-0 mt-2 d-none" data-rx-remove-row>
    Remove this medication
  </button>
</div>
