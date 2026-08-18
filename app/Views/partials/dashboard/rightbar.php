<?php

$dashboardRole = (string) ($dashboardRole ?? '');
$rightbar = $rightbar ?? [];
$upcomingTitle = (string) ($rightbar['upcomingTitle'] ?? 'Upcoming');
$upcomingItems = is_array($rightbar['upcomingItems'] ?? null) ? $rightbar['upcomingItems'] : [];
$quickActions = is_array($rightbar['quickActions'] ?? null) ? $rightbar['quickActions'] : [];

$renderRightbarContent = static function () use ($upcomingTitle, $upcomingItems, $quickActions): void {
    ?>
    <div class="rightbar-section rightbar-section--calendar">
      <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <h2 class="h6 mb-0">Calendar</h2>
        <span class="badge badge-soft-info rounded-pill px-3 py-2" data-dashboard-datetime="date">Today</span>
      </div>
      <div class="rightbar-calendar" data-mini-calendar style="height:auto;min-height:auto;max-height:none;overflow:visible;"></div>
    </div>

    <div class="rightbar-section">
      <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <h2 class="h6 mb-0"><?= \App\Helpers\Helper::escape($upcomingTitle) ?></h2>
        <span class="text-muted small"><?= \App\Helpers\Helper::escape((string) count($upcomingItems)) ?></span>
      </div>

      <?php if ($upcomingItems === []): ?>
        <div class="rightbar-empty">
          <div class="rightbar-empty-icon"><i class="bi bi-calendar2-check"></i></div>
          <p class="text-muted mb-0">No upcoming consultations.</p>
        </div>
      <?php else: ?>
        <div class="rightbar-list">
          <?php foreach ($upcomingItems as $item): ?>
            <div class="rightbar-list-item">
              <div class="rightbar-list-icon">
                <i class="bi <?= \App\Helpers\Helper::escape((string) ($item['icon'] ?? 'bi-dot')) ?>"></i>
              </div>
              <div class="flex-grow-1">
                <strong class="d-block rightbar-list-title"><?= \App\Helpers\Helper::escape((string) ($item['title'] ?? '')) ?></strong>
                <span class="d-block small text-muted"><?= \App\Helpers\Helper::escape((string) ($item['meta'] ?? '')) ?></span>
              </div>
              <?php if (!empty($item['badge'])): ?>
                <span class="badge badge-soft-neutral rounded-pill px-3 py-2"><?= \App\Helpers\Helper::escape((string) $item['badge']) ?></span>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($quickActions !== []): ?>
      <div class="rightbar-section">
        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
          <h2 class="h6 mb-0">Quick Actions</h2>
        </div>
        <div class="d-grid gap-2">
          <?php foreach ($quickActions as $action): ?>
            <a href="<?= \App\Helpers\Helper::url((string) ($action['url'] ?? '#')) ?>" class="btn btn-outline-primary rightbar-action">
              <i class="bi <?= \App\Helpers\Helper::escape((string) ($action['icon'] ?? 'bi-lightning-charge')) ?>"></i>
              <?= \App\Helpers\Helper::escape((string) ($action['label'] ?? 'Action')) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
    <?php
};
?>

<aside class="dashboard-rightbar d-none d-xl-flex flex-column">
  <?= $renderRightbarContent() ?>
</aside>

<div class="offcanvas offcanvas-end dashboard-rightbar-offcanvas" tabindex="-1" id="dashboardRightbar" aria-labelledby="dashboardRightbarLabel">
  <div class="offcanvas-header border-bottom">
    <div>
      <h2 class="h5 mb-0" id="dashboardRightbarLabel">Overview</h2>
      <p class="text-muted small mb-0">Calendar and next steps</p>
    </div>
    <button type="button" class="btn-close shadow-none" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <?= $renderRightbarContent() ?>
  </div>
</div>
