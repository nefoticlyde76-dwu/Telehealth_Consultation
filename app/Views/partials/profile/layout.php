<?php

require __DIR__ . '/_helpers.php';
require __DIR__ . '/../shared/status_helper.php';

$profilePage = is_array($profilePage ?? null) ? $profilePage : [];
$accountTiles = is_array($profilePage['account_tiles'] ?? null) ? $profilePage['account_tiles'] : [];
$history = is_array($profilePage['protected_history'] ?? null) ? $profilePage['protected_history'] : [];
$showHistory = !empty($history['show']);
$mainExtraHtml = (string) ($profilePage['main_extra_html'] ?? '');
$showDeleteModal = !empty($profilePage['show_delete_modal']);
$isDeleted = !empty($profilePage['is_deleted']);
?>

<div class="user-profile-page">
  <?php require __DIR__ . '/../shared/alerts.php'; ?>
  <?php require __DIR__ . '/header.php'; ?>

  <div class="user-profile-layout">
    <section class="user-profile-card">
      <?php require __DIR__ . '/identity.php'; ?>
      <?php
      $tiles = $accountTiles;
      require __DIR__ . '/info_grid.php';
      ?>
      <?php if ($mainExtraHtml !== ''): ?>
        <div class="mt-4">
          <?= $mainExtraHtml ?>
        </div>
      <?php endif; ?>
    </section>

    <?php require __DIR__ . '/manage_account.php'; ?>
  </div>

  <div class="user-profile-lower<?= $showHistory ? '' : ' user-profile-lower--solo' ?>">
    <?php require __DIR__ . '/role_card.php'; ?>
    <?php if ($showHistory): ?>
      <?php require __DIR__ . '/protected_history.php'; ?>
    <?php endif; ?>
  </div>
</div>

<?php if ($showDeleteModal && !$isDeleted): ?>
  <?php require __DIR__ . '/delete_modal.php'; ?>
<?php endif; ?>
