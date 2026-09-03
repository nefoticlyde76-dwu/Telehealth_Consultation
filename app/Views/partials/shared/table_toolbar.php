<?php

/**
 * Compact table toolbar: search, sort / per-page, and clear.
 *
 * @var array<string,mixed> $filterForm
 */

use App\Helpers\Helper;

$filterForm = is_array($filterForm ?? null) ? $filterForm : [];
$tableFilterId = (string) ($filterForm['id'] ?? 'table-filter');
$filterSearch = is_array($filterForm['search'] ?? null) ? $filterForm['search'] : null;
$toolbarFields = is_array($filterForm['toolbar'] ?? null) ? $filterForm['toolbar'] : [];
$filterClearUrl = (string) ($filterForm['clear_url'] ?? ($filterForm['action'] ?? ''));
$filterActive = (bool) ($filterForm['active'] ?? false);
$filterClearLabel = (string) ($filterForm['clear_label'] ?? 'Clear');
?>
<div class="ux-table-toolbar">
  <?php if ($filterSearch !== null): ?>
    <?php
    $searchName = (string) ($filterSearch['name'] ?? 'search');
    $searchId = (string) ($filterSearch['id'] ?? '');
    if ($searchId === '') {
        $searchId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $tableFilterId . '-' . $searchName) ?: ($tableFilterId . '-search');
    }
    $searchLabel = (string) ($filterSearch['label'] ?? 'Search');
    ?>
    <div class="ux-table-toolbar__search">
      <label class="visually-hidden" for="<?= Helper::escape($searchId) ?>"><?= Helper::escape($searchLabel) ?></label>
      <i class="bi bi-search" aria-hidden="true"></i>
      <input
        type="search"
        class="form-control form-control-sm ux-table-toolbar__input"
        id="<?= Helper::escape($searchId) ?>"
        name="<?= Helper::escape($searchName) ?>"
        form="<?= Helper::escape($tableFilterId) ?>"
        value="<?= Helper::escape((string) ($filterSearch['value'] ?? '')) ?>"
        placeholder="<?= Helper::escape((string) ($filterSearch['placeholder'] ?? 'Search…')) ?>"
        maxlength="100"
        autocomplete="off"
      >
    </div>
  <?php endif; ?>

  <div class="ux-table-toolbar__tools">
    <?php foreach ($toolbarFields as $toolbarField): ?>
      <?php
      if (!is_array($toolbarField)) {
          continue;
      }
      $filterControl = $toolbarField;
      $controlClass = 'form-select form-select-sm ux-table-toolbar__select';
      require __DIR__ . '/table_filter_control.php';
      ?>
    <?php endforeach; ?>
    <?php if ($filterActive && $filterClearUrl !== ''): ?>
      <a href="<?= Helper::escape($filterClearUrl) ?>" class="btn btn-outline-secondary btn-sm ux-table-toolbar__clear">
        <?= Helper::escape($filterClearLabel) ?>
      </a>
    <?php endif; ?>
    <button type="submit" class="btn btn-outline-primary btn-sm ux-table-toolbar__apply" form="<?= Helper::escape($tableFilterId) ?>">
      Apply
    </button>
  </div>
</div>
