<?php

$context = $context ?? [];
$consultationId         = (int)    ($context['consultation_id']         ?? 0);
$viewerRole             = (string) ($context['viewer_role']             ?? 'patient');
$otherPartyName         = (string) ($context['other_party_name']        ?? 'Other Participant');
$otherPartyTitle        = (string) ($context['other_party_title']       ?? '');
$otherPartyPhoto        = $context['other_party_photo'] ?? null;
$otherPartyMeta         = $context['other_party_meta'] ?? null;
$consultationDate       = (string) ($context['consultation_date']       ?? '');
$consultationStart      = (string) ($context['consultation_start_time'] ?? '');
$consultationEnd        = (string) ($context['consultation_end_time']   ?? '');
$consultationReason     = (string) ($context['consultation_reason']     ?? '');
$consultationStatus     = (string) ($context['consultation_status']     ?? '');
$joinTokenEndpoint      = (string) ($context['join_token_endpoint']     ?? '');
$joinCsrfToken          = (string) ($context['csrf_token']              ?? '');
$returnPath             = (string) ($context['return_path']             ?? '');
$returnPathLabel        = (string) ($context['return_path_label']       ?? 'Back');

$viewerLabel       = $viewerRole === 'doctor' ? 'Clinician view' : 'Patient view';
$viewerBadgeClass  = $viewerRole === 'doctor' ? 'bg-primary-soft text-primary-900' : 'bg-teal-soft text-teal-900';
$otherPartyHeading = $viewerRole === 'doctor' ? 'Patient' : 'Clinician';
$iconClass         = $viewerRole === 'doctor' ? 'bi-person-heart' : 'bi-stethoscope';
$isDoctorViewer    = $viewerRole === 'doctor';
$videoColClass     = $isDoctorViewer ? 'col-xl-7 order-1' : 'col-xl-8 order-2 order-xl-1';
$sideColClass      = $isDoctorViewer ? 'col-xl-5 order-2' : 'col-xl-4 order-1 order-xl-2';
$roomScriptAsset   = \App\Helpers\Helper::asset('js/consultation-room.js');
$clinicalScriptAsset = \App\Helpers\Helper::asset('js/consultation-clinical-record.js');

require_once __DIR__ . '/../../partials/shared/status_helper.php';

$formattedDate = \App\Helpers\Helper::formatDate($consultationDate, 'l, j F Y', '');
$formattedTime = '';
if ($consultationStart !== '' && $consultationEnd !== '') {
    $formattedTime = substr($consultationStart, 0, 5) . ' – ' . substr($consultationEnd, 0, 5);
}
?>

<!--
    SECURITY NOTE (DO NOT REMOVE / EDIT SUPERFLOUSLY)

    The Daily room URL and short-lived meeting token are NEVER emitted
    into this HTML.  The only data the page ships with below is:

      • consultation id
      • role-specific JSON endpoint to FETCH the credentials
      • publicly readable metadata (date/time, other party name, reason)

    The actual Daily credentials are only obtained AFTER the page
    renders, when daily-consultation.js does a same-origin fetch() to
    $joinTokenEndpoint (carrying the existing PHP session cookie).  The
    returned token & room URL live ONLY in the browser JS memory and
    are passed to window.DailyIframe.createFrame(...) — never the URL
    bar, never view-source, never localStorage.

    WEEK 6 — Video Consultation Integration
-->

