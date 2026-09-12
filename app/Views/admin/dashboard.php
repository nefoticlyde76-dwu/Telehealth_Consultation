<?php
use App\Helpers\Helper;
use App\Helpers\Status;

require_once __DIR__ . '/../partials/shared/status_helper.php';
require_once __DIR__ . '/../partials/profile/_helpers.php';

$stats = is_array($stats ?? null) ? $stats : [];
$recentConsultationRequests = is_array($recentConsultationRequests ?? null) ? $recentConsultationRequests : [];
$latestUsers = is_array($latestUsers ?? null) ? $latestUsers : [];
$recentAudit = is_array($recentAudit ?? null) ? $recentAudit : [];
$charts = is_array($charts ?? null) ? $charts : [];
$headerNotifications = is_array($headerNotifications ?? null) ? $headerNotifications : [];

$summaryStats = $stats;

$welcomeIcon = 'bi-shield-check';
?>

<?php require __DIR__ . '/../partials/shared/alerts.php'; ?>
<?php require __DIR__ . '/../partials/dashboard/welcome_banner.php'; ?>
<?php require __DIR__ . '/../partials/dashboard/summary_stats.php'; ?>

<section class="mb-4">
  <div class="ux-card ux-data-card">
    <div class="ux-card__header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
      <?php
      $sectionTitle = 'Pending Consultation Requests';
      $sectionSubtitle = 'Requests that need an administrator decision.';
      $sectionIcon = 'bi-clipboard2-check';
      $sectionTone = 'pending';
      require __DIR__ . '/../partials/dashboard/section_heading.php';
      ?>
      <a href="<?= Helper::url(Status::filteredListUrl('/admin/consultation-requests', Status::PENDING)) ?>" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-arrow-right me-1" aria-hidden="true"></i>View All Pending
      </a>
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
                    <a href="<?= Helper::url('/admin/consultation-requests/' . (int) ($request['id'] ?? 0)) ?>" class="btn btn-outline-primary btn-sm">
                      <i class="bi bi-eye me-1" aria-hidden="true"></i>Review
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
  <div class="row g-4">
    <div class="col-xl-8">
      <div class="ux-card ux-data-card h-100">
        <div class="ux-card__header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <?php
          $sectionTitle = 'Recent Audit Activity';
          $sectionSubtitle = 'A short record of recent accountable events.';
          $sectionIcon = 'bi-journal-text';
          $sectionTone = 'navy';
          require __DIR__ . '/../partials/dashboard/section_heading.php';
          ?>
          <a href="<?= Helper::url('/admin/audit-logs') ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-arrow-right me-1" aria-hidden="true"></i>View Audit Logs
          </a>
        </div>
        <div class="ux-card__body">
          <?php if ($recentAudit === []): ?>
            <?php
            $emptyIcon = 'bi-journal-text';
            $emptyTitle = 'No activity found';
            $emptyText = 'Audit records will appear here as the system is used.';
            $emptyActions = '';
            $emptyCompact = true;
            require __DIR__ . '/../partials/shared/empty_state.php';
            ?>
          <?php else: ?>
            <div class="ux-table-wrapper">
              <table class="ux-table align-middle mb-0">
                <caption class="visually-hidden">Recent audit events</caption>
                <thead>
                  <tr>
                    <th scope="col">Event</th>
                    <th scope="col">Detail</th>
                    <th scope="col">When</th>
                    <th scope="col" class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recentAudit as $auditItem): ?>
                    <tr>
                      <td><?= Helper::escape((string) ($auditItem['title'] ?? 'Activity')) ?></td>
                      <td class="text-muted"><?= Helper::escape((string) ($auditItem['description'] ?? '')) ?></td>
                      <td class="text-muted small text-nowrap"><?= Helper::escape((string) ($auditItem['meta'] ?? '')) ?></td>
                      <td class="text-end">
                        <?php $auditUrl = trim((string) ($auditItem['url'] ?? '')); ?>
                        <?php if ($auditUrl !== ''): ?>
                          <a href="<?= Helper::url($auditUrl) ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-eye me-1" aria-hidden="true"></i>View
                          </a>
                        <?php endif; ?>
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
          $sectionTitle = 'Latest Users';
          $sectionSubtitle = 'Recently registered accounts.';
          $sectionIcon = 'bi-people';
          $sectionTone = 'navy';
          require __DIR__ . '/../partials/dashboard/section_heading.php';
          ?>
          <a href="<?= Helper::url('/admin/users') ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-people me-1" aria-hidden="true"></i>Manage Users
          </a>
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
            <div class="ux-table-wrapper">
              <table class="ux-table align-middle mb-0">
                <caption class="visually-hidden">Recently registered user accounts</caption>
                <thead>
                  <tr>
                    <th scope="col">User</th>
                    <th scope="col">Role</th>
                    <th scope="col">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($latestUsers as $latestUser): ?>
                    <tr>
                      <td>
                        <?php
                        $personName = (string) ($latestUser['full_name'] ?? 'User');
                        $personPhoto = $latestUser['profile_photo_path'] ?? null;
                        $personMeta = (string) ($latestUser['email'] ?? '');
                        $personSize = 'sm';
                        require __DIR__ . '/../partials/shared/person_row.php';
                        ?>
                      </td>
                      <td class="text-muted"><?= Helper::escape(user_profile_role_label((string) ($latestUser['role_name'] ?? 'user'))) ?></td>
                      <td><?= ux_status_badge((string) ($latestUser['status'] ?? ''), Status::DOMAIN_USER) ?></td>
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
