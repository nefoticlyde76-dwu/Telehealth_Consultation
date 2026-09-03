<?php

/**
 * Table header cell contents: label plus an optional compact filter.
 *
 * @var string $colLabel
 * @var array<string,mixed>|null $colFilter
 * @var string $tableFilterId
 */

use App\Helpers\Helper;

$colLabel = (string) ($colLabel ?? '');
$colFilter = is_array($colFilter ?? null) ? $colFilter : null;
$tableFilterId = (string) ($tableFilterId ?? '');
?>
<div class="ux-th<?= $colFilter !== null ? ' ux-th--filter' : '' ?>">
  <span class="ux-th__label"><?= Helper::escape($colLabel) ?></span>
  <?php if ($colFilter !== null && $tableFilterId !== ''): ?>
    <?php
    $filterControl = $colFilter;
    $controlClass = 'form-control form-control-sm ux-th__control';
    require __DIR__ . '/table_filter_control.php';
    ?>
  <?php endif; ?>
</div>
