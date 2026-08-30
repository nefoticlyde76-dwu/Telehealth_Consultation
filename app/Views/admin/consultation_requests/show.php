<?php

$request = $request ?? [];
$csrfToken = $csrfToken ?? '';
$status = (string) ($request['status'] ?? 'Pending');
$requestId = (int) ($request['id'] ?? 0);

require __DIR__ . '/../../partials/shared/status_helper.php';

$dateLabel = \App\Helpers\Helper::formatDate((string) ($request['consultation_date'] ?? ''), 'D, d M Y', 'Not available');
$timeLabel = substr((string) ($request['start_time'] ?? ''), 0, 5) . ' - ' . substr((string) ($request['end_time'] ?? ''), 0, 5);
?>

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="ux-page-header d-flex flex-column flex-xl-row justify-content-between align-items-xl-start align-items-xl-center gap-3 mb-4">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/admin/dashboard') ?>">Dashboard</a></li>
        <li><a href="<?= \App\Helpers\Helper::url('/admin/consultation-requests') ?>">Consultation Requests</a></li>
        <li class="active">Request #<?= \App\Helpers\Helper::escape((string) $requestId) ?></li>
      </ol>
      <span class="section-badge mb-3">
        <i class="bi bi-clipboard2-check"></i>
        Consultation Request Details
      </span>
      <h2 class="ux-page-header__title h4 mb-2">Request #<?= \App\Helpers\Helper::escape((string) $requestId) ?></h2>
      <p class="ux-page-header__subtitle text-muted mb-0">Review full consultation request details and apply an approval decision securely.</p>
    </div>
    <div class="ux-page-header__right d-flex flex-wrap gap-2 align-items-center">
      <a href="<?= \App\Helpers\Helper::url('/admin/consultation-requests') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-arrow-left me-2"></i>
        Back to Requests
      </a>
      <?= ux_status_badge($status, \App\Helpers\Status::DOMAIN_CONSULTATION, ['class' => 'align-self-center']) ?>
    </div>
  </div>

  <div class="ux-card ux-data-card ux-queue-detail mb-4">
    <div class="ux-card__header">
      <h2 class="ux-data-card__title">Request details</h2>
      <?= ux_status_badge($status, \App\Helpers\Status::DOMAIN_CONSULTATION) ?>
    </div>
    <div class="table-responsive ux-queue-detail__table-wrap">
      <table class="table ux-table ux-details-table ux-queue-detail__table mb-0">
        <caption class="visually-hidden">Consultation request details</caption>
        <tbody>
          <tr>
            <th scope="row">Patient</th>
            <td>
              <?php
              $personName = (string) ($request['patient_name'] ?? 'Patient');
              $personPhoto = $request['patient_photo_path'] ?? null;
              $personMeta = 'Patient ID #' . (string) ((int) ($request['patient_id'] ?? 0));
              $personSize = 'sm';
              require __DIR__ . '/../../partials/shared/person_row.php';
              ?>
            </td>
          </tr>
          <tr>
            <th scope="row">Doctor</th>
            <td>
              <?php
              $personName = (string) ($request['doctor_name'] ?? 'Doctor');
              $personPhoto = $request['doctor_photo_path'] ?? null;
              $personMeta = trim((string) ($request['doctor_title'] ?? 'Medical Practitioner') . ' · ' . (string) ($request['specialization'] ?? 'General Practice'));
              $personSize = 'sm';
              require __DIR__ . '/../../partials/shared/person_row.php';
              ?>
            </td>
          </tr>
          <tr>
            <th scope="row">Consultation date</th>
            <td><?= \App\Helpers\Helper::escape((string) $dateLabel) ?></td>
          </tr>
          <tr>
            <th scope="row">Time</th>
            <td><?= \App\Helpers\Helper::escape((string) $timeLabel) ?></td>
          </tr>
          <tr>
            <th scope="row">Date submitted</th>
            <td><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($request['request_date'] ?? ''), 'd M Y H:i', 'Not available')) ?></td>
          </tr>
          <tr>
            <th scope="row">Availability status</th>
            <td><?= \App\Helpers\Helper::escape((string) ($request['availability_status'] ?? 'Not available')) ?></td>
          </tr>
          <tr>
            <th scope="row">Current status</th>
            <td><?= ux_status_badge($status, \App\Helpers\Status::DOMAIN_CONSULTATION) ?></td>
          </tr>
          <tr>
            <th scope="row">Chief complaint</th>
            <td><?= nl2br(\App\Helpers\Helper::escape((string) ($request['reason'] ?? ''))) ?></td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="ux-queue-detail__actions">
      <?php if ($status === 'Pending' || $status === 'Approved'): ?>
        <?php if ($status === 'Pending'): ?>
          <form method="POST" action="<?= \App\Helpers\Helper::url('/admin/consultation-requests/' . $requestId . '/reject') ?>">
            <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">
            <button type="submit" class="btn btn-outline-danger btn-sm">
              <i class="bi bi-x-circle me-1"></i>
              Reject Request
            </button>
          </form>
          <form method="POST" action="<?= \App\Helpers\Helper::url('/admin/consultation-requests/' . $requestId . '/approve') ?>">
            <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-check2-circle me-1"></i>
              Approve Request
            </button>
          </form>
        <?php endif; ?>
        <form method="POST" action="<?= \App\Helpers\Helper::url('/admin/consultation-requests/' . $requestId . '/cancel') ?>">
          <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape((string) $csrfToken) ?>">
          <button type="submit" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-slash-circle me-1"></i>
            Cancel Request
          </button>
        </form>
      <?php else: ?>
        <p class="text-muted small mb-0">This consultation request is no longer pending, so no further approval actions are available.</p>
      <?php endif; ?>
    </div>
  </div>
</section>
