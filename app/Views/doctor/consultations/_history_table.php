<?php

use App\Helpers\Status;

$historyRows = is_array($historyRows ?? null) ? $historyRows : [];
$emptyTitle = (string) ($emptyTitle ?? 'No consultations in this section');
$emptyText = (string) ($emptyText ?? 'Matching consultations will appear here.');
$showResetAction = (bool) ($showResetAction ?? false);
$csrfToken = (string) ($csrfToken ?? '');

require_once __DIR__ . '/../../partials/shared/status_helper.php';
?>

<div class="ux-table-wrapper border-0">
  <div class="table-responsive">
    <table class="ux-table">
      <caption class="visually-hidden">Doctor consultation listing with patient, schedule, status, record, prescription, and actions.</caption>
      <thead>
        <tr>
          <th scope="col">Patient</th>
          <th scope="col">Consultation Date</th>
          <th scope="col">Time</th>
          <th scope="col">Chief Complaint</th>
          <th scope="col">Status</th>
          <th scope="col">Record</th>
          <th scope="col">Prescription</th>
          <th scope="col" class="text-end">Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($historyRows === []): ?>
          <tr>
            <td colspan="8" class="ux-table__empty-state">
              <div class="ux-empty">
                <div class="ux-empty__icon">
                  <i class="bi bi-clipboard2-x"></i>
                </div>
                <h4 class="ux-empty__title"><?= \App\Helpers\Helper::escape($emptyTitle) ?></h4>
                <p class="ux-empty__text"><?= \App\Helpers\Helper::escape($emptyText) ?></p>
                <?php if ($showResetAction): ?>
                  <div class="ux-empty__action">
                    <a href="<?= \App\Helpers\Helper::url('/doctor/consultations') ?>" class="btn btn-outline-primary btn-sm">
                      <i class="bi bi-arrow-clockwise me-1"></i>
                      View all consultations
                    </a>
                  </div>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($historyRows as $consultation): ?>
            <?php
            $status = (string) ($consultation['status'] ?? Status::PENDING);
            $dateLabel = \App\Helpers\Helper::formatDate((string) ($consultation['consultation_date'] ?? ''), 'd M Y', 'Not available');
            $timeLabel = substr((string) ($consultation['start_time'] ?? ''), 0, 5) . ' - ' . substr((string) ($consultation['end_time'] ?? ''), 0, 5);
            $hasFinalRecord = (int) ($consultation['has_final_record'] ?? 0) === 1;
            $hasPrescription = (int) ($consultation['has_prescription'] ?? 0) === 1;
            $consultationId = (int) ($consultation['id'] ?? 0);
            $consultationRoomUrl = \App\Helpers\Helper::url('/doctor/consultations/' . (string) $consultationId . '/room');
            $consultationRecordUrl = \App\Helpers\Helper::url('/doctor/consultations/' . (string) $consultationId);
            $prescriptionUrl = \App\Helpers\Helper::url('/doctor/consultations/' . (string) $consultationId . '/prescription');
            $downloadRecordUrl = \App\Helpers\Helper::url('/doctor/consultations/' . (string) $consultationId . '/download-record');
            $downloadPrescriptionUrl = \App\Helpers\Helper::url('/doctor/consultations/' . (string) $consultationId . '/download-prescription');

            $videoJoin = $consultation['videoJoin'] ?? null;
            $joinUrl    = is_array($videoJoin) ? (string) ($videoJoin['joinUrl'] ?? '') : '';
            $canJoinNow = is_array($videoJoin) ? (bool) ($videoJoin['canJoin'] ?? false) : false;
            $joinStatus = is_array($videoJoin) ? (string) ($videoJoin['status'] ?? 'unavailable') : 'unavailable';
            $joinReason = is_array($videoJoin) ? (string) ($videoJoin['reason'] ?? '') : '';
            $actions = Status::consultationUiActions($status, [
                'role' => 'doctor',
                'has_final_record' => $hasFinalRecord,
                'has_prescription' => $hasPrescription,
                'join_status' => $joinStatus,
                'can_join' => $canJoinNow,
                'join_url' => $joinUrl,
                'can_no_show' => \App\Models\ConsultationRequest::appointmentHasStarted($consultation),
            ]);
            $isCompleted = (bool) $actions['view_record'];
            $joinLabel = (string) ($actions['join_label'] ?: 'Join Consultation');
            ?>
            <tr>
              <td>
                <?php
                $personName = (string) ($consultation['patient_name'] ?? 'Patient');
                $personPhoto = $consultation['patient_photo_path'] ?? null;
                $personMeta = \App\Helpers\Helper::formatDate((string) ($consultation['request_date'] ?? ''), 'd M Y', '');
                $personSize = 'sm';
                require __DIR__ . '/../../partials/shared/person_row.php';
                ?>
              </td>
              <td><strong><?= \App\Helpers\Helper::escape((string) $dateLabel) ?></strong></td>
              <td><span class="text-muted small"><?= \App\Helpers\Helper::escape((string) $timeLabel) ?></span></td>
              <td>
                <span class="text-muted small"><?= \App\Helpers\Helper::escape(mb_strimwidth((string) ($consultation['reason'] ?? ''), 0, 70, '...')) ?></span>
              </td>
              <td>
                <?= ux_status_badge($status) ?>
              </td>
              <td>
                <?php if ($hasFinalRecord): ?>
                  <?= ux_status_badge(Status::RECORD_FINAL, Status::DOMAIN_RECORD) ?>
                <?php elseif ($isCompleted): ?>
                  <span class="text-muted small">Not yet finalized</span>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($hasPrescription): ?>
                  <?= ux_status_badge('Issued', Status::DOMAIN_PRESENCE) ?>
                <?php elseif ($isCompleted): ?>
                  <span class="text-muted small">Not issued</span>
                <?php else: ?>
                  <span class="text-muted small">—</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <div class="ux-table__actions">
                  <?php if ($actions['join']): ?>
                    <?php if ($actions['join_enabled']): ?>
                      <a href="<?= \App\Helpers\Helper::escape($joinUrl) ?>"
                         class="btn btn-primary btn-sm"
                         aria-label="Join video consultation room now">
                        <i class="bi bi-camera-video-fill me-1"></i>
                        <?= \App\Helpers\Helper::escape($joinLabel) ?>
                      </a>
                    <?php else: ?>
                      <button type="button"
                              class="btn btn-primary btn-sm"
                              disabled
                              aria-disabled="true"
                              <?php if ($joinReason !== ''): ?>
                                title="<?= \App\Helpers\Helper::escape($joinReason) ?>"
                              <?php endif; ?>>
                        <i class="bi bi-camera-video me-1"></i>
                        <?= \App\Helpers\Helper::escape($joinLabel) ?>
                      </button>
                      <?php if ($joinReason !== '' && $joinStatus === 'early'): ?>
                        <small class="text-muted d-block mt-1 text-end">
                          <i class="bi bi-info-circle me-1"></i>
                          <?= \App\Helpers\Helper::escape($joinReason) ?>
                        </small>
                      <?php endif; ?>
                    <?php endif; ?>
                  <?php endif; ?>

                  <?php if ($actions['review_complete']): ?>
                    <a href="<?= \App\Helpers\Helper::escape($consultationRoomUrl) ?>" class="btn btn-outline-primary btn-sm">
                      <i class="bi bi-clipboard2-pulse me-1"></i>
                      Review &amp; complete
                    </a>
                    <?php if ($actions['no_show'] && $csrfToken !== ''): ?>
                      <?php
                      $noShowUrl = \App\Helpers\Helper::url('/doctor/consultations/' . (string) $consultationId . '/no-show');
                      $noShowFormClass = 'd-inline';
                      require __DIR__ . '/../../partials/shared/_no_show_action.php';
                      ?>
                    <?php endif; ?>
                  <?php elseif ($isCompleted): ?>
                    <a href="<?= \App\Helpers\Helper::escape($consultationRecordUrl) ?>" class="btn btn-outline-primary btn-sm">
                      <i class="bi bi-clipboard2-pulse me-1"></i>
                      View Record
                    </a>
                    <?php if ($hasFinalRecord): ?>
                      <a href="<?= \App\Helpers\Helper::escape($downloadRecordUrl) ?>" class="btn btn-outline-primary btn-sm" aria-label="Download consultation record">
                        <i class="bi bi-download me-1"></i>
                        Download Consultation Record
                      </a>
                    <?php endif; ?>
                    <a href="<?= \App\Helpers\Helper::escape($prescriptionUrl) ?>" class="btn btn-outline-primary btn-sm">
                      <i class="bi bi-capsule me-1"></i>
                      <?= $hasPrescription ? 'View Prescription' : 'Create prescription' ?>
                    </a>
                    <?php if ($hasPrescription): ?>
                      <a href="<?= \App\Helpers\Helper::escape($downloadPrescriptionUrl) ?>" class="btn btn-outline-primary btn-sm" aria-label="Download prescription">
                        <i class="bi bi-download me-1"></i>
                        Download Prescription
                      </a>
                    <?php endif; ?>
                  <?php elseif (!$actions['join']): ?>
                    <span class="text-muted small">No action</span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
