<?php
defined('APP_VERSION') or define('APP_VERSION', '1.0.0');

$pageKicker = $pageKicker ?? '';
$pageTitle = $pageTitle ?? '';
$pageLede = $pageLede ?? '';
$pageBadge = $pageBadge ?? 'Milne Bay Provincial Health Authority';
$pageTitleHtml = $pageTitleHtml ?? \App\Helpers\Helper::escape($pageTitle);
$pageLedeHtml = $pageLedeHtml ?? \App\Helpers\Helper::escape($pageLede);
?>
<header class="pp-intro">
  <div class="pp-intro__surface" aria-hidden="true"></div>

  <div class="pp-intro__inner">
    <span class="pp-intro__badge">
      <i class="bi bi-shield-check" aria-hidden="true"></i>
      <span class="pp-intro__badge-full"><?= \App\Helpers\Helper::escape($pageBadge) ?></span>
      <span class="pp-intro__badge-short">MBPHA</span>
    </span>

    <?php if ($pageKicker !== ''): ?>
      <p class="pp-intro__kicker"><?= \App\Helpers\Helper::escape($pageKicker) ?></p>
    <?php endif; ?>
    <h1 id="pp-intro-title" class="pp-intro__title"><?= $pageTitleHtml ?></h1>
    <?php if ($pageLedeHtml !== ''): ?>
      <p class="pp-intro__lede<?= !empty($pageLedeShortHtml) ? ' pp-intro__lede--full' : '' ?>"><?= $pageLedeHtml ?></p>
    <?php endif; ?>
    <?php if (!empty($pageLedeShortHtml)): ?>
      <p class="pp-intro__lede pp-intro__lede--short"><?= $pageLedeShortHtml ?></p>
    <?php endif; ?>
  </div>
</header>
