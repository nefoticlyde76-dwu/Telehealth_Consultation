<?php

use App\Helpers\Status;

$historyRows = is_array($historyRows ?? null) ? $historyRows : [];
$emptyTitle = (string) ($emptyTitle ?? 'No consultations in this section');
$emptyText = (string) ($emptyText ?? 'Matching consultations will appear here.');
$showEmptyAction = (bool) ($showEmptyAction ?? false);

require_once __DIR__ . '/../../partials/shared/status_helper.php';
?>

<div class="ux-table-wrapper border-0">
  <div class="table-responsive">
    <table class="ux-table">
      <caption class="visually-hidden">Consultation history with doctor, date, status, record, prescription, and actions.</caption>
      <thead>
        <tr>
          <th scope="col">Consultation date</th>
          <th scope="col">Doctor</th>
          <th scope="col">Type</th>
          <th scope="col">Status</th>
          <th scope="col">Record</th>
          <th scope="col">Prescription</th>
          <th scope="col" class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($historyRows === []): ?>
          <tr>
            <td colspan="7" class="ux-table__empty-state">
              <div class="ux-empty">
                <div class="ux-empty__icon">
                  <i class="bi bi-clipboard2-x"></i>
                </div>
                <h4 class="ux-empty__title"><?= \App\Helpers\Helper::escape($emptyTitle) ?></h4>
                <p class="ux-empty__text"><?= \App\Helpers\Helper::escape($emptyText) ?></p>
                <?php if ($showEmptyAction): ?>
                  <div class="ux-empty__action">
                    <a href="<?= \App\Helpers\Helper::url('/patient/doctors') ?>" class="btn btn-primary btn-sm">
                      <i class="bi bi-person-badge me-1"></i>
                      Browse Doctors
                    </a>
                  </div>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($historyRows as $request): ?>
            <?php
            $status = (string) ($request['status'] ?? Status::PENDING);
            $requestId = (int) ($request['id'] ?? 0);
            $hasFinalRecord = (int) ($request['has_final_record'] ?? 0) === 1;
            $hasPrescription = (int) ($request['has_prescription'] ?? 0) === 1;
            $detailUrl = \App\Helpers\Helper::url('/patient/consultation-requests/' . (string) $requestId);
            $downloadRecordUrl = \App\Helpers\Helper::url('/patient/consultation-requests/' . (string) $requestId . '/download-record');
            $downloadPrescriptionUrl = \App\Helpers\Helper::url('/patient/consultation-requests/' . (string) $requestId . '/download-prescription');
            $timeLabel = substr((string) ($request['start_time'] ?? ''), 0, 5) . ' - ' . substr((string) ($request['end_time'] ?? ''), 0, 5);
            $videoJoin = $request['videoJoin'] ?? null;
            $joinUrl = is_array($videoJoin) ? (string) ($videoJoin['joinUrl'] ?? '') : '';
            $canJoinNow = is_array($videoJoin) ? (bool) ($videoJoin['canJoin'] ?? false) : false;
            $joinStatus = is_array($videoJoin) ? (string) ($videoJoin['status'] ?? 'unavailable') : 'unavailable';
            $joinReason = is_array($videoJoin) ? (string) ($videoJoin['reason'] ?? '') : '';
            $actions = Status::consultationUiActions($status, [
                'role' => 'patient',
                'has_final_record' => $hasFinalRecord,
                'has_prescription' => $hasPrescription,
                'join_status' => $joinStatus,
                'can_join' => $canJoinNow,
                'join_url' => $joinUrl,
            ]);
            $isCompleted = (bool) $actions['view_record'];
            $joinLabel = (string) ($actions['join_label'] ?: 'Join Consultation');
            ?>
            <tr>
              <td>
                <div class="d-flex flex-column">
                  <strong><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($request['consultation_date'] ?? ''), 'd M Y', 'Not available')) ?></strong>
                  <span class="text-muted small"><?= \App\Helpers\Helper::escape($timeLabel) ?></span>
                </div>
              </td>
              <td>
                <?php
                $personName = (string) ($request['doctor_name'] ?? 'Doctor');
                $personPhoto = $request['doctor_photo_path'] ?? null;
                $personMeta = (string) ($request['doctor_title'] ?? 'Medical Practitioner');
                $personSize = 'sm';
                require __DIR__ . '/../../partials/shared/person_row.php';
                ?>
              </td>
              <td>
                <span class="ux-badge ux-badge--neutral ux-badge--dotless">
                  <?= \App\Helpers\Helper::escape((string) ($request['specialization'] ?? 'General Practice')) ?>
                </span>
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
                  <?php if ($isCompleted): ?>
                    <a href="<?= \App\Helpers\Helper::escape($detailUrl) ?>" class="btn btn-outline-primary btn-sm" aria-label="View consultation record">
                      View Record
                    </a>
                    <?php if ($hasFinalRecord): ?>
                      <a href="<?= \App\Helpers\Helper::escape($downloadRecordUrl) ?>" class="btn btn-outline-primary btn-sm" aria-label="Download consultation record">
                        <i class="bi bi-download me-1"></i>
                        Download Consultation Record
                      </a>
                    <?php endif; ?>
                    <?php if ($hasPrescription): ?>
                      <a href="<?= \App\Helpers\Helper::escape($detailUrl . '#prescription') ?>" class="btn btn-outline-primary btn-sm" aria-label="View prescription for this consultation">
                        View Prescription
                      </a>
                      <a href="<?= \App\Helpers\Helper::escape($downloadPrescriptionUrl) ?>" class="btn btn-outline-primary btn-sm" aria-label="Download prescription">
                        <i class="bi bi-download me-1"></i>
                        Download Prescription
                      </a>
                    <?php endif; ?>
                  <?php elseif ($actions['join']): ?>
                    <?php if ($actions['join_enabled']): ?>
                      <a href="<?= \App\Helpers\Helper::escape($joinUrl) ?>"
                         class="btn btn-primary btn-sm"
                         aria-label="Join the video consultation">
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
                    <?php endif; ?>
                    <a href="<?= \App\Helpers\Helper::escape($detailUrl) ?>" class="btn btn-outline-primary btn-sm" aria-label="View consultation details">
                      View Details
                    </a>
                  <?php else: ?>
                    <a href="<?= \App\Helpers\Helper::escape($detailUrl) ?>" class="btn btn-outline-primary btn-sm" aria-label="View consultation details">
                      View Details
                    </a>
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
