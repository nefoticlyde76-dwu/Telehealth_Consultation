<?php require __DIR__ . '/../partials/dashboard/overview.php'; ?>

<?php
$doctorProfile = $doctorProfile ?? [];
$profilePhotoPath = $doctorProfile['profile_photo_path'] ?? null;
$signaturePath = $doctorProfile['signature_path'] ?? null;
$todaySummary = $todaySummary ?? [];
$upcomingSlots = $upcomingSlots ?? [];
$upcomingApprovedAppointments = $upcomingApprovedAppointments ?? [];
$recentApprovedAppointmentCount = $recentApprovedAppointmentCount ?? 0;
$upcomingApprovedAppointmentCount = $upcomingApprovedAppointmentCount ?? 0;
$weeklySchedule = $weeklySchedule ?? [];
$availabilitySummary = $availabilitySummary ?? [];
$assetReadiness = $assetReadiness ?? [];

$weeklyScheduleMap = [];
foreach ($weeklySchedule as $scheduleDay) {
    $weeklyScheduleMap[(string) ($scheduleDay['consultation_date'] ?? '')] = $scheduleDay;
}
?>

<section class="mt-4">
  <div class="row g-4">
    <div class="col-xl-8">
      <div class="card border-0 shadow-sm rounded-4 h-100 dashboard-widget-shell">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
              <span class="section-badge mb-3">
                <i class="bi bi-calendar2-heart"></i>
                Appointment Overview
              </span>
              <h3 class="h5 mb-1">Today's appointments and weekly schedule at a glance</h3>
              <p class="text-muted mb-0">Review today’s appointment volume, scan the next seven days, and keep upcoming consultation windows in view from one clinician workspace.</p>
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
                <span class="widget-mini-stat-label">Today's Appointments</span>
                <strong data-counter="<?= \App\Helpers\Helper::escape((string) ($todaySummary['total_today_slots'] ?? 0)) ?>"><?= \App\Helpers\Helper::escape((string) ($todaySummary['total_today_slots'] ?? 0)) ?></strong>
                <span class="admin-summary-meta">Total consultation windows scheduled for today</span>
              </div>
            </div>
            <div class="col-sm-4">
              <div class="widget-mini-stat h-100">
                <span class="widget-mini-stat-label">Available Consultation Slots</span>
                <strong data-counter="<?= \App\Helpers\Helper::escape((string) ($todaySummary['available_today_slots'] ?? 0)) ?>"><?= \App\Helpers\Helper::escape((string) ($todaySummary['available_today_slots'] ?? 0)) ?></strong>
                <span class="admin-summary-meta">Still open for future patient booking</span>
              </div>
            </div>
            <div class="col-sm-4">
              <div class="widget-mini-stat h-100">
                <span class="widget-mini-stat-label">Booked Slots</span>
                <strong data-counter="<?= \App\Helpers\Helper::escape((string) ($todaySummary['booked_today_slots'] ?? 0)) ?>"><?= \App\Helpers\Helper::escape((string) ($todaySummary['booked_today_slots'] ?? 0)) ?></strong>
                <span class="admin-summary-meta">Reserved consultation windows for today</span>
              </div>
            </div>
          </div>

          <div class="dashboard-info-panel mb-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
              <div>
                <span class="dashboard-info-label">Weekly Schedule</span>
                <h4 class="h6 mb-0">Next seven days of consultation coverage</h4>
              </div>
              <span class="badge badge-soft-neutral rounded-pill border px-3 py-2">Weekly calendar</span>
            </div>

            <div class="weekly-calendar-grid">
              <?php for ($offset = 0; $offset < 7; $offset++): ?>
                <?php
                $dayDate = date('Y-m-d', strtotime('+' . $offset . ' day'));
                $dayData = $weeklyScheduleMap[$dayDate] ?? null;
                $dayTotal = (int) ($dayData['total_slots'] ?? 0);
                $dayAvailable = (int) ($dayData['available_slots'] ?? 0);
                $dayBooked = (int) ($dayData['booked_slots'] ?? 0);
                ?>
                <div class="weekly-calendar-day <?= $dayTotal > 0 ? 'weekly-calendar-day--active' : '' ?>">
                  <span class="weekly-calendar-day-name"><?= \App\Helpers\Helper::escape(date('D', strtotime($dayDate))) ?></span>
                  <strong class="weekly-calendar-day-number"><?= \App\Helpers\Helper::escape(date('d', strtotime($dayDate))) ?></strong>
                  <span class="weekly-calendar-day-meta"><?= \App\Helpers\Helper::escape((string) $dayTotal) ?> slots</span>
                  <div class="weekly-calendar-day-status">
                    <span class="badge badge-soft-success rounded-pill"><?= \App\Helpers\Helper::escape((string) $dayAvailable) ?> open</span>
                    <span class="badge badge-soft-warning rounded-pill"><?= \App\Helpers\Helper::escape((string) $dayBooked) ?> booked</span>
                  </div>
                </div>
              <?php endfor; ?>
            </div>
          </div>

          <div class="dashboard-info-panel">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
              <div>
                <span class="dashboard-info-label">Upcoming Consultations</span>
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

    <div class="col-xl-4">
      <div class="row g-4">
        <div class="col-12">
          <div class="card border-0 shadow-sm rounded-4 dashboard-widget-shell h-100">
            <div class="card-body p-4">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <div>
                  <h3 class="h5 mb-1">Consultation Summary</h3>
                  <p class="text-muted mb-0">Current schedule mix across available, booked, and completed consultation states.</p>
                </div>
                <span class="badge badge-soft-info rounded-pill px-3 py-2">Live schedule</span>
              </div>

              <div class="widget-mini-stat-list">
                <div class="widget-mini-stat">
                  <span class="widget-mini-stat-label">Available Consultation Slots</span>
                  <strong><?= \App\Helpers\Helper::escape((string) ($availabilitySummary['available_slots'] ?? 0)) ?></strong>
                </div>
                <div class="widget-mini-stat">
                  <span class="widget-mini-stat-label">Booked Slots</span>
                  <strong><?= \App\Helpers\Helper::escape((string) ($availabilitySummary['booked_slots'] ?? 0)) ?></strong>
                </div>
                <div class="widget-mini-stat">
                  <span class="widget-mini-stat-label">Upcoming Consultations</span>
                  <strong><?= \App\Helpers\Helper::escape((string) ((int) $upcomingApprovedAppointmentCount)) ?></strong>
                </div>
                <div class="widget-mini-stat">
                  <span class="widget-mini-stat-label">Completed Consultations</span>
                  <strong><?= \App\Helpers\Helper::escape((string) ($availabilitySummary['completed_consultations'] ?? 0)) ?></strong>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="card border-0 shadow-sm rounded-4 dashboard-widget-shell h-100">
            <div class="card-body p-4">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <div>
                  <h3 class="h5 mb-1">New Approved Appointments</h3>
                  <p class="text-muted mb-0">Recently approved consultations ready for your upcoming schedule.</p>
                </div>
                <span class="badge badge-soft-info rounded-pill px-3 py-2"><?= \App\Helpers\Helper::escape((string) ((int) $recentApprovedAppointmentCount)) ?> new</span>
              </div>

              <div class="schedule-slot-list">
                <?php if ($upcomingApprovedAppointments === []): ?>
                  <div class="schedule-slot-card">
                    <div>
                      <strong class="d-block">No approved appointments yet</strong>
                      <span class="small text-muted">Approved consultation appointments will appear here after administrator approval.</span>
                    </div>
                  </div>
                <?php else: ?>
                  <?php foreach ($upcomingApprovedAppointments as $appointment): ?>
                    <div class="schedule-slot-card">
                      <div>
                        <strong class="d-block"><?= \App\Helpers\Helper::escape((string) ($appointment['patient_name'] ?? 'Patient')) ?></strong>
                        <span class="small text-muted"><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($appointment['consultation_date'] ?? ''), 'D, d M Y', 'Not available')) ?></span>
                      </div>
                      <div class="text-end">
                        <span class="badge badge-soft-success rounded-pill mb-2">Approved</span>
                        <span class="d-block small text-muted"><?= \App\Helpers\Helper::escape(substr((string) ($appointment['start_time'] ?? ''), 0, 5)) ?> - <?= \App\Helpers\Helper::escape(substr((string) ($appointment['end_time'] ?? ''), 0, 5)) ?></span>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="card border-0 shadow-sm rounded-4 dashboard-widget-shell h-100">
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
    </div>
  </div>
</section>
