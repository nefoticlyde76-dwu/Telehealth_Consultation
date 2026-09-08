<?php
use App\Helpers\Helper;
use App\Helpers\Status;

$stats = is_array($stats ?? null) ? $stats : [];
$charts = is_array($charts ?? null) ? $charts : [];
$availabilityChart = $charts['availability'] ?? null;
$upcomingApprovedAppointments = is_array($upcomingApprovedAppointments ?? null) ? $upcomingApprovedAppointments : [];
$recentCompletedConsultations = is_array($recentCompletedConsultations ?? null) ? $recentCompletedConsultations : [];
$recentNotifications = is_array($recentNotifications ?? null) ? $recentNotifications : [];
$todaySummary = is_array($todaySummary ?? null) ? $todaySummary : [];
$headerNotifications = is_array($headerNotifications ?? null) ? $headerNotifications : [];
require_once __DIR__ . '/../partials/shared/status_helper.php';

$bookedToday = (int) ($todaySummary['booked_today_slots'] ?? 0);
$openToday = (int) ($todaySummary['available_today_slots'] ?? 0);
$unreadCount = (int) ($headerNotifications['unread_count'] ?? 0);
$todayDate = date('Y-m-d');
$todaysAppointments = array_values(array_filter($upcomingApprovedAppointments, static function ($apt) use ($todayDate) {
    return ($apt['consultation_date'] ?? '') === $todayDate;
}));

$pageHeaderTitle = 'Doctor Dashboard';
$pageHeaderSubtitle = "See today's workload, upcoming consultations, and what needs your attention.";
$pageHeaderBreadcrumbs = [];
ob_start();
?>
<a href="<?= Helper::url('/doctor/availability') ?>" class="btn btn-primary btn-sm">
  <i class="bi bi-calendar-week me-1" aria-hidden="true"></i>Manage Availability
</a>
<?php
$pageHeaderActions = ob_get_clean();
$summaryStats = $stats;

$notificationItems = [];
foreach ($recentNotifications as $item) {
    $notificationItems[] = [
        'title' => (string) ($item['title'] ?? 'Notification'),
        'description' => (string) ($item['message'] ?? ''),
        'meta' => (string) ($item['relative_time'] ?? ''),
        'url' => (string) ($item['open_url'] ?? '/notifications'),
        'icon' => (string) ($item['icon'] ?? 'bi-bell'),
        'unread' => !empty($item['unread']),
    ];
}

$welcomeMessage = "Here's your day at a glance. Stay on top of your consultations and keep making a difference.";
$welcomePills = [
    [
        'icon' => 'bi-calendar2-check',
        'label' => $bookedToday === 1 ? '1 booked today' : $bookedToday . ' booked today',
    ],
    [
        'icon' => 'bi-calendar2-week',
        'label' => $openToday === 1 ? '1 open slot' : $openToday . ' open slots',
    ],
];
$welcomeIcon = 'bi-heart-pulse';
?>

<?php require __DIR__ . '/../partials/shared/alerts.php'; ?>
<?php require __DIR__ . '/../partials/dashboard/page_header.php'; ?>
<?php require __DIR__ . '/../partials/dashboard/welcome_banner.php'; ?>
<?php require __DIR__ . '/../partials/dashboard/summary_stats.php'; ?>

