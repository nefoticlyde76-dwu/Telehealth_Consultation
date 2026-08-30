<?php

/**
 * Result count + numbered pagination that preserves filter query state.
 *
 * @var array{current_page?:int,per_page?:int,total_items?:int,total_pages?:int,from?:int,to?:int} $pagination
 * @var string $paginationPath
 * @var array<string,mixed> $paginationFilters
 * @var string $paginationLabel
 * @var string $paginationAria
 * @var bool $paginationShowCount
 */

$pagination = is_array($pagination ?? null) ? $pagination : [];
$paginationPath = (string) ($paginationPath ?? '');
$paginationFilters = is_array($paginationFilters ?? null) ? $paginationFilters : [];
$paginationLabel = (string) ($paginationLabel ?? 'results');
$paginationAria = (string) ($paginationAria ?? 'Pagination');
$paginationShowCount = (bool) ($paginationShowCount ?? true);
$paginationBuildUrl = isset($paginationBuildUrl) && is_callable($paginationBuildUrl)
    ? $paginationBuildUrl
    : null;

$totalItems = (int) ($pagination['total_items'] ?? 0);
$totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
$currentPage = max(1, (int) ($pagination['current_page'] ?? 1));
$from = (int) ($pagination['from'] ?? ($totalItems === 0 ? 0 : 1));
$to = (int) ($pagination['to'] ?? $totalItems);
$showPager = $totalPages > 1 && ($paginationPath !== '' || $paginationBuildUrl !== null);

if (!$paginationShowCount && !$showPager) {
    return;
}

$buildPageUrl = $paginationBuildUrl ?? static function (int $page) use ($paginationPath, $paginationFilters): string {
    return \App\Helpers\ListFilter::url($paginationPath, $paginationFilters, ['page' => $page]);
};
?>

<div class="ux-pagination d-flex flex-wrap align-items-center justify-content-between gap-3">
  <?php if ($paginationShowCount): ?>
    <p class="ux-pagination__count mb-0">
      <?php if ($totalItems === 0): ?>
        Showing 0 <?= \App\Helpers\Helper::escape($paginationLabel) ?>
      <?php elseif ($showPager): ?>
        Showing <?= (int) $from ?>–<?= (int) $to ?> of <?= (int) $totalItems ?> <?= \App\Helpers\Helper::escape($paginationLabel) ?>
      <?php else: ?>
        Showing <?= (int) $totalItems ?> <?= \App\Helpers\Helper::escape($paginationLabel) ?>
      <?php endif; ?>
    </p>
  <?php endif; ?>

  <?php if ($showPager): ?>
    <nav class="ux-pagination__nav d-flex flex-wrap align-items-center gap-2" aria-label="<?= \App\Helpers\Helper::escape($paginationAria) ?>">
      <?php if ($currentPage > 1): ?>
        <a class="btn btn-outline-primary btn-sm" href="<?= $buildPageUrl($currentPage - 1) ?>">
          <i class="bi bi-chevron-left me-1"></i>
          Previous
        </a>
      <?php else: ?>
        <span class="btn btn-outline-primary btn-sm disabled" aria-disabled="true">
          <i class="bi bi-chevron-left me-1"></i>
          Previous
        </span>
      <?php endif; ?>

      <ul class="ux-pagination__pages list-unstyled d-flex flex-wrap align-items-center gap-1 mb-0">
        <?php foreach (\App\Helpers\ListFilter::pageNumbers($currentPage, $totalPages) as $pageNumber): ?>
          <?php if ($pageNumber === 0): ?>
            <li class="ux-pagination__ellipsis" aria-hidden="true">…</li>
          <?php elseif ($pageNumber === $currentPage): ?>
            <li>
              <a class="btn btn-primary btn-sm" href="<?= $buildPageUrl($pageNumber) ?>" aria-current="page">
                <?= (int) $pageNumber ?>
              </a>
            </li>
          <?php else: ?>
            <li>
              <a class="btn btn-outline-primary btn-sm" href="<?= $buildPageUrl($pageNumber) ?>">
                <?= (int) $pageNumber ?>
              </a>
            </li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ul>

      <?php if ($currentPage < $totalPages): ?>
        <a class="btn btn-outline-primary btn-sm" href="<?= $buildPageUrl($currentPage + 1) ?>">
          Next
          <i class="bi bi-chevron-right ms-1"></i>
        </a>
      <?php else: ?>
        <span class="btn btn-outline-primary btn-sm disabled" aria-disabled="true">
          Next
          <i class="bi bi-chevron-right ms-1"></i>
        </span>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</div>
