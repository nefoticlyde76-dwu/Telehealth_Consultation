<?php

$filters = $filters ?? ['doctor_id' => 0, 'specialization' => '', 'consultation_date' => ''];
$slots = $slots ?? [];
$summary = $summary ?? [];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0];
$doctorOptions = $doctorOptions ?? [];
$specializationOptions = $specializationOptions ?? [];
$statusMessage = $statusMessage ?? null;

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

  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-4">
        <div>
          <span class="section-badge mb-3">
            <i class="bi bi-calendar2-week"></i>
            Consultation Slot Viewer
          </span>
          <h2 class="h4 mb-2">View available consultation slots only</h2>
          <p class="text-muted mb-0">Patients can filter future availability by doctor, specialization, and consultation date. Booking is now available for visible slots.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?= \App\Helpers\Helper::url('/patient/doctors') ?>" class="btn btn-outline-primary rounded-pill px-4">
            <i class="bi bi-person-badge me-2"></i>
            Browse Doctors
          </a>
          <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests') ?>" class="btn btn-primary rounded-pill px-4">
            <i class="bi bi-clipboard2-check me-2"></i>
            Consultation History
          </a>
          <a href="<?= \App\Helpers\Helper::url('/patient/dashboard') ?>" class="btn btn-outline-primary rounded-pill px-4">
            <i class="bi bi-arrow-left me-2"></i>
            Back to Dashboard
          </a>
        </div>
      </div>

      <form method="GET" action="<?= \App\Helpers\Helper::url('/patient/available-slots') ?>" class="user-filter-form">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label for="doctor_id" class="form-label">Filter by doctor</label>
            <select class="form-select" id="doctor_id" name="doctor_id">
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
            <label for="specialization" class="form-label">Filter by specialization</label>
            <select class="form-select" id="specialization" name="specialization">
              <option value="">All specializations</option>
              <?php foreach ($specializationOptions as $specializationOption): ?>
                <option value="<?= \App\Helpers\Helper::escape((string) $specializationOption) ?>" <?= ($filters['specialization'] ?? '') === $specializationOption ? 'selected' : '' ?>>
                  <?= \App\Helpers\Helper::escape((string) $specializationOption) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-md-4">
            <label for="consultation_date" class="form-label">Filter by consultation date</label>
            <input
              type="date"
              class="form-control"
              id="consultation_date"
              name="consultation_date"
              value="<?= \App\Helpers\Helper::escape((string) ($filters['consultation_date'] ?? '')) ?>"
            >
          </div>

          <div class="col-12 d-flex flex-wrap gap-2 justify-content-end">
            <button type="submit" class="btn btn-primary rounded-pill px-4">
              <i class="bi bi-funnel me-2"></i>
              Apply Filters
            </button>
            <a href="<?= \App\Helpers\Helper::url('/patient/available-slots') ?>" class="btn btn-outline-primary rounded-pill px-4">Reset</a>
          </div>
        </div>
      </form>
    </div>
  </div>
</section>

<section class="mb-4">
  <div class="row g-4">
    <div class="col-sm-6 col-xl-4">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Available Doctors</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($summary['available_doctors'] ?? 0)) ?></strong>
        <span class="admin-summary-meta">Doctors with patient-visible availability.</span>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Open Consultation Slots</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($summary['available_slots'] ?? 0)) ?></strong>
        <span class="admin-summary-meta">Future slots currently ready for patient browsing.</span>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="admin-summary-card h-100">
        <span class="admin-summary-label">Specializations</span>
        <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($summary['specializations'] ?? 0)) ?></strong>
        <span class="admin-summary-meta">Distinct specialization filters currently available.</span>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
          <h3 class="h5 mb-1">Available Consultation Slots</h3>
          <p class="text-muted mb-0">Only future slots with status <strong>Available</strong> are displayed here.</p>
        </div>
        <span class="badge badge-soft-info rounded-pill px-3 py-2">
          Page <?= \App\Helpers\Helper::escape((string) ($pagination['current_page'] ?? 1)) ?> of <?= \App\Helpers\Helper::escape((string) ($pagination['total_pages'] ?? 1)) ?>
        </span>
      </div>

      <div class="table-responsive">
        <table class="table admin-user-table align-middle mb-0">
          <caption class="visually-hidden">Patient-facing consultation slot viewer showing doctor, specialization, consultation date, time, notes, and visibility status.</caption>
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
                <td colspan="7">
                  <div class="admin-table-empty text-center py-5">
                    <div class="empty-state-icon mx-auto mb-3">
                      <i class="bi bi-calendar-x"></i>
                    </div>
                    <h4 class="h5 mb-2">No available consultation slots matched the current filters</h4>
                    <p class="text-muted mb-0">Adjust the patient filters to review other future consultation slots.</p>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($slots as $slot): ?>
                <?php $slotId = (int) ($slot['id'] ?? 0); ?>
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
                    <span class="badge badge-soft-neutral rounded-pill border px-3 py-2"><?= \App\Helpers\Helper::escape((string) ($slot['specialization'] ?? 'General Practice')) ?></span>
                  </td>
                  <td>
                    <strong><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($slot['consultation_date'] ?? ''), 'd M Y', 'Not available')) ?></strong>
                    <span class="d-block text-muted small"><?= \App\Helpers\Helper::escape((string) ($slot['consultation_date'] ?? '')) ?></span>
                  </td>
                  <td>
                    <strong><?= \App\Helpers\Helper::escape(substr((string) ($slot['start_time'] ?? ''), 0, 5)) ?> - <?= \App\Helpers\Helper::escape(substr((string) ($slot['end_time'] ?? ''), 0, 5)) ?></strong>
                    <span class="d-block text-muted small">Future consultation window</span>
                  </td>
                  <td>
                    <span class="text-muted small d-block"><?= \App\Helpers\Helper::escape((string) ($slot['notes'] ?? 'No notes provided')) ?></span>
                  </td>
                  <td>
                    <span class="badge badge-soft-success rounded-pill">Available</span>
                  </td>
                  <td class="text-end">
                    <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests/book/' . (string) $slotId) ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3 <?= $slotId <= 0 ? 'disabled' : '' ?>">
                      Book
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
        <nav class="mt-4" aria-label="Patient consultation slot pagination">
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
