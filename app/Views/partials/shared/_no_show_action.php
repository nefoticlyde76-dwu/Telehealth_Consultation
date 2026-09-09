<?php

use App\Helpers\Helper;

$noShowUrl = (string) ($noShowUrl ?? '');
$csrfToken = (string) ($csrfToken ?? '');
$noShowButtonClass = (string) ($noShowButtonClass ?? 'btn btn-outline-warning btn-sm');
$noShowLabel = (string) ($noShowLabel ?? 'Mark as No-Show');
$noShowReturnTo = (string) ($noShowReturnTo ?? '');
$noShowFormClass = (string) ($noShowFormClass ?? '');

if ($noShowUrl === '' || $csrfToken === '') {
    return;
}
?>
<form method="POST"
      action="<?= Helper::escape($noShowUrl) ?>"
      class="<?= Helper::escape($noShowFormClass) ?>"
      data-confirm-title="Mark this consultation as No-Show?"
      data-confirm-body="Use No-Show when the scheduled consultation was not attended. This is distinct from cancelling a booking."
      data-confirm-hint="The booked slot will be released. This cannot be undone from this page."
      data-confirm-tone="warning"
      data-confirm-action="Mark as No-Show">
  <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
  <?php if ($noShowReturnTo !== ''): ?>
    <input type="hidden" name="return_to" value="<?= Helper::escape($noShowReturnTo) ?>">
  <?php endif; ?>
  <button type="submit" class="<?= Helper::escape($noShowButtonClass) ?>" aria-label="Mark this consultation as No-Show">
    <i class="bi bi-person-x me-1"></i>
    <?= Helper::escape($noShowLabel) ?>
  </button>
</form>
