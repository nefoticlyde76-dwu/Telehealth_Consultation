<!DOCTYPE html>
<html lang="en" data-bs-theme="light" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= $title ?? 'MBPHA TeleHealth Consultation System' ?></title>
  <link rel="icon" type="image/png" href="/favicon.png">
  <link rel="apple-touch-icon" href="/favicon.png">
  <script>
    (function () {
      try {
        var stored = window.localStorage.getItem("mbpha-theme");
        var theme = (stored === "dark" || stored === "light")
          ? stored
          : (window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");
        document.documentElement.setAttribute("data-bs-theme", theme);
        document.documentElement.setAttribute("data-theme", theme);
      } catch (e) {}
    })();
  </script>
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/fonts.css') ?>">
  <?= \App\Helpers\Cdn::stylesheet(\App\Helpers\Cdn::BOOTSTRAP_CSS, \App\Helpers\Cdn::BOOTSTRAP_CSS_INTEGRITY) ?>
  <?= \App\Helpers\Cdn::stylesheet(\App\Helpers\Cdn::BOOTSTRAP_ICONS_CSS, \App\Helpers\Cdn::BOOTSTRAP_ICONS_CSS_INTEGRITY) ?>
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/theme.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/style.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/design-system.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/dashboard-ui.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/mbpha-calendar.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/wallet-heroes.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/user-profile.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/responsive.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/dashboard-home.css') ?>">
  <?= $pageStyles ?? '' ?>
</head>
<body class="dashboard-layout dashboard-layout--<?= \App\Helpers\Helper::escape((string) ($dashboardRole ?? 'default')) ?>"
      data-app-timezone="<?= \App\Helpers\Helper::escape(\App\Helpers\Helper::appTimezone()) ?>"
      data-server-now="<?= \App\Helpers\Helper::escape(\App\Helpers\Helper::nowIso()) ?>">
  <script>
    (function () {
      if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
      if ("startViewTransition" in document) return;
      document.body.classList.add("ux-page-enter");
    })();
  </script>
  <a class="skip-link visually-hidden-focusable" href="#dashboard-main-content">Skip to main content</a>
  <?php $showRightbar = (bool) ($showRightbar ?? false); ?>
  <div class="dashboard-shell <?= $showRightbar ? 'dashboard-shell--with-rightbar' : '' ?>" id="dashboardShell">
    <script>
      (function () {
        var shell = document.getElementById("dashboardShell");
        if (!shell) return;
        try {
          if (window.localStorage.getItem("mbpha-dashboard-sidebar-collapsed") === "1") {
            shell.classList.add("dashboard-shell--sidebar-collapsed");
            shell.setAttribute("data-collapsed", "");
          }
          if (
            shell.classList.contains("dashboard-shell--with-rightbar") &&
            window.localStorage.getItem("mbpha-dashboard-rightbar-collapsed") === "1"
          ) {
            shell.classList.add("dashboard-shell--rightbar-collapsed");
            shell.setAttribute("data-rightbar-collapsed", "");
          }
        } catch (e) {}
      })();
    </script>
    <?php require __DIR__ . '/../partials/dashboard/topbar.php'; ?>
    <div class="dashboard-body">
      <?php require __DIR__ . '/../partials/dashboard/sidebar.php'; ?>

      <div class="dashboard-main">
        <main class="dashboard-content" id="dashboard-main-content">
          <?= $content ?>
        </main>
      </div>

      <?php if ($showRightbar): ?>
        <?php require __DIR__ . '/../partials/dashboard/rightbar.php'; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php require __DIR__ . '/../partials/dashboard/notification_toasts.php'; ?>

  <div class="modal fade" id="uxConfirmModal" tabindex="-1" aria-labelledby="uxConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content rounded-3">
        <div class="modal-header border-bottom">
          <h5 class="modal-title" id="uxConfirmModalLabel">Confirm</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-2" id="uxConfirmModalBody">This action cannot be undone easily.</p>
          <p class="small text-muted mb-0" id="uxConfirmModalHint">You can cancel if you are not sure.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger" id="uxConfirmModalSubmit">Confirm</button>
        </div>
      </div>
    </div>
  </div>

  <?= \App\Helpers\Cdn::script(\App\Helpers\Cdn::BOOTSTRAP_JS, \App\Helpers\Cdn::BOOTSTRAP_JS_INTEGRITY) ?>
  <?php if (!empty($includeChartJs)): ?>
  <?= \App\Helpers\Cdn::script(\App\Helpers\Cdn::CHART_JS, \App\Helpers\Cdn::CHART_JS_INTEGRITY) ?>
  <?php endif; ?>
  <script src="<?= \App\Helpers\Helper::asset('js/app.js') ?>"></script>
  <script src="<?= \App\Helpers\Helper::asset('js/dashboard.js') ?>"></script>
  <script src="<?= \App\Helpers\Helper::asset('js/table-filters.js') ?>"></script>
  <?= $pageScripts ?? '' ?>
</body>
</html>
