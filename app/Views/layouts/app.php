<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php require __DIR__ . '/../partials/public/seo_head.php'; ?>
  <link rel="icon" type="image/png" href="<?= \App\Helpers\Helper::asset('images/LOGOS.png') ?>">
  <link rel="apple-touch-icon" href="<?= \App\Helpers\Helper::asset('images/LOGOS.png') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/theme.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/style.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/design-system.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/auth-login.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/auth-register.css') ?>">
  <link rel="stylesheet" href="<?= \App\Helpers\Helper::asset('css/home.css') ?>">
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
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= \App\Helpers\Helper::asset('js/app.js') ?>"></script>
  <?= $pageScripts ?? '' ?>
</body>
</html>