<section class="mb-4">
  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div id="vc-alert-region" class="mb-4" aria-live="polite" aria-atomic="true"></div>

  <div class="ux-page-header d-flex flex-column flex-xl-row justify-content-between align-items-xl-start align-items-xl-center gap-3 mb-0">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li>
          <a href="<?= $viewerRole === 'doctor' ? \App\Helpers\Helper::url('/doctor/dashboard') : \App\Helpers\Helper::url('/patient/dashboard') ?>">Dashboard</a>
        </li>
        <li>
          <a href="<?= $viewerRole === 'doctor' ? \App\Helpers\Helper::url('/doctor/consultations') : \App\Helpers\Helper::url('/patient/consultation-requests') ?>">
            <?= $viewerRole === 'doctor' ? 'Consultations' : 'My Consultations' ?>
          </a>
        </li>
        <li class="active">Consultation Room</li>
      </ol>
    </div>
  </div>

  <div class="card border-0 shadow-sm rounded-4 overflow-hidden mt-4">
    <div class="card-header bg-transparent border-bottom px-4 py-4">
      <div class="d-flex flex-column flex-xl-row align-items-xl-center gap-3 justify-content-between">
        <div>
          <span class="section-badge mb-3">
            <i class="bi bi-camera-video-fill"></i>
            Live Consultation Room
          </span>
          <h1 class="visually-hidden">Consultation with <?= \App\Helpers\Helper::escape($otherPartyName) ?></h1>
          <div class="d-flex align-items-center gap-3 flex-wrap">
            <?php
            $personName = $otherPartyName;
            $personPhoto = $otherPartyPhoto;
            $personMeta = trim(implode(' · ', array_filter([
                $otherPartyTitle,
                is_string($otherPartyMeta) ? $otherPartyMeta : '',
            ], static fn (string $value): bool => $value !== '')));
            $personSize = 'lg';
            require __DIR__ . '/../../partials/shared/person_row.php';
            ?>
            <span class="ux-badge ux-badge--neutral">
              <?= \App\Helpers\Helper::escape($viewerLabel) ?>
            </span>
          </div>
          <p class="text-muted mb-0 mt-1">
            Consultation #<?= \App\Helpers\Helper::escape((string) $consultationId) ?>
            <?php if ($formattedDate !== '' || $formattedTime !== ''): ?>
              <span class="mx-2 text-body-secondary">•</span>
            <?php endif; ?>
            <?= \App\Helpers\Helper::escape(trim($formattedDate . ' ' . $formattedTime)) ?>
          </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
          <?php if ($returnPath !== ''): ?>
            <a href="<?= \App\Helpers\Helper::escape($returnPath) ?>" class="btn btn-outline-primary btn-sm">
              <i class="bi bi-arrow-left me-2"></i>
              <?= \App\Helpers\Helper::escape($returnPathLabel) ?>
            </a>
          <?php endif; ?>
          <button type="button" id="vc-leave-call-top" class="btn btn-outline-danger btn-sm d-none" aria-label="Leave the consultation">
            <i class="bi bi-telephone-x-fill me-2"></i>
            Leave call
          </button>
        </div>
      </div>
    </div>

    <div class="card-body p-4">
      <div class="row g-4<?= $isDoctorViewer ? ' vc-room-layout--clinician' : '' ?>">
        <!-- ── Primary Daily Prebuilt container ─────────────────── -->
        <div class="<?= $videoColClass ?>">
          <div class="vc-room-wrapper">
            <div
              id="vc-daily-frame-wrapper"
              class="vc-daily-frame-wrapper"
              role="application"
              aria-label="Video consultation window"
              tabindex="0"
            >
              <!-- Loading / pre-authorization state (never blank) -->
              <div id="vc-pre-call-state" class="vc-precall-state">
                <div class="vc-precall-state__logo">
                  <div class="vc-precall-state__logo-circle">
                    <i class="bi <?= \App\Helpers\Helper::escape($iconClass) ?>"></i>
                  </div>
                </div>
                <h2 class="vc-precall-state__title">Preparing your consultation</h2>
                <p id="vc-precall-state__message" class="vc-precall-state__message">
                  Verifying appointment access and preparing a secure MBPHA TeleHealth video room…
                </p>
                <div class="d-flex justify-content-center mt-2">
                  <div class="spinner-border spinner-border-sm text-primary me-2" id="vc-initializing-spinner" role="status" aria-hidden="true"></div>
                  <span class="text-muted small">Initializing secure connection</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Side column: patient details, or doctor clinical notes ── -->
        <div class="<?= $sideColClass ?>">
          <?php if ($isDoctorViewer): ?>
            <?php require __DIR__ . '/../../doctor/consultations/_clinical_documentation.php'; ?>
          <?php else: ?>
          <div class="vc-side-panel mb-4">
            <div class="vc-side-panel__header">
              <i class="bi bi-info-circle-fill me-2"></i>
              Consultation details
            </div>
            <div class="vc-side-panel__body">
              <div class="mb-3">
                <?php
                $personName = $otherPartyName;
                $personPhoto = $otherPartyPhoto;
                $personMeta = $otherPartyTitle;
                $personSize = 'sm';
                require __DIR__ . '/../../partials/shared/person_row.php';
                ?>
              </div>
              <dl class="row g-2 mb-3 align-items-center">
                <?php if (is_string($otherPartyMeta) && $otherPartyMeta !== ''): ?>
                  <dt class="col-5 small text-muted mb-0">Additional</dt>
                  <dd class="col-7 mb-0 small"><?= \App\Helpers\Helper::escape($otherPartyMeta) ?></dd>
                <?php endif; ?>

                <?php if ($formattedDate !== ''): ?>
                  <dt class="col-5 small text-muted mb-0">Date</dt>
                  <dd class="col-7 mb-0"><?= \App\Helpers\Helper::escape($formattedDate) ?></dd>
                <?php endif; ?>

                <?php if ($formattedTime !== ''): ?>
                  <dt class="col-5 small text-muted mb-0">Scheduled time</dt>
                  <dd class="col-7 mb-0"><?= \App\Helpers\Helper::escape($formattedTime) ?></dd>
                <?php endif; ?>

                <?php if ($consultationStatus !== ''): ?>
                  <dt class="col-5 small text-muted mb-0">Status</dt>
                  <dd class="col-7 mb-0">
                    <?= ux_status_badge($consultationStatus) ?>
                  </dd>
                <?php endif; ?>
              </dl>

              <h3 class="h6 mb-2 mt-4">Chief complaint</h3>
              <div class="dashboard-inline-callout">
                <p class="mb-0 small text-muted">
                  <?= $consultationReason !== ''
                    ? nl2br(\App\Helpers\Helper::escape($consultationReason))
                    : '<em class="fst-italic text-body-tertiary">No reason recorded at time of booking.</em>'; ?>
                </p>
              </div>
              <?php
              $complaintImageUrl = trim((string) ($context['complaint_image_url'] ?? ''));
              if ($complaintImageUrl !== ''):
              ?>
              <h3 class="h6 mb-2 mt-4">Complaint Image</h3>
              <?php
              $complaintImageAlt = 'Patient complaint image for this consultation';
              $complaintImageClass = 'vc-complaint-image';
              require __DIR__ . '/../../partials/shared/complaint_image.php';
              endif;
              ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- ── Pre-call checklist (visible while no frame mounted) -->
          <div class="vc-side-panel" id="vc-precall-checklist">
            <div class="vc-side-panel__header vc-side-panel__header--teal">
              <i class="bi bi-check2-square me-2"></i>
              Before you join
            </div>
            <div class="vc-side-panel__body">
              <ul class="vc-precall-list mb-0">
                <li>
                  <i class="bi bi-check-circle-fill text-success"></i>
                  <div>
                    <strong>Camera permission</strong>
                    <span>Your browser will ask for camera and microphone access the first time you join.</span>
                  </div>
                </li>
                <li>
                  <i class="bi bi-check-circle-fill text-success"></i>
                  <div>
                    <strong>Headphones recommended</strong>
                    <span>Using headphones improves audio and helps avoid echoes during the call.</span>
                  </div>
                </li>
                <li>
                  <i class="bi bi-check-circle-fill text-success"></i>
                  <div>
                    <strong>Secure connection</strong>
                    <span>MBPHA TeleHealth joins you through a private Daily room tied to this appointment only.</span>
                  </div>
                </li>
              </ul>
            </div>
          </div>

          <!-- ── Connection status (visible AFTER mount) -->
          <div class="vc-side-panel d-none" id="vc-call-status-panel">
            <div class="vc-side-panel__header vc-side-panel__header--primary">
              <i class="bi bi-broadcast-pin me-2"></i>
              Connection status
            </div>
            <div class="vc-side-panel__body">
              <dl class="row g-2 mb-0 align-items-center small">
                <dt class="col-5 text-muted mb-0">Room state</dt>
                <dd class="col-7 mb-0">
                  <span id="vc-call-status-state" class="ux-badge ux-badge--neutral">Connecting…</span>
                </dd>
                <dt class="col-5 text-muted mb-0">Participants</dt>
                <dd class="col-7 mb-0"><span id="vc-call-status-participants">1</span></dd>
                <dt class="col-5 text-muted mb-0">Microphone</dt>
                <dd class="col-7 mb-0"><span id="vc-call-status-mic" class="text-muted">Checking…</span></dd>
                <dt class="col-5 text-muted mb-0">Camera</dt>
                <dd class="col-7 mb-0"><span id="vc-call-status-camera" class="text-muted">Checking…</span></dd>
              </dl>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php
