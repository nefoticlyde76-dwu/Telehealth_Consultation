<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $title ?? 'MBPHA TeleHealth Consultation System' ?></title>
  <link rel="icon" type="image/png" href="<?= \App\Helpers\Helper::asset('images/LOGOS.png') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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
