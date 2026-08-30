<?php

$clinicalRecord = is_array($context['clinical_record'] ?? null) ? $context['clinical_record'] : null;
$clinicalSaveEndpoint = (string) ($context['clinical_save_endpoint'] ?? '');
$completeEndpoint = (string) ($context['complete_endpoint'] ?? '');
$prescriptionPath = (string) ($context['prescription_path'] ?? '');
$clinicalCanEdit = (bool) ($context['clinical_can_edit'] ?? false);
$clinicalCsrf = (string) ($context['csrf_token'] ?? '');
$bookingReason = (string) ($context['consultation_reason'] ?? '');
$patientName = (string) ($context['other_party_name'] ?? 'Patient');
$consultationId = (int) ($context['consultation_id'] ?? 0);
$consultationStatus = (string) ($context['consultation_status'] ?? '');

$field = static function (string $key, string $fallback = '') use ($clinicalRecord): string {
    if (!is_array($clinicalRecord)) {
        return $fallback;
    }

    $value = trim((string) ($clinicalRecord[$key] ?? ''));
    return $value !== '' ? $value : $fallback;
};

$chiefComplaint = $field('chief_complaint', $bookingReason);
$recordId = (int) ($clinicalRecord['id'] ?? 0);
$recordStatus = (string) ($clinicalRecord['record_status'] ?? 'Draft');
$isFinal = $recordStatus === \App\Models\ConsultationRecord::STATUS_FINAL;
$canEdit = $clinicalCanEdit && !$isFinal && $clinicalSaveEndpoint !== '';
$canComplete = $canEdit && $completeEndpoint !== '';
$isCompleted = $consultationStatus === 'Completed' || $isFinal;
$savedAt = (string) ($clinicalRecord['updated_at'] ?? '');
$finalizedAt = (string) ($clinicalRecord['finalized_at'] ?? '');
$initialState = $clinicalRecord === null
    ? ''
    : ($isFinal ? 'Final' : 'Saved');
$disabledAttr = $canEdit ? '' : ' disabled';
$consultationDate = \App\Helpers\Helper::formatDate((string) ($context['consultation_date'] ?? ''), 'D, d M Y', 'Not scheduled');
$startLabel = substr((string) ($context['consultation_start_time'] ?? ''), 0, 5);
$endLabel = substr((string) ($context['consultation_end_time'] ?? ''), 0, 5);
$timeLabel = ($startLabel !== '' && $endLabel !== '') ? $startLabel . ' – ' . $endLabel : 'Not scheduled';

$clinicalFields = [
    [
        'id' => 'clinical_chief_complaint',
        'name' => 'chief_complaint',
        'label' => \App\Models\ConsultationRecord::REQUIRED_FIELDS['chief_complaint'],
        'value' => $chiefComplaint,
        'rows' => 2,
        'required' => false,
    ],
    [
        'id' => 'clinical_symptoms',
        'name' => 'symptoms',
        'label' => \App\Models\ConsultationRecord::REQUIRED_FIELDS['symptoms'],
        'value' => $field('symptoms'),
        'rows' => 3,
        'required' => false,
    ],
    [
        'id' => 'clinical_findings',
        'name' => 'clinical_findings',
        'label' => \App\Models\ConsultationRecord::REQUIRED_FIELDS['clinical_findings'],
        'value' => $field('clinical_findings'),
        'rows' => 3,
        'required' => false,
    ],
    [
        'id' => 'clinical_diagnosis',
        'name' => 'diagnosis',
        'label' => \App\Models\ConsultationRecord::REQUIRED_FIELDS['diagnosis'],
        'value' => $field('diagnosis'),
        'rows' => 2,
        'required' => false,
    ],
    [
        'id' => 'clinical_treatment',
        'name' => 'treatment_plan',
        'label' => \App\Models\ConsultationRecord::REQUIRED_FIELDS['treatment_plan'],
        'value' => $field('treatment_plan'),
        'rows' => 3,
        'required' => false,
    ],
    [
        'id' => 'clinical_notes',
        'name' => 'additional_notes',
        'label' => 'Additional clinical notes',
        'value' => $field('additional_notes'),
        'rows' => 3,
        'required' => false,
    ],
];
?>

