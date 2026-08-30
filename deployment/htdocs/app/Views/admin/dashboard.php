<?php
use App\Helpers\Helper;
use App\Helpers\Status;

$stats = is_array($stats ?? null) ? $stats : [];
$recentConsultationRequests = is_array($recentConsultationRequests ?? null) ? $recentConsultationRequests : [];
$latestUsers = is_array($latestUsers ?? null) ? $latestUsers : [];
$recentNotifications = is_array($recentNotifications ?? null) ? $recentNotifications : [];
$recentAudit = is_array($recentAudit ?? null) ? $recentAudit : [];
$charts = is_array($charts ?? null) ? $charts : [];
$headerNotifications = is_array($headerNotifications ?? null) ? $headerNotifications : [];
require_once __DIR__ . '/../partials/shared/status_helper.php';

$adminPending = 0;
$unreadCount = (int) ($headerNotifications['unread_count'] ?? 0);
foreach ($stats as $statItem) {
    $lblLower = strtolower(trim((string) ($statItem['label'] ?? '')));
    if (str_contains($lblLower, 'pending')) {
        $adminPending = (int) ($statItem['value'] ?? 0);
    }
}

$attentionText = 'No pending consultation requests right now.';
$attentionIcon = 'bi-check-circle';
$attentionAction = [
    'label' => 'View Queue',
    'url' => '/admin/consultation-requests',
    'icon' => 'bi-clipboard2-check',
];
if ($adminPending > 0) {
    $attentionText = $adminPending === 1
        ? '1 consultation request is waiting for review.'
        : $adminPending . ' consultation requests are waiting for review.';
    $attentionIcon = 'bi-hourglass-split';
    $attentionAction = [
        'label' => 'Review Pending Requests',
        'url' => Status::filteredListUrl('/admin/consultation-requests', Status::PENDING),
        'icon' => 'bi-clipboard2-check',
    ];
} elseif ($unreadCount > 0) {
    $attentionText = $unreadCount === 1
        ? 'You have 1 unread notification.'
        : 'You have ' . $unreadCount . ' unread notifications.';
    $attentionIcon = 'bi-bell';
    $attentionAction = ['label' => 'View Notifications', 'url' => '/notifications?read_state=unread', 'icon' => 'bi-bell'];
}

$pageHeaderTitle = 'Administrator Dashboard';
$pageHeaderSubtitle = 'Review pending requests, manage accounts, and monitor recent activity.';
$pageHeaderBreadcrumbs = [];
ob_start();
?>
<a href="<?= Helper::url(Status::filteredListUrl('/admin/consultation-requests', Status::PENDING)) ?>" class="btn btn-primary btn-sm">
  <i class="bi bi-clipboard2-check me-1" aria-hidden="true"></i>Review Pending Requests
</a>
<?php
$pageHeaderActions = ob_get_clean();

