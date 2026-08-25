<?php

/**
 * Safe user-facing error panel. Never print SQL, traces, or file paths here.
 */
$errorTitle = (string) ($errorTitle ?? 'Unable to load this page');
$errorText = (string) ($errorText ?? 'Please refresh the page or try again.');
$errorRetryUrl = trim((string) ($errorRetryUrl ?? ''));
?>

<div class="ux-error" role="alert">
  <div class="ux-error__icon" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></div>
  <div>
    <h3 class="ux-error__title"><?= \App\Helpers\Helper::escape($errorTitle) ?></h3>
    <p class="ux-error__text mb-0"><?= \App\Helpers\Helper::escape($errorText) ?></p>
  </div>
  <?php if ($errorRetryUrl !== ''): ?>
    <a href="<?= \App\Helpers\Helper::url($errorRetryUrl) ?>" class="btn btn-outline-primary btn-sm">Try again</a>
  <?php endif; ?>
</div>
