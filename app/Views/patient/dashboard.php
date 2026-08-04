<?php require __DIR__ . '/../partials/dashboard/overview.php'; ?>

<?php
$featuredDoctors = $featuredDoctors ?? [];
$slotPreview = $slotPreview ?? [];
$assignedDoctor = $featuredDoctors[0] ?? null;
?>

<section class="mt-4">
  <div class="row g-4">
    <div class="col-xl-8">
      <div class="card border-0 shadow-sm rounded-4 h-100 dashboard-widget-shell">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
              <span class="section-badge mb-3">
                <i class="bi bi-calendar2-check"></i>
                Consultation Status
              </span>
              <h3 class="h5 mb-1">Upcoming consultation visibility before booking opens</h3>
              <p class="text-muted mb-0">Track the next patient-visible consultation opportunities and stay ready for Week 5 booking without leaving your secure dashboard.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <a href="<?= \App\Helpers\Helper::url('/patient/available-slots') ?>" class="btn btn-primary rounded-pill px-4">
                <i class="bi bi-calendar2-week me-2"></i>
                Browse Slots
              </a>
            </div>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-md-5">
              <div class="widget-mini-stat h-100">
                <span class="widget-mini-stat-label">Upcoming Consultation</span>
                <strong><?= $slotPreview === [] ? 'Not scheduled' : 'Available to browse' ?></strong>
                <span class="admin-summary-meta">
                  <?= $slotPreview === [] ? 'No future consultation has been booked yet.' : 'Future consultation slots are visible and ready for review.' ?>
                </span>
              </div>
            </div>
            <div class="col-md-3">
              <div class="widget-mini-stat h-100">
                <span class="widget-mini-stat-label">Consultation History</span>
                <strong data-counter="0">0</strong>
                <span class="admin-summary-meta">History becomes active after booking begins</span>
              </div>
            </div>
            <div class="col-md-4">
              <div class="widget-mini-stat h-100">
                <span class="widget-mini-stat-label">Prescriptions</span>
                <strong data-counter="0">0</strong>
                <span class="admin-summary-meta">Prescription records will follow later consultation modules</span>
              </div>
            </div>
          </div>

          <div class="dashboard-info-panel">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
              <div>
                <span class="dashboard-info-label">Available Consultation Slots</span>
                <h4 class="h6 mb-0">Next patient-visible consultation windows</h4>
              </div>
              <span class="badge badge-soft-info rounded-pill px-3 py-2"><?= \App\Helpers\Helper::escape((string) count($slotPreview)) ?> visible now</span>
            </div>

            <div class="schedule-slot-list">
              <?php if ($slotPreview === []): ?>
                <div class="schedule-slot-card">
                  <div>
                    <strong class="d-block">No future slots are currently visible</strong>
                    <span class="small text-muted">As doctors publish availability, patient-viewable consultation windows will appear here automatically.</span>
                  </div>
                </div>
              <?php else: ?>
                <?php foreach ($slotPreview as $slot): ?>
                  <div class="schedule-slot-card">
                    <div>
                      <strong class="d-block"><?= \App\Helpers\Helper::escape((string) ($slot['full_name'] ?? 'Doctor')) ?></strong>
                      <span class="small text-muted"><?= \App\Helpers\Helper::escape((string) ($slot['specialization'] ?? 'General Practice')) ?></span>
                    </div>
                    <div class="text-end">
                      <strong class="d-block"><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($slot['consultation_date'] ?? ''), 'D, d M Y', 'Not available')) ?></strong>
                      <span class="small text-muted"><?= \App\Helpers\Helper::escape(substr((string) ($slot['start_time'] ?? ''), 0, 5)) ?> - <?= \App\Helpers\Helper::escape(substr((string) ($slot['end_time'] ?? ''), 0, 5)) ?></span>
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
                  <h3 class="h5 mb-1">Assigned Doctor</h3>
                  <p class="text-muted mb-0">A dedicated doctor will appear here after patient booking and assignment workflows begin.</p>
                </div>
                <span class="badge badge-soft-warning rounded-pill px-3 py-2">Pending assignment</span>
              </div>

              <?php if ($assignedDoctor !== null): ?>
                <div class="dashboard-inline-callout">
                  <span class="dashboard-info-label">Current best match to browse</span>
                  <strong class="d-block mb-1"><?= \App\Helpers\Helper::escape((string) ($assignedDoctor['full_name'] ?? 'Doctor')) ?></strong>
                  <p class="text-muted small mb-0"><?= \App\Helpers\Helper::escape((string) ($assignedDoctor['specialization'] ?? 'General Practice')) ?> is currently visible in the doctor directory, but no formal assignment exists yet.</p>
                </div>
              <?php else: ?>
                <div class="dashboard-inline-callout">
                  <span class="dashboard-info-label">No assigned doctor yet</span>
                  <p class="text-muted small mb-0">Browse the doctor directory and available slots while waiting for the booking workflow to activate.</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="card border-0 shadow-sm rounded-4 dashboard-widget-shell h-100">
            <div class="card-body p-4">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <div>
                  <h3 class="h5 mb-1">Booking Readiness</h3>
                  <p class="text-muted mb-0">Patient booking remains scheduled for Week 5, but your browsing workspace is now active.</p>
                </div>
                <span class="badge badge-soft-warning rounded-pill px-3 py-2">Week 5 next</span>
              </div>

              <div class="widget-mini-stat-list mb-4">
                <div class="widget-mini-stat">
                  <span class="widget-mini-stat-label">Account Status</span>
                  <strong>Active</strong>
                </div>
                <div class="widget-mini-stat">
                  <span class="widget-mini-stat-label">Available Doctors</span>
                  <strong><?= \App\Helpers\Helper::escape((string) ($stats[0]['value'] ?? '0')) ?></strong>
                </div>
                <div class="widget-mini-stat">
                  <span class="widget-mini-stat-label">Open Consultation Slots</span>
                  <strong><?= \App\Helpers\Helper::escape((string) ($stats[1]['value'] ?? '0')) ?></strong>
                </div>
              </div>

              <div class="dashboard-inline-callout">
                <span class="dashboard-info-label">Patient Journey</span>
                <p class="text-muted small mb-0">Use the doctor directory and slot browser now. Consultation booking, appointment history, and prescriptions will connect into this same workspace as later modules are approved.</p>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="card border-0 shadow-sm rounded-4 dashboard-widget-shell h-100">
            <div class="card-body p-4">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <div>
                  <h3 class="h5 mb-1">Notifications</h3>
                  <p class="text-muted mb-0">Workspace updates, availability reminders, and future booking prompts will gather here.</p>
                </div>
                <span class="badge badge-soft-info rounded-pill px-3 py-2">Prepared</span>
              </div>

              <div class="dashboard-inline-callout">
                <span class="dashboard-info-label">No critical alerts</span>
                <p class="text-muted small mb-0">There are no urgent patient-specific consultation alerts yet. Use the notification panel in the overview area for current dashboard updates.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="mt-4">
  <div class="card border-0 shadow-sm rounded-4 dashboard-widget-shell">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
          <h3 class="h5 mb-1">Available Doctors</h3>
          <p class="text-muted mb-0">Featured clinicians with future patient-visible availability.</p>
        </div>
        <a href="<?= \App\Helpers\Helper::url('/patient/doctors') ?>" class="btn btn-outline-primary rounded-pill px-4">
          <i class="bi bi-person-badge me-2"></i>
          Open Doctor Directory
        </a>
      </div>

      <div class="row g-3">
        <?php if ($featuredDoctors === []): ?>
          <div class="col-12">
            <div class="dashboard-inline-callout">
              <span class="dashboard-info-label">No featured doctors yet</span>
              <p class="text-muted small mb-0">Doctor cards will appear here when active clinicians publish future available slots.</p>
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($featuredDoctors as $doctor): ?>
            <div class="col-md-6 col-xl-4">
              <div class="patient-directory-card h-100">
                <div class="d-flex align-items-start gap-3 mb-3">
                  <?php
                  $avatarPath = $doctor['profile_photo_path'] ?? null;
                  $fullName = $doctor['full_name'] ?? 'Doctor';
                  $avatarClass = 'user-avatar user-avatar--sm';
                  require __DIR__ . '/../partials/shared/user_avatar.php';
                  ?>
                  <div>
                    <strong class="d-block"><?= \App\Helpers\Helper::escape((string) ($doctor['full_name'] ?? 'Doctor')) ?></strong>
                    <span class="small text-muted"><?= \App\Helpers\Helper::escape((string) ($doctor['professional_title'] ?? 'Medical Practitioner')) ?></span>
                  </div>
                </div>
                <span class="patient-directory-label">Specialization</span>
                <p class="mb-2"><?= \App\Helpers\Helper::escape((string) ($doctor['specialization'] ?? 'General Practice')) ?></p>
                <span class="patient-directory-label">Next Available Day</span>
                <p class="mb-0"><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($doctor['next_available_date'] ?? ''), 'D, d M Y', 'Not available')) ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
