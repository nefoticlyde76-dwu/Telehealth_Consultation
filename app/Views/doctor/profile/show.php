<?php

$profile = $profile ?? [];
$profilePhotoPath = $profile['profile_photo_path'] ?? null;
$signaturePath = $profile['signature_path'] ?? null;
$statusMessage = $statusMessage ?? null;
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
    <div>
      <span class="section-badge mb-3">
        <i class="bi bi-person-vcard"></i>
        Doctor Profile
      </span>
      <h2 class="h4 mb-2">View your clinician profile</h2>
      <p class="text-muted mb-0">Review your identity and uploaded assets as they will appear in future clinical workflows.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a href="<?= \App\Helpers\Helper::url('/doctor/profile/edit') ?>" class="btn btn-primary rounded-pill px-4">
        <i class="bi bi-person-gear me-2"></i>
        Edit Profile
      </a>
      <a href="<?= \App\Helpers\Helper::url('/doctor/dashboard') ?>" class="btn btn-outline-primary rounded-pill px-4">
        <i class="bi bi-arrow-left me-2"></i>
        Back to Dashboard
      </a>
    </div>
  </div>
</section>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm rounded-4 h-100">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
          <h3 class="h5 mb-0">Profile Photo</h3>
          <span class="badge badge-soft-info rounded-pill px-3 py-2">Identity</span>
        </div>

        <div class="doctor-profile-photo-shell">
          <?php
          $avatarPath = $profilePhotoPath;
          $fullName = $profile['full_name'] ?? 'Doctor';
          $avatarClass = 'user-avatar user-avatar--profile';
          require __DIR__ . '/../../partials/shared/user_avatar.php';
          ?>
        </div>

        <div class="admin-foundation-list mt-4">
          <div class="admin-foundation-item">
            <i class="bi bi-shield-lock"></i>
            <span>Your profile photo is only managed by you and is stored under secure upload controls.</span>
          </div>
          <div class="admin-foundation-item">
            <i class="bi bi-person-check"></i>
            <span>Upload a professional headshot to strengthen clinician trust during future consultations.</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card border-0 shadow-sm rounded-4 h-100">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
          <div>
            <h3 class="h5 mb-1">Profile Details</h3>
            <p class="text-muted mb-0">Your specialization and contact details are displayed in clinician workflows.</p>
          </div>
          <span class="badge badge-soft-neutral rounded-pill border px-3 py-2"><?= \App\Helpers\Helper::escape(ucfirst((string) ($profile['status'] ?? 'active'))) ?></span>
        </div>

        <div class="user-detail-grid">
          <div class="user-detail-item">
            <span class="user-detail-label">Full Name</span>
            <strong><?= \App\Helpers\Helper::escape((string) ($profile['full_name'] ?? 'Not available')) ?></strong>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Email</span>
            <strong><?= \App\Helpers\Helper::escape((string) ($profile['email'] ?? 'Not available')) ?></strong>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Phone</span>
            <strong><?= \App\Helpers\Helper::escape((string) ($profile['phone'] ?? 'Not provided')) ?></strong>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Specialization</span>
            <strong><?= \App\Helpers\Helper::escape((string) ($profile['specialization'] ?? 'Not provided')) ?></strong>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Professional Title</span>
            <strong><?= \App\Helpers\Helper::escape((string) ($profile['professional_title'] ?? 'Not provided')) ?></strong>
          </div>
          <div class="user-detail-item">
            <span class="user-detail-label">Employee ID</span>
            <strong><?= \App\Helpers\Helper::escape((string) ($profile['employee_id'] ?? 'Not provided')) ?></strong>
          </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mt-4">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
              <h4 class="h6 mb-0">Digital Signature</h4>
              <span class="badge badge-soft-info rounded-pill px-3 py-2">Future-ready</span>
            </div>

            <?php if (!empty($signaturePath)): ?>
              <img class="doctor-signature-preview" src="<?= \App\Helpers\Helper::asset($signaturePath) ?>" alt="Doctor digital signature">
            <?php else: ?>
              <div class="doctor-signature-placeholder">
                <i class="bi bi-pen"></i>
                <span>No signature uploaded yet</span>
              </div>
            <?php endif; ?>

            <p class="text-muted small mb-0 mt-3">Your signature will be used in future consultation records and prescription workflows once those modules are activated.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
