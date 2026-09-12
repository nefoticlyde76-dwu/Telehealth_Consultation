<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php require __DIR__ . '/../partials/public/seo_head.php'; ?>
  <link rel="icon" type="image/png" href="/favicon.png">
  <link rel="apple-touch-icon" href="/favicon.png">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/fonts.css') ?>">
  <?= \App\Helpers\Cdn::stylesheet(\App\Helpers\Cdn::BOOTSTRAP_CSS, \App\Helpers\Cdn::BOOTSTRAP_CSS_INTEGRITY) ?>
  <?= \App\Helpers\Cdn::stylesheet(\App\Helpers\Cdn::BOOTSTRAP_ICONS_CSS, \App\Helpers\Cdn::BOOTSTRAP_ICONS_CSS_INTEGRITY) ?>
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/theme.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/style.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/design-system.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/auth-login.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/auth-register.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/home.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/app-ui.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/responsive.css') ?>">
</head>
<body class="<?= \App\Helpers\Helper::escape($bodyClass ?? 'public-layout') ?>">
  <script>
    (function () {
      if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
      if ("startViewTransition" in document) {
        try { sessionStorage.removeItem("mbpha-ux-pt"); } catch (e) {}
        return;
      }
      if (!document.body.classList.contains("public-layout")) return;
      try { sessionStorage.removeItem("mbpha-ux-pt"); } catch (e) {}
      if (document.body.classList.contains("auth-login-layout")) {
        document.body.classList.add("ux-page-enter");
        return;
      }
      document.body.classList.add("ux-pt-enter");
    })();
  </script>
  <?= $content ?>
  <?= \App\Helpers\Cdn::script(\App\Helpers\Cdn::BOOTSTRAP_JS, \App\Helpers\Cdn::BOOTSTRAP_JS_INTEGRITY) ?>
  <script src="<?= \App\Helpers\Helper::asset('js/app.js') ?>"></script>
  <?= $pageScripts ?? '' ?>
</body>
</html>
