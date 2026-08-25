<?php

use App\Helpers\Helper;

$history = is_array($history ?? null) ? $history : [];
$stats = is_array($history['stats'] ?? null) ? $history['stats'] : [];
$subtitle = (string) ($history['subtitle'] ?? 'These counts confirm related operational records. Their contents are not displayed.');
$notice = (string) ($history['notice'] ?? 'Sensitive operational data is hidden for non-clinical accounts.');
?>

<section class="user-profile-card user-profile-history">
  <h3 class="user-profile-card__title">
    <i class="bi bi-lock" aria-hidden="true"></i>
    Protected history
  </h3>
  <p class="user-profile-card__lede"><?= Helper::escape($subtitle) ?></p>

  <div class="user-profile-history-grid">
    <?php foreach ($stats as $stat): ?>
      <?php
      $tone = (string) ($stat['tone'] ?? 'blue');
      ?>
      <div class="user-profile-stat">
        <span class="user-profile-stat__icon is-<?= Helper::escape($tone) ?>" aria-hidden="true">
          <i class="bi <?= Helper::escape((string) ($stat['icon'] ?? 'bi-circle')) ?>"></i>
        </span>
        <span class="user-profile-stat__label"><?= Helper::escape((string) ($stat['label'] ?? '')) ?></span>
        <span class="user-profile-stat__value"><?= Helper::escape((string) ($stat['value'] ?? '0')) ?></span>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($notice !== ''): ?>
    <p class="user-profile-notice">
      <i class="bi bi-info-circle" aria-hidden="true"></i>
      <span><?= Helper::escape($notice) ?></span>
    </p>
  <?php endif; ?>
</section>
