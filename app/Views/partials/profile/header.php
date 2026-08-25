<?php

use App\Helpers\Helper;

$profilePage = is_array($profilePage ?? null) ? $profilePage : [];
$breadcrumbs = is_array($profilePage['breadcrumbs'] ?? null) ? $profilePage['breadcrumbs'] : [];
$title = (string) ($profilePage['title'] ?? '');
$subtitle = (string) ($profilePage['subtitle'] ?? '');
$back = is_array($profilePage['back'] ?? null) ? $profilePage['back'] : null;
?>

<header class="user-profile-header">
  <div class="user-profile-header__copy">
    <?php if ($breadcrumbs !== []): ?>
      <nav aria-label="Breadcrumb">
        <ol class="ux-breadcrumb">
          <?php foreach ($breadcrumbs as $index => $crumb): ?>
            <?php
            $crumbLabel = (string) ($crumb['label'] ?? '');
            $crumbUrl = (string) ($crumb['url'] ?? '');
            $isLast = $index === array_key_last($breadcrumbs);
            ?>
            <?php if (!$isLast && $crumbUrl !== ''): ?>
              <li>
                <a href="<?= Helper::url($crumbUrl) ?>"><?= Helper::escape($crumbLabel) ?></a>
              </li>
            <?php else: ?>
              <li class="active" aria-current="page"><?= Helper::escape($crumbLabel) ?></li>
            <?php endif; ?>
          <?php endforeach; ?>
        </ol>
      </nav>
    <?php endif; ?>

    <?php if ($title !== ''): ?>
      <h1 class="user-profile-header__title"><?= Helper::escape($title) ?></h1>
    <?php endif; ?>

    <?php if ($subtitle !== ''): ?>
      <p class="user-profile-header__subtitle"><?= Helper::escape($subtitle) ?></p>
    <?php endif; ?>
  </div>

  <?php
  $headerLinks = is_array($profilePage['header_links'] ?? null) ? $profilePage['header_links'] : [];
  $hasHeaderActions = (is_array($back) && ($back['url'] ?? '') !== '') || $headerLinks !== [];
  ?>
  <?php if ($hasHeaderActions): ?>
    <div class="user-profile-header__actions">
      <?php foreach ($headerLinks as $link): ?>
        <a href="<?= Helper::url((string) ($link['url'] ?? '#')) ?>" class="user-profile-header__back">
          <?= Helper::escape((string) ($link['label'] ?? 'Open')) ?>
        </a>
      <?php endforeach; ?>
      <?php if (is_array($back) && ($back['url'] ?? '') !== ''): ?>
        <a href="<?= Helper::url((string) $back['url']) ?>" class="user-profile-header__back">
          <?= Helper::escape((string) ($back['label'] ?? 'Back')) ?>
        </a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</header>
