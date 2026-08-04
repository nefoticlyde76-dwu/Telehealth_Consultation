<?php

$request = $request ?? [];
$csrfToken = $csrfToken ?? '';
$status = (string) ($request['status'] ?? 'Pending');
$statusBadgeMap = [
    'Pending' => 'badge-soft-warning',
    'Assigned' => 'badge-soft-info',
    'Approved' => 'badge-soft-success',
    'Rejected' => 'badge-soft-danger',
    'Cancelled' => 'badge-soft-danger',
    'Completed' => 'badge-soft-success',
];
$badgeClass = $statusBadgeMap[$status] ?? 'badge-soft-neutral';
$requestId = (int) ($request['id'] ?? 0);
$dateLabel = \App\Helpers\Helper::formatDate((string) ($request['consultation_date'] ?? ''), 'D, d M Y', 'Not available');
$timeLabel = substr((string) ($request['start_time'] ?? ''), 0, 5) . ' - ' . substr((string) ($request['end_time'] ?? ''), 0, 5);
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-4">
        <div>
          <span class="section-badge mb-3">
            <i class="bi bi-clipboard2-check"></i>
            Consultation Request Details
          </span>
          <h2 class="h4 mb-2">Request #<?= \App\Helpers\Helper::escape((string) $requestId) ?></h2>
          <p class="text-muted mb-0">Review full consultation request details and apply an approval decision securely.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?= \App\Helpers\Helper::url('/admin/consultation-requests') ?>" class="btn btn-outline-primary rounded-pill px-4">
            <i class="bi bi-arrow-left me-2"></i>
            Back to Requests
          </a>
          <span class="badge <?= \App\Helpers\Helper::escape($badgeClass) ?> rounded-pill px-3 py-2 align-self-center">
            <?= \App\Helpers\Helper::escape($status) ?>
          </span>
        </div>
      </div>

      <div class="row g-4 mb-4">
        <div class="col-xl-4">
          <div class="admin-summary-card h-100">
            <span class="admin-summary-label">Patient</span>
            <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($request['patient_name'] ?? 'Patient')) ?></strong>
            <span class="admin-summary-meta">Patient ID #<?= \App\Helpers\Helper::escape((string) ((int) ($request['patient_id'] ?? 0))) ?></span>
          </div>
        </div>
        <div class="col-xl-4">
          <div class="admin-summary-card h-100">
            <span class="admin-summary-label">Doctor</span>
            <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($request['doctor_name'] ?? 'Doctor')) ?></strong>
            <span class="admin-summary-meta"><?= \App\Helpers\Helper::escape((string) ($request['doctor_title'] ?? 'Medical Practitioner')) ?> · <?= \App\Helpers\Helper::escape((string) ($request['specialization'] ?? 'General Practice')) ?></span>
          </div>
        </div>
        <div class="col-xl-4">
          <div class="admin-summary-card h-100">
            <span class="admin-summary-label">Consultation Slot</span>
            <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) $dateLabel) ?></strong>
            <span class="admin-summary-meta"><?= \App\Helpers\Helper::escape((string) $timeLabel) ?></span>
          </div>
        </div>
      </div>

      <div class="dashboard-inline-callout mb-4">
        <span class="dashboard-info-label">Chief Complaint</span>
        <p class="text-muted mb-0"><?= nl2br(\App\Helpers\Helper::escape((string) ($request['reason'] ?? ''))) ?></p>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-md-6">
          <div class="widget-mini-stat h-100">
            <span class="widget-mini-stat-label">Date Submitted</span>
            <strong><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($request['request_date'] ?? ''), 'd M Y', 'Not available')) ?></strong>
            <span class="admin-summary-meta"><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($request['request_date'] ?? ''), 'H:i', '')) ?></span>
          </div>
        </div>
        <div class="col-md-6">
          <div class="widget-mini-stat h-100">
            <span class="widget-mini-stat-label">Availability Status</span>
            <strong><?= \App\Helpers\Helper::escape((string) ($request['availability_status'] ?? 'Not available')) ?></strong>
            <span class="admin-summary-meta">Reserved slots remain unavailable for other patients</span>
          </div>
        </div>
      </div>

      <?php if ($status === 'Pending'): ?>
        <div class="d-flex flex-wrap gap-2 justify-content-end">
          <form method="POST" action="<?= \App\Helpers\Helper::url('/admin/consultation-requests/' . $requestId . '/reject') ?>">
            <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">
            <button type="submit" class="btn btn-outline-danger rounded-pill px-4">
              <i class="bi bi-x-circle me-2"></i>
              Reject Request
            </button>
          </form>
          <form method="POST" action="<?= \App\Helpers\Helper::url('/admin/consultation-requests/' . $requestId . '/approve') ?>">
            <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">
            <button type="submit" class="btn btn-primary rounded-pill px-4">
              <i class="bi bi-check2-circle me-2"></i>
              Approve Request
            </button>
          </form>
        </div>
      <?php else: ?>
        <div class="dashboard-inline-callout">
          <span class="dashboard-info-label">Approval Decision</span>
          <p class="text-muted small mb-0">This consultation request is no longer pending, so no further approval actions are available.</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

