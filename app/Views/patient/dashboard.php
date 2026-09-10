<?php
use App\Helpers\Helper;
use App\Helpers\Status;

$bookingSummary = is_array($bookingSummary ?? null) ? $bookingSummary : [];
$slotPreview = is_array($slotPreview ?? null) ? $slotPreview : [];
$recentRequests = is_array($recentRequests ?? null) ? $recentRequests : [];
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

$pageHeaderTitle = 'Patient Dashboard';
$pageHeaderSubtitle = 'Check your next consultation, track request status, and book care when you need it.';
$pageHeaderBreadcrumbs = [];
$pageHeaderCompact = true;
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

$welcomePills = [
    [
        'icon' => 'bi-calendar2-check',
        'label' => $upcomingCount === 1 ? '1 upcoming consultation' : $upcomingCount . ' upcoming consultations',
    ],
    [
        'icon' => 'bi-hourglass-split',
        'label' => $pendingCount === 1 ? '1 pending request' : $pendingCount . ' pending requests',
    ],
];
$welcomeIcon = 'bi-calendar2-heart';
?>

<?php require __DIR__ . '/../partials/shared/alerts.php'; ?>
<?php require __DIR__ . '/../partials/dashboard/page_header.php'; ?>
<?php require __DIR__ . '/../partials/dashboard/welcome_banner.php'; ?>
<?php require __DIR__ . '/../partials/dashboard/summary_stats.php'; ?>

<section class="mb-4">
  <div class="ux-card">
    <div class="ux-card__header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
      <?php
          $sectionTitle = 'Your Next Consultation';
          $sectionSubtitle = 'The appointment or request that needs your attention first.';
          $sectionIcon = 'bi-calendar2-event';
          $sectionTone = 'pending';
      require __DIR__ . '/../partials/dashboard/section_heading.php';
      ?>
      <a href="<?= Helper::url('/patient/consultation-requests') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-clipboard2-check me-1" aria-hidden="true"></i>My Consultations
      </a>
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
        <div class="ux-next-consult mb-3">
          <div class="ux-next-consult__identity">
            <?php
            $personName = (string) ($latestRequest['doctor_name'] ?? 'Doctor');
            $personPhoto = $latestRequest['doctor_photo_path'] ?? null;
            $personMeta = (string) ($latestRequest['specialization'] ?? 'General Practice');
            $personSize = 'sm';
            require __DIR__ . '/../partials/shared/person_row.php';
            ?>
            <?= ux_status_badge($latestStatus) ?>
          </div>
          <dl class="ux-next-consult__grid">
            <div class="ux-next-consult__item">
              <dt>Consultation date</dt>
              <dd><?= Helper::escape(Helper::formatDate((string) ($latestRequest['consultation_date'] ?? ''), 'D, d M Y', 'Not scheduled')) ?></dd>
            </div>
            <div class="ux-next-consult__item">
              <dt>Time</dt>
              <dd><?= Helper::escape($timeLabel) ?></dd>
            </div>
            <div class="ux-next-consult__item">
              <dt>Reference</dt>
              <dd class="font-monospace">#TH-<?= Helper::escape((string) ((int) ($latestRequest['id'] ?? 0))) ?></dd>
            </div>
            <div class="ux-next-consult__item">
              <dt>Specialization</dt>
              <dd><?= Helper::escape((string) ($latestRequest['specialization'] ?? 'General Practice')) ?></dd>
            </div>
            <div class="ux-next-consult__item ux-next-consult__item--wide">
              <dt>Next step</dt>
              <dd><?= Helper::escape($nextStep) ?></dd>
            </div>
          </dl>
        </div>
        <div class="ux-next-consult__actions d-flex flex-wrap gap-2">
          <?php if ($latestStatus === Status::PENDING): ?>
            <a href="<?= Helper::url('/patient/consultation-requests/' . (int) ($latestRequest['id'] ?? 0)) ?>" class="btn btn-primary btn-sm">
              <i class="bi bi-eye me-1" aria-hidden="true"></i>Track Request
            </a>
            <a href="<?= Helper::url('/patient/consultation-requests') ?>" class="btn btn-outline-primary btn-sm">
              <i class="bi bi-list-ul me-1" aria-hidden="true"></i>All Requests
            </a>
          <?php elseif ($latestStatus === Status::APPROVED): ?>
            <a href="<?= Helper::url('/patient/consultation-requests/' . (int) ($latestRequest['id'] ?? 0)) ?>" class="btn btn-primary btn-sm">
              <i class="bi bi-calendar2-check me-1" aria-hidden="true"></i>View Details
            </a>
            <a href="<?= Helper::url('/patient/available-slots') ?>" class="btn btn-outline-primary btn-sm">
              <i class="bi bi-calendar2-plus me-1" aria-hidden="true"></i>Book Another
            </a>
          <?php else: ?>
            <a href="<?= Helper::url('/patient/consultation-requests/' . (int) ($latestRequest['id'] ?? 0)) ?>" class="btn btn-primary btn-sm">
              <i class="bi <?= $latestStatus === Status::COMPLETED ? 'bi-file-earmark-text' : 'bi-eye' ?> me-1" aria-hidden="true"></i>
              <?= $latestStatus === Status::COMPLETED ? 'View Record' : 'View Details' ?>
            </a>
            <a href="<?= Helper::url('/patient/available-slots') ?>" class="btn btn-outline-primary btn-sm">
              <i class="bi bi-calendar2-plus me-1" aria-hidden="true"></i>Book Consultation
            </a>
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
  <div class="ux-card ux-data-card">
    <div class="ux-card__header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
      <?php
      $sectionTitle = 'Recent Consultations';
      $sectionSubtitle = 'Your latest requests and their status.';
      $sectionIcon = 'bi-clock-history';
      $sectionTone = 'navy';
      require __DIR__ . '/../partials/dashboard/section_heading.php';
      ?>
      <a href="<?= Helper::url('/patient/consultation-requests') ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-arrow-right me-1" aria-hidden="true"></i>View All
      </a>
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
          <table class="ux-table ux-table--stack align-middle mb-0">
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
                  <td data-label="Doctor">
                    <?php
                    $personName = (string) ($request['doctor_name'] ?? 'Doctor');
                    $personPhoto = $request['doctor_photo_path'] ?? null;
                    $personMeta = (string) ($request['specialization'] ?? '');
                    $personSize = 'sm';
                    require __DIR__ . '/../partials/shared/person_row.php';
                    ?>
                  </td>
                  <td data-label="Date"><?= Helper::escape(Helper::formatDate((string) ($request['consultation_date'] ?? ''), 'd M Y', 'Not scheduled')) ?></td>
                  <td data-label="Status"><?= ux_status_badge($status) ?></td>
                  <td class="text-end" data-label="Action">
                    <a href="<?= Helper::url('/patient/consultation-requests/' . (int) ($request['id'] ?? 0)) ?>" class="btn btn-outline-primary btn-sm">
                      <i class="bi <?= $status === Status::COMPLETED ? 'bi-file-earmark-text' : 'bi-eye' ?> me-1" aria-hidden="true"></i>
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
</section>