<section class="mb-4">
  <div class="row g-4">
    <div class="col-xl-8">
      <div class="ux-card ux-data-card h-100">
        <div class="ux-card__header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <?php
          $sectionTitle = "Today's Schedule";
          $sectionSubtitle = (string) $bookedToday . ' booked · ' . (string) $openToday . ' open slots';
          $sectionIcon = 'bi-calendar2-week';
          $sectionTone = 'pending';
          require __DIR__ . '/../partials/dashboard/section_heading.php';
          ?>
          <a href="<?= Helper::url('/doctor/consultations?date=today') ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-calendar2-check me-1" aria-hidden="true"></i>View Today
          </a>
        </div>
        <div class="ux-card__body">
          <?php if ($todaysAppointments === []): ?>
            <?php
            $emptyIcon = 'bi-calendar2-x';
            $emptyTitle = 'No consultations scheduled today';
            $emptyText = 'Approved consultations for today will appear here.';
            $emptyActions = '<a href="' . Helper::url('/doctor/availability') . '" class="btn btn-primary btn-sm">Create Availability Slot</a>';
            $emptyCompact = false;
            require __DIR__ . '/../partials/shared/empty_state.php';
            ?>
          <?php else: ?>
            <div class="ux-table-wrapper">
              <table class="ux-table align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Time</th>
                    <th scope="col">Patient</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach (array_slice($todaysAppointments, 0, 5) as $appointment): ?>
                    <tr>
                      <td class="text-muted small"><?= Helper::escape(substr((string) ($appointment['start_time'] ?? ''), 0, 5)) ?> – <?= Helper::escape(substr((string) ($appointment['end_time'] ?? ''), 0, 5)) ?></td>
                      <td>
                        <?php
                        $personName = (string) ($appointment['patient_name'] ?? 'Patient');
                        $personPhoto = $appointment['patient_photo_path'] ?? null;
                        $personMeta = '';
                        $personSize = 'sm';
                        require __DIR__ . '/../partials/shared/person_row.php';
                        ?>
                      </td>
                      <td><?= ux_status_badge(Status::APPROVED) ?></td>
                      <td class="text-end">
                        <a href="<?= Helper::url('/doctor/consultations/' . (int) ($appointment['id'] ?? 0)) ?>" class="btn btn-outline-primary btn-sm">
                          <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Open
                        </a>
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
      <div class="ux-card ux-data-card h-100">
        <div class="ux-card__header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <?php
          $sectionTitle = 'Notifications';
          $sectionSubtitle = 'Recent consultation updates.';
          $sectionIcon = 'bi-bell';
          $sectionTone = 'info';
          require __DIR__ . '/../partials/dashboard/section_heading.php';
          ?>
          <a href="<?= Helper::url('/notifications') ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-right me-1" aria-hidden="true"></i>View All
          </a>
        </div>
        <div class="ux-card__body">
          <?php
          $activityEmptyTitle = "You're all caught up";
          $activityEmptyText = "You don't have any notifications yet.";
          $activityEmptyIcon = 'bi-bell';
          require __DIR__ . '/../partials/shared/notification_preview_table.php';
          ?>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="mb-4">
  <div class="row g-4">
    <div class="col-xl-7">
      <div class="ux-card ux-data-card h-100">
        <div class="ux-card__header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <?php
          $sectionTitle = 'Upcoming Consultations';
          $sectionSubtitle = 'Approved consultations from today forward.';
          $sectionIcon = 'bi-calendar2-check';
          $sectionTone = 'success';
          require __DIR__ . '/../partials/dashboard/section_heading.php';
          ?>
          <a href="<?= Helper::url(Status::filteredListUrl('/doctor/consultations', Status::APPROVED)) ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-right me-1" aria-hidden="true"></i>View Upcoming
          </a>
        </div>
        <div class="ux-card__body">
          <?php if ($upcomingApprovedAppointments === []): ?>
            <?php
            $emptyIcon = 'bi-calendar2-x';
            $emptyTitle = 'No upcoming consultations';
            $emptyText = 'Your next approved consultation will appear here.';
            $emptyActions = '';
            $emptyCompact = true;
            require __DIR__ . '/../partials/shared/empty_state.php';
            ?>
          <?php else: ?>
            <div class="ux-table-wrapper">
              <table class="ux-table align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Patient</th>
                    <th scope="col">Date</th>
                    <th scope="col">Time</th>
                    <th scope="col" class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach (array_slice($upcomingApprovedAppointments, 0, 5) as $appointment): ?>
                    <tr>
                      <td>
                        <?php
                        $personName = (string) ($appointment['patient_name'] ?? 'Patient');
                        $personPhoto = $appointment['patient_photo_path'] ?? null;
                        $personMeta = '';
                        $personSize = 'sm';
                        require __DIR__ . '/../partials/shared/person_row.php';
                        ?>
                      </td>
                      <td><?= Helper::escape(Helper::formatDate((string) ($appointment['consultation_date'] ?? ''), 'd M Y', 'Not scheduled')) ?></td>
                      <td class="text-muted small"><?= Helper::escape(substr((string) ($appointment['start_time'] ?? ''), 0, 5)) ?> – <?= Helper::escape(substr((string) ($appointment['end_time'] ?? ''), 0, 5)) ?></td>
                      <td class="text-end">
                        <a href="<?= Helper::url('/doctor/consultations/' . (int) ($appointment['id'] ?? 0)) ?>" class="btn btn-outline-primary btn-sm">
                          <i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>Open
                        </a>
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
      <div class="ux-card ux-data-card h-100">
        <div class="ux-card__header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <?php
          $sectionTitle = 'Recent Consultations';
          $sectionSubtitle = 'Recently completed visits.';
          $sectionIcon = 'bi-journal-medical';
          $sectionTone = 'navy';
          require __DIR__ . '/../partials/dashboard/section_heading.php';
          ?>
          <a href="<?= Helper::url(Status::filteredListUrl('/doctor/consultations', Status::COMPLETED)) ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-clock-history me-1" aria-hidden="true"></i>View History
          </a>
        </div>
        <div class="ux-card__body">
          <?php if ($recentCompletedConsultations === []): ?>
            <?php
            $emptyIcon = 'bi-journal-medical';
            $emptyTitle = 'No completed consultations';
            $emptyText = 'Completed consultations and records will appear here.';
            $emptyActions = '';
            $emptyCompact = true;
            require __DIR__ . '/../partials/shared/empty_state.php';
            ?>
          <?php else: ?>
            <div class="ux-table-wrapper">
              <table class="ux-table align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Patient</th>
                    <th scope="col">Date</th>
                    <th scope="col" class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentCompletedConsultations as $consultation): ?>
                    <tr>
                      <td>
                        <?php
                        $personName = (string) ($consultation['patient_name'] ?? 'Patient');
                        $personPhoto = $consultation['patient_photo_path'] ?? null;
                        $personMeta = '';
                        $personSize = 'sm';
                        require __DIR__ . '/../partials/shared/person_row.php';
                        ?>
                      </td>
                      <td><?= Helper::escape(Helper::formatDate((string) ($consultation['consultation_date'] ?? ''), 'd M Y', 'Not scheduled')) ?></td>
                      <td class="text-end">
                        <a href="<?= Helper::url('/doctor/consultations/' . (int) ($consultation['id'] ?? 0)) ?>" class="btn btn-outline-primary btn-sm">
                          <i class="bi bi-file-earmark-text me-1" aria-hidden="true"></i>View Record
                        </a>
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
  </div>
</section>

<?php require __DIR__ . '/../partials/dashboard/wallet_heroes.php'; ?>

<?php if ($availabilityChart !== null): ?>
<section class="mb-0" aria-label="Availability coverage">
  <div class="ux-card ux-card--quiet">
    <div class="ux-card__header mb-3">
      <?php
      $sectionTitle = 'Availability Coverage';
      $sectionSubtitle = 'Slots for the next 7 days';
      $sectionIcon = 'bi-graph-up';
      $sectionTone = 'info';
      require __DIR__ . '/../partials/dashboard/section_heading.php';
      ?>
    </div>
    <div class="ux-card__body">
      <canvas height="220" data-chart="<?= Helper::escape((string) json_encode($availabilityChart, JSON_UNESCAPED_SLASHES)) ?>"></canvas>
    </div>
  </div>
</section>
<?php endif; ?>
