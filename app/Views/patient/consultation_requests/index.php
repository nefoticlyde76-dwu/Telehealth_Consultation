<?php

$requests = $requests ?? [];
$summary = $summary ?? [];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0];

require __DIR__ . '/../../partials/shared/status_helper.php';

$totalItems = (int) ($pagination['total_items'] ?? 0);
$buildPageUrl = static function (int $page): string {
    $queryString = http_build_query(['page' => $page]);
    return \App\Helpers\Helper::url('/patient/consultation-requests') . ($queryString !== '' ? '?' . $queryString : '');
};
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/patient/dashboard') ?>">Dashboard</a></li>
        <li class="active">Consultation Requests</li>
      </ol>
      <h2 class="ux-page-header__title">My Consultation Requests</h2>
      <p class="ux-page-header__subtitle">Track booked consultation slots, review request status, and open details for each appointment request.</p>
    </div>
    <div class="ux-page-header__right">
      <span class="ux-chip ux-badge--dotless">
        <i class="bi bi-clipboard2-check-fill"></i>
        <span><?= $totalItems ?> requests visible</span>
      </span>
      <a href="<?= \App\Helpers\Helper::url('/patient/doctors') ?>" class="btn btn-primary btn-primary-xl">
        <i class="bi bi-person-badge me-2"></i>
        Book a Slot
      </a>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--amber">
          <i class="bi bi-clock-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['pending_requests'] ?? 0) ?></div>
          <div class="ux-stat__label">Pending</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--success">
          <i class="bi bi-check-circle-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['approved_requests'] ?? 0) ?></div>
          <div class="ux-stat__label">Approved</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--cyan">
          <i class="bi bi-calendar3-event-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['upcoming_appointments'] ?? 0) ?></div>
          <div class="ux-stat__label">Upcoming</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--navy">
          <i class="bi bi-clipboard2-data-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['consultation_history'] ?? 0) ?></div>
          <div class="ux-stat__label">Total History</div>
        </div>
      </div>
    </div>
  </div>

  <div class="ux-card">
    <div class="ux-table-wrapper border-0">
      <div class="table-responsive">
        <table class="ux-table">
          <caption class="visually-hidden">Table listing submitted consultation requests with booked slot details and status.</caption>
          <thead>
            <tr>
              <th scope="col">Doctor</th>
              <th scope="col">Specialization</th>
              <th scope="col">Consultation Date</th>
              <th scope="col">Time</th>
              <th scope="col">Status</th>
              <th scope="col" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($requests === []): ?>
              <tr>
                <td colspan="6" class="ux-table__empty-state">
                  <div class="ux-empty">
                    <div class="ux-empty__icon">
                      <i class="bi bi-clipboard2-x"></i>
                    </div>
                    <h4 class="ux-empty__title">No consultation requests submitted yet</h4>
                    <p class="ux-empty__text">Book an available slot from the doctor directory or slot viewer to get started.</p>
                    <div class="ux-empty__action">
                      <a href="<?= \App\Helpers\Helper::url('/patient/doctors') ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-person-badge me-1"></i>
                        Browse Doctors
                      </a>
                    </div>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($requests as $request): ?>
                <?php
                $status = (string) ($request['status'] ?? 'Pending');
                $statusBadge = ux_status_badge_class($status);
                $requestId = (int) ($request['id'] ?? 0);
                ?>
                <tr>
                  <td>
                    <div class="d-flex flex-column">
                      <strong><?= \App\Helpers\Helper::escape((string) ($request['doctor_name'] ?? 'Doctor')) ?></strong>
                      <span class="text-muted small"><?= \App\Helpers\Helper::escape((string) ($request['doctor_title'] ?? 'Medical Practitioner')) ?></span>
                    </div>
                  </td>
                  <td>
                    <span class="ux-badge ux-badge--neutral">
                      <?= \App\Helpers\Helper::escape((string) ($request['specialization'] ?? '')) ?>
                    </span>
                  </td>
                  <td>
                    <div class="d-flex flex-column">
                      <strong><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($request['consultation_date'] ?? ''), 'd M Y', 'Not available')) ?></strong>
                      <span class="text-muted small"><?= \App\Helpers\Helper::escape((string) ($request['consultation_date'] ?? '')) ?></span>
                    </div>
                  </td>
                  <td>
                    <strong><?= \App\Helpers\Helper::escape(substr((string) ($request['start_time'] ?? ''), 0, 5)) ?> - <?= \App\Helpers\Helper::escape(substr((string) ($request['end_time'] ?? ''), 0, 5)) ?></strong>
                  </td>
                  <td>
                    <span class="ux-badge <?= $statusBadge ?>">
                      <?= \App\Helpers\Helper::escape($status) ?>
                    </span>
                  </td>
                  <td class="text-end">
                    <div class="ux-table__actions">
                      <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests/' . (string) $requestId) ?>" class="btn btn-outline-primary btn-sm" aria-label="View details for consultation request #<?= (string) $requestId ?>">
                        View Details
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
      <div class="card-footer border-0 bg-transparent pt-4 pb-0">
        <nav class="admin-pagination d-flex justify-content-end align-items-center gap-2 flex-wrap" aria-label="Patient consultation history pagination">
          <a class="btn btn-outline-primary btn-sm <?= ($pagination['current_page'] ?? 1) <= 1 ? 'disabled' : '' ?>" href="<?= ($pagination['current_page'] ?? 1) <= 1 ? '#' : $buildPageUrl((int) $pagination['current_page'] - 1) ?>">
            <i class="bi bi-chevron-left me-1"></i>
            Previous
          </a>
          <span class="small text-muted">
            Page <?= (int) ($pagination['current_page'] ?? 1) ?> of <?= (int) ($pagination['total_pages'] ?? 1) ?>
          </span>
          <a class="btn btn-outline-primary btn-sm <?= ($pagination['current_page'] ?? 1) >= ($pagination['total_pages'] ?? 1) ? 'disabled' : '' ?>" href="<?= ($pagination['current_page'] ?? 1) >= ($pagination['total_pages'] ?? 1) ? '#' : $buildPageUrl((int) $pagination['current_page'] + 1) ?>">
            Next
            <i class="bi bi-chevron-right ms-1"></i>
          </a>
        </nav>
      </div>
    <?php endif; ?>
  </div>
</section>
