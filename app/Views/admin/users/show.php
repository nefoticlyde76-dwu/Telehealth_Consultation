<?php

$managedUser = $managedUser ?? [];
$roleName = ucfirst((string) ($managedUser['role_name'] ?? 'user'));
$statusName = ucfirst((string) ($managedUser['status'] ?? 'unknown'));
?>

<section class="mb-4">
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
    <div>
      <span class="section-badge mb-3">
        <i class="bi bi-person-vcard"></i>
        Administrator User Detail Review
      </span>
      <h2 class="h4 mb-2"><?= \App\Helpers\Helper::escape($managedUser['full_name'] ?? 'User') ?></h2>
      <p class="text-muted mb-0">Review account identity, role placement, status, and available profile data without modifying records.</p>
    </div>
    <a href="<?= \App\Helpers\Helper::url('/admin/users') ?>" class="btn btn-outline-primary rounded-pill px-4">
      <i class="bi bi-arrow-left me-2"></i>
      Back to User Management
    </a>
  </div>
</section>

<section class="mb-4">
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-body p-4">
          <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
            <div>
              <h3 class="h5 mb-1">Account Overview</h3>
              <p class="text-muted mb-0">Core user identity and authentication-related metadata.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <span class="badge badge-soft-neutral rounded-pill border px-3 py-2"><?= \App\Helpers\Helper::escape($roleName) ?></span>
              <span class="badge <?= ($managedUser['status'] ?? '') === 'active' ? 'badge-soft-success' : 'badge-soft-warning' ?> rounded-pill px-3 py-2"><?= \App\Helpers\Helper::escape($statusName) ?></span>
            </div>
          </div>

          <div class="user-detail-grid">
            <div class="user-detail-item">
              <span class="user-detail-label">Full Name</span>
              <strong><?= \App\Helpers\Helper::escape($managedUser['full_name'] ?? 'Not available') ?></strong>
            </div>
            <div class="user-detail-item">
              <span class="user-detail-label">Email Address</span>
              <strong><?= \App\Helpers\Helper::escape($managedUser['email'] ?? 'Not available') ?></strong>
            </div>
            <div class="user-detail-item">
              <span class="user-detail-label">Account Status</span>
              <strong><?= \App\Helpers\Helper::escape($statusName) ?></strong>
            </div>
            <div class="user-detail-item">
              <span class="user-detail-label">Role</span>
              <strong><?= \App\Helpers\Helper::escape($roleName) ?></strong>
            </div>
            <div class="user-detail-item">
              <span class="user-detail-label">Created At</span>
              <strong><?= \App\Helpers\Helper::escape(date('d M Y, h:i A', strtotime((string) ($managedUser['created_at'] ?? 'now')))) ?></strong>
            </div>
            <div class="user-detail-item">
              <span class="user-detail-label">Last Updated</span>
              <strong><?= \App\Helpers\Helper::escape(date('d M Y, h:i A', strtotime((string) ($managedUser['updated_at'] ?? 'now')))) ?></strong>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-body p-4">
          <h3 class="h5 mb-3">Administrative Review Notes</h3>
          <div class="activity-list">
            <div class="activity-item">
              <span class="activity-dot"></span>
              <div>
                <strong class="d-block">Read-only inspection</strong>
                <p class="text-muted small mb-0">Today’s foundation supports oversight and user detail visibility only. No account creation or mutation is included.</p>
              </div>
            </div>
            <div class="activity-item">
              <span class="activity-dot"></span>
              <div>
                <strong class="d-block">Role-aware visibility</strong>
                <p class="text-muted small mb-0">The detail view adapts to administrator, doctor, and patient role data that already exists in the database.</p>
              </div>
            </div>
            <div class="activity-item">
              <span class="activity-dot"></span>
              <div>
                <strong class="d-block">Secure admin-only access</strong>
                <p class="text-muted small mb-0">The route remains protected by existing administrator role checks and the authenticated dashboard shell.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="row g-4">
        <div class="col-lg-6">
          <h3 class="h5 mb-3">Role-Specific Profile Data</h3>
          <div class="user-detail-grid">
            <?php if (($managedUser['role_name'] ?? '') === 'admin'): ?>
              <div class="user-detail-item">
                <span class="user-detail-label">Employee ID</span>
                <strong><?= \App\Helpers\Helper::escape($managedUser['employee_id'] ?? 'Not assigned') ?></strong>
              </div>
            <?php elseif (($managedUser['role_name'] ?? '') === 'doctor'): ?>
              <div class="user-detail-item">
                <span class="user-detail-label">Specialization</span>
                <strong><?= \App\Helpers\Helper::escape($managedUser['specialization'] ?? 'Not assigned') ?></strong>
              </div>
              <div class="user-detail-item">
                <span class="user-detail-label">License Number</span>
                <strong><?= \App\Helpers\Helper::escape($managedUser['license_number'] ?? 'Not assigned') ?></strong>
              </div>
              <div class="user-detail-item">
                <span class="user-detail-label">Clinic Address</span>
                <strong><?= \App\Helpers\Helper::escape($managedUser['clinic_address'] ?? 'Not assigned') ?></strong>
              </div>
            <?php elseif (($managedUser['role_name'] ?? '') === 'patient'): ?>
              <div class="user-detail-item">
                <span class="user-detail-label">Date of Birth</span>
                <strong><?= \App\Helpers\Helper::escape($managedUser['dob'] ? date('d M Y', strtotime((string) $managedUser['dob'])) : 'Not provided') ?></strong>
              </div>
              <div class="user-detail-item">
                <span class="user-detail-label">Gender</span>
                <strong><?= \App\Helpers\Helper::escape($managedUser['gender'] ? ucfirst((string) $managedUser['gender']) : 'Not provided') ?></strong>
              </div>
              <div class="user-detail-item">
                <span class="user-detail-label">Address</span>
                <strong><?= \App\Helpers\Helper::escape($managedUser['address'] ?? 'Not provided') ?></strong>
              </div>
            <?php else: ?>
              <div class="user-detail-item">
                <span class="user-detail-label">Role Data</span>
                <strong>No role-specific profile data is available for this account.</strong>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="col-lg-6">
          <h3 class="h5 mb-3">Foundation Scope</h3>
          <div class="admin-foundation-list">
            <div class="admin-foundation-item">
              <i class="bi bi-check2-circle"></i>
              <span>All users can be viewed through the administrator listing.</span>
            </div>
            <div class="admin-foundation-item">
              <i class="bi bi-check2-circle"></i>
              <span>Search and filter support helps administrators narrow platform accounts safely.</span>
            </div>
            <div class="admin-foundation-item">
              <i class="bi bi-check2-circle"></i>
              <span>Detailed inspection is available for role and status review.</span>
            </div>
            <div class="admin-foundation-item">
              <i class="bi bi-info-circle"></i>
              <span>Patient management, doctor account management, and administrator profile maintenance are now handled through their dedicated Week 3 administration screens.</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
