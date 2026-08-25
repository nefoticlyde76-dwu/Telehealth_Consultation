<?php
use App\Helpers\Helper;

$audit = is_array($audit ?? null) ? $audit : [];
$entityType = trim((string) ($audit['entity_type'] ?? ''));
$entityId = (int) ($audit['entity_id'] ?? 0);
$entityLabel = $entityType !== '' ? ucfirst(str_replace('_', ' ', $entityType)) . ($entityId > 0 ? ' #' . $entityId : '') : 'Not linked';
?>

<section class="mb-4">
  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= Helper::url('/admin/dashboard') ?>">Dashboard</a></li>
        <li><a href="<?= Helper::url('/admin/audit-logs') ?>">Activity &amp; Audit Logs</a></li>
        <li class="active">Event Details</li>
      </ol>
      <h2 class="ux-page-header__title">Audit Event</h2>
      <p class="ux-page-header__subtitle">Record #<?= (int) ($audit['id'] ?? 0) ?></p>
    </div>
  </div>

  <div class="ux-card p-4">
    <div class="row g-3">
      <div class="col-md-6">
        <h6>Action</h6>
        <p><?= Helper::escape((string) ($audit['event_label'] ?? '')) ?></p>
      </div>
      <div class="col-md-6">
        <h6>Outcome</h6>
        <p><?= Helper::escape(ucfirst((string) ($audit['outcome'] ?? 'success'))) ?></p>
      </div>
      <div class="col-md-6">
        <h6>Performed By</h6>
        <p><?= Helper::escape((string) ($audit['actor_name'] ?? 'System')) ?></p>
      </div>
      <div class="col-md-6">
        <h6>Role</h6>
        <p><?= Helper::escape((string) ($audit['actor_role'] ?? '')) ?></p>
      </div>
      <div class="col-md-6">
        <h6>Date &amp; Time</h6>
        <p><?= Helper::formatDate((string) ($audit['created_at'] ?? ''), 'd F Y, g:i A', '—') ?></p>
      </div>
      <div class="col-md-6">
        <h6>Entity</h6>
        <p><?= Helper::escape($entityLabel) ?></p>
      </div>
      <div class="col-12">
        <h6>Description</h6>
        <p class="mb-0"><?= Helper::escape((string) ($audit['description'] ?? '')) ?></p>
      </div>
    </div>
  </div>
</section>