/* ──────────────────────────────────────────────────────────────────
 * Inline page-specific scripts:
 *   1.  Daily CDN prebuilt iframe loader (daily-js).
 *       NEVER ships the Daily API key or any secret.
 *   2.  Application wiring script: fetch credentials -> mount frame
 *       -> wire error handlers -> show Bootstrap alerts.
 * ────────────────────────────────────────────────────────────────── */
?>

<?= \App\Helpers\Cdn::script(\App\Helpers\Cdn::DAILY_JS, \App\Helpers\Cdn::DAILY_JS_INTEGRITY, [
        'id' => 'vc-daily-cdn',
        'onerror' => "(function(){window.__dailyJsLoadFailed=1;var e=new Event('daily-js-load-failed',{bubbles:true});document.dispatchEvent(e);})()",
]) ?>

<?php
$roomScriptAssetEsc   = \App\Helpers\Helper::escape($roomScriptAsset);
$consultationIdEsc    = \App\Helpers\Helper::escape((string) $consultationId);
$joinTokenEndpointEsc = \App\Helpers\Helper::escape($joinTokenEndpoint);
$joinCsrfTokenEsc     = \App\Helpers\Helper::escape($joinCsrfToken);
$viewerRoleEsc        = \App\Helpers\Helper::escape($viewerRole);
$clinicalScriptEsc    = \App\Helpers\Helper::escape($clinicalScriptAsset);
$pageScript = <<<PAGE_SCRIPT
<script id="vc-room-wiring"
        data-consultation-room="{$consultationIdEsc}"
        data-join-endpoint="{$joinTokenEndpointEsc}"
        data-csrf-token="{$joinCsrfTokenEsc}"
        data-viewer-role="{$viewerRoleEsc}"
        src="{$roomScriptAssetEsc}"
        defer></script>
PAGE_SCRIPT;
echo $pageScript;

if ($isDoctorViewer) {
    echo '<script src="' . $clinicalScriptEsc . '" defer></script>';
}
?>
