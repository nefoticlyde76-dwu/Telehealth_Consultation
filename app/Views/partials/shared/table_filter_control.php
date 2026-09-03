<?php

/**
 * Compact filter control bound to a table GET form via the form="" attribute.
 *
 * @var array<string,mixed> $filterControl
 * @var string $tableFilterId
 * @var string $controlClass
 */

use App\Helpers\Helper;
use App\Helpers\ListFilter;

$filterControl = is_array($filterControl ?? null) ? $filterControl : [];
$tableFilterId = (string) ($tableFilterId ?? '');
$controlClass = trim((string) ($controlClass ?? 'form-control form-control-sm ux-th__control'));
$fieldType = (string) ($filterControl['type'] ?? 'select');
$fieldName = (string) ($filterControl['name'] ?? '');
if ($fieldName === '' || $tableFilterId === '') {
    return;
}

$fieldId = (string) ($filterControl['id'] ?? '');
if ($fieldId === '') {
    $fieldId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $tableFilterId . '-' . $fieldName) ?: ($tableFilterId . '-field');
}
$fieldLabel = (string) ($filterControl['label'] ?? $fieldName);
$fieldValue = (string) ($filterControl['value'] ?? '');
$placeholder = (string) ($filterControl['placeholder'] ?? '');
$keepEmpty = !empty($filterControl['keep_empty']);
$controlAria = $fieldLabel !== '' ? $fieldLabel : $fieldName;

if ($fieldType === 'select' || $fieldType === 'date_preset') {
    $options = ListFilter::selectOptions(is_array($filterControl['options'] ?? null) ? $filterControl['options'] : []);
    $emptyLabel = (string) ($filterControl['empty_label'] ?? 'All');
    $includeEmpty = array_key_exists('include_empty', $filterControl) ? (bool) $filterControl['include_empty'] : true;
    $selectClass = str_replace('form-control', 'form-select', $controlClass);
    if (!str_contains($selectClass, 'form-select')) {
        $selectClass = 'form-select form-select-sm ux-th__control';
    }
    ?>
    <select
      class="<?= Helper::escape($selectClass) ?>"
      id="<?= Helper::escape($fieldId) ?>"
      name="<?= Helper::escape($fieldName) ?>"
      form="<?= Helper::escape($tableFilterId) ?>"
      aria-label="<?= Helper::escape($controlAria) ?>"
      <?php if ($fieldType === 'date_preset'): ?>data-date-preset<?php endif; ?>
      <?php if ($keepEmpty): ?>data-keep-empty<?php endif; ?>
    >
      <?php if ($includeEmpty): ?>
        <option value=""><?= Helper::escape($emptyLabel) ?></option>
      <?php endif; ?>
      <?php foreach ($options as $option): ?>
        <option
          value="<?= Helper::escape((string) $option['value']) ?>"
          <?= $fieldValue === (string) $option['value'] ? 'selected' : '' ?>
        >
          <?= Helper::escape((string) $option['label']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <?php
    if ($fieldType === 'date_preset') {
        $rangeFrom = (string) ($filterControl['from_value'] ?? '');
        $rangeTo = (string) ($filterControl['to_value'] ?? '');
        $rangeVisible = $fieldValue === 'custom';
        ?>
        <div class="ux-th-date__range<?= $rangeVisible ? '' : ' d-none' ?>" data-date-range <?= $rangeVisible ? '' : 'hidden' ?>>
          <label class="visually-hidden" for="<?= Helper::escape($fieldId) ?>-from">From date</label>
          <input
            type="date"
            class="form-control form-control-sm ux-th__control"
            id="<?= Helper::escape($fieldId) ?>-from"
            name="date_from"
            form="<?= Helper::escape($tableFilterId) ?>"
            value="<?= Helper::escape($rangeFrom) ?>"
            <?= $rangeVisible ? '' : 'disabled' ?>
          >
          <label class="visually-hidden" for="<?= Helper::escape($fieldId) ?>-to">To date</label>
          <input
            type="date"
            class="form-control form-control-sm ux-th__control"
            id="<?= Helper::escape($fieldId) ?>-to"
            name="date_to"
            form="<?= Helper::escape($tableFilterId) ?>"
            value="<?= Helper::escape($rangeTo) ?>"
            <?= $rangeVisible ? '' : 'disabled' ?>
          >
        </div>
        <?php
    }
    return;
}

$inputType = $fieldType === 'search' ? 'search' : $fieldType;
if (!in_array($inputType, ['search', 'text', 'date', 'number'], true)) {
    $inputType = 'text';
}
?>
<input
  type="<?= Helper::escape($inputType) ?>"
  class="<?= Helper::escape($controlClass) ?>"
  id="<?= Helper::escape($fieldId) ?>"
  name="<?= Helper::escape($fieldName) ?>"
  form="<?= Helper::escape($tableFilterId) ?>"
  value="<?= Helper::escape($fieldValue) ?>"
  placeholder="<?= Helper::escape($placeholder) ?>"
  aria-label="<?= Helper::escape($controlAria) ?>"
  <?php if ($inputType === 'search' || $inputType === 'text'): ?>
    maxlength="100"
    autocomplete="off"
  <?php endif; ?>
  <?php if ($keepEmpty): ?>data-keep-empty<?php endif; ?>
>
