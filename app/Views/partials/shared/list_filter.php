<?php

/**
 * Reusable Search → Filters → Sort form.
 *
 * @var array{
 *   action:string,
 *   title?:string,
 *   search?:array{name?:string,value?:string,placeholder?:string,label?:string}|null,
 *   fields?:list<array<string,mixed>>,
 *   clear_url:string,
 *   apply_label?:string,
 *   clear_label?:string
 * } $filterForm
 */

$filterForm = is_array($filterForm ?? null) ? $filterForm : [];
$filterAction = (string) ($filterForm['action'] ?? '');
$filterTitle = (string) ($filterForm['title'] ?? 'Filter results');
$filterSearch = is_array($filterForm['search'] ?? null) ? $filterForm['search'] : null;
$filterFields = is_array($filterForm['fields'] ?? null) ? $filterForm['fields'] : [];
$filterClearUrl = (string) ($filterForm['clear_url'] ?? $filterAction);
$filterApplyLabel = (string) ($filterForm['apply_label'] ?? 'Apply Filters');
$filterClearLabel = (string) ($filterForm['clear_label'] ?? 'Clear Filters');
$filterHidden = is_array($filterForm['hidden'] ?? null) ? $filterForm['hidden'] : [];

$dateRangeFrom = '';
$dateRangeTo = '';
$dateRangeVisible = false;
$hasDatePreset = false;
foreach ($filterFields as $field) {
    if (!is_array($field) || (string) ($field['type'] ?? '') !== 'date_preset') {
        continue;
    }
    $hasDatePreset = true;
    $dateRangeFrom = (string) ($field['from_value'] ?? '');
    $dateRangeTo = (string) ($field['to_value'] ?? '');
    $dateRangeVisible = (string) ($field['value'] ?? '') === 'custom';
    break;
}
?>