<section class="mb-4">
  <div class="ux-card">
    <div class="ux-card__header mb-3">
      <?php
      $sectionTitle = 'Next Available Slots';
      $sectionSubtitle = (string) count($slotPreview) . ' upcoming times you can book';
      $sectionIcon = 'bi-calendar2-plus';
      $sectionTone = 'success';
      require __DIR__ . '/../partials/dashboard/section_heading.php';
      ?>
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
          <?php
          $previewExpiresAt = Helper::combineDateTimeIso(
              (string) ($slot['consultation_date'] ?? ''),
              (string) ($slot['end_time'] ?? '')
          );
          ?>
          <div class="ux-slot-row"<?= $previewExpiresAt !== '' ? ' data-slot-expires-at="' . Helper::escape($previewExpiresAt) . '"' : '' ?>>
            <?php
            $personName = (string) ($slot['full_name'] ?? 'Doctor');
            $personPhoto = $slot['profile_photo_path'] ?? null;
            $personMeta = (string) ($slot['specialization'] ?? 'General Practice');
            $personSize = 'sm';
            require __DIR__ . '/../partials/shared/person_row.php';
            ?>
            <div class="ux-slot-row__when">
              <strong class="ux-slot-row__date"><?= Helper::escape(Helper::formatDate((string) ($slot['consultation_date'] ?? ''), 'd M', '')) ?></strong>
              <span class="ux-slot-row__time"><?= Helper::escape(substr((string) ($slot['start_time'] ?? ''), 0, 5)) ?></span>
            </div>
            <a href="<?= Helper::url('/patient/consultation-requests/book/' . (int) ($slot['id'] ?? 0)) ?>" class="btn btn-primary btn-sm">
              <i class="bi bi-calendar2-plus me-1" aria-hidden="true"></i>Book
            </a>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../partials/dashboard/wallet_heroes.php'; ?>
