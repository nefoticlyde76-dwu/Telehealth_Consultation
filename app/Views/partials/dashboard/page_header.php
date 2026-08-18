<?php

$pageHeaderTitle = (string) ($pageHeaderTitle ?? $dashboardTitle ?? '');
$pageHeaderSubtitle = (string) ($pageHeaderSubtitle ?? '');
$pageHeaderBreadcrumbs = is_array($pageHeaderBreadcrumbs ?? null) ? $pageHeaderBreadcrumbs : [];
$pageHeaderActions = $pageHeaderActions ?? '';
?>

<div class="ux-page-header">
  <div class="ux-page-header__left">
    <?php if ($pageHeaderBreadcrumbs !== []): ?>
      <ol class="ux-breadcrumb">
        <?php foreach ($pageHeaderBreadcrumbs as $crumb): ?>
          <?php
          $crumbLabel = (string) ($crumb['label'] ?? '');
          $crumbUrl = (string) ($crumb['url'] ?? '');
          $crumbActive = !empty($crumb['active']) || $crumbUrl === '';
          ?>
          <?php if (!$crumbActive): ?>
            <li>
              <a href="<?= \App\Helpers\Helper::url($crumbUrl) ?>"><?= \App\Helpers\Helper::escape($crumbLabel) ?></a>
            </li>
          <?php else: ?>
            <li class="active"><?= \App\Helpers\Helper::escape($crumbLabel) ?></li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>

    <?php if ($pageHeaderTitle !== ''): ?>
      <h1 class="ux-page-header__title"><?= \App\Helpers\Helper::escape($pageHeaderTitle) ?></h1>
    <?php endif; ?>

    <?php if ($pageHeaderSubtitle !== ''): ?>
      <p class="ux-page-header__subtitle"><?= \App\Helpers\Helper::escape($pageHeaderSubtitle) ?></p>
    <?php endif; ?>
  </div>

  <?php if ($pageHeaderActions !== ''): ?>
    <div class="ux-page-header__right">
      <?= $pageHeaderActions ?>
    </div>
  <?php endif; ?>
</div>
