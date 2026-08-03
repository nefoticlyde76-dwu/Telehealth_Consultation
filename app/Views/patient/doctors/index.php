<?php

$doctors = $doctors ?? [];
$summary = $summary ?? [];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0];
$statusMessage = $statusMessage ?? null;

$buildPageUrl = static function (int $page): string {
    $queryString = http_build_query(['page' => $page]);

    return \App\Helpers\Helper::url('/patient/doctors') . ($queryString !== '' ? '?' . $queryString : '');
};
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-4">
        <div>
          <span class="section-badge mb-3">
            <i class="bi bi-person-badge"></i>
            Doctor Directory
          </span>
          <h2 class="h4 mb-2">Browse active doctors with available schedules</h2>
          <p class="text-muted mb-0">Patients can review clinician details, available consultation days, and time windows before booking opens in Week 5.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?= \App\Helpers\Helper::url('/patient/available-slots') ?>" class="btn btn-primary rounded-pill px-4">
            <i class="bi bi-calendar2-week me-2"></i>
            View Available Slots
          </a>
          <a href="<?= \App\Helpers\Helper::url('/patient/dashboard') ?>" class="btn btn-outline-primary rounded-pill px-4">
            <i class="bi bi-arrow-left me-2"></i>
            Back to Dashboard
          </a>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-sm-6 col-xl-4">
          <div class="admin-summary-card h-100">
            <span class="admin-summary-label">Available Doctors</span>
            <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($summary['available_doctors'] ?? 0)) ?></strong>
            <span class="admin-summary-meta">Active doctors with at least one patient-visible future slot.</span>
          </div>
        </div>
        <div class="col-sm-6 col-xl-4">
          <div class="admin-summary-card h-100">
            <span class="admin-summary-label">Open Consultation Slots</span>
            <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($summary['available_slots'] ?? 0)) ?></strong>
            <span class="admin-summary-meta">Future consultation windows currently marked as available.</span>
          </div>
        </div>
        <div class="col-sm-6 col-xl-4">
          <div class="admin-summary-card h-100">
            <span class="admin-summary-label">Specializations</span>
            <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($summary['specializations'] ?? 0)) ?></strong>
            <span class="admin-summary-meta">Distinct care areas currently visible to patients.</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section>
  <?php if ($doctors === []): ?>
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-5 text-center">
        <div class="empty-state-icon mx-auto mb-3">
          <i class="bi bi-search"></i>
        </div>
        <h3 class="h5 mb-2">No doctors are currently available to browse</h3>
        <p class="text-muted mb-0">Once future availability is published by clinicians, their directory cards will appear here automatically.</p>
      </div>
    </div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($doctors as $doctor): ?>
        <div class="col-lg-6 col-xl-4">
          <article class="patient-directory-card h-100">
            <div class="d-flex align-items-start gap-3 mb-4">
              <?php
              $avatarPath = $doctor['profile_photo_path'] ?? null;
              $fullName = $doctor['full_name'] ?? 'Doctor';
              $avatarClass = 'user-avatar user-avatar--sm';
              require __DIR__ . '/../../partials/shared/user_avatar.php';
              ?>
              <div class="flex-grow-1">
                <span class="badge badge-soft-success rounded-pill mb-2">Available</span>
                <h3 class="h5 mb-1"><?= \App\Helpers\Helper::escape((string) ($doctor['full_name'] ?? 'Doctor')) ?></h3>
                <p class="text-muted mb-1"><?= \App\Helpers\Helper::escape((string) ($doctor['professional_title'] ?? 'Medical Practitioner')) ?></p>
                <p class="mb-0 small text-primary fw-semibold"><?= \App\Helpers\Helper::escape((string) ($doctor['specialization'] ?? 'General Practice')) ?></p>
              </div>
            </div>

            <div class="patient-directory-meta mb-3">
              <span class="patient-directory-label">Next Available Day</span>
              <strong><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($doctor['next_available_date'] ?? ''), 'D, d M Y', 'Not available')) ?></strong>
            </div>

            <div class="patient-directory-meta mb-3">
              <span class="patient-directory-label">Available Consultation Days</span>
              <div class="d-flex flex-wrap gap-2">
                <?php foreach (($doctor['available_days'] ?? []) as $day): ?>
                  <span class="badge badge-soft-info rounded-pill px-3 py-2"><?= \App\Helpers\Helper::escape((string) $day) ?></span>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="patient-directory-meta">
              <span class="patient-directory-label">Available Consultation Times</span>
              <div class="d-flex flex-wrap gap-2">
                <?php foreach (($doctor['available_times'] ?? []) as $time): ?>
                  <span class="badge badge-soft-neutral rounded-pill border px-3 py-2"><?= \App\Helpers\Helper::escape((string) $time) ?></span>
                <?php endforeach; ?>
              </div>
            </div>
          </article>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
      <nav class="mt-4" aria-label="Doctor directory pagination">
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
  <?php endif; ?>
</section>
