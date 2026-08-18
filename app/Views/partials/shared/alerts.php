<?php

$errors = $errors ?? [];
$statusMessage = $statusMessage ?? null;
$statusType = is_array($statusMessage) ? (string) ($statusMessage['type'] ?? 'info') : 'info';
$statusIconMap = [
    'success' => 'bi-check-circle-fill',
    'warning' => 'bi-exclamation-triangle-fill',
    'danger' => 'bi-shield-exclamation',
    'info' => 'bi-info-circle-fill',
];
$statusIcon = $statusIconMap[$statusType] ?? $statusIconMap['info'];
?>

<?php if (!empty($errors)): ?>
  <div class="alert alert-danger mb-4" role="alert">
    <div class="d-flex align-items-start gap-3">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <div>
        <strong class="d-block mb-1">Please review the following issues</strong>
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
  <div class="alert alert-<?= \App\Helpers\Helper::escape($statusType) ?> mb-4" role="alert">
    <div class="d-flex align-items-center gap-3">
      <i class="bi <?= \App\Helpers\Helper::escape($statusIcon) ?>"></i>
      <div><?= \App\Helpers\Helper::escape($statusMessage['message']) ?></div>
    </div>
  </div>
<?php endif; ?>
