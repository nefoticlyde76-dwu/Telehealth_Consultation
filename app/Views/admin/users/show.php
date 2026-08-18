<?php

$managedUser = $managedUser ?? [];
$roleName = ucfirst((string) ($managedUser['role_name'] ?? 'user'));
$statusName = ucfirst((string) ($managedUser['status'] ?? 'unknown'));
?>

<section class="mb-4">
  <div class="ux-page-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-start align-items-lg-center gap-3 mb-0">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/admin/dashboard') ?>">Dashboard</a></li>
        <li><a href="<?= \App\Helpers\Helper::url('/admin/users') ?>">Users</a></li>
        <li class="active"><?= \App\Helpers\Helper::escape($managedUser['full_name'] ?? 'User') ?></li>
      </ol>
      <span class="section-badge mb-3">
        <i class="bi bi-person-vcard"></i>
        User account
      </span>
      <h2 class="ux-page-header__title h4 mb-2"><?= \App\Helpers\Helper::escape($managedUser['full_name'] ?? 'User') ?></h2>
      <p class="ux-page-header__subtitle text-muted mb-0">Review this account’s identity, role, status, and profile details.</p>
    </div>
    <div class="ux-page-header__right">
      <a href="<?= \App\Helpers\Helper::url('/admin/users') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-arrow-left me-2"></i>
        Back to User Management
      </a>
    </div>
  </div>
</section>

<section class="mb-4">
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-body p-4">
          <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
            <div>
              <?php
              $personName = (string) ($managedUser['full_name'] ?? 'User');
              $personPhoto = $managedUser['profile_photo_path'] ?? null;
              $personMeta = (string) ($managedUser['email'] ?? '');
              $personSize = 'lg';
              require __DIR__ . '/../../partials/shared/person_row.php';
              ?>
              <p class="text-muted mb-0 mt-3">Name, email, role, and current account status.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <span class="ux-badge ux-badge--neutral"><?= \App\Helpers\Helper::escape($roleName) ?></span>
              <span class="ux-badge <?= ($managedUser['status'] ?? '') === 'active' ? 'ux-badge--approved' : 'ux-badge--pending' ?>"><?= \App\Helpers\Helper::escape($statusName) ?></span>
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
          <h3 class="h5 mb-3">Account notes</h3>
          <div class="activity-list">
            <div class="activity-item">
              <span class="activity-dot"></span>
              <div>
                <strong class="d-block">Status and role</strong>
                <p class="text-muted small mb-0">Use this page to confirm identity, role, and whether the account is active.</p>
              </div>
            </div>
            <div class="activity-item">
              <span class="activity-dot"></span>
              <div>
                <strong class="d-block">Patient and doctor records</strong>
                <p class="text-muted small mb-0">Patient details and doctor accounts are managed from their dedicated administration pages.</p>
              </div>
            </div>
            <div class="activity-item">
              <span class="activity-dot"></span>
              <div>
                <strong class="d-block">Access control</strong>
                <p class="text-muted small mb-0">Only signed-in administrators can view this record.</p>
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
          <h3 class="h5 mb-3">What you can do next</h3>
          <div class="admin-foundation-list">
            <div class="admin-foundation-item">
              <i class="bi bi-check2-circle"></i>
              <span>Search and filter accounts from the Users list.</span>
            </div>
            <div class="admin-foundation-item">
              <i class="bi bi-check2-circle"></i>
              <span>Create and update doctor accounts from Doctors.</span>
            </div>
            <div class="admin-foundation-item">
              <i class="bi bi-check2-circle"></i>
              <span>Review patient records and account status from Patients.</span>
            </div>
            <div class="admin-foundation-item">
              <i class="bi bi-info-circle"></i>
              <span>Permanent deletion of patient and doctor accounts is available from the Users list, with safeguards against removing your own or the last administrator account.</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
