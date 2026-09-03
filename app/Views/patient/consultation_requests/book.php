<?php

$slot = $slot ?? [];
$errors = $errors ?? [];
$fieldErrors = $fieldErrors ?? [];
$formData = $formData ?? ['reason' => ''];
$csrfToken = $csrfToken ?? '';

$slotDay = \App\Helpers\Helper::formatDate((string) ($slot['consultation_date'] ?? ''), 'D, d M Y', 'Not available');
$slotTime = substr((string) ($slot['start_time'] ?? ''), 0, 5) . ' - ' . substr((string) ($slot['end_time'] ?? ''), 0, 5);
$doctorName = (string) ($slot['full_name'] ?? 'Doctor');
$doctorTitle = (string) ($slot['professional_title'] ?? 'Medical Practitioner');
$doctorSpecialization = (string) ($slot['specialization'] ?? 'General Practice');
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-page-header d-flex flex-column flex-xl-row justify-content-between align-items-xl-start align-items-xl-center gap-3 mb-4">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/patient/dashboard') ?>">Dashboard</a></li>
        <li><a href="<?= \App\Helpers\Helper::url('/patient/available-slots') ?>">Slots</a></li>
        <li class="active">Book Consultation</li>
      </ol>
      <span class="section-badge mb-3">
        <i class="bi bi-calendar2-check"></i>
        Book Consultation
      </span>
      <h2 class="ux-page-header__title h4 mb-2">Confirm your selected consultation slot</h2>
      <p class="ux-page-header__subtitle text-muted mb-0">Review the doctor and slot details, provide a brief reason for consultation, and optionally attach a complaint image.</p>
    </div>
    <div class="ux-page-header__right d-flex flex-wrap gap-2">
      <a href="<?= \App\Helpers\Helper::url('/patient/available-slots') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-calendar2-week me-2"></i>
        Back to Slots
      </a>
      <a href="<?= \App\Helpers\Helper::url('/patient/doctors') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-person-badge me-2"></i>
        Doctor Directory
      </a>
    </div>
  </div>

  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="row g-4 mb-4">
        <div class="col-lg-6">
          <div class="dashboard-inline-callout h-100">
            <span class="dashboard-info-label">Doctor Information</span>
            <?php
            $personName = $doctorName;
            $personPhoto = $slot['profile_photo_path'] ?? null;
            $personMeta = $doctorTitle . ' · ' . $doctorSpecialization;
            $personSize = 'lg';
            require __DIR__ . '/../../partials/shared/person_row.php';
            ?>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="dashboard-inline-callout h-100">
            <span class="dashboard-info-label">Selected Consultation Slot</span>
            <strong class="d-block mb-1"><?= \App\Helpers\Helper::escape($slotDay) ?></strong>
            <p class="text-muted small mb-0"><?= \App\Helpers\Helper::escape($slotTime) ?></p>
          </div>
        </div>
      </div>

      <?php
      $slotExpiresAt = \App\Helpers\Helper::combineDateTimeIso(
          (string) ($slot['consultation_date'] ?? ''),
          (string) ($slot['end_time'] ?? '')
      );
      ?>
      <form
        method="POST"
        action="<?= \App\Helpers\Helper::url('/patient/consultation-requests/book/' . (string) ((int) ($slot['id'] ?? 0))) ?>"
        enctype="multipart/form-data"
        data-slot-booking
        <?= $slotExpiresAt !== '' ? 'data-slot-expires-at="' . \App\Helpers\Helper::escape($slotExpiresAt) . '"' : '' ?>
      >
        <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">
        <div class="alert alert-warning d-none" role="alert" data-slot-expired-note>
          This consultation slot has ended and can no longer be booked. Please choose another available time.
        </div>

        <div class="mb-3">
          <label for="reason" class="form-label">Brief Reason for Consultation (Chief Complaint) <span class="text-danger">*</span></label>
          <textarea
            class="form-control <?= isset($fieldErrors['reason']) ? 'is-invalid' : '' ?>"
            id="reason"
            name="reason"
            rows="5"
            maxlength="500"
            required
            placeholder="Briefly describe your main symptoms or reason for consultation."
          ><?= \App\Helpers\Helper::escape((string) ($formData['reason'] ?? '')) ?></textarea>
          <div class="invalid-feedback"><?= \App\Helpers\Helper::escape((string) ($fieldErrors['reason'] ?? 'Consultation reason is required.')) ?></div>
          <div class="form-text">Maximum 500 characters.</div>
        </div>

        <div
          class="mb-4 complaint-image-upload"
          data-complaint-image-upload
          data-max-bytes="<?= (int) \App\Services\ComplaintImageService::MAX_BYTES ?>"
        >
          <label for="complaint_image" class="form-label">Upload Complaint Image (Optional)</label>
          <p class="form-text mt-0 mb-2">
            Attach a photo of your symptom or affected area if it helps the doctor understand your complaint.
            JPG, JPEG, or PNG files up to <?= (int) \App\Services\ComplaintImageService::MAX_LABEL_BYTES ?> MB are accepted.
          </p>
          <input
            type="file"
            class="form-control <?= isset($fieldErrors['complaint_image']) ? 'is-invalid' : '' ?>"
            id="complaint_image"
            name="complaint_image"
            accept=".jpg,.jpeg,.png,image/jpeg,image/png"
            data-complaint-image-input
          >
          <div class="invalid-feedback" data-complaint-image-error><?= \App\Helpers\Helper::escape((string) ($fieldErrors['complaint_image'] ?? 'Please choose a JPG or PNG image of 5 MB or less.')) ?></div>
          <div class="complaint-image-upload__preview d-none mt-3" data-complaint-image-preview>
            <div class="dashboard-inline-callout d-flex flex-column flex-sm-row align-items-sm-center gap-3">
              <img src="" alt="Selected complaint image preview" class="complaint-image-upload__thumb" data-complaint-image-thumb>
              <div class="min-w-0 flex-grow-1">
                <span class="dashboard-info-label">Selected image</span>
                <strong class="d-block text-break" data-complaint-image-name></strong>
                <button type="button" class="btn btn-outline-secondary btn-sm mt-2" data-complaint-image-remove>
                  <i class="bi bi-x-circle me-1"></i>
                  Remove image
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="d-flex flex-wrap justify-content-end gap-2">
          <a href="<?= \App\Helpers\Helper::url('/patient/available-slots') ?>" class="btn btn-outline-primary btn-sm">Cancel</a>
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="bi bi-send me-2"></i>
            Submit Booking Request
          </button>
        </div>
      </form>
    </div>
  </div>
</section>

