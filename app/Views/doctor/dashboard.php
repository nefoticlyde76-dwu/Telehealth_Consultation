<?php
$showRightbar = true;
$stats = is_array($stats ?? null) ? $stats : [];
$charts = is_array($charts ?? null) ? $charts : [];
$weeklyChart = $charts['weekly_requests'] ?? null;
$availabilityChart = $charts['availability'] ?? null;
$statusChart = $charts['status_distribution'] ?? null;
$upcomingApprovedAppointments = is_array($upcomingApprovedAppointments ?? null) ? $upcomingApprovedAppointments : [];
$todaySummary = is_array($todaySummary ?? null) ? $todaySummary : [];
?>

<?php require __DIR__ . '/../partials/shared/alerts.php'; ?>

<?php
$doctorStats = ['Today' => 0, 'Approved' => 0, 'Pending' => 0, 'Completed' => 0];
foreach ($stats as $statItem) {
    $lblLower = strtolower(trim((string) ($statItem['label'] ?? '')));
    $valInt = (int) ($statItem['value'] ?? 0);
    if (str_contains($lblLower, 'today')) { $doctorStats['Today'] = $valInt; }
    elseif (str_contains($lblLower, 'approved') || str_contains($lblLower, 'upcoming')) { $doctorStats['Approved'] = $valInt; }
    elseif (str_contains($lblLower, 'pending')) { $doctorStats['Pending'] = $valInt; }
    elseif (str_contains($lblLower, 'completed')) { $doctorStats['Completed'] = $valInt; }
}
$nowDt = new DateTimeImmutable();
$doctorToday = $nowDt->format('l, d F Y');
$doctorCurrentTime = $nowDt->format('g:i A');
$bookedToday = (int) ($todaySummary['booked_today_slots'] ?? 0);
$openToday = (int) ($todaySummary['available_today_slots'] ?? 0);
$shiftStatus = 'On Shift';
if ($bookedToday > 0 && $openToday === 0) { $shiftStatus = 'Full Today'; }
elseif ($bookedToday === 0 && $openToday > 0) { $shiftStatus = 'Accepting Bookings'; }
elseif ($bookedToday === 0 && $openToday === 0) { $shiftStatus = 'No Availability Today'; }
?>

<div class="ux-welcome ux-welcome--doctor">
  <span class="ux-welcome__corner-tick ux-welcome__corner-tick--tr"></span>
  <span class="ux-welcome__corner-tick ux-welcome__corner-tick--br"></span>
  <span class="ux-welcome__sweep"></span>
  <span class="ux-welcome__sweep ux-welcome__sweep--b"></span>

  <div class="ux-welcome__grid">
    <div>
      <ol class="ux-welcome__breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/') ?>">Home</a></li>
        <li class="active">Doctor Dashboard</li>
      </ol>
      <span class="ux-welcome__eyebrow">Clinical Workspace · Today's Schedule</span>
      <h1 class="ux-welcome__title">Doctor Dashboard</h1>
      <p class="ux-welcome__description">Your clinical workspace. Prepare for today's appointments, maintain your schedule, and review incoming consultation requests.</p>

      <div class="ux-welcome__meta-pill-row">
        <span class="ux-welcome__meta-pill">
          <i class="bi bi-calendar2-week"></i>
          <span><?= \App\Helpers\Helper::escape($doctorToday) ?></span>
        </span>
        <span class="ux-welcome__meta-pill">
          <i class="bi bi-clock"></i>
          <span><?= \App\Helpers\Helper::escape($doctorCurrentTime) ?></span>
        </span>
        <span class="ux-welcome__meta-pill">
          <i class="bi bi-clipboard2-pulse"></i>
          <span><?= \App\Helpers\Helper::escape((string) $bookedToday) ?> booked today · <?= \App\Helpers\Helper::escape((string) $openToday) ?> open slot<?= $openToday === 1 ? '' : 's' ?></span>
        </span>
        <span class="ux-welcome__meta-pill">
          <i class="bi bi-person-check"></i>
          <span><?= \App\Helpers\Helper::escape((string) $doctorStats['Approved']) ?> upcoming approved</span>
        </span>
        <span class="ux-welcome__meta-pill">
          <i class="bi bi-activity"></i>
          <span><?= \App\Helpers\Helper::escape($shiftStatus) ?></span>
        </span>
      </div>
    </div>

    <div class="ux-welcome__actions">
      <a href="<?= \App\Helpers\Helper::url('/doctor/availability') ?>" class="btn-primary-xl">
        <i class="bi bi-calendar-week me-2"></i>Manage Availability
      </a>
      <a href="<?= \App\Helpers\Helper::url('/doctor/consultations') ?>" class="ux-welcome__cta-secondary">
        <i class="bi bi-calendar2-check"></i>
        <span>View Consultations</span>
      </a>
    </div>
  </div>
