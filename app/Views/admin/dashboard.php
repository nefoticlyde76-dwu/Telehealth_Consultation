<?php require __DIR__ . '/../partials/dashboard/overview.php'; ?>

<section class="mt-4">
  <div class="row g-4">
    <div class="col-xl-8">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
              <h3 class="h5 mb-1">User Management Snapshot</h3>
              <p class="text-muted mb-0">Current platform distribution across patient, doctor, and administrator accounts.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
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

          <div class="row g-3">
            <div class="col-sm-6 col-lg-3">
              <div class="admin-summary-card h-100">
                <span class="admin-summary-label">Patients</span>
                <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($userSummary['patient_users'] ?? 0)) ?></strong>
                <span class="admin-summary-meta">Self-registered accounts currently stored</span>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="admin-summary-card h-100">
                <span class="admin-summary-label">Doctors</span>
                <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($userSummary['doctor_users'] ?? 0)) ?></strong>
                <span class="admin-summary-meta">Clinician profiles visible for future scheduling workflows</span>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="admin-summary-card h-100">
                <span class="admin-summary-label">Administrators</span>
                <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($userSummary['admin_users'] ?? 0)) ?></strong>
                <span class="admin-summary-meta">Governance accounts with secure operational access</span>
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <div class="admin-summary-card h-100">
                <span class="admin-summary-label">Inactive</span>
                <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($userSummary['inactive_users'] ?? 0)) ?></strong>
                <span class="admin-summary-meta">Accounts retained but unavailable for login access</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
              <h3 class="h5 mb-1">Latest Visible Users</h3>
              <p class="text-muted mb-0">Most recently added user records in the current environment.</p>
            </div>
            <span class="badge badge-soft-info rounded-pill"><?= count($latestUsers ?? []) ?></span>
          </div>

          <div class="admin-user-preview-list">
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
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
