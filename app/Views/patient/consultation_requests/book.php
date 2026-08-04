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

  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-4">
        <div>
          <span class="section-badge mb-3">
            <i class="bi bi-calendar2-check"></i>
            Book Consultation
          </span>
          <h2 class="h4 mb-2">Confirm your selected consultation slot</h2>
          <p class="text-muted mb-0">Review the doctor and slot details, then provide a brief reason for consultation.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?= \App\Helpers\Helper::url('/patient/available-slots') ?>" class="btn btn-outline-primary rounded-pill px-4">
            <i class="bi bi-calendar2-week me-2"></i>
            Back to Slots
          </a>
          <a href="<?= \App\Helpers\Helper::url('/patient/doctors') ?>" class="btn btn-outline-primary rounded-pill px-4">
            <i class="bi bi-person-badge me-2"></i>
            Doctor Directory
          </a>
        </div>
      </div>

      <div class="row g-4 mb-4">
        <div class="col-lg-6">
          <div class="dashboard-inline-callout h-100">
            <span class="dashboard-info-label">Doctor Information</span>
            <strong class="d-block mb-1"><?= \App\Helpers\Helper::escape($doctorName) ?></strong>
            <p class="text-muted small mb-0">
              <?= \App\Helpers\Helper::escape($doctorTitle) ?> · <?= \App\Helpers\Helper::escape($doctorSpecialization) ?>
            </p>
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

      <form method="POST" action="<?= \App\Helpers\Helper::url('/patient/consultation-requests/book/' . (string) ((int) ($slot['id'] ?? 0))) ?>">
        <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">

        <div class="mb-3">
          <label for="reason" class="form-label">Brief Reason for Consultation (Chief Complaint)</label>
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

        <div class="d-flex flex-wrap justify-content-end gap-2">
          <a href="<?= \App\Helpers\Helper::url('/patient/available-slots') ?>" class="btn btn-outline-primary rounded-pill px-4">Cancel</a>
          <button type="submit" class="btn btn-primary rounded-pill px-4">
            <i class="bi bi-send me-2"></i>
            Submit Booking Request
          </button>
        </div>
      </form>
    </div>
  </div>
</section>

