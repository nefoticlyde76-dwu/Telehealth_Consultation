<?php
$showRightbar = true;
$stats = is_array($stats ?? null) ? $stats : [];
$bookingSummary = is_array($bookingSummary ?? null) ? $bookingSummary : [];
$slotPreview = is_array($slotPreview ?? null) ? $slotPreview : [];
$recentRequests = is_array($recentRequests ?? null) ? $recentRequests : [];
$charts = is_array($charts ?? null) ? $charts : [];
$statusChart = $charts['status_distribution'] ?? null;
$monthlyChart = $charts['monthly_requests'] ?? null;
$latestRequest = is_array($bookingSummary['latest_request'] ?? null) ? $bookingSummary['latest_request'] : null;
require_once __DIR__ . '/../partials/shared/status_helper.php';

$totalRequests = (int) ($bookingSummary['total_requests'] ?? ($stats[0]['value'] ?? 0));
$pendingCount = (int) ($bookingSummary['pending_count'] ?? 0);
$approvedCount = (int) ($bookingSummary['approved_count'] ?? 0);
$completedCount = (int) ($bookingSummary['completed_count'] ?? 0);
if ($pendingCount === 0 && $approvedCount === 0 && $completedCount === 0) {
    foreach ($stats as $s) {
        $lbl = strtolower((string) ($s['label'] ?? ''));
        $val = (int) ($s['value'] ?? 0);
        if (str_contains($lbl, 'pending')) { $pendingCount = $val; }
        elseif (str_contains($lbl, 'approved')) { $approvedCount = $val; }
        elseif (str_contains($lbl, 'completed')) { $completedCount = $val; }
    }
}
$latestStatus = (string) ($latestRequest['status'] ?? '');
?>

<?php require __DIR__ . '/../partials/shared/alerts.php'; ?>

<?php
$patientFullName = (string) ($user->full_name ?? 'Patient');
$patientFirstName = trim(explode(' ', $patientFullName, 2)[0] ?? $patientFullName);
$patientToday = (new DateTimeImmutable())->format('l, d F Y');

$patientStatusText = 'Ready to book a consultation';
$patientStatusIcon = 'bi-calendar2-heart';
if ($latestRequest !== null) {
    if ($latestStatus === 'Approved') {
        $patientStatusText = 'Your next consultation is approved';
        $patientStatusIcon = 'bi-calendar2-check';
    } elseif ($latestStatus === 'Pending') {
        $patientStatusText = 'Your request is awaiting administrator review';
        $patientStatusIcon = 'bi-clock';
    } elseif ($latestStatus === 'Completed') {
        $patientStatusText = 'Book a follow-up consultation';
        $patientStatusIcon = 'bi-calendar2-plus';
    }
}
?>

<div class="ux-welcome ux-welcome--patient">
  <div class="ux-welcome__grid">
    <div>
      <ol class="ux-welcome__breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/') ?>">Home</a></li>
        <li class="active">Patient Dashboard</li>
      </ol>
      <span class="ux-welcome__eyebrow">MBPHA TeleHealth</span>
      <h1 class="ux-welcome__title">Hello, <?= \App\Helpers\Helper::escape($patientFirstName) ?></h1>
      <p class="ux-welcome__description">Book a consultation, check request status, and join your appointment when it is approved.</p>

      <div class="ux-welcome__meta-pill-row">
        <span class="ux-welcome__meta-pill">
          <i class="bi <?= \App\Helpers\Helper::escape($patientStatusIcon) ?>"></i>
          <span><?= \App\Helpers\Helper::escape($patientStatusText) ?></span>
        </span>
        <?php if ($latestRequest !== null && $latestStatus === 'Approved'): ?>
          <span class="ux-welcome__meta-pill">
            <i class="bi bi-person-badge"></i>
            <span>Next: <?= \App\Helpers\Helper::escape((string) ($latestRequest['doctor_name'] ?? 'Doctor')) ?> · <?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($latestRequest['consultation_date'] ?? ''), 'd M', 'TBD')) ?></span>
          </span>
        <?php endif; ?>
      </div>
    </div>

    <div class="ux-welcome__actions">
      <a href="<?= \App\Helpers\Helper::url('/patient/available-slots') ?>" class="btn-primary-xl">
        <i class="bi bi-calendar2-plus me-2"></i>Book Consultation
      </a>
      <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests') ?>" class="ux-welcome__cta-secondary">
        <i class="bi bi-journal-text"></i>
        <span>My Appointments</span>
      </a>
    </div>
  </div>
