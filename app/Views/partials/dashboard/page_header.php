<?php

$pageHeaderTitle = (string) ($pageHeaderTitle ?? $dashboardTitle ?? '');
$pageHeaderSubtitle = (string) ($pageHeaderSubtitle ?? $dashboardDescription ?? '');
$pageHeaderBreadcrumbs = is_array($pageHeaderBreadcrumbs ?? null) ? $pageHeaderBreadcrumbs : [];
$pageHeaderActions = $pageHeaderActions ?? '';
$pageHeaderCompact = !empty($pageHeaderCompact);
$pageHeaderHeadingTag = (string) ($pageHeaderHeadingTag ?? 'h1');
if (!in_array($pageHeaderHeadingTag, ['h1', 'h2'], true)) {
    $pageHeaderHeadingTag = 'h1';
}
?>

<div class="ux-page-header<?= $pageHeaderCompact ? ' ux-page-header--home' : '' ?>">
  <div class="ux-page-header__left">
    <?php if ($pageHeaderBreadcrumbs !== []): ?>
      <nav aria-label="Breadcrumb">
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
              <li class="active" aria-current="page"><?= \App\Helpers\Helper::escape($crumbLabel) ?></li>
            <?php endif; ?>
          <?php endforeach; ?>
        </ol>
      </nav>
    <?php endif; ?>

    <?php if ($pageHeaderTitle !== ''): ?>
      <<?= $pageHeaderHeadingTag ?> class="ux-page-header__title"><?= \App\Helpers\Helper::escape($pageHeaderTitle) ?></<?= $pageHeaderHeadingTag ?>>
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
<?php
$pageHeaderCompact = false;
$pageHeaderActions = '';
?>
