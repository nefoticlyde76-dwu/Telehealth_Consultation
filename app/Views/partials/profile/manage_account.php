<?php

use App\Helpers\Helper;

$profilePage = is_array($profilePage ?? null) ? $profilePage : [];
$actions = is_array($profilePage['actions'] ?? null) ? $profilePage['actions'] : [];
$csrfToken = (string) ($profilePage['csrf_token'] ?? $csrfToken ?? '');
$userId = (int) ($profilePage['user_id'] ?? $userId ?? 0);
$userName = (string) ($profilePage['name'] ?? $userName ?? 'User');
$forceReset = is_array($profilePage['force_password_reset'] ?? null) ? $profilePage['force_password_reset'] : [];
$manageExtraHtml = (string) ($profilePage['manage_extra_html'] ?? '');
$returnTo = (string) ($profilePage['return_to'] ?? '');
?>

<aside class="user-profile-card user-profile-card--manage">
  <h3 class="user-profile-card__title">
    <i class="bi bi-gear" aria-hidden="true"></i>
    Manage account
  </h3>

  <div class="user-profile-manage__actions">
    <?php foreach ($actions as $action): ?>
      <?php
      $key = (string) ($action['key'] ?? '');
      if ($key === 'view') {
          continue;
      }
      $tone = (string) ($action['tone'] ?? 'neutral');
      $actionClass = match ($tone) {
          'success' => 'user-profile-action user-profile-action--success',
          'warning' => 'user-profile-action user-profile-action--warning',
          'danger' => 'user-profile-action user-profile-action--danger',
          'destroy' => 'user-profile-action user-profile-action--destroy',
          default => 'user-profile-action',
      };
      $icon = user_profile_action_icon($key);
      $label = (string) ($action['label'] ?? '');
      ?>
      <?php if (($action['method'] ?? 'GET') === 'GET'): ?>
        <a href="<?= Helper::url((string) $action['url']) ?>" class="<?= Helper::escape($actionClass) ?>">
          <i class="bi <?= Helper::escape($icon) ?>" aria-hidden="true"></i>
          <span><?= Helper::escape($label) ?></span>
          <i class="bi bi-chevron-right user-profile-action__chevron" aria-hidden="true"></i>
        </a>
      <?php elseif ($key === 'delete'): ?>
        <button
          type="button"
          class="<?= Helper::escape($actionClass) ?>"
          data-bs-toggle="modal"
          data-bs-target="#permanentDeleteModal"
          data-delete-url="<?= Helper::escape(Helper::url((string) $action['url'])) ?>"
          data-delete-name="<?= Helper::escape($userName) ?>"
        >
          <i class="bi <?= Helper::escape($icon) ?>" aria-hidden="true"></i>
          <?= Helper::escape($label) ?>
        </button>
      <?php else: ?>
        <?php
        $confirmTitle = match ($key) {
            'suspend' => 'Suspend this account?',
            'deactivate' => 'Deactivate this account?',
            'reactivate' => 'Reactivate this account?',
            default => '',
        };
        $confirmBody = match ($key) {
            'suspend' => 'The user will be temporarily blocked from signing in. This is not permanent deletion.',
            'deactivate' => 'The account will be disabled and retained for operational history. This is not permanent deletion.',
            'reactivate' => 'This user will be able to sign in again.',
            default => '',
        };
        $confirmHint = $key === 'reactivate'
            ? 'You can change the status again later if needed.'
            : 'You can reactivate the account later if this was a mistake.';
        $confirmTone = $key === 'reactivate' ? 'primary' : 'danger';
        ?>
        <form
          method="POST"
          action="<?= Helper::url((string) $action['url']) ?>"
          <?php if ($confirmTitle !== ''): ?>
            data-confirm-title="<?= Helper::escape($confirmTitle) ?>"
            data-confirm-body="<?= Helper::escape($confirmBody) ?>"
            data-confirm-hint="<?= Helper::escape($confirmHint) ?>"
            data-confirm-tone="<?= Helper::escape($confirmTone) ?>"
          <?php endif; ?>
        >
          <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
          <?php if ($returnTo !== ''): ?>
            <input type="hidden" name="return_to" value="<?= Helper::escape($returnTo) ?>">
          <?php endif; ?>
          <button type="submit" class="<?= Helper::escape($actionClass) ?>">
            <i class="bi <?= Helper::escape($icon) ?>" aria-hidden="true"></i>
            <?= Helper::escape($label) ?>
          </button>
        </form>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <?php if (!empty($forceReset['show'])): ?>
    <form
      method="POST"
      action="<?= Helper::url((string) ($forceReset['url'] ?? '')) ?>"
      data-confirm-title="Require a password change?"
      data-confirm-body="This user will have to choose a new password at next login. The current password is not shown."
      data-confirm-hint="This does not permanently delete the account."
      data-confirm-tone="primary"
    >
      <input type="hidden" name="_token" value="<?= Helper::escape((string) ($forceReset['csrf'] ?? $csrfToken)) ?>">
      <button type="submit" class="user-profile-force-reset">
        <span class="user-profile-force-reset__box<?= !empty($forceReset['checked']) ? ' is-checked' : '' ?>" aria-hidden="true">
          <i class="bi bi-check-lg"></i>
        </span>
        <span>Require password change at next login</span>
      </button>
    </form>
  <?php endif; ?>

  <?php if ($manageExtraHtml !== ''): ?>
    <div class="mt-3">
      <?= $manageExtraHtml ?>
    </div>
  <?php endif; ?>
</aside>
