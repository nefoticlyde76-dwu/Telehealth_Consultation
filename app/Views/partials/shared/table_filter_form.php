<?php

/**
 * GET form that owns table toolbar and column filter controls.
 *
 * Controls live outside this element and submit through form="{id}".
 *
 * @var array<string,mixed> $filterForm
 */

use App\Helpers\Helper;

$filterForm = is_array($filterForm ?? null) ? $filterForm : [];
$tableFilterId = (string) ($filterForm['id'] ?? 'table-filter');
$filterAction = (string) ($filterForm['action'] ?? '');
$filterHidden = is_array($filterForm['hidden'] ?? null) ? $filterForm['hidden'] : [];
?>
<form
  id="<?= Helper::escape($tableFilterId) ?>"
  method="GET"
  action="<?= Helper::escape($filterAction) ?>"
  class="ux-table-filter-form"
  data-table-filter-form
  novalidate
>
  <?php foreach ($filterHidden as $hiddenName => $hiddenValue): ?>
    <input
      type="hidden"
      name="<?= Helper::escape((string) $hiddenName) ?>"
      value="<?= Helper::escape((string) $hiddenValue) ?>"
    >
  <?php endforeach; ?>
  <button type="submit" class="visually-hidden" tabindex="-1">Apply filters</button>
</form>
