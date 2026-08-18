<?php

$selectedRequest = is_array($selectedRequest ?? null) ? $selectedRequest : null;
$csrfToken = $csrfToken ?? '';
$filters = $filters ?? [];
$pagination = $pagination ?? [];
$currentPage = max(1, (int) ($pagination['current_page'] ?? 1));

if ($selectedRequest === null) {
    ?>
    <div class="ux-card h-100">
      <div class="ux-empty p-5">
        <div class="ux-empty__icon"><i class="bi bi-clipboard2-pulse"></i></div>
        <h3 class="ux-empty__title">No request selected</h3>
        <p class="ux-empty__text mb-0">When pending requests are available, the first one opens here automatically.</p>
      </div>
    </div>
    <?php
    return;
}

$request = $selectedRequest;
$requestId = (int) ($request['id'] ?? 0);
$status = (string) ($request['status'] ?? 'Pending');
$dateLabel = \App\Helpers\Helper::formatDate((string) ($request['consultation_date'] ?? ''), 'D, d M Y', 'Not available');
$startLabel = substr((string) ($request['start_time'] ?? ''), 0, 5);
$endLabel = substr((string) ($request['end_time'] ?? ''), 0, 5);

$workspaceFields = static function () use ($filters, $currentPage): void {
    echo '<input type="hidden" name="workspace" value="1">';
    echo '<input type="hidden" name="page" value="' . \App\Helpers\Helper::escape((string) $currentPage) . '">';
    echo '<input type="hidden" name="search" value="' . \App\Helpers\Helper::escape((string) ($filters['search'] ?? '')) . '">';
    echo '<input type="hidden" name="status" value="' . \App\Helpers\Helper::escape((string) ($filters['status'] ?? '')) . '">';
    echo '<input type="hidden" name="doctor_id" value="' . \App\Helpers\Helper::escape((string) ((int) ($filters['doctor_id'] ?? 0))) . '">';
    echo '<input type="hidden" name="consultation_date" value="' . \App\Helpers\Helper::escape((string) ($filters['consultation_date'] ?? '')) . '">';
};
?>

<article class="ux-card ux-queue-detail">
  <div class="ux-queue-detail__header">
    <div>
      <?php
      $personName = (string) ($request['patient_name'] ?? 'Patient');
      $personPhoto = $request['patient_photo_path'] ?? null;
      $personMeta = 'Request #' . (string) $requestId;
      $personSize = 'lg';
      require __DIR__ . '/../../partials/shared/person_row.php';
      ?>
    </div>
    <span class="ux-badge <?= ux_status_badge_class($status) ?>"><?= \App\Helpers\Helper::escape($status) ?></span>
  </div>

  <dl class="ux-queue-detail__facts">
    <div>
      <dt>Patient</dt>
      <dd>
        <?php
        $personName = (string) ($request['patient_name'] ?? 'Patient');
        $personPhoto = $request['patient_photo_path'] ?? null;
        $personMeta = '';
        $personSize = 'sm';
        require __DIR__ . '/../../partials/shared/person_row.php';
        ?>
      </dd>
    </div>
    <div>
      <dt>Doctor</dt>
      <dd>
        <?php
        $personName = (string) ($request['doctor_name'] ?? 'Doctor');
        $personPhoto = $request['doctor_photo_path'] ?? null;
        $personMeta = '';
        $personSize = 'sm';
        require __DIR__ . '/../../partials/shared/person_row.php';
        ?>
      </dd>
    </div>
    <div>
      <dt>Specialization</dt>
      <dd><?= \App\Helpers\Helper::escape((string) ($request['specialization'] ?? 'Not specified')) ?></dd>
    </div>
    <div>
      <dt>Consultation date</dt>
      <dd><?= \App\Helpers\Helper::escape($dateLabel) ?></dd>
    </div>
    <div>
      <dt>Start time</dt>
      <dd><?= \App\Helpers\Helper::escape($startLabel !== '' ? $startLabel : 'Not available') ?></dd>
    </div>
    <div>
      <dt>End time</dt>
      <dd><?= \App\Helpers\Helper::escape($endLabel !== '' ? $endLabel : 'Not available') ?></dd>
    </div>
    <div>
      <dt>Submitted</dt>
      <dd><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($request['request_date'] ?? ''), 'd M Y H:i', 'Not available')) ?></dd>
    </div>
    <div>
      <dt>Current status</dt>
      <dd><?= \App\Helpers\Helper::escape($status) ?></dd>
    </div>
  </dl>

  <div class="ux-queue-detail__complaint">
    <h4 class="h6 mb-2">Chief complaint</h4>
    <p class="mb-0"><?= nl2br(\App\Helpers\Helper::escape((string) ($request['reason'] ?? ''))) ?></p>
  </div>

  <div class="ux-queue-detail__actions">
    <?php if ($status === 'Pending' && $requestId > 0): ?>
      <form method="POST" action="<?= \App\Helpers\Helper::url('/admin/consultation-requests/' . $requestId . '/approve') ?>" data-queue-decision>
        <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">
        <?php $workspaceFields(); ?>
        <input type="hidden" name="advance" value="1">
        <button type="submit" class="btn btn-primary btn-sm">
          <i class="bi bi-check2-circle me-1"></i>
          Approve &amp; Next
        </button>
      </form>
      <form method="POST" action="<?= \App\Helpers\Helper::url('/admin/consultation-requests/' . $requestId . '/reject') ?>" data-queue-decision>
        <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">
        <?php $workspaceFields(); ?>
        <input type="hidden" name="advance" value="1">
        <button type="submit" class="btn btn-outline-danger btn-sm">
          <i class="bi bi-x-circle me-1"></i>
          Reject &amp; Next
        </button>
      </form>
      <form method="POST" action="<?= \App\Helpers\Helper::url('/admin/consultation-requests/' . $requestId . '/approve') ?>" data-queue-decision>
        <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">
        <?php $workspaceFields(); ?>
        <button type="submit" class="btn btn-outline-primary btn-sm">
          <i class="bi bi-check2-circle me-1"></i>
          Approve
        </button>
      </form>
      <form method="POST" action="<?= \App\Helpers\Helper::url('/admin/consultation-requests/' . $requestId . '/reject') ?>" data-queue-decision>
        <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">
        <?php $workspaceFields(); ?>
        <button type="submit" class="btn btn-outline-danger btn-sm">
          <i class="bi bi-x-circle me-1"></i>
          Reject
        </button>
      </form>
    <?php endif; ?>
    <a href="<?= \App\Helpers\Helper::url('/admin/consultation-requests/' . $requestId) ?>" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-eye me-1"></i>
      View Details
    </a>
  </div>
</article>