</div>

<section class="mb-4">
  <div class="row g-4">
    <?php
    $patientKpis = [
        [
            'label' => 'Total Requests',
            'value' => $totalRequests,
            'description' => 'All consultations submitted',
            'icon' => 'bi-journal-text',
            'tone' => 'surface',
        ],
        [
            'label' => 'Pending',
            'value' => $pendingCount,
            'description' => 'Awaiting administrator review',
            'icon' => 'bi-hourglass-split',
            'tone' => 'amber',
        ],
        [
            'label' => 'Approved',
            'value' => $approvedCount,
            'description' => 'Confirmed consultations',
            'icon' => 'bi-calendar2-check',
            'tone' => 'mint',
        ],
        [
            'label' => 'Completed',
            'value' => $completedCount,
            'description' => 'Finished consultation sessions',
            'icon' => 'bi-journal-medical',
            'tone' => 'navy',
        ],
    ];
    $patientKpiToneMap = [
        'surface' => 'ux-stat__icon--surface',
        'mint'    => 'ux-stat__icon--mint',
        'amber'   => 'ux-stat__icon--amber',
        'navy'    => 'ux-stat__icon--navy',
    ];
    ?>
    <?php foreach ($patientKpis as $kpi): ?>
      <?php $kpiIconTone = $patientKpiToneMap[$kpi['tone']] ?? 'ux-stat__icon--surface'; ?>
      <div class="col-sm-6 col-xl-3">
        <div class="ux-stat h-100">
          <div class="d-flex justify-content-between align-items-start mb-3 gap-3">
            <div>
              <span class="ux-stat__label"><?= \App\Helpers\Helper::escape((string) $kpi['label']) ?></span>
              <h3 class="ux-stat__value" data-counter="<?= \App\Helpers\Helper::escape((string) $kpi['value']) ?>">
                <?= \App\Helpers\Helper::escape((string) $kpi['value']) ?>
              </h3>
            </div>
            <span class="ux-stat__icon <?= $kpiIconTone ?>"><i class="bi <?= \App\Helpers\Helper::escape((string) $kpi['icon']) ?>"></i></span>
          </div>
          <p class="text-muted mb-0 small"><?= \App\Helpers\Helper::escape((string) $kpi['description']) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="mb-4">
  <div class="row g-4">
    <div class="col-12">
      <div class="ux-card h-100">
        <div class="ux-card__header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <div>
            <h3 class="h5 mb-1">Your Next Consultation</h3>
            <p class="text-muted mb-0 small">Summary of your next appointment, request status, or suggested next action.</p>
          </div>
          <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests') ?>" class="btn btn-outline-primary btn-sm">View Appointments</a>
        </div>
        <div class="ux-card__body">

          <?php if ($latestRequest !== null && $latestStatus === 'Approved'): ?>
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
              <div>
                <h4 class="h5 mb-1"><?= \App\Helpers\Helper::escape((string) ($latestRequest['doctor_name'] ?? 'Doctor')) ?></h4>
                <p class="text-muted mb-0"><?= \App\Helpers\Helper::escape((string) ($latestRequest['specialization'] ?? 'General Practice')) ?></p>
              </div>
              <span class="ux-badge ux-badge--approved">Approved</span>
            </div>
            <div class="row g-4 mb-4">
              <div class="col-sm-6 col-md-3">
                <span class="d-block small text-muted mb-1">Date</span>
                <strong><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($latestRequest['consultation_date'] ?? ''), 'l, d M Y', 'Not scheduled')) ?></strong>
              </div>
              <div class="col-sm-6 col-md-3">
                <span class="d-block small text-muted mb-1">Time</span>
                <strong><?= \App\Helpers\Helper::escape(substr((string) ($latestRequest['start_time'] ?? ''), 0, 5)) ?> – <?= \App\Helpers\Helper::escape(substr((string) ($latestRequest['end_time'] ?? ''), 0, 5)) ?></strong>
              </div>
              <div class="col-sm-6 col-md-3">
                <span class="d-block small text-muted mb-1">Consultation</span>
                <strong><?= \App\Helpers\Helper::escape(ucwords((string) ($latestRequest['consultation_type'] ?? 'Standard'))) ?></strong>
              </div>
              <div class="col-sm-6 col-md-3">
                <span class="d-block small text-muted mb-1">Reference</span>
                <strong class="font-monospace">#TH-<?= \App\Helpers\Helper::escape((string) ((int) ($latestRequest['id'] ?? 0))) ?></strong>
              </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests/' . (string) ((int) ($latestRequest['id'] ?? 0))) ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-arrow-right me-2"></i>View Details
              </a>
              <a href="<?= \App\Helpers\Helper::url('/patient/available-slots') ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-calendar2-plus me-2"></i>Book Another
              </a>
            </div>
          <?php elseif ($latestRequest !== null && $latestStatus === 'Pending'): ?>
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
              <div>
                <h4 class="h5 mb-1"><?= \App\Helpers\Helper::escape((string) ($latestRequest['doctor_name'] ?? 'Doctor')) ?></h4>
                <p class="text-muted mb-0"><?= \App\Helpers\Helper::escape((string) ($latestRequest['specialization'] ?? 'General Practice')) ?></p>
              </div>
              <span class="ux-badge ux-badge--pending">Awaiting Administrator Review</span>
            </div>
            <div class="row g-4 mb-4">
              <div class="col-sm-6 col-md-4">
                <span class="d-block small text-muted mb-1">Requested Date</span>
                <strong><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($latestRequest['consultation_date'] ?? ''), 'l, d M Y', 'Not scheduled')) ?></strong>
              </div>
              <div class="col-sm-6 col-md-4">
                <span class="d-block small text-muted mb-1">Preferred Time</span>
                <strong><?= \App\Helpers\Helper::escape(substr((string) ($latestRequest['start_time'] ?? ''), 0, 5)) ?> – <?= \App\Helpers\Helper::escape(substr((string) ($latestRequest['end_time'] ?? ''), 0, 5)) ?></strong>
              </div>
              <div class="col-sm-12 col-md-4">
                <span class="d-block small text-muted mb-1">Reference</span>
                <strong class="font-monospace">#TH-<?= \App\Helpers\Helper::escape((string) ((int) ($latestRequest['id'] ?? 0))) ?></strong>
              </div>
            </div>
            <p class="text-muted small mb-4"><i class="bi bi-info-circle me-1"></i>You will receive a status update once the administrator reviews your request.</p>
            <div class="d-flex flex-wrap gap-2">
              <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests/' . (string) ((int) ($latestRequest['id'] ?? 0))) ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-eye me-2"></i>Track Request
              </a>
              <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests') ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-journal-text me-2"></i>All Requests
              </a>
            </div>
          <?php else: ?>
            <div class="ux-empty py-5 text-center">
              <div class="ux-empty__icon mx-auto mb-3"><i class="bi bi-calendar2-heart"></i></div>
              <h4 class="h6 mb-2">No consultation booked</h4>
              <p class="text-muted mb-4" style="max-width: 480px; margin-left: auto; margin-right: auto;">
                Book your first appointment with an MBPHA clinician and your upcoming consultation details will appear here.
              </p>
              <div class="d-flex flex-wrap justify-content-center gap-2">
                <a href="<?= \App\Helpers\Helper::url('/patient/available-slots') ?>" class="btn btn-primary btn-sm">
                  <i class="bi bi-calendar2-plus me-2"></i>Book Consultation
                </a>
                <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests') ?>" class="btn btn-outline-primary btn-sm">
                  <i class="bi bi-journal-text me-2"></i>My Appointments
                </a>
              </div>
            </div>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>
