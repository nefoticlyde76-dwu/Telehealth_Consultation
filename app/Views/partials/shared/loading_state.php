<?php

/**
 * Loading indicator used by filter forms and async dashboard widgets.
 */
$loadingLabel = (string) ($loadingLabel ?? 'Loading…');
?>

<div class="ux-loading" role="status" aria-live="polite" hidden data-loading-indicator>
  <span class="ux-loading__spinner" aria-hidden="true"></span>
  <span class="ux-loading__label"><?= \App\Helpers\Helper::escape($loadingLabel) ?></span>
</div>
