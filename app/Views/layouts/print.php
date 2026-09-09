<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= $title ?? 'MBPHA TeleHealth Consultation System' ?></title>
  <link rel="icon" type="image/png" href="/favicon.png">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/fonts.css') ?>">
  <?= \App\Helpers\Cdn::stylesheet(\App\Helpers\Cdn::BOOTSTRAP_CSS, \App\Helpers\Cdn::BOOTSTRAP_CSS_INTEGRITY) ?>
  <?= \App\Helpers\Cdn::stylesheet(\App\Helpers\Cdn::BOOTSTRAP_ICONS_CSS, \App\Helpers\Cdn::BOOTSTRAP_ICONS_CSS_INTEGRITY) ?>
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/theme.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/style.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/design-system.css') ?>">
  <?= $pageStyles ?? '' ?>
</head>
<body class="print-document-layout">
  <div class="print-document-toolbar cr-print-hide">
    <a href="<?= \App\Helpers\Helper::escape((string) ($returnUrl ?? '/')) ?>" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>
      <?= \App\Helpers\Helper::escape((string) ($returnLabel ?? 'Back')) ?>
    </a>
    <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
      <i class="bi bi-download me-1"></i>
      Print or save as PDF
    </button>
  </div>
  <?= $content ?>
</body>
</html>
