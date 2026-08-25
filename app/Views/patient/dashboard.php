<?php
use App\Helpers\Helper;
use App\Helpers\Status;

$bookingSummary = is_array($bookingSummary ?? null) ? $bookingSummary : [];
$slotPreview = is_array($slotPreview ?? null) ? $slotPreview : [];
$recentRequests = is_array($recentRequests ?? null) ? $recentRequests : [];
$recentNotifications = is_array($recentNotifications ?? null) ? $recentNotifications : [];
$charts = is_array($charts ?? null) ? $charts : [];
$latestRequest = is_array($bookingSummary['latest_request'] ?? null) ? $bookingSummary['latest_request'] : null;
$headerNotifications = is_array($headerNotifications ?? null) ? $headerNotifications : [];
require_once __DIR__ . '/../partials/shared/status_helper.php';

$pendingCount = (int) ($bookingSummary['pending_count'] ?? $bookingSummary['pending_requests'] ?? 0);
$approvedCount = (int) ($bookingSummary['approved_count'] ?? $bookingSummary['approved_requests'] ?? 0);
$completedCount = (int) ($bookingSummary['completed_count'] ?? $bookingSummary['completed_requests'] ?? 0);
$upcomingCount = (int) ($bookingSummary['upcoming_appointments'] ?? $approvedCount);
$unreadCount = (int) ($headerNotifications['unread_count'] ?? 0);
$latestStatus = (string) ($latestRequest['status'] ?? '');

$hasPrescription = false;
foreach ($recentRequests as $requestRow) {
    if ((int) ($requestRow['has_prescription'] ?? 0) === 1) {
        $hasPrescription = true;
        break;
    }
}

$attentionText = 'Book a consultation when you are ready.';
$attentionIcon = 'bi-calendar2-heart';
$attentionAction = ['label' => 'Book a Consultation', 'url' => '/patient/available-slots', 'icon' => 'bi-calendar2-plus'];
if ($latestStatus === Status::APPROVED) {
    $attentionText = 'Your next consultation is approved.';
    $attentionIcon = 'bi-calendar2-check';
    $attentionAction = [
        'label' => 'View Consultation',
        'url' => '/patient/consultation-requests/' . (int) ($latestRequest['id'] ?? 0),
        'icon' => 'bi-arrow-right',
    ];
} elseif ($latestStatus === Status::PENDING) {
    $attentionText = 'Your request is awaiting administrator review.';
    $attentionIcon = 'bi-clock';
    $attentionAction = [
        'label' => 'Track Request',
        'url' => '/patient/consultation-requests/' . (int) ($latestRequest['id'] ?? 0),
        'icon' => 'bi-eye',
    ];
} elseif ($unreadCount > 0) {
    $attentionText = $unreadCount === 1
        ? 'You have 1 unread notification.'
        : 'You have ' . $unreadCount . ' unread notifications.';
    $attentionIcon = 'bi-bell';
    $attentionAction = ['label' => 'View Notifications', 'url' => '/notifications?read_state=unread', 'icon' => 'bi-bell'];
} elseif ($hasPrescription) {
    $attentionText = 'A prescription is available from a completed consultation.';
    $attentionIcon = 'bi-capsule';
    $attentionAction = ['label' => 'View Consultations', 'url' => '/patient/consultation-requests?status=' . urlencode(Status::COMPLETED), 'icon' => 'bi-journal-medical'];
}

$pageHeaderTitle = 'Patient Dashboard';
$pageHeaderSubtitle = 'Check your next consultation, track request status, and book care when you need it.';
$pageHeaderBreadcrumbs = [];
ob_start();
?>
<a href="<?= Helper::url('/patient/available-slots') ?>" class="btn btn-primary btn-sm">
  <i class="bi bi-calendar2-plus me-1" aria-hidden="true"></i>Book Consultation
</a>
<?php
$pageHeaderActions = ob_get_clean();

$summaryStats = [
    [
        'label' => 'Upcoming',
        'value' => $upcomingCount,
        'description' => 'Approved consultations still ahead',
        'icon' => 'bi-calendar2-check',
        'tone' => 'mint',
        'url' => Status::filteredListUrl('/patient/consultation-requests', Status::APPROVED),
    ],
    [
        'label' => 'Pending',
        'value' => $pendingCount,
        'description' => 'Awaiting administrator review',
        'icon' => 'bi-hourglass-split',
        'tone' => 'pending',
        'url' => Status::filteredListUrl('/patient/consultation-requests', Status::PENDING),
    ],
    [
        'label' => 'Completed',
        'value' => $completedCount,
        'description' => 'Records and prescriptions when available',
        'icon' => 'bi-journal-medical',
        'tone' => 'navy',
        'url' => Status::filteredListUrl('/patient/consultation-requests', Status::COMPLETED),
    ],
    [
        'label' => 'Unread Notifications',
        'value' => $unreadCount,
        'description' => 'Updates about your consultations',
        'icon' => 'bi-bell',
        'tone' => 'info',
        'url' => '/notifications?read_state=unread',
    ],
];

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
?>

