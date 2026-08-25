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
        My Consultations
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

  <div class="ux-card">
    <div class="ux-table-wrapper border-0">
      <div class="table-responsive">
        <table class="ux-table">
          <caption class="visually-hidden">Available doctors with specialization, next available day, and booking actions.</caption>
          <thead>
            <tr>
              <th scope="col">Doctor</th>
              <th scope="col">Specialization</th>
              <th scope="col">Status</th>
              <th scope="col">Next available</th>
              <th scope="col">Book</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($doctors === []): ?>
              <tr>
                <td colspan="5" class="ux-table__empty-state">
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
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($doctors as $doctor): ?>
                <?php
                $availableDays = is_array($doctor['available_days'] ?? null) ? $doctor['available_days'] : [];
                $bookingSlots = is_array($doctor['booking_slots'] ?? null) ? $doctor['booking_slots'] : [];
                ?>
                <tr>
                  <td>
                    <?php
                    $personName = (string) ($doctor['full_name'] ?? 'Doctor');
                    $personPhoto = $doctor['profile_photo_path'] ?? null;
                    $personMeta = trim((string) ($doctor['professional_title'] ?? 'Medical Practitioner'));
                    $personSize = 'sm';
                    require __DIR__ . '/../../partials/shared/person_row.php';
                    ?>
                  </td>
                  <td><?= \App\Helpers\Helper::escape((string) ($doctor['specialization'] ?? 'General Practice')) ?></td>
                  <td><?= ux_status_badge(\App\Helpers\Status::SLOT_AVAILABLE, \App\Helpers\Status::DOMAIN_SLOT) ?></td>
                  <td>
                    <strong class="d-block"><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($doctor['next_available_date'] ?? ''), 'D, d M Y', 'Not available')) ?></strong>
                    <?php if ($availableDays !== []): ?>
                      <span class="text-muted small"><?= \App\Helpers\Helper::escape(implode('; ', $availableDays)) ?></span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($bookingSlots === []): ?>
                      <span class="text-muted small">No open slots</span>
                    <?php else: ?>
                      <div class="d-flex flex-column align-items-start gap-2">
                        <?php foreach ($bookingSlots as $slot): ?>
                          <?php
                          $slotId = (int) ($slot['id'] ?? 0);
                          $slotDay = \App\Helpers\Helper::formatDate((string) ($slot['consultation_date'] ?? ''), 'D, d M Y', 'Not available');
                          $slotTime = substr((string) ($slot['start_time'] ?? ''), 0, 5) . ' - ' . substr((string) ($slot['end_time'] ?? ''), 0, 5);
                          $bookAriaLabel = 'Book consultation for ' . $slotDay . ' at ' . $slotTime . ' with ' . ($doctor['full_name'] ?? 'doctor');
                          ?>
                          <a
                            href="<?= \App\Helpers\Helper::url('/patient/consultation-requests/book/' . (string) $slotId) ?>"
                            class="btn btn-outline-primary btn-sm <?= $slotId <= 0 ? 'disabled' : '' ?>"
                            aria-label="<?= $slotId > 0 ? \App\Helpers\Helper::escape($bookAriaLabel) : '' ?>"
                          >
                            <i class="bi bi-calendar2-check me-1"></i>
                            <?= \App\Helpers\Helper::escape($slotDay) ?> · <?= \App\Helpers\Helper::escape($slotTime) ?>
                          </a>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <?php
    $paginationPath = '/patient/doctors';
    $paginationFilters = [];
    $paginationBuildUrl = $buildPageUrl;
    $paginationLabel = 'doctors';
    $paginationAria = 'Doctor directory pagination';
    require __DIR__ . '/../../partials/shared/list_pagination.php';
    ?>
  </div>
</section>