</div>

<section class="mb-4">
  <div class="row g-4">
    <?php foreach ($stats as $stat): ?>
      <?php $statValue = (string) ($stat['value'] ?? '0'); ?>
      <?php $iconTone = ($stat['tone'] ?? '') === 'success' ? 'ux-stat__icon--mint' : 'ux-stat__icon--surface'; ?>
      <div class="col-sm-6 col-xl-3">
        <div class="ux-stat h-100">
          <div class="d-flex justify-content-between align-items-start mb-3 gap-3">
            <div>
              <span class="ux-stat__label"><?= \App\Helpers\Helper::escape((string) ($stat['label'] ?? '')) ?></span>
              <h3 class="ux-stat__value" <?= is_numeric($statValue) ? 'data-counter="' . \App\Helpers\Helper::escape($statValue) . '"' : '' ?>>
                <?= \App\Helpers\Helper::escape($statValue) ?>
              </h3>
            </div>
            <span class="ux-stat__icon <?= $iconTone ?>"><i class="bi <?= \App\Helpers\Helper::escape((string) ($stat['icon'] ?? 'bi-graph-up')) ?>"></i></span>
          </div>
          <p class="text-muted mb-0 small"><?= \App\Helpers\Helper::escape((string) ($stat['description'] ?? '')) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="mb-4">
  <div class="row g-4">
    <div class="col-xl-8">
      <div class="ux-card h-100">
        <div class="ux-card__header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <div>
            <h3 class="h5 mb-1">Today's Schedule</h3>
            <p class="text-muted mb-0 small">Approved consultations booked for today · <?= \App\Helpers\Helper::escape((string) ((int) ($todaySummary['booked_today_slots'] ?? 0))) ?> booked · <?= \App\Helpers\Helper::escape((string) ((int) ($todaySummary['available_today_slots'] ?? 0))) ?> open slots.</p>
          </div>
          <a href="<?= \App\Helpers\Helper::url('/doctor/consultations') ?>" class="btn btn-outline-primary btn-sm">View Consultations</a>
        </div>
        <div class="ux-card__body">
          <?php if ($upcomingApprovedAppointments === []): ?>
            <div class="ux-empty py-5 text-center">
              <div class="ux-empty__icon mx-auto mb-3"><i class="bi bi-calendar2-x"></i></div>
              <h4 class="h6 mb-2">No approved appointments scheduled today.</h4>
              <p class="text-muted mb-4">Enjoy a clear schedule, or manage availability to add new capacity.</p>
              <a href="<?= \App\Helpers\Helper::url('/doctor/availability') ?>" class="btn btn-primary btn-sm">Create Availability Slot</a>
            </div>
          <?php else: ?>
            <div class="ux-table-wrapper">
              <table class="ux-table align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Patient</th>
                    <th scope="col">Date</th>
                    <th scope="col">Time</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $todayDate = date('Y-m-d');
                  $todaysAppointments = array_filter($upcomingApprovedAppointments, function ($apt) use ($todayDate) {
                      return ($apt['consultation_date'] ?? '') === $todayDate;
                  });
                  $displayAppointments = !empty($todaysAppointments) ? $todaysAppointments : array_slice($upcomingApprovedAppointments, 0, 5);
                  ?>
                  <?php foreach ($displayAppointments as $appointment): ?>
                    <?php
                    $dateLabel = \App\Helpers\Helper::formatDate((string) ($appointment['consultation_date'] ?? ''), 'd M Y', 'Not scheduled');
                    $timeLabel = substr((string) ($appointment['start_time'] ?? ''), 0, 5) . ' - ' . substr((string) ($appointment['end_time'] ?? ''), 0, 5);
                    ?>
                    <tr>
                      <td><strong><?= \App\Helpers\Helper::escape((string) ($appointment['patient_name'] ?? 'Patient')) ?></strong></td>
                      <td><?= \App\Helpers\Helper::escape((string) $dateLabel) ?></td>
                      <td class="text-muted small"><?= \App\Helpers\Helper::escape((string) $timeLabel) ?></td>
                      <td><span class="ux-badge ux-badge--approved"><i class="bi bi-check-circle-fill me-1"></i>Approved</span></td>
                      <td class="text-end">
                        <a href="<?= \App\Helpers\Helper::url('/doctor/consultations') ?>" class="btn btn-outline-primary btn-sm">View</a>
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

    <div class="col-xl-4">
      <div class="ux-card h-100">
        <div class="ux-card__header mb-3">
          <h3 class="h5 mb-1">Availability Coverage</h3>
          <p class="text-muted mb-0 small">Slots for the next 7 days</p>
        </div>
        <div class="ux-card__body">
          <canvas height="280" data-chart="<?= $availabilityChart !== null ? \App\Helpers\Helper::escape((string) json_encode($availabilityChart, JSON_UNESCAPED_SLASHES)) : '' ?>"></canvas>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="mb-4">
  <div class="row g-4">
    <div class="col-xl-7">
      <div class="ux-card h-100">
        <div class="ux-card__header mb-3">
          <h3 class="h5 mb-1">Weekly Consultation Requests</h3>
          <p class="text-muted mb-0 small">Assigned request volume this week</p>
        </div>
        <div class="ux-card__body">
          <canvas height="280" data-chart="<?= $weeklyChart !== null ? \App\Helpers\Helper::escape((string) json_encode($weeklyChart, JSON_UNESCAPED_SLASHES)) : '' ?>"></canvas>
        </div>
      </div>
    </div>

    <div class="col-xl-5">
      <div class="ux-card h-100">
        <div class="ux-card__header mb-3">
          <h3 class="h5 mb-1">Request Status Mix</h3>
          <p class="text-muted mb-0 small">Distribution of all request statuses</p>
        </div>
        <div class="ux-card__body">
          <canvas height="280" data-chart="<?= $statusChart !== null ? \App\Helpers\Helper::escape((string) json_encode($statusChart, JSON_UNESCAPED_SLASHES)) : '' ?>"></canvas>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="mb-4">
  <div class="ux-card">
    <div class="ux-card__header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
      <div>
        <h3 class="h5 mb-1">Upcoming Approved Consultations</h3>
        <p class="text-muted mb-0 small">From today forward</p>
      </div>
      <a href="<?= \App\Helpers\Helper::url('/doctor/consultations') ?>" class="btn btn-outline-primary btn-sm">Open Schedule</a>
    </div>
    <div class="ux-card__body">
      <?php if ($upcomingApprovedAppointments === []): ?>
        <div class="ux-empty py-5 text-center">
          <div class="ux-empty__icon mx-auto mb-3"><i class="bi bi-calendar2-x"></i></div>
          <h4 class="h6 mb-2">No upcoming approved consultations.</h4>
          <p class="text-muted mb-0">Approved consultations will appear here once requests are reviewed.</p>
        </div>
      <?php else: ?>
        <?php $nextFive = array_slice($upcomingApprovedAppointments, 0, 5); ?>
        <div class="ux-table-wrapper">
          <table class="ux-table align-middle mb-0">
            <thead>
              <tr>
                <th scope="col">Patient</th>
                <th scope="col">Date</th>
                <th scope="col">Time</th>
                <th scope="col">Status</th>
                <th scope="col" class="text-end">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($nextFive as $appointment): ?>
                <?php
                $dateLabel = \App\Helpers\Helper::formatDate((string) ($appointment['consultation_date'] ?? ''), 'd M Y', 'Not scheduled');
                $timeLabel = substr((string) ($appointment['start_time'] ?? ''), 0, 5) . ' - ' . substr((string) ($appointment['end_time'] ?? ''), 0, 5);
                ?>
                <tr>
                  <td><strong><?= \App\Helpers\Helper::escape((string) ($appointment['patient_name'] ?? 'Patient')) ?></strong></td>
                  <td><?= \App\Helpers\Helper::escape((string) $dateLabel) ?></td>
                  <td class="text-muted small"><?= \App\Helpers\Helper::escape((string) $timeLabel) ?></td>
                  <td><span class="ux-badge ux-badge--approved"><i class="bi bi-check-circle-fill me-1"></i>Approved</span></td>
                  <td class="text-end">
                    <a href="<?= \App\Helpers\Helper::url('/doctor/consultations') ?>" class="btn btn-outline-primary btn-sm">View</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>
