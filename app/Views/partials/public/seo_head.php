<?php
/**
 * Public-layout SEO tags. Titles stay in one place so pages do not
 * emit duplicate <title> or description elements.
 */
$seo = \App\Helpers\Seo::forRequest(
    isset($title) && is_string($title) ? $title : null,
    isset($metaDescription) && is_string($metaDescription) ? $metaDescription : null,
    isset($robots) && is_string($robots) ? $robots : null
);
$seoHelper = \App\Helpers\Helper::class;
$seoImage = \App\Helpers\Seo::defaultImageUrl();
?>
  <title><?= $seoHelper::escape($seo['title']) ?></title>
<?php if ($seo['description'] !== null): ?>
  <meta name="description" content="<?= $seoHelper::escape($seo['description']) ?>">
<?php endif; ?>
<?php if ($seo['robots'] !== null): ?>
  <meta name="robots" content="<?= $seoHelper::escape($seo['robots']) ?>">
<?php endif; ?>
<?php if ($seo['canonical'] !== null): ?>
  <link rel="canonical" href="<?= $seoHelper::escape($seo['canonical']) ?>">
<?php endif; ?>
<?php if ($seo['openGraph']): ?>
  <meta property="og:title" content="<?= $seoHelper::escape($seo['title']) ?>">
  <?php if ($seo['description'] !== null): ?>
  <meta property="og:description" content="<?= $seoHelper::escape($seo['description']) ?>">
  <?php endif; ?>
  <meta property="og:url" content="<?= $seoHelper::escape((string) $seo['canonical']) ?>">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?= $seoHelper::escape(\App\Helpers\Seo::SITE_NAME) ?>">
  <meta property="og:locale" content="en">
  <meta property="og:image" content="<?= $seoHelper::escape($seoImage) ?>">
  <meta property="og:image:alt" content="<?= $seoHelper::escape(\App\Helpers\Seo::SITE_NAME . ' logo') ?>">
  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="<?= $seoHelper::escape($seo['title']) ?>">
  <?php if ($seo['description'] !== null): ?>
  <meta name="twitter:description" content="<?= $seoHelper::escape($seo['description']) ?>">
  <?php endif; ?>
  <meta name="twitter:image" content="<?= $seoHelper::escape($seoImage) ?>">
<?php endif; ?>
<?php if (!empty($seo['jsonLd']) && is_array($seo['jsonLd'])): ?>
  <script type="application/ld+json"><?= \App\Helpers\Seo::encodeJsonLd($seo['jsonLd']) ?></script>
<?php endif; ?>
