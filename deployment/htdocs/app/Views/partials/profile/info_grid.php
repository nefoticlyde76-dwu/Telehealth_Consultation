<?php

use App\Helpers\Helper;

$tiles = is_array($tiles ?? null) ? $tiles : [];
?>

<div class="user-profile-info-grid">
  <?php foreach ($tiles as $tile): ?>
    <?php
    $label = (string) ($tile['label'] ?? '');
    $value = (string) ($tile['value'] ?? '');
    $icon = (string) ($tile['icon'] ?? '');
    $tone = (string) ($tile['tone'] ?? '');
    $wide = !empty($tile['wide']);
    $valueClass = 'user-profile-tile__value' . ($tone !== '' ? ' is-' . $tone : '');
    ?>
    <div class="user-profile-tile<?= $wide ? ' user-profile-tile--wide' : '' ?>">
      <?php if ($icon !== ''): ?>
        <span class="user-profile-tile__icon" aria-hidden="true">
          <i class="bi <?= Helper::escape($icon) ?>"></i>
        </span>
      <?php endif; ?>
      <span class="user-profile-tile__body">
        <span class="user-profile-tile__label"><?= Helper::escape($label) ?></span>
        <strong class="<?= Helper::escape($valueClass) ?>"><?= Helper::escape($value) ?></strong>
      </span>
    </div>
  <?php endforeach; ?>
</div>
