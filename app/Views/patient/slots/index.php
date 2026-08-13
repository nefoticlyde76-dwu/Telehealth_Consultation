<?php

$filters = $filters ?? ['doctor_id' => 0, 'specialization' => '', 'consultation_date' => ''];
$slots = $slots ?? [];
$summary = $summary ?? [];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0];
$doctorOptions = $doctorOptions ?? [];
$specializationOptions = $specializationOptions ?? [];
$statusMessage = $statusMessage ?? null;

require __DIR__ . '/../../partials/shared/status_helper.php';

$totalItems = (int) ($pagination['total_items'] ?? 0);
$buildPageUrl = static function (int $page) use ($filters): string {
    $query = array_filter([
        'doctor_id' => (int) ($filters['doctor_id'] ?? 0),
        'specialization' => $filters['specialization'] ?? '',
        'consultation_date' => $filters['consultation_date'] ?? '',
        'page' => $page,
    ], static fn ($value) => $value !== '' && $value !== 0);

    $queryString = http_build_query($query);

    return \App\Helpers\Helper::url('/patient/available-slots') . ($queryString !== '' ? '?' . $queryString : '');
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
      <p class="ux-page-header__subtitle">Filter future availability by doctor, specialization, or consultation date to find and book your preferred appointment.</p>
    </div>
    <div class="ux-page-header__right">
      <span class="ux-chip ux-badge--dotless">
        <i class="bi bi-calendar2-week-fill"></i>
        <span><?= $totalItems ?> slots visible</span>
      </span>
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

  <div class="ux-card ux-filter">
    <div class="card-header">
      <h3 class="h6">
        <i class="bi bi-funnel-fill ux-filter__header-icon"></i>
        Filter slots
      </h3>
    </div>
    <form method="GET" action="<?= \App\Helpers\Helper::url('/patient/available-slots') ?>" class="user-filter-form">
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label for="doctor_id" class="form-label">Doctor</label>
          <select class="form-select" id="doctor_id" name="doctor_id" aria-label="Filter by doctor">
            <option value="">All doctors</option>
            <?php foreach ($doctorOptions as $doctorOption): ?>
              <?php $optionDoctorId = (int) ($doctorOption['doctor_id'] ?? 0); ?>
              <option value="<?= \App\Helpers\Helper::escape((string) $optionDoctorId) ?>" <?= (int) ($filters['doctor_id'] ?? 0) === $optionDoctorId ? 'selected' : '' ?>>
                <?= \App\Helpers\Helper::escape((string) ($doctorOption['full_name'] ?? 'Doctor')) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label for="specialization" class="form-label">Specialization</label>
          <select class="form-select" id="specialization" name="specialization" aria-label="Filter by specialization">
            <option value="">All specializations</option>
            <?php foreach ($specializationOptions as $specializationOption): ?>
              <option value="<?= \App\Helpers\Helper::escape((string) $specializationOption) ?>" <?= ($filters['specialization'] ?? '') === $specializationOption ? 'selected' : '' ?>>
                <?= \App\Helpers\Helper::escape((string) $specializationOption) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label for="consultation_date" class="form-label">Consultation Date</label>
          <input
            type="date"
            class="form-control"
            id="consultation_date"
            name="consultation_date"
            value="<?= \App\Helpers\Helper::escape((string) ($filters['consultation_date'] ?? '')) ?>"
          >
        </div>

        <div class="col-12">
          <div class="ux-filter__actions">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-funnel-fill me-1"></i>
              Apply Filters
            </button>
            <a href="<?= \App\Helpers\Helper::url('/patient/available-slots') ?>" class="btn btn-outline-primary btn-sm">
              Reset
            </a>
          </div>
        </div>
      </div>
    </form>
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

  <div class="ux-card">
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
                    <h4 class="ux-empty__title">No available consultation slots matched the current filters</h4>
                    <p class="ux-empty__text">Adjust the filters to review other future consultation slots.</p>
                    <div class="ux-empty__action">
                      <a href="<?= \App\Helpers\Helper::url('/patient/available-slots') ?>" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-arrow-clockwise me-1"></i>
                        Reset Filters
                      </a>
                    </div>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($slots as $slot): ?>
                <?php
                $slotId = (int) ($slot['id'] ?? 0);
                $slotStatusBadge = ux_slot_status_badge_class('Available', false);
                ?>
                <tr>
                  <td>
                    <div class="d-flex align-items-center gap-3">
                      <?php
                      $avatarPath = $slot['profile_photo_path'] ?? null;
                      $fullName = $slot['full_name'] ?? 'Doctor';
                      $avatarClass = 'user-avatar user-avatar--xs';
                      require __DIR__ . '/../../partials/shared/user_avatar.php';
                      ?>
                      <div>
                        <strong class="d-block"><?= \App\Helpers\Helper::escape((string) ($slot['full_name'] ?? 'Doctor')) ?></strong>
                        <span class="text-muted small"><?= \App\Helpers\Helper::escape((string) ($slot['professional_title'] ?? 'Medical Practitioner')) ?></span>
                      </div>
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
                    <span class="ux-badge <?= $slotStatusBadge ?>">Available</span>
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
