<?php require __DIR__ . '/../partials/dashboard/overview.php'; ?>

<?php
$consultationSummary = $consultationSummary ?? [];
$recentConsultationRequests = $recentConsultationRequests ?? [];
$consultationStatusBadgeMap = [
    'Pending' => 'badge-soft-warning',
    'Approved' => 'badge-soft-success',
    'Rejected' => 'badge-soft-danger',
    'Cancelled' => 'badge-soft-danger',
    'Completed' => 'badge-soft-success',
];
?>

<section class="mt-4">
  <div class="row g-4">
    <div class="col-xl-8">
      <div class="card border-0 shadow-sm rounded-4 h-100 dashboard-widget-shell">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
              <span class="section-badge mb-3">
                <i class="bi bi-shield-lock"></i>
                Administrative Command Center
              </span>
              <h3 class="h5 mb-1">System governance and account oversight</h3>
              <p class="text-muted mb-0">Monitor account health, balance clinical access, and move directly into the areas that need administrative attention.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <a href="<?= \App\Helpers\Helper::url('/admin/users') ?>" class="btn btn-outline-primary rounded-pill px-4">
                <i class="bi bi-diagram-3 me-2"></i>
                Manage Users
              </a>
              <a href="<?= \App\Helpers\Helper::url('/admin/patients') ?>" class="btn btn-outline-primary rounded-pill px-4">
                <i class="bi bi-people me-2"></i>
                Manage Patients
              </a>
              <a href="<?= \App\Helpers\Helper::url('/admin/profile') ?>" class="btn btn-primary rounded-pill px-4">
                <i class="bi bi-person-gear me-2"></i>
                Profile Settings
              </a>
            </div>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
              <div class="admin-summary-card h-100 dashboard-emphasis-card">
                <span class="admin-summary-label">Patients</span>
                <strong class="admin-summary-value" data-counter="<?= \App\Helpers\Helper::escape((string) ($userSummary['patient_users'] ?? 0)) ?>"><?= \App\Helpers\Helper::escape((string) ($userSummary['patient_users'] ?? 0)) ?></strong>
                <span class="admin-summary-meta">Self-registered accounts currently stored</span>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="admin-summary-card h-100">
                <span class="admin-summary-label">Doctors</span>
                <strong class="admin-summary-value" data-counter="<?= \App\Helpers\Helper::escape((string) ($userSummary['doctor_users'] ?? 0)) ?>"><?= \App\Helpers\Helper::escape((string) ($userSummary['doctor_users'] ?? 0)) ?></strong>
                <span class="admin-summary-meta">Clinician profiles visible for future scheduling workflows</span>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="admin-summary-card h-100">
                <span class="admin-summary-label">Administrators</span>
                <strong class="admin-summary-value" data-counter="<?= \App\Helpers\Helper::escape((string) ($userSummary['admin_users'] ?? 0)) ?>"><?= \App\Helpers\Helper::escape((string) ($userSummary['admin_users'] ?? 0)) ?></strong>
                <span class="admin-summary-meta">Governance accounts with secure operational access</span>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="admin-summary-card h-100">
                <span class="admin-summary-label">Inactive</span>
                <strong class="admin-summary-value" data-counter="<?= \App\Helpers\Helper::escape((string) ($userSummary['inactive_users'] ?? 0)) ?>"><?= \App\Helpers\Helper::escape((string) ($userSummary['inactive_users'] ?? 0)) ?></strong>
                <span class="admin-summary-meta">Accounts retained but unavailable for login access</span>
              </div>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-lg-8">
              <div class="dashboard-info-panel h-100">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                  <div>
                    <span class="dashboard-info-label">User Distribution</span>
                    <h4 class="h6 mb-2">Operational account health remains stable</h4>
                    <p class="text-muted small mb-0">The current Week 4 environment keeps doctor, patient, and administrator access grouped under one secured governance workspace.</p>
                  </div>
                  <span class="badge badge-soft-info rounded-pill px-3 py-2">System Overview</span>
                </div>

                <div class="dashboard-progress-list">
                  <?php
                  $totalUsers = max(1, (int) ($userSummary['total_users'] ?? 0));
                  $activeWidth = (int) round(((int) ($userSummary['active_users'] ?? 0) / $totalUsers) * 100);
                  $patientWidth = (int) round(((int) ($userSummary['patient_users'] ?? 0) / $totalUsers) * 100);
                  $doctorWidth = (int) round(((int) ($userSummary['doctor_users'] ?? 0) / $totalUsers) * 100);
                  ?>
                  <div class="dashboard-progress-item">
                    <div class="d-flex justify-content-between gap-3 mb-2">
                      <span>Active Accounts</span>
                      <strong><?= \App\Helpers\Helper::escape((string) ($userSummary['active_users'] ?? 0)) ?></strong>
                    </div>
                    <div class="dashboard-progress-track"><span class="dashboard-progress-bar" style="width: <?= \App\Helpers\Helper::escape((string) $activeWidth) ?>%"></span></div>
                  </div>
                  <div class="dashboard-progress-item">
                    <div class="d-flex justify-content-between gap-3 mb-2">
                      <span>Patient Distribution</span>
                      <strong><?= \App\Helpers\Helper::escape((string) ($userSummary['patient_users'] ?? 0)) ?></strong>
                    </div>
                    <div class="dashboard-progress-track"><span class="dashboard-progress-bar dashboard-progress-bar--teal" style="width: <?= \App\Helpers\Helper::escape((string) $patientWidth) ?>%"></span></div>
                  </div>
                  <div class="dashboard-progress-item">
                    <div class="d-flex justify-content-between gap-3 mb-2">
                      <span>Doctor Distribution</span>
                      <strong><?= \App\Helpers\Helper::escape((string) ($userSummary['doctor_users'] ?? 0)) ?></strong>
                    </div>
                    <div class="dashboard-progress-track"><span class="dashboard-progress-bar dashboard-progress-bar--cyan" style="width: <?= \App\Helpers\Helper::escape((string) $doctorWidth) ?>%"></span></div>
                  </div>
                </div>

                <div class="row g-3 mt-1">
                  <div class="col-md-4">
                    <div class="widget-mini-stat h-100">
                      <span class="widget-mini-stat-label">Total Users</span>
                      <strong data-counter="<?= \App\Helpers\Helper::escape((string) ($userSummary['total_users'] ?? 0)) ?>"><?= \App\Helpers\Helper::escape((string) ($userSummary['total_users'] ?? 0)) ?></strong>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="widget-mini-stat h-100">
                      <span class="widget-mini-stat-label">Active Accounts</span>
                      <strong data-counter="<?= \App\Helpers\Helper::escape((string) ($userSummary['active_users'] ?? 0)) ?>"><?= \App\Helpers\Helper::escape((string) ($userSummary['active_users'] ?? 0)) ?></strong>
                    </div>
                  </div>
                  <div class="col-md-4">
                    <div class="widget-mini-stat h-100">
                      <span class="widget-mini-stat-label">Pending Consultation Requests</span>
                      <strong data-counter="<?= \App\Helpers\Helper::escape((string) ((int) ($consultationSummary['pending_requests'] ?? 0))) ?>"><?= \App\Helpers\Helper::escape((string) ((int) ($consultationSummary['pending_requests'] ?? 0))) ?></strong>
                      <span class="admin-summary-meta">Awaiting administrator approval</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="col-lg-4">
              <div class="dashboard-info-panel h-100">
                <span class="dashboard-info-label">Pending Requests</span>
                <h4 class="h6 mb-2">Workflow readiness snapshot</h4>
                <div class="widget-mini-stat-list">
                  <div class="widget-mini-stat">
                    <span class="widget-mini-stat-label">Inactive Patients</span>
                    <strong><?= \App\Helpers\Helper::escape((string) ($patientSummary['inactive_patients'] ?? 0)) ?></strong>
                  </div>
                  <div class="widget-mini-stat">
                    <span class="widget-mini-stat-label">Inactive Doctors</span>
                    <strong><?= \App\Helpers\Helper::escape((string) ($doctorSummary['inactive_doctors'] ?? 0)) ?></strong>
                  </div>
                  <div class="widget-mini-stat">
                    <span class="widget-mini-stat-label">Active Accounts</span>
                    <strong><?= \App\Helpers\Helper::escape((string) ($userSummary['active_users'] ?? 0)) ?></strong>
                  </div>
                </div>
                <p class="text-muted small mb-0">Review inactive accounts and recent registrations to decide where governance action is needed next.</p>
              </div>
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
                  <h3 class="h5 mb-1">Consultation Requests</h3>
                  <p class="text-muted mb-0">Approval pipeline visibility and recent request activity.</p>
                </div>
                <a href="<?= \App\Helpers\Helper::url('/admin/consultation-requests') ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                  View All
                </a>
              </div>

              <div class="widget-mini-stat-list mb-4">
                <div class="widget-mini-stat">
                  <span class="widget-mini-stat-label">Pending</span>
                  <strong><?= \App\Helpers\Helper::escape((string) ((int) ($consultationSummary['pending_requests'] ?? 0))) ?></strong>
                </div>
                <div class="widget-mini-stat">
                  <span class="widget-mini-stat-label">Approved</span>
                  <strong><?= \App\Helpers\Helper::escape((string) ((int) ($consultationSummary['approved_requests'] ?? 0))) ?></strong>
                </div>
                <div class="widget-mini-stat">
                  <span class="widget-mini-stat-label">Rejected</span>
                  <strong><?= \App\Helpers\Helper::escape((string) ((int) ($consultationSummary['rejected_requests'] ?? 0))) ?></strong>
                </div>
              </div>

              <div class="admin-user-preview-list">
                <?php if ($recentConsultationRequests === []): ?>
                  <div class="admin-user-preview-item">
                    <div>
                      <strong class="d-block">No consultation requests yet</strong>
                      <span class="small text-muted">Consultation requests will appear once patients begin booking available slots.</span>
                    </div>
                  </div>
                <?php else: ?>
                  <?php foreach ($recentConsultationRequests as $recentRequest): ?>
                    <?php
                    $recentStatus = (string) ($recentRequest['status'] ?? 'Pending');
                    $badgeClass = $consultationStatusBadgeMap[$recentStatus] ?? 'badge-soft-neutral';
                    $consultDate = \App\Helpers\Helper::formatDate((string) ($recentRequest['consultation_date'] ?? ''), 'd M Y', 'Not scheduled');
                    $consultTime = substr((string) ($recentRequest['start_time'] ?? ''), 0, 5) . ' - ' . substr((string) ($recentRequest['end_time'] ?? ''), 0, 5);
                    ?>
                    <a href="<?= \App\Helpers\Helper::url('/admin/consultation-requests/' . (string) ((int) ($recentRequest['id'] ?? 0))) ?>" class="admin-user-preview-item text-decoration-none">
                      <div>
                        <strong class="d-block"><?= \App\Helpers\Helper::escape((string) ($recentRequest['patient_name'] ?? 'Patient')) ?></strong>
                        <span class="small text-muted"><?= \App\Helpers\Helper::escape((string) ($recentRequest['doctor_name'] ?? 'Doctor')) ?> · <?= \App\Helpers\Helper::escape((string) $consultDate) ?></span>
                      </div>
                      <div class="text-end">
                        <span class="badge <?= \App\Helpers\Helper::escape($badgeClass) ?> rounded-pill mb-2">
                          <?= \App\Helpers\Helper::escape($recentStatus) ?>
                        </span>
                        <span class="d-block small text-muted"><?= \App\Helpers\Helper::escape((string) $consultTime) ?></span>
                      </div>
                    </a>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="card border-0 shadow-sm rounded-4 dashboard-widget-shell h-100">
            <div class="card-body p-4">
              <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                  <h3 class="h5 mb-1">Recently Registered Users</h3>
                  <p class="text-muted mb-0">Newly visible accounts surfaced in a compact registration feed.</p>
                </div>
                <span class="badge badge-soft-info rounded-pill"><?= count($latestUsers ?? []) ?></span>
              </div>

              <div class="admin-user-preview-list">
                <?php if (($latestUsers ?? []) === []): ?>
                  <div class="admin-user-preview-item">
                    <div>
                      <strong class="d-block">No recent users available</strong>
                      <span class="small text-muted">Recent account activity will appear here as more records are added to the platform.</span>
                    </div>
                  </div>
                <?php else: ?>
                  <?php foreach (($latestUsers ?? []) as $latestUser): ?>
                    <div class="admin-user-preview-item">
                      <div>
                        <strong class="d-block"><?= \App\Helpers\Helper::escape($latestUser['full_name'] ?? 'User') ?></strong>
                        <span class="small text-muted"><?= \App\Helpers\Helper::escape($latestUser['email'] ?? '') ?></span>
                      </div>
                      <div class="text-end">
                        <span class="badge <?= ($latestUser['status'] ?? '') === 'active' ? 'badge-soft-success' : 'badge-soft-warning' ?> rounded-pill mb-2">
                          <?= \App\Helpers\Helper::escape(ucfirst((string) ($latestUser['status'] ?? 'unknown'))) ?>
                        </span>
                        <span class="d-block small text-muted"><?= \App\Helpers\Helper::escape(ucfirst((string) ($latestUser['role_name'] ?? 'user'))) ?></span>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <div class="dashboard-inline-callout mt-4">
                <span class="dashboard-info-label">System Access Mix</span>
                <div class="d-flex flex-wrap gap-2 mt-2">
                  <span class="badge badge-soft-success rounded-pill px-3 py-2">Patients <?= \App\Helpers\Helper::escape((string) ($patientSummary['active_patients'] ?? 0)) ?></span>
                  <span class="badge badge-soft-info rounded-pill px-3 py-2">Doctors <?= \App\Helpers\Helper::escape((string) ($doctorSummary['active_doctors'] ?? 0)) ?></span>
                  <span class="badge badge-soft-neutral rounded-pill border px-3 py-2">Admins <?= \App\Helpers\Helper::escape((string) ($userSummary['admin_users'] ?? 0)) ?></span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
