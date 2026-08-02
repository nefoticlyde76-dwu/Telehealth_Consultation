<?php require __DIR__ . '/../partials/dashboard/overview.php'; ?>

<?php
$doctorProfile = $doctorProfile ?? [];
$profilePhotoPath = $doctorProfile['profile_photo_path'] ?? null;
$signaturePath = $doctorProfile['signature_path'] ?? null;
?>

<section class="mt-4">
  <div class="row g-4">
    <div class="col-xl-7">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
              <span class="section-badge mb-3">
                <i class="bi bi-person-badge"></i>
                Clinician Profile Snapshot
              </span>
              <h3 class="h5 mb-1">Your profile is part of your clinical identity</h3>
              <p class="text-muted mb-0">Keep your phone number, specialization, profile photo, and signature accurate for future-ready consultation documentation.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <a href="<?= \App\Helpers\Helper::url('/doctor/profile') ?>" class="btn btn-outline-primary rounded-pill px-4">
                <i class="bi bi-person-vcard me-2"></i>
                View Profile
              </a>
              <a href="<?= \App\Helpers\Helper::url('/doctor/profile/edit') ?>" class="btn btn-primary rounded-pill px-4">
                <i class="bi bi-person-gear me-2"></i>
                Edit Profile
              </a>
            </div>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <div class="admin-summary-card h-100">
                <span class="admin-summary-label">Phone Number</span>
                <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($doctorProfile['phone'] ?? 'Not provided')) ?></strong>
                <span class="admin-summary-meta">Used for secure contact and future appointment coordination.</span>
              </div>
            </div>
            <div class="col-md-6">
              <div class="admin-summary-card h-100">
                <span class="admin-summary-label">Specialization</span>
                <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($doctorProfile['specialization'] ?? 'Not provided')) ?></strong>
                <span class="admin-summary-meta">Displayed across clinician workflows and future booking modules.</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-5">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
              <h3 class="h5 mb-1">Uploaded Assets</h3>
              <p class="text-muted mb-0">Profile photo and digital signature are stored securely for future use.</p>
            </div>
            <span class="badge badge-soft-info rounded-pill px-3 py-2">Uploads enabled</span>
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
