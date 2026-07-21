<?php

$errors = $errors ?? [];
$statusMessage = $statusMessage ?? null;
?>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4" role="alert">
    <div class="d-flex align-items-start gap-3">
      <i class="bi bi-shield-exclamation fs-4"></i>
      <div>
        <h3 class="h6 mb-2">Please review the following issues</h3>
        <ul class="mb-0 ps-3">
          <?php foreach ($errors as $error): ?>
            <li><?= \App\Helpers\Helper::escape($error) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if (is_array($statusMessage) && !empty($statusMessage['message'])): ?>
  <div class="alert alert-<?= \App\Helpers\Helper::escape($statusMessage['type'] ?? 'info') ?> border-0 shadow-sm rounded-4 mb-4" role="alert">
    <div class="d-flex align-items-center gap-3">
      <i class="bi bi-info-circle fs-4"></i>
      <div><?= \App\Helpers\Helper::escape($statusMessage['message']) ?></div>
    </div>
  </div>
<?php endif; ?>
