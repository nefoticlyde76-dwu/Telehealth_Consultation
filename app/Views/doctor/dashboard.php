<?php require __DIR__ . '/../partials/dashboard/overview.php'; ?>

<?php
$doctorProfile = $doctorProfile ?? [];
$profilePhotoPath = $doctorProfile['profile_photo_path'] ?? null;
$signaturePath = $doctorProfile['signature_path'] ?? null;
$todaySummary = $todaySummary ?? [];
$upcomingSlots = $upcomingSlots ?? [];
$assetReadiness = $assetReadiness ?? [];
?>

<section class="mt-4">
  <div class="row g-4">
    <div class="col-xl-7">
      <div class="card border-0 shadow-sm rounded-4 h-100 dashboard-widget-shell">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
              <span class="section-badge mb-3">
                <i class="bi bi-calendar2-heart"></i>
                Today's Schedule
              </span>
              <h3 class="h5 mb-1">Consultation visibility built around your active schedule</h3>
              <p class="text-muted mb-0">Review today’s schedule, monitor open slots, and keep upcoming consultation windows in view from one clinician workspace.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <a href="<?= \App\Helpers\Helper::url('/doctor/availability') ?>" class="btn btn-primary rounded-pill px-4">
                <i class="bi bi-calendar-week me-2"></i>
                Open Availability
              </a>
            </div>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-sm-4">
              <div class="widget-mini-stat h-100">
                <span class="widget-mini-stat-label">Today's Schedule</span>
                <strong data-counter="<?= \App\Helpers\Helper::escape((string) ($todaySummary['total_today_slots'] ?? 0)) ?>"><?= \App\Helpers\Helper::escape((string) ($todaySummary['total_today_slots'] ?? 0)) ?></strong>
                <span class="admin-summary-meta">Total slots scheduled for today</span>
              </div>
            </div>
            <div class="col-sm-4">
              <div class="widget-mini-stat h-100">
                <span class="widget-mini-stat-label">Available Today</span>
                <strong data-counter="<?= \App\Helpers\Helper::escape((string) ($todaySummary['available_today_slots'] ?? 0)) ?>"><?= \App\Helpers\Helper::escape((string) ($todaySummary['available_today_slots'] ?? 0)) ?></strong>
                <span class="admin-summary-meta">Still open for future patient booking</span>
              </div>
            </div>
            <div class="col-sm-4">
              <div class="widget-mini-stat h-100">
                <span class="widget-mini-stat-label">Booked Today</span>
                <strong data-counter="<?= \App\Helpers\Helper::escape((string) ($todaySummary['booked_today_slots'] ?? 0)) ?>"><?= \App\Helpers\Helper::escape((string) ($todaySummary['booked_today_slots'] ?? 0)) ?></strong>
                <span class="admin-summary-meta">Reserved slots already committed</span>
              </div>
            </div>
          </div>

          <div class="dashboard-info-panel">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
              <div>
                <span class="dashboard-info-label">Upcoming Consultation Windows</span>
                <h4 class="h6 mb-0">Next visible schedule entries</h4>
              </div>
              <span class="badge badge-soft-info rounded-pill px-3 py-2"><?= \App\Helpers\Helper::escape((string) count($upcomingSlots)) ?> upcoming</span>
            </div>

            <div class="schedule-slot-list">
              <?php if ($upcomingSlots === []): ?>
                <div class="schedule-slot-card">
                  <div>
                    <strong class="d-block">No upcoming schedule entries yet</strong>
                    <span class="small text-muted">Create availability slots to populate your clinician schedule timeline.</span>
                  </div>
                </div>
              <?php else: ?>
                <?php foreach ($upcomingSlots as $slot): ?>
                  <div class="schedule-slot-card">
                    <div>
                      <strong class="d-block"><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($slot['consultation_date'] ?? ''), 'D, d M Y', 'Not available')) ?></strong>
                      <span class="small text-muted"><?= \App\Helpers\Helper::escape(substr((string) ($slot['start_time'] ?? ''), 0, 5)) ?> - <?= \App\Helpers\Helper::escape(substr((string) ($slot['end_time'] ?? ''), 0, 5)) ?></span>
                    </div>
                    <div class="text-end">
                      <span class="badge <?= ($slot['status'] ?? '') === 'Available' ? 'badge-soft-success' : 'badge-soft-warning' ?> rounded-pill mb-2">
                        <?= \App\Helpers\Helper::escape((string) ($slot['status'] ?? 'Available')) ?>
                      </span>
                      <span class="d-block small text-muted"><?= \App\Helpers\Helper::escape((string) ($slot['notes'] ?? 'No notes provided')) ?></span>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-5">
      <div class="card border-0 shadow-sm rounded-4 h-100 dashboard-widget-shell">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
              <h3 class="h5 mb-1">Clinician Identity & Assets</h3>
              <p class="text-muted mb-0">Profile identity and signature readiness for future clinical documentation.</p>
            </div>
            <span class="badge <?= !empty($assetReadiness['has_profile_photo']) && !empty($assetReadiness['has_signature']) ? 'badge-soft-success' : 'badge-soft-warning' ?> rounded-pill px-3 py-2">
              <?= !empty($assetReadiness['has_profile_photo']) && !empty($assetReadiness['has_signature']) ? 'Ready' : 'Needs review' ?>
            </span>
          </div>

          <div class="widget-mini-stat-list mb-4">
            <div class="widget-mini-stat">
              <span class="widget-mini-stat-label">Phone Number</span>
              <strong><?= \App\Helpers\Helper::escape((string) ($doctorProfile['phone'] ?? 'Not provided')) ?></strong>
            </div>
            <div class="widget-mini-stat">
              <span class="widget-mini-stat-label">Specialization</span>
              <strong><?= \App\Helpers\Helper::escape((string) ($doctorProfile['specialization'] ?? 'Not provided')) ?></strong>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <div class="doctor-asset-card h-100">
                <span class="doctor-asset-label">Profile Photo</span>
                <div class="doctor-asset-avatar-shell">
                  <?php
                  $avatarPath = $profilePhotoPath;
                  $fullName = $doctorProfile['full_name'] ?? ($user->full_name ?? 'Doctor');
                  $avatarClass = 'user-avatar user-avatar--asset';
                  require __DIR__ . '/../partials/shared/user_avatar.php';
                  ?>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="doctor-asset-card h-100">
                <span class="doctor-asset-label">Digital Signature</span>
                <?php if (!empty($signaturePath)): ?>
                  <img class="doctor-asset-image doctor-signature-image" src="<?= \App\Helpers\Helper::asset($signaturePath) ?>" alt="Doctor digital signature">
                <?php else: ?>
                  <div class="doctor-asset-placeholder">
                    <i class="bi bi-pen"></i>
                    <span>No signature uploaded</span>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="mt-4 d-grid gap-2">
            <a href="<?= \App\Helpers\Helper::url('/doctor/profile') ?>" class="btn btn-outline-primary rounded-pill">
              <i class="bi bi-person-vcard me-2"></i>
              View Clinician Profile
            </a>
            <a href="<?= \App\Helpers\Helper::url('/doctor/profile/edit') ?>" class="btn btn-outline-primary rounded-pill">
              <i class="bi bi-upload me-2"></i>
              Upload or Replace Assets
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
