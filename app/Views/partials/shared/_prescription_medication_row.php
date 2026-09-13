<?php

$index = (int) ($index ?? 0);
$line = is_array($line ?? null) ? $line : [];
$name = (string) ($line['medication_name'] ?? '');
$dosage = (string) ($line['dosage'] ?? '');
$frequency = (string) ($line['frequency'] ?? '');
$duration = (string) ($line['duration'] ?? '');
$quantity = (string) ($line['quantity'] ?? '');
$notes = (string) ($line['additional_notes'] ?? '');
?>

<div class="rx-med-row rx-medication-card" data-rx-medication-row>
  <div class="rx-medication-card__head">
    <h4 class="rx-medication-card__title">
      Medication <span class="rx-medication-card__index" aria-hidden="true"></span>
    </h4>
    <button type="button" class="btn btn-link btn-sm text-danger px-0 rx-medication-card__remove d-none" data-rx-remove-row>
      Remove
    </button>
  </div>

  <div class="rx-medication-card__grid">
    <div class="rx-field rx-field--full">
      <label class="form-label" for="rx-med-name-<?= $index ?>">Medication <span class="text-danger">*</span></label>
      <input
        type="text"
        class="form-control"
        id="rx-med-name-<?= $index ?>"
        name="medications[<?= $index ?>][medication_name]"
        maxlength="255"
        value="<?= \App\Helpers\Helper::escape($name) ?>"
        autocomplete="off"
        required
      >
    </div>
    <div class="rx-field">
      <label class="form-label" for="rx-med-dosage-<?= $index ?>">Dosage <span class="text-danger">*</span></label>
      <input
        type="text"
        class="form-control"
        id="rx-med-dosage-<?= $index ?>"
        name="medications[<?= $index ?>][dosage]"
        maxlength="255"
        value="<?= \App\Helpers\Helper::escape($dosage) ?>"
        placeholder="e.g. 500 mg"
        autocomplete="off"
        required
      >
    </div>
    <div class="rx-field">
      <label class="form-label" for="rx-med-frequency-<?= $index ?>">Frequency <span class="text-danger">*</span></label>
      <input
        type="text"
        class="form-control"
        id="rx-med-frequency-<?= $index ?>"
        name="medications[<?= $index ?>][frequency]"
        maxlength="255"
        value="<?= \App\Helpers\Helper::escape($frequency) ?>"
        placeholder="e.g. 3 times daily"
        autocomplete="off"
        required
      >
    </div>
    <div class="rx-field">
      <label class="form-label" for="rx-med-duration-<?= $index ?>">Duration</label>
      <input
        type="text"
        class="form-control"
        id="rx-med-duration-<?= $index ?>"
        name="medications[<?= $index ?>][duration]"
        maxlength="255"
        value="<?= \App\Helpers\Helper::escape($duration) ?>"
        placeholder="e.g. 7 days"
        autocomplete="off"
      >
    </div>
    <div class="rx-field">
      <label class="form-label" for="rx-med-quantity-<?= $index ?>">Quantity</label>
      <input
        type="text"
        class="form-control"
        id="rx-med-quantity-<?= $index ?>"
        name="medications[<?= $index ?>][quantity]"
        maxlength="100"
        value="<?= \App\Helpers\Helper::escape($quantity) ?>"
        placeholder="e.g. 21 tablets"
        autocomplete="off"
      >
    </div>
    <div class="rx-field rx-field--full">
      <label class="form-label" for="rx-med-notes-<?= $index ?>">Instructions</label>
      <textarea
        class="form-control rx-field__notes"
        id="rx-med-notes-<?= $index ?>"
        name="medications[<?= $index ?>][additional_notes]"
        maxlength="2000"
        rows="2"
        placeholder="e.g. Take after meals"
      ><?= \App\Helpers\Helper::escape($notes) ?></textarea>
    </div>
  </div>
</div>
