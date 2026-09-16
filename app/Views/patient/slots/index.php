<?php

use App\Helpers\DoctorScheduleColor;
use App\Helpers\Helper;
use App\Helpers\ListFilter;

$filters = $filters ?? ['doctor_id' => 0, 'specialization' => '', 'consultation_date' => ''];
$slots = $slots ?? [];
$summary = $summary ?? [];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0];
$specializationOptions = $specializationOptions ?? [];
$statusMessage = $statusMessage ?? null;

require __DIR__ . '/../../partials/shared/status_helper.php';

$totalItems = (int) ($pagination['total_items'] ?? 0);
$buildPageUrl = static function (int $page) use ($filters): string {
    $query = array_filter([
        'view' => 'list',
        'doctor_id' => (int) ($filters['doctor_id'] ?? 0),
        'specialization' => $filters['specialization'] ?? '',
        'consultation_date' => $filters['consultation_date'] ?? '',
        'page' => $page,
    ], static fn ($value) => $value !== '' && $value !== 0);

    $queryString = http_build_query($query);

    return Helper::url('/patient/available-slots') . ($queryString !== '' ? '?' . $queryString : '');
};

$filterTabs = array_merge(
    [['value' => '', 'label' => 'All']],
    array_map(static fn (string $option): array => [
        'value' => $option,
        'label' => $option,
    ], $specializationOptions)
);
$filterTabCurrent = (string) ($filters['specialization'] ?? '');
$filterTabAria = 'Filter slots by specialization';
$filterTabUrl = static function (string $value) use ($filters): string {
    return ListFilter::url('/patient/available-slots', $filters + ['view' => 'list'], ['specialization' => $value, 'page' => 1]);
};
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/patient/dashboard') ?>">Dashboard</a></li>
        <li class="active">Available Slots</li>
      </ol>
      <h2 class="ux-page-header__title">Available Consultation Slots</h2>
      <p class="ux-page-header__subtitle">Browse future availability and book your preferred appointment.</p>
    </div>
    <div class="ux-page-header__right">
      <span class="ux-chip ux-badge--dotless">
        <i class="bi bi-calendar2-week-fill"></i>
        <span><?= $totalItems ?> slots visible</span>
      </span>
      <a href="<?= \App\Helpers\Helper::url('/patient/available-slots') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-calendar3 me-1"></i>
        Weekly schedule
      </a>
      <a href="<?= \App\Helpers\Helper::url('/patient/doctors') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-person-badge me-1"></i>
        Browse Doctors
      </a>
      <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests') ?>" class="btn btn-primary btn-sm">
        <i class="bi bi-clipboard2-check me-1"></i>
        My Requests
      </a>
    </div>
  </div>

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
          <div class="ux-stat__label">Open Slots</div>
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

  <div class="ux-card ux-data-card">
    <div class="ux-card__header">
      <h2 class="ux-data-card__title">Available slots</h2>
      <?php require __DIR__ . '/../../partials/shared/table_filter_tabs.php'; ?>
    </div>
    <div class="ux-table-wrapper border-0">
      <div class="table-responsive">
        <table class="ux-table">
          <caption class="visually-hidden">Patient-facing consultation slot viewer showing doctor, specialization, consultation date, time, notes, and booking action.</caption>
          <thead>
            <tr>
              <th scope="col">Doctor</th>
              <th scope="col">Specialization</th>
              <th scope="col">Consultation Date</th>
              <th scope="col">Time</th>
              <th scope="col">Notes</th>
              <th scope="col">Status</th>
              <th scope="col" class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($slots === []): ?>
              <tr>
                <td colspan="7" class="ux-table__empty-state">
                  <div class="ux-empty">
                    <div class="ux-empty__icon">
                      <i class="bi bi-calendar-x"></i>
                    </div>
                    <h4 class="ux-empty__title">No available consultation slots</h4>
                    <p class="ux-empty__text">There are no future consultation slots in this view.</p>
                    <div class="ux-empty__action">
                      <a href="<?= \App\Helpers\Helper::url('/patient/available-slots?view=list') ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-arrow-clockwise me-1"></i>
                        View all slots
                      </a>
                    </div>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($slots as $slot): ?>
                <?php
                $slotId = (int) ($slot['id'] ?? 0);
                $slotExpiresAt = \App\Helpers\Helper::combineDateTimeIso(
                    (string) ($slot['consultation_date'] ?? ''),
                    (string) ($slot['end_time'] ?? '')
                );
                ?>
                <tr<?= $slotExpiresAt !== '' ? ' data-slot-expires-at="' . \App\Helpers\Helper::escape($slotExpiresAt) . '"' : '' ?>>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <?php
                      $slotColor = is_array($slot['color'] ?? null) ? $slot['color'] : [];
                      ?>
                      <span
                        class="mbpha-avail__legend-swatch is-doctor"
                        style="<?= Helper::escape(DoctorScheduleColor::inlineSwatchStyle($slotColor)) ?>"
                        aria-hidden="true"
                      ></span>
                      <?php
                      $personName = (string) ($slot['full_name'] ?? 'Doctor');
                      $personPhoto = $slot['profile_photo_path'] ?? null;
                      $personMeta = (string) ($slot['professional_title'] ?? 'Medical Practitioner');
                      $personSize = 'sm';
                      require __DIR__ . '/../../partials/shared/person_row.php';
                      ?>
                    </div>
                  </td>
                  <td>
                    <span class="ux-badge ux-badge--neutral">
                      <?= \App\Helpers\Helper::escape((string) ($slot['specialization'] ?? 'General Practice')) ?>
                    </span>
                  </td>
                  <td>
                    <div class="d-flex flex-column">
                      <strong><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($slot['consultation_date'] ?? ''), 'd M Y', 'Not available')) ?></strong>
                      <span class="text-muted small"><?= \App\Helpers\Helper::escape((string) ($slot['consultation_date'] ?? '')) ?></span>
                    </div>
                  </td>
                  <td>
                    <div class="d-flex flex-column">
                      <strong><?= \App\Helpers\Helper::escape(substr((string) ($slot['start_time'] ?? ''), 0, 5)) ?> - <?= \App\Helpers\Helper::escape(substr((string) ($slot['end_time'] ?? ''), 0, 5)) ?></strong>
                      <span class="text-muted small">Future consultation window</span>
                    </div>
                  </td>
                  <td>
                    <span class="text-muted small"><?= \App\Helpers\Helper::escape((string) ($slot['notes'] ?? 'No notes provided')) ?></span>
                  </td>
                  <td>
                    <?= ux_status_badge(\App\Helpers\Status::SLOT_AVAILABLE, \App\Helpers\Status::DOMAIN_SLOT) ?>
                  </td>
                  <td class="text-end">
                    <div class="ux-table__actions">
                      <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests/book/' . (string) $slotId) ?>" class="btn btn-outline-primary btn-sm <?= $slotId <= 0 ? 'disabled' : '' ?>" aria-label="Book consultation slot with <?= \App\Helpers\Helper::escape((string) ($slot['full_name'] ?? 'a doctor')) ?>">
                        Book
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
        <nav class="admin-pagination d-flex justify-content-end align-items-center gap-2 flex-wrap" aria-label="Patient consultation slot pagination">
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
