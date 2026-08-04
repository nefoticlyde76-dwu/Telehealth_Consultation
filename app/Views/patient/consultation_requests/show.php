<?php

$request = $request ?? [];
$status = (string) ($request['status'] ?? 'Pending');
$statusBadgeMap = [
    'Pending' => 'badge-soft-warning',
    'Approved' => 'badge-soft-success',
    'Cancelled' => 'badge-soft-danger',
];
$badgeClass = $statusBadgeMap[$status] ?? 'badge-soft-neutral';
$requestId = (int) ($request['id'] ?? 0);
$hasAttachment = !empty($request['attachment_path']);
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
          <p class="text-muted mb-0">Review your submitted consultation request details and download supporting documents securely.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests') ?>" class="btn btn-outline-primary rounded-pill px-4">
            <i class="bi bi-clipboard2-data me-2"></i>
            Back to Requests
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
            <span class="admin-summary-meta">Status updates will reflect clinical review and approval workflows.</span>
          </div>
        </div>
        <div class="col-xl-4">
          <div class="admin-summary-card h-100">
            <span class="admin-summary-label">Specialization</span>
            <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($request['specialization'] ?? '')) ?></strong>
            <span class="admin-summary-meta">Specialization used for clinician matching.</span>
          </div>
        </div>
        <div class="col-xl-4">
          <div class="admin-summary-card h-100">
            <span class="admin-summary-label">Submitted</span>
            <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($request['request_date'] ?? ''), 'd M Y', 'Not available')) ?></strong>
            <span class="admin-summary-meta"><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($request['request_date'] ?? ''), 'H:i', '')) ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="row g-4">
        <div class="col-xl-7">
          <h3 class="h5 mb-3">Chief Complaint / Consultation Reason</h3>
          <div class="dashboard-inline-callout">
            <p class="text-muted mb-0"><?= nl2br(\App\Helpers\Helper::escape((string) ($request['reason'] ?? ''))) ?></p>
          </div>
        </div>

        <div class="col-xl-5">
          <h3 class="h5 mb-3">Assigned Doctor</h3>
          <div class="dashboard-inline-callout">
            <span class="dashboard-info-label">Matched clinician</span>
            <strong class="d-block mb-1"><?= \App\Helpers\Helper::escape((string) ($request['doctor_name'] ?? 'Doctor')) ?></strong>
            <p class="text-muted small mb-0">
              <?= \App\Helpers\Helper::escape((string) ($request['doctor_title'] ?? 'Medical Practitioner')) ?>
              <?php if (!empty($request['doctor_profile_specialization'])): ?>
                · <?= \App\Helpers\Helper::escape((string) $request['doctor_profile_specialization']) ?>
              <?php endif; ?>
            </p>
          </div>

          <div class="mt-4">
            <h3 class="h5 mb-3">Supporting Attachment</h3>
            <?php if (!$hasAttachment): ?>
              <div class="dashboard-inline-callout">
                <span class="dashboard-info-label">No attachment uploaded</span>
                <p class="text-muted small mb-0">This request was submitted without any supporting document or image.</p>
              </div>
            <?php else: ?>
              <div class="dashboard-inline-callout">
                <span class="dashboard-info-label"><?= \App\Helpers\Helper::escape((string) ($request['attachment_mime'] ?? 'Attachment')) ?></span>
                <strong class="d-block mb-2"><?= \App\Helpers\Helper::escape((string) ($request['attachment_original_name'] ?? 'Attachment')) ?></strong>
                <div class="d-flex flex-wrap gap-2">
                  <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests/' . (string) $requestId . '/attachment') ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                    <i class="bi bi-download me-2"></i>
                    Download
                  </a>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