</section>

<section class="mb-4">
  <div class="row g-4">
    <div class="col-xl-7">
      <div class="ux-card h-100">
        <div class="ux-card__header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <div>
            <h3 class="h5 mb-1">Consultation History</h3>
            <p class="text-muted mb-0 small">Your most recent requests and their status</p>
          </div>
          <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests') ?>" class="btn btn-outline-primary btn-sm">View All</a>
        </div>
        <div class="ux-card__body">
          <?php if ($recentRequests === []): ?>
            <div class="ux-empty py-5 text-center">
              <div class="ux-empty__icon mx-auto mb-3"><i class="bi bi-clipboard2-x"></i></div>
              <h4 class="h6 mb-2">No consultation requests yet.</h4>
              <p class="text-muted mb-4">Book a consultation slot to start building your consultation history.</p>
              <a href="<?= \App\Helpers\Helper::url('/patient/available-slots') ?>" class="btn btn-primary btn-sm">Book Consultation</a>
            </div>
          <?php else: ?>
            <div class="ux-table-wrapper">
              <table class="ux-table align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Doctor</th>
                    <th scope="col">Date</th>
                    <th scope="col">Time</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentRequests as $request): ?>
                    <?php
                    $status = (string) ($request['status'] ?? 'Pending');
                    $badgeClass = ux_status_badge_class($status, 'ux-badge--pending');
                    $statusIcon = ux_status_icon_class($status, 'bi-dash-circle');
                    ?>
                    <tr>
                      <td>
                        <strong class="d-block"><?= \App\Helpers\Helper::escape((string) ($request['doctor_name'] ?? 'Doctor')) ?></strong>
                        <span class="text-muted small"><?= \App\Helpers\Helper::escape((string) ($request['specialization'] ?? '')) ?></span>
                      </td>
                      <td><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($request['consultation_date'] ?? ''), 'd M Y', 'Not scheduled')) ?></td>
                      <td class="text-muted small"><?= \App\Helpers\Helper::escape(substr((string) ($request['start_time'] ?? ''), 0, 5)) ?> - <?= \App\Helpers\Helper::escape(substr((string) ($request['end_time'] ?? ''), 0, 5)) ?></td>
                      <td><span class="ux-badge <?= $badgeClass ?>"><i class="bi <?= $statusIcon ?> me-1"></i><?= \App\Helpers\Helper::escape($status) ?></span></td>
                      <td class="text-end">
                        <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests/' . (string) ((int) ($request['id'] ?? 0))) ?>" class="btn btn-outline-primary btn-sm"><?= $status === 'Completed' ? 'View Record' : 'View' ?></a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-xl-5">
      <div class="ux-card h-100">
        <div class="ux-card__header mb-3">
          <h3 class="h5 mb-1">Next Available Slots</h3>
          <p class="text-muted mb-0 small">Fastest ways to book care today <span class="text-body">(<?= \App\Helpers\Helper::escape((string) count($slotPreview)) ?> available)</span></p>
        </div>
        <div class="ux-card__body">
          <?php if ($slotPreview === []): ?>
            <div class="ux-empty py-4 text-center">
              <div class="ux-empty__icon mx-auto mb-3"><i class="bi bi-calendar-x"></i></div>
              <h4 class="h6 mb-2">No slots available</h4>
              <p class="text-muted mb-0">When doctors publish availability, slots will appear here automatically.</p>
            </div>
          <?php else: ?>
            <?php $previewSlots = array_slice($slotPreview, 0, 5); ?>
            <?php $slotIdx = 0; ?>
            <?php $slotTotal = count($previewSlots); ?>
            <?php foreach ($previewSlots as $slot): ?>
              <?php $slotIdx++; ?>
              <div class="ux-slot-row">
                <div class="flex-shrink-0">
                  <strong class="d-block"><?= \App\Helpers\Helper::escape((string) ($slot['full_name'] ?? 'Doctor')) ?></strong>
                  <span class="small text-muted"><?= \App\Helpers\Helper::escape((string) ($slot['specialization'] ?? 'General Practice')) ?></span>
                </div>
                <div class="flex-grow-1 text-center">
                  <strong class="d-block"><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($slot['consultation_date'] ?? ''), 'd M', '')) ?></strong>
                  <span class="small text-muted"><?= \App\Helpers\Helper::escape(substr((string) ($slot['start_time'] ?? ''), 0, 5)) ?></span>
                </div>
                <div class="flex-shrink-0">
                  <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests/book/' . (string) ((int) ($slot['id'] ?? 0))) ?>" class="btn btn-outline-primary btn-sm">Book</a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>
