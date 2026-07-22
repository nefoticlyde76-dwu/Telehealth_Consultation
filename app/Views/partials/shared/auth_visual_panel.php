<?php

$authVisualBadge = $authVisualBadge ?? 'MBPHA TeleHealth';
$authVisualTitle = $authVisualTitle ?? 'Secure healthcare access for Milne Bay Province.';
$authVisualCopy = $authVisualCopy ?? 'Professional digital care begins with a trusted, accessible, and secure entry point.';
$authVisualQuote = $authVisualQuote ?? 'Serving patients, clinicians, and administrators through a premium healthcare platform.';
?>

<div class="auth-visual-panel">
  <div class="auth-visual-content">
    <div class="mb-4">
      <?php
      $brandVariant = 'auth';
      $brandSubtitle = '';
      $brandShowTitle = false;
      $brandLink = \App\Helpers\Helper::url('/');
      require __DIR__ . '/brand_logo.php';
      ?>
    </div>

    <span class="section-badge section-badge-inverse mb-3">
      <i class="bi bi-hospital"></i>
      <?= \App\Helpers\Helper::escape($authVisualBadge) ?>
    </span>

    <h1 class="display-6 fw-bold text-white mb-3"><?= \App\Helpers\Helper::escape($authVisualTitle) ?></h1>
    <p class="auth-visual-copy mb-4"><?= \App\Helpers\Helper::escape($authVisualCopy) ?></p>

    <div class="auth-visual-quote">
      <span class="mini-label text-white-50">Alotau General Hospital</span>
      <p class="mb-0 text-white"><?= \App\Helpers\Helper::escape($authVisualQuote) ?></p>
    </div>
  </div>
</div>
