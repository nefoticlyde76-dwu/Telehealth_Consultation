<?php

/**
 * Notification-style All / category tabs for a list table header.
 *
 * @var list<array{value?:string,label?:string}> $filterTabs
 * @var string $filterTabCurrent
 * @var callable $filterTabUrl
 * @var string $filterTabAria
 */

use App\Helpers\Helper;

$filterTabs = is_array($filterTabs ?? null) ? $filterTabs : [];
$filterTabCurrent = (string) ($filterTabCurrent ?? '');
$filterTabAria = (string) ($filterTabAria ?? 'Filter results');
$filterTabsClass = trim((string) ($filterTabsClass ?? ''));
$filterTabUrl = isset($filterTabUrl) && is_callable($filterTabUrl) ? $filterTabUrl : null;

if ($filterTabs === [] || $filterTabUrl === null) {
    return;
}
?>
<div class="ux-table-filters<?= $filterTabsClass !== '' ? ' ' . Helper::escape($filterTabsClass) : '' ?>" role="group" aria-label="<?= Helper::escape($filterTabAria) ?>">
  <?php foreach ($filterTabs as $tab): ?>
    <?php
    if (!is_array($tab)) {
        continue;
    }
    $tabValue = (string) ($tab['value'] ?? '');
    $tabLabel = (string) ($tab['label'] ?? 'All');
    $tabActive = $filterTabCurrent === $tabValue;
    ?>
    <a
      href="<?= Helper::escape((string) $filterTabUrl($tabValue)) ?>"
      class="<?= $tabActive ? 'is-active' : '' ?>"
      <?= $tabActive ? 'aria-current="page"' : '' ?>
    ><?= Helper::escape($tabLabel) ?></a>
  <?php endforeach; ?>
</div>
<?php
$filterTabsClass = '';
?>
