<?php
/**
 * Public landing page — hero, service overview, and sign-in CTA.
 */
defined('APP_VERSION') or define('APP_VERSION', '1.0.0');

$helperClass = \App\Helpers\Helper::class;
$_u = static function (string $path = '') use ($helperClass): string {
    return $helperClass::url($path);
};
?>

<?php require __DIR__ . '/../partials/public/navbar.php'; ?>

<main class="public-shell" aria-label="MBPHA TeleHealth home">
    <?php require __DIR__ . '/../partials/home/hero.php'; ?>
    <?php require __DIR__ . '/../partials/home/workflow.php'; ?>
    <?php require __DIR__ . '/../partials/home/cta.php'; ?>
</main>

<?php require __DIR__ . '/../partials/public/footer.php'; ?>
