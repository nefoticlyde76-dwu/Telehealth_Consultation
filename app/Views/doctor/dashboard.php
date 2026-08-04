<?php
$stats = is_array($stats ?? null) ? $stats : [];
$charts = is_array($charts ?? null) ? $charts : [];
$weeklyChart = $charts['weekly_requests'] ?? null;
$availabilityChart = $charts['availability'] ?? null;
$statusChart = $charts['status_distribution'] ?? null;
$upcomingApprovedAppointments = is_array($upcomingApprovedAppointments ?? null) ? $upcomingApprovedAppointments : [];
$todaySummary = is_array($todaySummary ?? null) ? $todaySummary : [];
?>

<?php require __DIR__ . '/../partials/shared/alerts.php'; ?>

<section class="dashboard-banner mb-4" data-aos="fade-up">
  <div class="row g-4 align-items-center">
    <div class="col-lg-8">
      <span class="section-badge mb-3">
        <i class="bi bi-clipboard2-pulse"></i>
        Clinician Workspace
      </span>
      <h2 class="h3 mb-3">Welcome back, <?= \App\Helpers\Helper::escape($user->full_name ?? 'Doctor') ?>.</h2>
      <p class="text-muted mb-0">Review schedule coverage, monitor approved consultations, and keep availability ready for patient bookings.</p>
      <div class="dashboard-banner-actions">
        <a href="<?= \App\Helpers\Helper::url('/doctor/consultations') ?>" class="btn btn-primary rounded-pill px-4">
          <i class="bi bi-calendar2-check me-2"></i>
          View Consultations
        </a>
        <a href="<?= \App\Helpers\Helper::url('/doctor/availability') ?>" class="btn btn-outline-primary rounded-pill px-4">
          <i class="bi bi-calendar-week me-2"></i>
          Manage Availability
        </a>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="dashboard-spotlight-card">
        <span class="dashboard-spotlight-label">Today's schedule</span>
        <strong class="d-block mb-2"><?= \App\Helpers\Helper::escape((string) ((int) ($todaySummary['total_today_slots'] ?? 0))) ?> appointments</strong>
        <p class="text-muted small mb-0"><?= \App\Helpers\Helper::escape((string) ((int) ($todaySummary['available_today_slots'] ?? 0))) ?> open · <?= \App\Helpers\Helper::escape((string) ((int) ($todaySummary['booked_today_slots'] ?? 0))) ?> booked</p>
      </div>
    </div>
  </div>
</section>

<section class="mb-4">
  <div class="row g-4">
    <?php foreach ($stats as $stat): ?>
      <?php $statValue = (string) ($stat['value'] ?? '0'); ?>
      <div class="col-sm-6 col-xl-3" data-aos="fade-up">
        <div class="stats-card h-100">
          <div class="d-flex justify-content-between align-items-start mb-3 gap-3">
            <div>
              <span class="stats-label"><?= \App\Helpers\Helper::escape((string) ($stat['label'] ?? '')) ?></span>
              <h3 class="stats-value" <?= is_numeric($statValue) ? 'data-counter="' . \App\Helpers\Helper::escape($statValue) . '"' : '' ?>>
                <?= \App\Helpers\Helper::escape($statValue) ?>
              </h3>
            </div>
            <span class="stats-icon"><i class="bi <?= \App\Helpers\Helper::escape((string) ($stat['icon'] ?? 'bi-graph-up')) ?>"></i></span>
          </div>
          <span class="stats-pill">Clinician overview</span>
          <p class="text-muted mb-0 small"><?= \App\Helpers\Helper::escape((string) ($stat['description'] ?? '')) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="mb-4">
  <div class="row g-4">
    <div class="col-xl-8">
      <div class="card border-0 shadow-sm rounded-4 dashboard-widget-shell h-100" data-aos="fade-up">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
              <span class="dashboard-info-label">Schedule Analytics</span>
              <h3 class="h5 mb-1">Availability coverage</h3>
              <p class="text-muted mb-0">Track available vs booked slots across the next seven days.</p>
            </div>
            <a href="<?= \App\Helpers\Helper::url('/doctor/availability') ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">Open</a>
          </div>
          <div class="dashboard-chart-shell dashboard-chart-shell--lg" data-chart-shell>
            <canvas height="320" data-chart="<?= $availabilityChart !== null ? \App\Helpers\Helper::escape((string) json_encode($availabilityChart, JSON_UNESCAPED_SLASHES)) : '' ?>"></canvas>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="card border-0 shadow-sm rounded-4 dashboard-widget-shell h-100" data-aos="fade-up">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
              <span class="dashboard-info-label">Workflow Trend</span>
              <h3 class="h5 mb-1">Weekly requests</h3>
              <p class="text-muted mb-0">Request volume assigned to your clinician schedule.</p>
            </div>
          </div>
          <div class="dashboard-chart-shell" data-chart-shell>
            <canvas height="240" data-chart="<?= $weeklyChart !== null ? \App\Helpers\Helper::escape((string) json_encode($weeklyChart, JSON_UNESCAPED_SLASHES)) : '' ?>"></canvas>
          </div>
          <div class="dashboard-chart-shell mt-4" data-chart-shell>
            <canvas height="240" data-chart="<?= $statusChart !== null ? \App\Helpers\Helper::escape((string) json_encode($statusChart, JSON_UNESCAPED_SLASHES)) : '' ?>"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="card border-0 shadow-sm rounded-4 dashboard-widget-shell" data-aos="fade-up">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
          <span class="dashboard-info-label">Upcoming Consultations</span>
          <h3 class="h5 mb-1">Approved appointments</h3>
          <p class="text-muted mb-0">Approved consultations scheduled from today onward.</p>
        </div>
        <a href="<?= \App\Helpers\Helper::url('/doctor/consultations') ?>" class="btn btn-outline-primary rounded-pill px-4">Open</a>
      </div>

      <div class="table-responsive">
        <table class="table admin-user-table align-middle mb-0">
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
            <?php if ($upcomingApprovedAppointments === []): ?>
              <tr>
                <td colspan="5">
                  <div class="admin-table-empty text-center py-5">
                    <div class="empty-state-icon mx-auto mb-3"><i class="bi bi-calendar2-x"></i></div>
                    <h4 class="h5 mb-2">No approved consultations yet</h4>
                    <p class="text-muted mb-0">Approved consultation appointments will appear here after administrator review.</p>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($upcomingApprovedAppointments as $appointment): ?>
                <?php
                $dateLabel = \App\Helpers\Helper::formatDate((string) ($appointment['consultation_date'] ?? ''), 'd M Y', 'Not scheduled');
                $timeLabel = substr((string) ($appointment['start_time'] ?? ''), 0, 5) . ' - ' . substr((string) ($appointment['end_time'] ?? ''), 0, 5);
                ?>
                <tr>
                  <td><strong><?= \App\Helpers\Helper::escape((string) ($appointment['patient_name'] ?? 'Patient')) ?></strong></td>
                  <td><?= \App\Helpers\Helper::escape((string) $dateLabel) ?></td>
                  <td class="text-muted small"><?= \App\Helpers\Helper::escape((string) $timeLabel) ?></td>
                  <td><span class="badge badge-soft-success rounded-pill px-3 py-2">Approved</span></td>
                  <td class="text-end">
                    <a href="<?= \App\Helpers\Helper::url('/doctor/consultations') ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">View</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</section>
