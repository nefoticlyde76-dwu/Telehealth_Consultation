<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $title ?? 'TeleHealth Consultation System' ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/style.css') ?>">
</head>
<body class="<?= \App\Helpers\Helper::escape($bodyClass ?? 'public-layout') ?>">
  <?= $content ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= \App\Helpers\Helper::asset('js/app.js') ?>"></script>
  <?= $pageScripts ?? '' ?>
</body>
</html>
