<?php

$requests = $requests ?? [];
$summary = $summary ?? [];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0];

$buildPageUrl = static function (int $page): string {
    $queryString = http_build_query(['page' => $page]);
    return \App\Helpers\Helper::url('/patient/consultation-requests') . ($queryString !== '' ? '?' . $queryString : '');
};

$statusBadgeMap = [
    'Pending' => 'badge-soft-warning',
    'Approved' => 'badge-soft-success',
    'Cancelled' => 'badge-soft-danger',
];
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-4">
        <div>
          <span class="section-badge mb-3">
            <i class="bi bi-clipboard2-pulse"></i>
            Patient Consultation Requests
          </span>
          <h2 class="h4 mb-2">Review submitted consultation requests</h2>
          <p class="text-muted mb-0">Track request status updates and open individual requests for full details and supporting attachments.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests/create') ?>" class="btn btn-primary rounded-pill px-4">
            <i class="bi bi-clipboard2-plus me-2"></i>
            New Request
          </a>
          <a href="<?= \App\Helpers\Helper::url('/patient/dashboard') ?>" class="btn btn-outline-primary rounded-pill px-4">
            <i class="bi bi-arrow-left me-2"></i>
            Back to Dashboard
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="mb-4">
  <div class="row g-4">
    <div class="col-sm-6 col-xl-3">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Total Requests</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ((int) ($summary['total_requests'] ?? 0))) ?></strong>
        <span class="admin-summary-meta">All submitted consultation requests.</span>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Pending Requests</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ((int) ($summary['pending_requests'] ?? 0))) ?></strong>
        <span class="admin-summary-meta">Awaiting clinician review.</span>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Approved Requests</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ((int) ($summary['approved_requests'] ?? 0))) ?></strong>
        <span class="admin-summary-meta">Ready for scheduling workflow.</span>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Completed Consultations</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ((int) ($summary['completed_consultations'] ?? 0))) ?></strong>
        <span class="admin-summary-meta">Consultations recorded in your history.</span>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
          <h3 class="h5 mb-1">My Consultation Requests</h3>
          <p class="text-muted mb-0">Most recent requests appear first.</p>
        </div>
        <span class="badge badge-soft-info rounded-pill px-3 py-2">
          Page <?= \App\Helpers\Helper::escape((string) ($pagination['current_page'] ?? 1)) ?> of <?= \App\Helpers\Helper::escape((string) ($pagination['total_pages'] ?? 1)) ?>
        </span>
      </div>

      <div class="table-responsive">
        <table class="table admin-user-table align-middle mb-0">
          <caption class="visually-hidden">Table listing submitted consultation requests with specialization, assigned doctor, status, and view actions.</caption>
          <thead>
            <tr>
              <th scope="col">Request</th>
              <th scope="col">Submitted</th>
              <th scope="col">Specialization</th>
              <th scope="col">Assigned Doctor</th>
              <th scope="col">Status</th>
              <th scope="col" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($requests === []): ?>
              <tr>
                <td colspan="6">
                  <div class="admin-table-empty text-center py-5">
                    <div class="empty-state-icon mx-auto mb-3">
                      <i class="bi bi-clipboard2-x"></i>
                    </div>
                    <h4 class="h5 mb-2">No consultation requests submitted yet</h4>
                    <p class="text-muted mb-0">Submit a new consultation request to begin the Week 5 patient workflow.</p>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($requests as $request): ?>
                <?php
                $status = (string) ($request['status'] ?? 'Pending');
                $badgeClass = $statusBadgeMap[$status] ?? 'badge-soft-neutral';
                ?>
                <tr>
                  <td>
                    <strong>#<?= \App\Helpers\Helper::escape((string) ((int) ($request['id'] ?? 0))) ?></strong>
                  </td>
                  <td>
                    <strong><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($request['request_date'] ?? ''), 'd M Y', 'Not available')) ?></strong>
                    <span class="d-block text-muted small"><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($request['request_date'] ?? ''), 'H:i', '')) ?></span>
                  </td>
                  <td>
                    <span class="badge badge-soft-neutral rounded-pill border px-3 py-2"><?= \App\Helpers\Helper::escape((string) ($request['specialization'] ?? '')) ?></span>
                  </td>
                  <td>
                    <strong class="d-block"><?= \App\Helpers\Helper::escape((string) ($request['doctor_name'] ?? 'Doctor')) ?></strong>
                    <span class="text-muted small"><?= \App\Helpers\Helper::escape((string) ($request['doctor_title'] ?? 'Medical Practitioner')) ?></span>
                  </td>
                  <td>
                    <span class="badge <?= \App\Helpers\Helper::escape($badgeClass) ?> rounded-pill px-3 py-2"><?= \App\Helpers\Helper::escape($status) ?></span>
                  </td>
                  <td class="text-end">
                    <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests/' . (string) ((int) ($request['id'] ?? 0))) ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                      View Details
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
        <nav class="mt-4" aria-label="Patient consultation request pagination">
          <ul class="pagination admin-pagination justify-content-end mb-0">
            <li class="page-item <?= ($pagination['current_page'] ?? 1) <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= ($pagination['current_page'] ?? 1) <= 1 ? '#' : $buildPageUrl((int) $pagination['current_page'] - 1) ?>">Previous</a>
            </li>
            <?php for ($page = 1; $page <= (int) ($pagination['total_pages'] ?? 1); $page++): ?>
              <li class="page-item <?= $page === (int) ($pagination['current_page'] ?? 1) ? 'active' : '' ?>">
                <a class="page-link" href="<?= $buildPageUrl($page) ?>"><?= \App\Helpers\Helper::escape((string) $page) ?></a>
              </li>
            <?php endfor; ?>
            <li class="page-item <?= ($pagination['current_page'] ?? 1) >= ($pagination['total_pages'] ?? 1) ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= ($pagination['current_page'] ?? 1) >= ($pagination['total_pages'] ?? 1) ? '#' : $buildPageUrl((int) $pagination['current_page'] + 1) ?>">Next</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</section>