<div class="ux-card ux-filter">
  <div class="card-header">
    <h3 class="h6">
      <i class="bi bi-funnel-fill ux-filter__header-icon"></i>
      <?= \App\Helpers\Helper::escape($filterTitle) ?>
    </h3>
  </div>
  <form
    method="GET"
    action="<?= \App\Helpers\Helper::escape($filterAction) ?>"
    class="ux-filter__form"
    data-list-filter
    novalidate
  >
    <?php foreach ($filterHidden as $hiddenName => $hiddenValue): ?>
      <input
        type="hidden"
        name="<?= \App\Helpers\Helper::escape((string) $hiddenName) ?>"
        value="<?= \App\Helpers\Helper::escape((string) $hiddenValue) ?>"
      >
    <?php endforeach; ?>
    <?php if ($filterSearch !== null): ?>
      <?php
      $searchName = (string) ($filterSearch['name'] ?? 'search');
      $searchId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $searchName) ?: 'search';
      ?>
      <div class="ux-filter__search mb-3">
        <label for="<?= \App\Helpers\Helper::escape($searchId) ?>" class="form-label">
          <?= \App\Helpers\Helper::escape((string) ($filterSearch['label'] ?? 'Search')) ?>
        </label>
        <input
          type="search"
          class="form-control"
          id="<?= \App\Helpers\Helper::escape($searchId) ?>"
          name="<?= \App\Helpers\Helper::escape($searchName) ?>"
          value="<?= \App\Helpers\Helper::escape((string) ($filterSearch['value'] ?? '')) ?>"
          placeholder="<?= \App\Helpers\Helper::escape((string) ($filterSearch['placeholder'] ?? 'Search…')) ?>"
          maxlength="100"
          autocomplete="off"
        >
      </div>
    <?php endif; ?>

    <?php if ($filterFields !== []): ?>
      <div class="row g-3 ux-filter__grid align-items-end">
        <?php foreach ($filterFields as $index => $field): ?>
          <?php
          if (!is_array($field)) {
              continue;
          }
          $fieldType = (string) ($field['type'] ?? 'select');
          $fieldName = (string) ($field['name'] ?? '');
          if ($fieldName === '') {
              continue;
          }
          $fieldId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $fieldName) ?: ('filter-field-' . (string) $index);
          $fieldLabel = (string) ($field['label'] ?? $fieldName);
          $fieldValue = (string) ($field['value'] ?? '');
          $colClass = (string) ($field['col'] ?? 'col-sm-6 col-xl');
          ?>
          <div class="<?= \App\Helpers\Helper::escape($colClass) ?>">
            <label for="<?= \App\Helpers\Helper::escape($fieldId) ?>" class="form-label">
              <?= \App\Helpers\Helper::escape($fieldLabel) ?>
            </label>
            <?php if ($fieldType === 'select' || $fieldType === 'date_preset'): ?>
              <?php
              $options = \App\Helpers\ListFilter::selectOptions(is_array($field['options'] ?? null) ? $field['options'] : []);
              $emptyLabel = (string) ($field['empty_label'] ?? 'All');
              $includeEmpty = array_key_exists('include_empty', $field) ? (bool) $field['include_empty'] : true;
              ?>
              <select
                class="form-select"
                id="<?= \App\Helpers\Helper::escape($fieldId) ?>"
                name="<?= \App\Helpers\Helper::escape($fieldName) ?>"
                aria-label="<?= \App\Helpers\Helper::escape($fieldLabel) ?>"
                <?php if ($fieldType === 'date_preset'): ?>
                  data-date-preset
                <?php endif; ?>
              >
                <?php if ($includeEmpty): ?>
                  <option value=""><?= \App\Helpers\Helper::escape($emptyLabel) ?></option>
                <?php endif; ?>
                <?php foreach ($options as $option): ?>
                  <option
                    value="<?= \App\Helpers\Helper::escape((string) $option['value']) ?>"
                    <?= $fieldValue === (string) $option['value'] ? 'selected' : '' ?>
                  >
                    <?= \App\Helpers\Helper::escape((string) $option['label']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <input
                type="<?= \App\Helpers\Helper::escape($fieldType) ?>"
                class="form-control"
                id="<?= \App\Helpers\Helper::escape($fieldId) ?>"
                name="<?= \App\Helpers\Helper::escape($fieldName) ?>"
                value="<?= \App\Helpers\Helper::escape($fieldValue) ?>"
              >
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
        <div class="col-12 col-xl-auto ux-filter__actions-col">
          <div class="ux-filter__actions">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-funnel-fill me-1"></i>
              <?= \App\Helpers\Helper::escape($filterApplyLabel) ?>
            </button>
            <a href="<?= \App\Helpers\Helper::escape($filterClearUrl) ?>" class="btn btn-outline-primary btn-sm">
              <?= \App\Helpers\Helper::escape($filterClearLabel) ?>
            </a>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($hasDatePreset): ?>
      <div class="row g-3 ux-filter__range mt-0<?= $dateRangeVisible ? '' : ' d-none' ?>" data-date-range <?= $dateRangeVisible ? '' : 'hidden' ?>>
        <div class="col-sm-6 col-lg-4">
          <label for="date_from" class="form-label">From</label>
          <input type="date" class="form-control" id="date_from" name="date_from" value="<?= \App\Helpers\Helper::escape($dateRangeFrom) ?>">
        </div>
        <div class="col-sm-6 col-lg-4">
          <label for="date_to" class="form-label">To</label>
          <input type="date" class="form-control" id="date_to" name="date_to" value="<?= \App\Helpers\Helper::escape($dateRangeTo) ?>">
        </div>
      </div>
    <?php endif; ?>

    <?php if ($filterFields === []): ?>
      <div class="ux-filter__actions">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="bi bi-funnel-fill me-1"></i>
          <?= \App\Helpers\Helper::escape($filterApplyLabel) ?>
        </button>
        <a href="<?= \App\Helpers\Helper::escape($filterClearUrl) ?>" class="btn btn-outline-primary btn-sm">
          <?= \App\Helpers\Helper::escape($filterClearLabel) ?>
        </a>
      </div>
    <?php endif; ?>
  </form>
</div>
