<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $title ?? 'MBPHA TeleHealth Consultation System' ?></title>
  <link rel="icon" type="image/png" href="<?= \App\Helpers\Helper::asset('images/LOGOS.png') ?>">
  <link rel="apple-touch-icon" href="<?= \App\Helpers\Helper::asset('images/LOGOS.png') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/theme.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/style.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/design-system.css') ?>">
  <?= $pageStyles ?? '' ?>
</head>
<body class="dashboard-layout dashboard-layout--<?= \App\Helpers\Helper::escape((string) ($dashboardRole ?? 'default')) ?>"
      data-app-timezone="<?= \App\Helpers\Helper::escape(\App\Helpers\Helper::appTimezone()) ?>">
  <?php $showRightbar = (bool) ($showRightbar ?? false); ?>
  <div class="dashboard-shell <?= $showRightbar ? 'dashboard-shell--with-rightbar' : '' ?>">
    <?php require __DIR__ . '/../partials/dashboard/sidebar.php'; ?>

    <div class="dashboard-main">
      <?php require __DIR__ . '/../partials/dashboard/topbar.php'; ?>

      <main class="dashboard-content">
        <?= $content ?>
      </main>
    </div>

    <?php if ($showRightbar): ?>
      <?php require __DIR__ . '/../partials/dashboard/rightbar.php'; ?>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
  <script src="<?= \App\Helpers\Helper::asset('js/app.js') ?>"></script>
  <script src="<?= \App\Helpers\Helper::asset('js/dashboard.js') ?>"></script>
  <?= $pageScripts ?? '' ?>
</body>
</html>
