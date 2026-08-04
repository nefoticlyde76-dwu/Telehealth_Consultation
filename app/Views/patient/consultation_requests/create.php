<?php

$errors = $errors ?? [];
$fieldErrors = $fieldErrors ?? [];
$formData = $formData ?? ['reason' => '', 'specialization' => ''];
$specializationOptions = $specializationOptions ?? [];
$csrfToken = $csrfToken ?? '';
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
      <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-3 mb-4">
        <div>
          <span class="section-badge mb-3">
            <i class="bi bi-clipboard2-plus"></i>
            New Consultation Request
          </span>
          <h2 class="h4 mb-2">Submit a new patient consultation request</h2>
          <p class="text-muted mb-0">Describe your main reason for consultation and select the medical specialization required. Supporting documents or images are optional.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests') ?>" class="btn btn-outline-primary rounded-pill px-4">
            <i class="bi bi-clipboard2-data me-2"></i>
            View Requests
          </a>
          <a href="<?= \App\Helpers\Helper::url('/patient/dashboard') ?>" class="btn btn-outline-primary rounded-pill px-4">
            <i class="bi bi-arrow-left me-2"></i>
            Back to Dashboard
          </a>
        </div>
      </div>

      <form method="POST" action="<?= \App\Helpers\Helper::url('/patient/consultation-requests/create') ?>" enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">

        <div class="row g-3">
          <div class="col-12">
            <label for="reason" class="form-label">Consultation Reason / Chief Complaint</label>
            <textarea
              class="form-control <?= isset($fieldErrors['reason']) ? 'is-invalid' : '' ?>"
              id="reason"
              name="reason"
              rows="6"
              maxlength="2000"
              required
              placeholder="Describe your main symptoms, concerns, or the reason you are requesting a consultation."
            ><?= \App\Helpers\Helper::escape((string) ($formData['reason'] ?? '')) ?></textarea>
            <div class="invalid-feedback"><?= \App\Helpers\Helper::escape((string) ($fieldErrors['reason'] ?? 'Consultation reason is required.')) ?></div>
            <div class="form-text">Maximum 2000 characters. Avoid sharing sensitive identifiers in free text where possible.</div>
          </div>

          <div class="col-md-6">
            <label for="specialization" class="form-label">Required Medical Specialization</label>
            <select
              class="form-select <?= isset($fieldErrors['specialization']) ? 'is-invalid' : '' ?>"
              id="specialization"
              name="specialization"
              required
            >
              <option value="">Select a specialization</option>
              <?php foreach ($specializationOptions as $specializationOption): ?>
                <option value="<?= \App\Helpers\Helper::escape((string) $specializationOption) ?>" <?= ($formData['specialization'] ?? '') === $specializationOption ? 'selected' : '' ?>>
                  <?= \App\Helpers\Helper::escape((string) $specializationOption) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback"><?= \App\Helpers\Helper::escape((string) ($fieldErrors['specialization'] ?? 'Medical specialization is required.')) ?></div>
            <div class="form-text">Only specializations with active doctors and future availability are listed.</div>
          </div>

          <div class="col-md-6">
            <label for="attachment" class="form-label">Optional Supporting Attachment</label>
            <input
              type="file"
              class="form-control <?= isset($fieldErrors['attachment']) ? 'is-invalid' : '' ?>"
              id="attachment"
              name="attachment"
              accept="application/pdf,image/jpeg,image/png,image/webp"
            >
            <div class="invalid-feedback"><?= \App\Helpers\Helper::escape((string) ($fieldErrors['attachment'] ?? 'Attachment upload is invalid.')) ?></div>
            <div class="form-text">Allowed: PDF, JPG, PNG, WEBP. Max size: 15 MB.</div>
          </div>
        </div>

        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
          <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests') ?>" class="btn btn-outline-primary rounded-pill px-4">Cancel</a>
          <button type="submit" class="btn btn-primary rounded-pill px-4">
            <i class="bi bi-send me-2"></i>
            Submit Request
          </button>
        </div>
      </form>
    </div>
  </div>
</section>
