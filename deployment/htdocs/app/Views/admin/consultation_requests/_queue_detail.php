<?php

$selectedRequest = is_array($selectedRequest ?? null) ? $selectedRequest : null;
$csrfToken = $csrfToken ?? '';
$filters = $filters ?? [];
$pagination = $pagination ?? [];
$currentPage = max(1, (int) ($pagination['current_page'] ?? 1));

if ($selectedRequest === null) {
    ?>
    <div class="ux-card ux-queue-detail h-100">
      <?php
      $emptyIcon = 'bi-clipboard2-pulse';
      $emptyTitle = 'No request selected';
      $emptyText = 'When pending requests are available, the first one opens here automatically.';
      $emptyCompact = true;
      require __DIR__ . '/../../partials/shared/empty_state.php';
      ?>
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
    foreach (['search', 'status', 'doctor_id', 'date', 'date_from', 'date_to', 'sort'] as $filterKey) {
        echo '<input type="hidden" name="' . \App\Helpers\Helper::escape($filterKey) . '" value="' . \App\Helpers\Helper::escape((string) ($filters[$filterKey] ?? '')) . '">';
    }
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
    <?= ux_status_badge($status) ?>
  </div>

  <div class="table-responsive ux-queue-detail__table-wrap">
    <table class="table ux-table ux-queue-detail__table mb-0">
      <tbody>
        <tr>
          <th scope="row">Patient</th>
          <td>
            <?php
            $personName = (string) ($request['patient_name'] ?? 'Patient');
            $personPhoto = $request['patient_photo_path'] ?? null;
            $personMeta = '';
            $personSize = 'sm';
            require __DIR__ . '/../../partials/shared/person_row.php';
            ?>
          </td>
        </tr>
        <tr>
          <th scope="row">Doctor</th>
          <td>
            <?php
            $personName = (string) ($request['doctor_name'] ?? 'Doctor');
            $personPhoto = $request['doctor_photo_path'] ?? null;
            $personMeta = '';
            $personSize = 'sm';
            require __DIR__ . '/../../partials/shared/person_row.php';
            ?>
          </td>
        </tr>
        <tr>
          <th scope="row">Specialization</th>
          <td><?= \App\Helpers\Helper::escape((string) ($request['specialization'] ?? 'Not specified')) ?></td>
        </tr>
        <tr>
          <th scope="row">Consultation date</th>
          <td><?= \App\Helpers\Helper::escape($dateLabel) ?></td>
        </tr>
        <tr>
          <th scope="row">Start time</th>
          <td><?= \App\Helpers\Helper::escape($startLabel !== '' ? $startLabel : 'Not available') ?></td>
        </tr>
        <tr>
          <th scope="row">End time</th>
          <td><?= \App\Helpers\Helper::escape($endLabel !== '' ? $endLabel : 'Not available') ?></td>
        </tr>
        <tr>
          <th scope="row">Submitted</th>
          <td><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($request['request_date'] ?? ''), 'd M Y H:i', 'Not available')) ?></td>
        </tr>
        <tr>
          <th scope="row">Current status</th>
          <td><?= ux_status_badge($status) ?></td>
        </tr>
      </tbody>
    </table>
  </div>

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
