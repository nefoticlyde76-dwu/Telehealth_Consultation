<?php

$slot = $slot ?? [];
$errors = $errors ?? [];
$fieldErrors = $fieldErrors ?? [];
$formData = $formData ?? ['reason' => ''];
$csrfToken = $csrfToken ?? '';

$formatSlotTime = static function (string $value): string {
    $raw = substr(trim($value), 0, 8);
    if ($raw === '') {
        return '';
    }

    $parsed = DateTimeImmutable::createFromFormat('H:i:s', $raw)
        ?: DateTimeImmutable::createFromFormat('H:i', substr($raw, 0, 5));

    return $parsed instanceof DateTimeImmutable ? $parsed->format('H:i') : $value;
};

$slotDay = \App\Helpers\Helper::formatDate((string) ($slot['consultation_date'] ?? ''), 'l, j M Y', 'Not available');
$startLabel = $formatSlotTime((string) ($slot['start_time'] ?? ''));
$endLabel = $formatSlotTime((string) ($slot['end_time'] ?? ''));
$slotTime = trim($startLabel . ($startLabel !== '' && $endLabel !== '' ? ' – ' : '') . $endLabel);
$doctorName = (string) ($slot['full_name'] ?? 'Doctor');
$doctorTitle = (string) ($slot['professional_title'] ?? 'Medical Practitioner');
$doctorSpecialization = (string) ($slot['specialization'] ?? 'General Practice');
$doctorMeta = trim($doctorTitle . ($doctorSpecialization !== '' ? ' · ' . $doctorSpecialization : ''));
?>

<section class="booking-confirm-page">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <?php
  $pageHeaderTitle = 'Confirm your selected consultation slot';
  $pageHeaderSubtitle = 'Review the doctor and slot details, provide a brief reason for consultation, and optionally attach a complaint image.';
  $pageHeaderHeadingTag = 'h1';
  $pageHeaderBreadcrumbs = [
      ['label' => 'Dashboard', 'url' => '/patient/dashboard'],
      ['label' => 'Slots', 'url' => '/patient/available-slots'],
      ['label' => 'Book Consultation', 'active' => true],
  ];
  ob_start();
  ?>
  <a href="<?= \App\Helpers\Helper::url('/patient/available-slots') ?>" class="btn booking-header-btn">
    <i class="bi bi-arrow-left" aria-hidden="true"></i>
    Back to Slots
  </a>
  <a href="<?= \App\Helpers\Helper::url('/patient/doctors') ?>" class="btn booking-header-btn">
    <i class="bi bi-person-badge" aria-hidden="true"></i>
    Doctor Directory
  </a>
  <?php
  $pageHeaderActions = ob_get_clean();
  require __DIR__ . '/../../partials/dashboard/page_header.php';
  ?>

  <div class="booking-confirm-card">
    <div class="booking-summary-grid">
      <article class="booking-summary-card">
        <span class="booking-summary-label">Doctor Information</span>
        <div class="booking-summary-doctor">
          <?php
          $avatarPath = $slot['profile_photo_path'] ?? null;
          $avatarUserId = (int) ($slot['doctor_id'] ?? 0);
          $fullName = $doctorName;
          $avatarClass = 'user-avatar user-avatar--lg booking-summary-avatar';
          require __DIR__ . '/../../partials/shared/user_avatar.php';
          ?>
          <div class="booking-summary-copy">
            <p class="booking-summary-value"><?= \App\Helpers\Helper::escape($doctorName) ?></p>
            <?php if ($doctorMeta !== ''): ?>
              <p class="booking-summary-secondary"><?= \App\Helpers\Helper::escape($doctorMeta) ?></p>
            <?php endif; ?>
          </div>
        </div>
      </article>

      <article class="booking-summary-card">
        <span class="booking-summary-label">Selected Consultation Slot</span>
        <div class="booking-summary-slot">
          <p class="booking-summary-value booking-summary-line">
            <i class="bi bi-calendar3" aria-hidden="true"></i>
            <span><?= \App\Helpers\Helper::escape($slotDay) ?></span>
          </p>
          <p class="booking-summary-secondary booking-summary-line">
            <i class="bi bi-clock" aria-hidden="true"></i>
            <span><?= \App\Helpers\Helper::escape($slotTime !== '' ? $slotTime : 'Time not available') ?></span>
          </p>
        </div>
      </article>
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

      <div class="complaint-field">
        <label for="reason" class="form-label complaint-label">
          Brief Reason for Consultation (Chief Complaint)<span class="text-danger">*</span>
        </label>
        <textarea
          class="form-control complaint-textarea <?= isset($fieldErrors['reason']) ? 'is-invalid' : '' ?>"
          id="reason"
          name="reason"
          rows="5"
          maxlength="500"
          required
          placeholder="Briefly describe your main symptoms or reason for consultation."
        ><?= \App\Helpers\Helper::escape((string) ($formData['reason'] ?? '')) ?></textarea>
        <div class="invalid-feedback"><?= \App\Helpers\Helper::escape((string) ($fieldErrors['reason'] ?? 'Consultation reason is required.')) ?></div>
        <div class="complaint-hint">Maximum 500 characters.</div>
      </div>

      <div
        class="complaint-upload complaint-image-upload"
        data-complaint-image-upload
        data-max-bytes="<?= (int) \App\Services\ComplaintImageService::MAX_BYTES ?>"
      >
        <label for="complaint_image" class="form-label complaint-label">Upload Complaint Image (Optional)</label>
        <p class="complaint-upload__help">
          Attach a photo of your symptom or affected area if it helps the doctor understand your complaint.
          JPG, JPEG, or PNG files up to <?= (int) \App\Services\ComplaintImageService::MAX_LABEL_BYTES ?> MB are accepted.
        </p>
        <input
          type="file"
          class="form-control complaint-upload__input <?= isset($fieldErrors['complaint_image']) ? 'is-invalid' : '' ?>"
          id="complaint_image"
          name="complaint_image"
          accept=".jpg,.jpeg,.png,image/jpeg,image/png"
          data-complaint-image-input
        >
        <div class="invalid-feedback" data-complaint-image-error><?= \App\Helpers\Helper::escape((string) ($fieldErrors['complaint_image'] ?? 'Please choose a JPG or PNG image of 5 MB or less.')) ?></div>
        <div class="complaint-image-upload__preview d-none mt-3" data-complaint-image-preview>
          <div class="booking-summary-card d-flex flex-column flex-sm-row align-items-sm-center gap-3">
            <img src="" alt="Selected complaint image preview" class="complaint-image-upload__thumb" data-complaint-image-thumb>
            <div class="min-w-0 flex-grow-1">
              <span class="booking-summary-label">Selected image</span>
              <strong class="d-block text-break" data-complaint-image-name></strong>
              <button type="button" class="btn btn-outline-secondary btn-sm mt-2" data-complaint-image-remove>
                <i class="bi bi-x-circle me-1"></i>
                Remove image
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="booking-actions">
        <a href="<?= \App\Helpers\Helper::url('/patient/available-slots') ?>" class="btn booking-cancel">Cancel</a>
        <button type="submit" class="btn booking-submit">
          <i class="bi bi-send" aria-hidden="true"></i>
          Submit Booking Request
        </button>
      </div>
    </form>
  </div>
</section>