<?php require __DIR__ . '/../partials/shared/alerts.php'; ?>
<?php require __DIR__ . '/../partials/dashboard/page_header.php'; ?>
<?php require __DIR__ . '/../partials/dashboard/attention_banner.php'; ?>
<?php require __DIR__ . '/../partials/dashboard/summary_stats.php'; ?>

<section class="mb-4">
  <div class="ux-card">
    <div class="ux-card__header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
      <div>
        <h2 class="h5 mb-1">Your Next Consultation</h2>
        <p class="text-muted mb-0 small">The appointment or request that needs your attention first.</p>
      </div>
      <a href="<?= Helper::url('/patient/consultation-requests') ?>" class="btn btn-outline-primary btn-sm">My Consultations</a>
    </div>
    <div class="ux-card__body">
      <?php if ($latestRequest !== null): ?>
        <?php
        $startLabel = substr((string) ($latestRequest['start_time'] ?? ''), 0, 5);
        $endLabel = substr((string) ($latestRequest['end_time'] ?? ''), 0, 5);
        $timeLabel = ($startLabel !== '' && $endLabel !== '')
            ? $startLabel . ' – ' . $endLabel
            : 'Not scheduled';
        $nextStep = 'View this consultation for more details.';
        if ($latestStatus === Status::PENDING) {
            $nextStep = 'You will receive a notification once an administrator reviews this request.';
        } elseif ($latestStatus === Status::APPROVED) {
            $nextStep = 'Your consultation is approved. Join at the scheduled time.';
        } elseif ($latestStatus === Status::COMPLETED) {
            $nextStep = 'This consultation is complete. You can view the record and prescription when available.';
        }
        ?>
        <div class="table-responsive ux-details-table-wrap">
          <table class="table ux-table ux-details-table mb-0">
            <tbody>
              <tr>
                <th scope="row">Doctor</th>
                <td>
                  <?php
                  $personName = (string) ($latestRequest['doctor_name'] ?? 'Doctor');
                  $personPhoto = $latestRequest['doctor_photo_path'] ?? null;
                  $personMeta = '';
                  $personSize = 'sm';
                  require __DIR__ . '/../partials/shared/person_row.php';
                  ?>
                </td>
              </tr>
              <tr>
                <th scope="row">Specialization</th>
                <td><?= Helper::escape((string) ($latestRequest['specialization'] ?? 'General Practice')) ?></td>
              </tr>
              <tr>
                <th scope="row">Status</th>
                <td><?= ux_status_badge($latestStatus) ?></td>
              </tr>
              <tr>
                <th scope="row">Consultation date</th>
                <td><?= Helper::escape(Helper::formatDate((string) ($latestRequest['consultation_date'] ?? ''), 'D, d M Y', 'Not scheduled')) ?></td>
              </tr>
              <tr>
                <th scope="row">Time</th>
                <td><?= Helper::escape($timeLabel) ?></td>
              </tr>
              <tr>
                <th scope="row">Reference</th>
                <td class="font-monospace">#TH-<?= Helper::escape((string) ((int) ($latestRequest['id'] ?? 0))) ?></td>
              </tr>
              <tr>
                <th scope="row">Next step</th>
                <td><?= Helper::escape($nextStep) ?></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <?php if ($latestStatus === Status::PENDING): ?>
            <a href="<?= Helper::url('/patient/consultation-requests/' . (int) ($latestRequest['id'] ?? 0)) ?>" class="btn btn-primary btn-sm">Track Request</a>
            <a href="<?= Helper::url('/patient/consultation-requests') ?>" class="btn btn-outline-primary btn-sm">All Requests</a>
          <?php elseif ($latestStatus === Status::APPROVED): ?>
            <a href="<?= Helper::url('/patient/consultation-requests/' . (int) ($latestRequest['id'] ?? 0)) ?>" class="btn btn-primary btn-sm">View Details</a>
            <a href="<?= Helper::url('/patient/available-slots') ?>" class="btn btn-outline-primary btn-sm">Book Another</a>
          <?php else: ?>
            <a href="<?= Helper::url('/patient/consultation-requests/' . (int) ($latestRequest['id'] ?? 0)) ?>" class="btn btn-primary btn-sm">
              <?= $latestStatus === Status::COMPLETED ? 'View Record' : 'View Details' ?>
            </a>
            <a href="<?= Helper::url('/patient/available-slots') ?>" class="btn btn-outline-primary btn-sm">Book Consultation</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <?php
        $emptyIcon = 'bi-calendar2-heart';
        $emptyTitle = 'No consultation booked';
        $emptyText = 'Your next approved consultation will appear here.';
        $emptyActions = '<a href="' . Helper::url('/patient/available-slots') . '" class="btn btn-primary btn-sm">Book Consultation</a>';
        $emptyCompact = false;
        require __DIR__ . '/../partials/shared/empty_state.php';
        ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="mb-4">
  <div class="row g-4">
    <div class="col-xl-7">
      <div class="ux-card h-100">
        <div class="ux-card__header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <div>
            <h2 class="h5 mb-1">Recent Consultations</h2>
            <p class="text-muted mb-0 small">Your latest requests and their status.</p>
          </div>
          <a href="<?= Helper::url('/patient/consultation-requests') ?>" class="btn btn-outline-primary btn-sm">View All</a>
        </div>
        <div class="ux-card__body">
          <?php if ($recentRequests === []): ?>
            <?php
            $emptyIcon = 'bi-clipboard2-x';
            $emptyTitle = 'No consultations found';
            $emptyText = 'Your consultations will appear here once you have an appointment.';
            $emptyActions = '<a href="' . Helper::url('/patient/available-slots') . '" class="btn btn-primary btn-sm">Book Consultation</a>';
            $emptyCompact = true;
            require __DIR__ . '/../partials/shared/empty_state.php';
            ?>
          <?php else: ?>
            <div class="ux-table-wrapper">
              <table class="ux-table align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Doctor</th>
                    <th scope="col">Date</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentRequests as $request): ?>
                    <?php $status = (string) ($request['status'] ?? Status::PENDING); ?>
                    <tr>
                      <td>
                        <?php
                        $personName = (string) ($request['doctor_name'] ?? 'Doctor');
                        $personPhoto = $request['doctor_photo_path'] ?? null;
                        $personMeta = (string) ($request['specialization'] ?? '');
                        $personSize = 'sm';
                        require __DIR__ . '/../partials/shared/person_row.php';
                        ?>
                      </td>
                      <td><?= Helper::escape(Helper::formatDate((string) ($request['consultation_date'] ?? ''), 'd M Y', 'Not scheduled')) ?></td>
                      <td><?= ux_status_badge($status) ?></td>
                      <td class="text-end">
                        <a href="<?= Helper::url('/patient/consultation-requests/' . (int) ($request['id'] ?? 0)) ?>" class="btn btn-outline-primary btn-sm">
                          <?= $status === Status::COMPLETED ? 'View Record' : 'View Details' ?>
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
      <div class="ux-card h-100 mb-4">
        <div class="ux-card__header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <div>
            <h2 class="h5 mb-1">Notifications</h2>
            <p class="text-muted mb-0 small">Updates about your consultations.</p>
          </div>
          <a href="<?= Helper::url('/notifications') ?>" class="btn btn-outline-primary btn-sm">View All</a>
        </div>
        <div class="ux-card__body">
          <?php
          $activityItems = $notificationItems;
          $activityEmptyTitle = "You're all caught up";
          $activityEmptyText = "You don't have any notifications yet.";
          $activityEmptyIcon = 'bi-bell';
          require __DIR__ . '/../partials/shared/activity_list.php';
          ?>
        </div>
      </div>

      <div class="ux-card">
        <div class="ux-card__header mb-3">
          <h2 class="h5 mb-1">Next Available Slots</h2>
          <p class="text-muted mb-0 small"><?= Helper::escape((string) count($slotPreview)) ?> upcoming times you can book</p>
        </div>
        <div class="ux-card__body">
          <?php if ($slotPreview === []): ?>
            <?php
            $emptyIcon = 'bi-calendar-x';
            $emptyTitle = 'No slots available';
            $emptyText = 'When doctors publish availability, times will appear here.';
            $emptyActions = '';
            $emptyCompact = true;
            require __DIR__ . '/../partials/shared/empty_state.php';
            ?>
          <?php else: ?>
            <?php foreach (array_slice($slotPreview, 0, 4) as $slot): ?>
              <div class="ux-slot-row">
                <?php
                $personName = (string) ($slot['full_name'] ?? 'Doctor');
                $personPhoto = $slot['profile_photo_path'] ?? null;
                $personMeta = (string) ($slot['specialization'] ?? 'General Practice');
                $personSize = 'sm';
                require __DIR__ . '/../partials/shared/person_row.php';
                ?>
                <div class="flex-grow-1 text-center">
                  <strong class="d-block"><?= Helper::escape(Helper::formatDate((string) ($slot['consultation_date'] ?? ''), 'd M', '')) ?></strong>
                  <span class="small text-muted"><?= Helper::escape(substr((string) ($slot['start_time'] ?? ''), 0, 5)) ?></span>
                </div>
                <a href="<?= Helper::url('/patient/consultation-requests/book/' . (int) ($slot['id'] ?? 0)) ?>" class="btn btn-outline-primary btn-sm">Book</a>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../partials/dashboard/wallet_heroes.php'; ?>