$summaryStats = $stats;
$actionCards = [
    [
        'title' => 'Review Pending Requests',
        'description' => 'Approve or reject consultation booking requests.',
        'url' => Status::filteredListUrl('/admin/consultation-requests', Status::PENDING),
        'icon' => 'bi-clipboard2-check',
        'action_label' => 'Review',
        'emphasis' => 'primary',
    ],
    [
        'title' => 'Consultation Queue',
        'description' => 'Open the full request workspace with search and filters.',
        'url' => '/admin/consultation-requests',
        'icon' => 'bi-list-check',
        'action_label' => 'Open',
    ],
    [
        'title' => 'User Accounts',
        'description' => 'Search, suspend, deactivate, or permanently delete accounts.',
        'url' => '/admin/users',
        'icon' => 'bi-people',
        'action_label' => 'Manage',
    ],
    [
        'title' => 'Audit Activity',
        'description' => 'Review recent security and workflow events.',
        'url' => '/admin/audit-logs',
        'icon' => 'bi-journal-text',
        'action_label' => 'Open',
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
<?php require __DIR__ . '/../partials/dashboard/action_cards.php'; ?>

<section class="mb-4">
  <div class="row g-4">
    <div class="col-xl-8">
      <div class="ux-card h-100">
        <div class="ux-card__header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <div>
            <h2 class="h5 mb-1">Pending Consultation Requests</h2>
            <p class="text-muted mb-0 small">Requests that need an administrator decision.</p>
          </div>
          <a href="<?= Helper::url(Status::filteredListUrl('/admin/consultation-requests', Status::PENDING)) ?>" class="btn btn-outline-primary btn-sm">View All Pending</a>
        </div>
        <div class="ux-card__body">
          <?php if ($recentConsultationRequests === []): ?>
            <?php
            $emptyIcon = 'bi-clipboard2-check';
            $emptyTitle = 'No pending requests';
            $emptyText = 'New consultation requests will appear here for review.';
            $emptyActions = '<a href="' . Helper::url('/admin/consultation-requests') . '" class="btn btn-primary btn-sm">Open Consultation Queue</a>';
            $emptyCompact = false;
            $emptyPositive = true;
            require __DIR__ . '/../partials/shared/empty_state.php';
            ?>
          <?php else: ?>
            <div class="ux-table-wrapper">
              <table class="ux-table align-middle mb-0">
                <thead>
                  <tr>
                    <th scope="col">Patient</th>
                    <th scope="col">Doctor</th>
                    <th scope="col">Date</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentConsultationRequests as $request): ?>
                    <tr>
                      <td>
                        <?php
                        $personName = (string) ($request['patient_name'] ?? 'Patient');
                        $personPhoto = $request['patient_photo_path'] ?? null;
                        $personMeta = '';
                        $personSize = 'sm';
                        require __DIR__ . '/../partials/shared/person_row.php';
                        ?>
                      </td>
                      <td>
                        <?php
                        $personName = (string) ($request['doctor_name'] ?? 'Doctor');
                        $personPhoto = $request['doctor_photo_path'] ?? null;
                        $personMeta = '';
                        $personSize = 'sm';
                        require __DIR__ . '/../partials/shared/person_row.php';
                        ?>
                      </td>
                      <td><?= Helper::escape(Helper::formatDate((string) ($request['consultation_date'] ?? ''), 'd M Y', 'Not scheduled')) ?></td>
                      <td><?= ux_status_badge((string) ($request['status'] ?? Status::PENDING)) ?></td>
                      <td class="text-end">
                        <a href="<?= Helper::url('/admin/consultation-requests/' . (int) ($request['id'] ?? 0)) ?>" class="btn btn-outline-primary btn-sm">Review</a>
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
        <div class="ux-card__header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <div>
            <h2 class="h5 mb-1">Notifications</h2>
            <p class="text-muted mb-0 small">Recent updates that may need attention.</p>
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
    </div>
  </div>
</section>

<section class="mb-4">
  <div class="row g-4">
    <div class="col-xl-8">
      <div class="ux-card h-100">
        <div class="ux-card__header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <div>
            <h2 class="h5 mb-1">Recent Audit Activity</h2>
            <p class="text-muted mb-0 small">A short record of recent accountable events.</p>
          </div>
          <a href="<?= Helper::url('/admin/audit-logs') ?>" class="btn btn-outline-primary btn-sm">View Audit Logs</a>
        </div>
        <div class="ux-card__body">
          <?php
          $activityItems = $recentAudit;
          $activityEmptyTitle = 'No activity found';
          $activityEmptyText = 'Audit records will appear here as the system is used.';
          $activityEmptyIcon = 'bi-journal-text';
          require __DIR__ . '/../partials/shared/activity_list.php';
          ?>
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="ux-card h-100">
        <div class="ux-card__header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <div>
            <h2 class="h5 mb-1">Latest Users</h2>
            <p class="text-muted mb-0 small">Recently registered accounts.</p>
          </div>
          <a href="<?= Helper::url('/admin/users') ?>" class="btn btn-outline-primary btn-sm">Manage Users</a>
        </div>
        <div class="ux-card__body">
          <?php if ($latestUsers === []): ?>
            <?php
            $emptyIcon = 'bi-people';
            $emptyTitle = 'No recent users';
            $emptyText = 'New accounts will appear here after registration.';
            $emptyActions = '';
            $emptyCompact = true;
            require __DIR__ . '/../partials/shared/empty_state.php';
            ?>
          <?php else: ?>
            <div class="ux-list">
              <?php foreach ($latestUsers as $latestUser): ?>
                <div class="ux-list__item d-flex justify-content-between align-items-center gap-3">
                  <?php
                  $personName = (string) ($latestUser['full_name'] ?? 'User');
                  $personPhoto = $latestUser['profile_photo_path'] ?? null;
                  $personMeta = (string) ($latestUser['email'] ?? '');
                  $personSize = 'sm';
                  require __DIR__ . '/../partials/shared/person_row.php';
                  ?>
                  <div class="text-end">
                    <?= ux_status_badge((string) ($latestUser['status'] ?? ''), Status::DOMAIN_USER, ['class' => 'mb-2']) ?>
                    <span class="d-block small text-muted"><?= Helper::escape(ucfirst((string) ($latestUser['role_name'] ?? 'user'))) ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../partials/dashboard/wallet_heroes.php'; ?>
