<?php

$request = $request ?? [];
$status = (string) ($request['status'] ?? 'Pending');
$requestId = (int) ($request['id'] ?? 0);
$statusBadgeMap = [
    'Pending' => 'badge-soft-warning',
    'Approved' => 'badge-soft-success',
    'Rejected' => 'badge-soft-danger',
    'Cancelled' => 'badge-soft-danger',
    'Completed' => 'badge-soft-success',
];
$badgeClass = $statusBadgeMap[$status] ?? 'badge-soft-neutral';
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-4">
        <div>
          <span class="section-badge mb-3">
            <i class="bi bi-clipboard2-data"></i>
            Consultation Details
          </span>
          <h2 class="h4 mb-2">Request #<?= \App\Helpers\Helper::escape((string) $requestId) ?></h2>
          <p class="text-muted mb-0">Review your consultation booking request, selected slot, and current status.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests') ?>" class="btn btn-outline-primary rounded-pill px-4">
            <i class="bi bi-clipboard2-check me-2"></i>
            Back to History
          </a>
          <a href="<?= \App\Helpers\Helper::url('/patient/dashboard') ?>" class="btn btn-outline-primary rounded-pill px-4">
            <i class="bi bi-arrow-left me-2"></i>
            Dashboard
          </a>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-xl-4">
          <div class="admin-summary-card h-100">
            <span class="admin-summary-label">Current Status</span>
            <strong class="admin-summary-value">
              <span class="badge <?= \App\Helpers\Helper::escape($badgeClass) ?> rounded-pill px-3 py-2"><?= \App\Helpers\Helper::escape($status) ?></span>
            </strong>
            <span class="admin-summary-meta">Status updates reflect the clinical workflow progression.</span>
          </div>
        </div>
        <div class="col-xl-4">
          <div class="admin-summary-card h-100">
            <span class="admin-summary-label">Consultation Slot</span>
            <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($request['consultation_date'] ?? ''), 'd M Y', 'Not available')) ?></strong>
            <span class="admin-summary-meta">
              <?= \App\Helpers\Helper::escape(substr((string) ($request['start_time'] ?? ''), 0, 5)) ?> - <?= \App\Helpers\Helper::escape(substr((string) ($request['end_time'] ?? ''), 0, 5)) ?>
            </span>
          </div>
        </div>
        <div class="col-xl-4">
          <div class="admin-summary-card h-100">
            <span class="admin-summary-label">Doctor</span>
            <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($request['doctor_name'] ?? 'Doctor')) ?></strong>
            <span class="admin-summary-meta"><?= \App\Helpers\Helper::escape((string) ($request['doctor_title'] ?? 'Medical Practitioner')) ?> · <?= \App\Helpers\Helper::escape((string) ($request['specialization'] ?? 'General Practice')) ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <h3 class="h5 mb-3">Chief Complaint</h3>
      <div class="dashboard-inline-callout">
        <p class="text-muted mb-0"><?= nl2br(\App\Helpers\Helper::escape((string) ($request['reason'] ?? ''))) ?></p>
      </div>
    </div>
  </div>
</section>
