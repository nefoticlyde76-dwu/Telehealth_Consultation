<?php

use App\Helpers\Helper;
use App\Services\AccountSecurityService;

$profilePage = is_array($profilePage ?? null) ? $profilePage : [];
$userId = (int) ($profilePage['user_id'] ?? $userId ?? 0);
$userName = (string) ($profilePage['name'] ?? $userName ?? 'User');
$csrfToken = (string) ($profilePage['csrf_token'] ?? $csrfToken ?? '');
$confirmationPhrase = (string) ($profilePage['confirmation_phrase'] ?? $confirmationPhrase ?? AccountSecurityService::CONFIRMATION_PHRASE);
?>

<div class="modal fade" id="permanentDeleteModal" tabindex="-1" aria-labelledby="permanentDeleteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-3">
      <form method="POST" action="<?= Helper::url('/admin/users/' . $userId . '/delete') ?>" id="permanentDeleteForm" novalidate>
        <div class="modal-header border-bottom">
          <h5 class="modal-title" id="permanentDeleteModalLabel">Permanently delete this user?</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
          <p class="mb-3">This is different from deactivation. The action cannot normally be undone. Related protected clinical and audit information will not be casually deleted.</p>
          <p class="small text-muted mb-3">You are deleting <strong><?= Helper::escape($userName) ?></strong>.</p>
          <div class="mb-3">
            <label for="confirmation_phrase" class="form-label">Type <code><?= Helper::escape($confirmationPhrase) ?></code> to confirm</label>
            <input type="text" class="form-control" id="confirmation_phrase" name="confirmation_phrase" autocomplete="off" required data-confirm-phrase="<?= Helper::escape($confirmationPhrase) ?>">
          </div>
          <div>
            <label for="admin_password" class="form-label">Your administrator password</label>
            <input type="password" class="form-control" id="admin_password" name="admin_password" autocomplete="current-password" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger" id="permanentDeleteSubmit" disabled>Delete permanently</button>
        </div>
      </form>
    </div>
  </div>
</div>
