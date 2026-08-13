<?php

$doctors = $doctors ?? [];
$summary = $summary ?? [];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0];
$statusMessage = $statusMessage ?? null;

require __DIR__ . '/../../partials/shared/status_helper.php';

$totalItems = (int) ($pagination['total_items'] ?? 0);
$buildPageUrl = static function (int $page): string {
    $queryString = http_build_query(['page' => $page]);

    return \App\Helpers\Helper::url('/patient/doctors') . ($queryString !== '' ? '?' . $queryString : '');
};
?>

<section class="mb-4">
  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/patient/dashboard') ?>">Dashboard</a></li>
        <li class="active">Find Doctors</li>
      </ol>
      <h2 class="ux-page-header__title">Find a Doctor</h2>
      <p class="ux-page-header__subtitle">Browse active clinicians and book directly from an available slot.</p>
    </div>
    <div class="ux-page-header__right">
      <span class="ux-chip ux-badge--dotless">
        <i class="bi bi-person-badge-fill"></i>
        <span><?= $totalItems ?> doctors available</span>
      </span>
      <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-clipboard2-check me-1"></i>
        Consultation History
      </a>
      <a href="<?= \App\Helpers\Helper::url('/patient/available-slots') ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-calendar2-week me-1"></i>
        View All Slots
      </a>
    </div>
  </div>

  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--success">
          <i class="bi bi-person-badge-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['available_doctors'] ?? 0) ?></div>
          <div class="ux-stat__label">Available Doctors</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--navy">
          <i class="bi bi-calendar2-check-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['available_slots'] ?? 0) ?></div>
          <div class="ux-stat__label">Open Consultation Slots</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--purple">
          <i class="bi bi-heart-pulse-fill"></i>
        </div>
        <div class="flex-grow-1">
          <div class="ux-stat__value"><?= (int) ($summary['specializations'] ?? 0) ?></div>
          <div class="ux-stat__label">Specializations</div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($doctors === []): ?>
    <div class="ux-card">
      <div class="ux-table-wrapper border-0">
        <div class="ux-table__empty-state">
          <div class="ux-empty">
            <div class="ux-empty__icon">
              <i class="bi bi-search"></i>
            </div>
            <h4 class="ux-empty__title">No doctors currently available</h4>
            <p class="ux-empty__text">Once clinicians publish availability, this directory will populate automatically.</p>
            <div class="ux-empty__action">
              <a href="<?= \App\Helpers\Helper::url('/patient/dashboard') ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-arrow-left me-1"></i>
                Back to Dashboard
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="row g-4">
      <?php foreach ($doctors as $doctor): ?>
        <div class="col-lg-6 col-xl-4">
          <article class="ux-card h-100 d-flex flex-column">
            <div class="card-header border-0 bg-transparent pb-0 pt-4 px-4">
              <div class="d-flex align-items-start gap-3">
                <?php
                $avatarPath = $doctor['profile_photo_path'] ?? null;
                $fullName = $doctor['full_name'] ?? 'Doctor';
                $avatarClass = 'user-avatar user-avatar--sm';
                require __DIR__ . '/../../partials/shared/user_avatar.php';
                ?>
                <div class="flex-grow-1">
                  <span class="ux-badge ux-badge--approved">
                    <i class="bi bi-check-circle-fill me-1"></i>
                    Available
                  </span>
                  <h3 class="h6 fw-bold mb-1 mt-2"><?= \App\Helpers\Helper::escape((string) ($doctor['full_name'] ?? 'Doctor')) ?></h3>
                  <p class="mb-1 text-primary fw-semibold small"><?= \App\Helpers\Helper::escape((string) ($doctor['specialization'] ?? 'General Practice')) ?></p>
                  <p class="text-muted small mb-0"><?= \App\Helpers\Helper::escape((string) ($doctor['professional_title'] ?? 'Medical Practitioner')) ?></p>
                </div>
              </div>
            </div>

            <div class="card-body flex-grow-1 d-flex flex-column">
              <div class="mb-3">
                <span class="d-block small text-muted mb-1">Next Available Day</span>
                <strong><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($doctor['next_available_date'] ?? ''), 'D, d M Y', 'Not available')) ?></strong>
              </div>

              <div class="mb-3">
                <span class="d-block small text-muted mb-2">Available Days</span>
                <div class="d-flex flex-wrap gap-2">
                  <?php foreach (($doctor['available_days'] ?? []) as $day): ?>
                    <span class="ux-badge ux-badge--neutral"><?= \App\Helpers\Helper::escape((string) $day) ?></span>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="mb-4">
                <span class="d-block small text-muted mb-2">Available Times</span>
                <div class="d-flex flex-wrap gap-2">
                  <?php foreach (($doctor['available_times'] ?? []) as $time): ?>
                    <span class="ux-badge ux-badge--neutral"><?= \App\Helpers\Helper::escape((string) $time) ?></span>
                  <?php endforeach; ?>
                </div>
              </div>

              <div class="mt-auto">
                <span class="d-block small text-muted mb-2">Book a Consultation</span>
                <div class="d-grid gap-2">
                  <?php foreach (($doctor['booking_slots'] ?? []) as $slot): ?>
                    <?php
                    $slotId = (int) ($slot['id'] ?? 0);
                    $slotDay = \App\Helpers\Helper::formatDate((string) ($slot['consultation_date'] ?? ''), 'D, d M Y', 'Not available');
                    $slotTime = substr((string) ($slot['start_time'] ?? ''), 0, 5) . ' - ' . substr((string) ($slot['end_time'] ?? ''), 0, 5);
                    $bookAriaLabel = 'Book consultation for ' . $slotDay . ' at ' . $slotTime . ' with ' . ($doctor['full_name'] ?? 'doctor');
                    ?>
                    <a
                      href="<?= \App\Helpers\Helper::url('/patient/consultation-requests/book/' . (string) $slotId) ?>"
                      class="btn btn-outline-primary btn-sm px-3 <?= $slotId <= 0 ? 'disabled' : '' ?>"
                      aria-label="<?= $slotId > 0 ? \App\Helpers\Helper::escape($bookAriaLabel) : '' ?>"
                    >
                      <i class="bi bi-calendar2-check me-1"></i>
                      <?= \App\Helpers\Helper::escape($slotDay) ?> · <?= \App\Helpers\Helper::escape($slotTime) ?>
                    </a>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </article>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (is_array($pagination) && ($pagination['total_pages'] ?? 0) > 1): ?>
      <?php
      $page = (int) ($pagination['current_page'] ?? 1);
      $totalPages = (int) ($pagination['total_pages'] ?? 1);
      ?>
      <div class="ux-card mt-4">
        <div class="card-footer border-0 bg-transparent pt-4 pb-0">
          <nav class="d-flex justify-content-end align-items-center gap-2 flex-wrap" aria-label="Doctor directory pagination">
            <?php if ($page > 1): ?>
              <a href="<?= $buildPageUrl($page - 1) ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-chevron-left me-1"></i>
                Previous
              </a>
            <?php endif; ?>
            <span class="small text-muted">
              Page <?= $page ?> of <?= $totalPages ?>
            </span>
            <?php if ($page < $totalPages): ?>
              <a href="<?= $buildPageUrl($page + 1) ?>" class="btn btn-outline-primary btn-sm">
                Next
                <i class="bi bi-chevron-right ms-1"></i>
              </a>
            <?php endif; ?>
          </nav>
        </div>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>