<section class="vc-side-panel vc-clinical-panel" aria-label="Clinical documentation">
  <div class="vc-side-panel__header">
    <div class="d-flex align-items-center justify-content-between gap-2 w-100">
      <span>
        <i class="bi bi-clipboard2-pulse me-2"></i>
        Clinical documentation
      </span>
        <span class="vc-clinical-panel__status-group">
          <?= ux_status_badge($recordStatus, \App\Helpers\Status::DOMAIN_RECORD) ?>
        <span id="vc-clinical-save-state" class="vc-clinical-panel__status<?= $initialState === 'Saved' ? ' is-saved' : '' ?>" aria-live="polite">
          <?= \App\Helpers\Helper::escape($initialState) ?>
        </span>
      </span>
    </div>
  </div>
  <div class="vc-side-panel__body">
    <div class="table-responsive vc-clinical-doc">
      <table class="vc-clinical-doc__table">
        <caption class="visually-hidden">Consultation identity</caption>
        <tbody>
          <tr>
            <th scope="row">Patient</th>
            <td>
              <?php
              $personName = $patientName;
              $personPhoto = $context['other_party_photo'] ?? null;
              $personMeta = '';
              $personSize = 'sm';
              require __DIR__ . '/../../partials/shared/person_row.php';
              ?>
            </td>
          </tr>
          <tr>
            <th scope="row">Consultation</th>
            <td class="font-monospace">#<?= \App\Helpers\Helper::escape((string) $consultationId) ?></td>
          </tr>
          <tr>
            <th scope="row">Date</th>
            <td><?= \App\Helpers\Helper::escape($consultationDate) ?></td>
          </tr>
          <tr>
            <th scope="row">Time</th>
            <td><?= \App\Helpers\Helper::escape($timeLabel) ?></td>
          </tr>
        </tbody>
      </table>
    </div>

    <?php if (!$canEdit): ?>
      <p class="small text-muted mb-3">
        <?php if ($isFinal): ?>
          This clinical record is finalized and cannot be edited from the consultation room.
          <?php if ($finalizedAt !== ''): ?>
            Completed <?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate($finalizedAt, 'd M Y H:i', '')) ?>.
          <?php endif; ?>
        <?php else: ?>
          Clinical notes can be recorded here during an approved consultation.
        <?php endif; ?>
      </p>
    <?php else: ?>
      <p class="small text-muted mb-3">Record notes during the consultation. Drafts save automatically until you mark it complete.</p>
    <?php endif; ?>

    <form id="vc-clinical-form"
          method="POST"
          action="<?= \App\Helpers\Helper::escape($clinicalSaveEndpoint) ?>"
          data-clinical-record-form
          data-can-edit="<?= $canEdit ? '1' : '0' ?>"
          data-complete-endpoint="<?= \App\Helpers\Helper::escape($completeEndpoint) ?>"
          novalidate>
      <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($clinicalCsrf) ?>">
      <input type="hidden" name="record_id" id="vc-clinical-record-id" value="<?= \App\Helpers\Helper::escape((string) $recordId) ?>">

      <div class="table-responsive vc-clinical-doc">
        <table class="vc-clinical-doc__table vc-clinical-doc__table--notes">
          <caption>Clinical notes</caption>
          <thead>
            <tr>
              <th scope="col">Section</th>
              <th scope="col">Entry</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($clinicalFields as $clinicalField): ?>
              <tr>
                <th scope="row">
                  <label for="<?= \App\Helpers\Helper::escape((string) $clinicalField['id']) ?>">
                    <?= \App\Helpers\Helper::escape((string) $clinicalField['label']) ?>
                    <?php if (!empty($clinicalField['required'])): ?>
                      <span class="text-danger">*</span>
                    <?php endif; ?>
                  </label>
                </th>
                <td>
                  <textarea
                    class="form-control"
                    id="<?= \App\Helpers\Helper::escape((string) $clinicalField['id']) ?>"
                    name="<?= \App\Helpers\Helper::escape((string) $clinicalField['name']) ?>"
                    rows="<?= (int) $clinicalField['rows'] ?>"
                    maxlength="8000"<?= $disabledAttr ?>
                  ><?= \App\Helpers\Helper::escape((string) $clinicalField['value']) ?></textarea>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($canEdit): ?>
        <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-3">
          <button type="submit" class="btn btn-outline-primary btn-sm" id="vc-clinical-save-btn">
            Save draft
          </button>
          <?php if ($savedAt !== ''): ?>
            <span class="small text-muted" id="vc-clinical-saved-at">Last saved <?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate($savedAt, 'H:i', '')) ?></span>
          <?php else: ?>
            <span class="small text-muted" id="vc-clinical-saved-at"></span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </form>

    <?php if ($canComplete): ?>
      <div class="vc-clinical-panel__complete">
        <button type="button"
                class="btn btn-primary w-100"
                data-bs-toggle="modal"
                data-bs-target="#vc-complete-modal">
          <i class="bi bi-check2-circle me-1"></i>
          Complete Consultation
        </button>
        <p class="small text-muted mb-0 mt-2">Review the draft first. Completing finalizes the clinical record and cannot be undone from this screen.</p>
      </div>
    <?php elseif ($isCompleted && $prescriptionPath !== ''): ?>
      <a href="<?= \App\Helpers\Helper::escape($prescriptionPath) ?>" class="btn btn-primary w-100">
        <i class="bi bi-capsule me-1"></i>
        Open prescription
      </a>
    <?php endif; ?>
  </div>
</section>

<?php if ($canComplete): ?>
<div class="modal fade" id="vc-complete-modal" tabindex="-1" aria-labelledby="vc-complete-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-3">
      <div class="modal-header border-bottom">
        <h2 class="modal-title h5" id="vc-complete-modal-label">Complete this consultation?</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">This will save the latest clinical notes, finalize the consultation record, and mark the consultation as <strong>Completed</strong>.</p>
        <p class="mb-0 text-muted small">Finalized clinical information cannot be casually edited afterwards. Continue only after you have reviewed the notes.</p>
        <p id="vc-complete-modal-error" class="text-danger small mt-3 mb-0 d-none" role="alert"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Review notes</button>
        <button type="button" class="btn btn-primary" id="vc-complete-confirm-btn">
          Complete Consultation
        </button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
