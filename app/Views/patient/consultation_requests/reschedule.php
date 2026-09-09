<?php

use App\Helpers\Helper;
use App\Helpers\Status;

$request = is_array($request ?? null) ? $request : [];
$slots = is_array($slots ?? null) ? $slots : [];
$errors = is_array($errors ?? null) ? $errors : [];
$csrfToken = (string) ($csrfToken ?? '');
$eligibility = is_array($eligibility ?? null) ? $eligibility : [];
$requestId = (int) ($request['id'] ?? 0);
$cutoffHours = (int) ($eligibility['cutoff_hours'] ?? 24);

require __DIR__ . '/../../partials/shared/status_helper.php';

$currentDay = Helper::formatDate((string) ($request['consultation_date'] ?? ''), 'D, d M Y', 'Not available');
$currentTime = trim(
    substr((string) ($request['start_time'] ?? ''), 0, 5) . ' - ' . substr((string) ($request['end_time'] ?? ''), 0, 5),
    ' -'
);
$doctorName = (string) ($request['doctor_name'] ?? 'Doctor');
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-page-header d-flex flex-column flex-xl-row justify-content-between align-items-xl-start gap-3 mb-4">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= Helper::url('/patient/dashboard') ?>">Dashboard</a></li>
        <li><a href="<?= Helper::url('/patient/consultation-requests') ?>">My Consultations</a></li>
        <li><a href="<?= Helper::url('/patient/consultation-requests/' . $requestId) ?>">Consultation Details</a></li>
        <li class="active">Reschedule</li>
      </ol>
      <span class="section-badge mb-3">
        <i class="bi bi-calendar2-week"></i>
        Reschedule Consultation
      </span>
      <h2 class="ux-page-header__title h4 mb-2">Choose another slot with the same doctor</h2>
      <p class="ux-page-header__subtitle text-muted mb-0">
        Changes must be made at least <?= (int) $cutoffHours ?> hour<?= $cutoffHours === 1 ? '' : 's' ?> before the appointment.
        Your request stays with <?= Helper::escape($doctorName) ?>.
      </p>
    </div>
    <div class="ux-page-header__right d-flex flex-wrap gap-2">
      <a href="<?= Helper::url('/patient/consultation-requests/' . $requestId) ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>
        Back to details
      </a>
    </div>
  </div>

  <?php if ($errors !== []): ?>
    <div class="alert alert-danger" role="alert">
      <ul class="mb-0 ps-3">
        <?php foreach ($errors as $error): ?>
          <li><?= Helper::escape((string) $error) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card border-0 shadow-sm rounded-4 mb-4">
    <div class="card-body p-4">
      <span class="dashboard-info-label">Current appointment</span>
      <strong class="d-block mb-1"><?= Helper::escape($currentDay) ?></strong>
      <p class="text-muted small mb-0"><?= Helper::escape($currentTime !== '' ? $currentTime : 'Time not set') ?> · <?= Helper::escape($doctorName) ?></p>
    </div>
  </div>

  <form method="POST"
        action="<?= Helper::url('/patient/consultation-requests/' . $requestId . '/reschedule') ?>"
        data-confirm-title="Reschedule this consultation?"
        data-confirm-body="The current slot will be released and this booking will move to the time you selected."
        data-confirm-hint="The doctor will be notified of the new appointment time."
        data-confirm-tone="primary"
        data-confirm-action="Confirm reschedule">
    <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">

    <div class="ux-card ux-data-card">
      <div class="ux-card__header">
        <h2 class="ux-data-card__title">Available slots</h2>
      </div>
      <div class="ux-table-wrapper border-0">
        <div class="table-responsive">
          <table class="ux-table">
            <caption class="visually-hidden">Available consultation slots with the same doctor for rescheduling.</caption>
            <thead>
              <tr>
                <th scope="col">Select</th>
                <th scope="col">Consultation date</th>
                <th scope="col">Time</th>
                <th scope="col">Notes</th>
                <th scope="col">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($slots === []): ?>
                <tr>
                  <td colspan="5" class="ux-table__empty-state">
                    <div class="ux-empty">
                      <div class="ux-empty__icon">
                        <i class="bi bi-calendar-x"></i>
                      </div>
                      <h4 class="ux-empty__title">No other slots available</h4>
                      <p class="ux-empty__text">There are no later bookable slots with this doctor outside the change cutoff. You can cancel this booking and book a different doctor instead.</p>
                    </div>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($slots as $index => $slot): ?>
                  <?php
                  $slotId = (int) ($slot['id'] ?? 0);
                  $slotDay = Helper::formatDate((string) ($slot['consultation_date'] ?? ''), 'd M Y', 'Not available');
                  $slotTime = substr((string) ($slot['start_time'] ?? ''), 0, 5) . ' - ' . substr((string) ($slot['end_time'] ?? ''), 0, 5);
                  $inputId = 'reschedule-slot-' . $slotId;
                  ?>
                  <tr>
                    <td>
                      <div class="form-check mb-0">
                        <input class="form-check-input" type="radio" name="availability_id" id="<?= Helper::escape($inputId) ?>" value="<?= $slotId ?>" <?= $index === 0 ? 'checked' : '' ?> required>
                        <label class="form-check-label visually-hidden" for="<?= Helper::escape($inputId) ?>">Select <?= Helper::escape($slotDay . ' ' . $slotTime) ?></label>
                      </div>
                    </td>
                    <td><strong><?= Helper::escape($slotDay) ?></strong></td>
                    <td><?= Helper::escape($slotTime) ?></td>
                    <td><span class="text-muted small"><?= Helper::escape((string) ($slot['notes'] ?? 'No notes provided')) ?></span></td>
                    <td><?= ux_status_badge(Status::SLOT_AVAILABLE, Status::DOMAIN_SLOT) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <?php if ($slots !== []): ?>
      <div class="d-flex justify-content-end gap-2 mt-4">
        <a href="<?= Helper::url('/patient/consultation-requests/' . $requestId) ?>" class="btn btn-outline-secondary">
          Keep current time
        </a>
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-calendar2-check me-1"></i>
          Confirm new slot
        </button>
      </div>
    <?php endif; ?>
  </form>
</section>
