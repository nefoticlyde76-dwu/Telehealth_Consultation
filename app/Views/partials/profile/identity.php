<?php

use App\Helpers\Helper;
use App\Helpers\Status;

$profilePage = is_array($profilePage ?? null) ? $profilePage : [];
$name = (string) ($profilePage['name'] ?? 'User');
$email = (string) ($profilePage['email'] ?? '');
$roleLabel = (string) ($profilePage['role_label'] ?? 'User');
$statusKey = (string) ($profilePage['status'] ?? '');
$photo = $profilePage['photo'] ?? null;
$photoEditUrl = trim((string) ($profilePage['photo_edit_url'] ?? ''));
$overflowActions = is_array($profilePage['overflow_actions'] ?? null) ? $profilePage['overflow_actions'] : [];
?>

<div class="user-profile-identity">
  <div class="user-profile-avatar">
    <?php
    $avatarPath = $photo;
    $fullName = $name;
    $avatarClass = 'user-avatar';
    require __DIR__ . '/../shared/user_avatar.php';
    ?>
    <?php if ($photoEditUrl !== ''): ?>
      <a
        class="user-profile-avatar__edit"
        href="<?= Helper::url($photoEditUrl) ?>"
        title="Edit profile photo"
        aria-label="Edit profile photo"
      >
        <i class="bi bi-pencil" aria-hidden="true"></i>
      </a>
    <?php endif; ?>
  </div>

  <div class="user-profile-identity__body">
    <div class="user-profile-identity__name-row">
      <h2 class="user-profile-identity__name"><?= Helper::escape($name) ?></h2>
      <span class="user-profile-role-badge"><?= Helper::escape($roleLabel) ?></span>
    </div>
    <?php if ($email !== ''): ?>
      <p class="user-profile-identity__email"><?= Helper::escape($email) ?></p>
    <?php endif; ?>
    <div class="user-profile-identity__status">
      <?= ux_status_badge($statusKey, Status::DOMAIN_USER) ?>
    </div>
  </div>

  <?php if ($overflowActions !== []): ?>
    <div class="user-profile-identity__menu dropdown">
      <button
        class="user-profile-overflow"
        type="button"
        data-bs-toggle="dropdown"
        aria-expanded="false"
        aria-label="Profile actions"
      >
        <i class="bi bi-three-dots-vertical" aria-hidden="true"></i>
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <?php foreach ($overflowActions as $item): ?>
          <li>
            <a class="dropdown-item" href="<?= Helper::url((string) ($item['url'] ?? '#')) ?>">
              <?= Helper::escape((string) ($item['label'] ?? 'Open')) ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
</div>
