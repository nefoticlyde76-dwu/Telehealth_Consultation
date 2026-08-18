<?php
$stats = is_array($stats ?? null) ? $stats : [];
$consultationSummary = is_array($consultationSummary ?? null) ? $consultationSummary : [];
$recentConsultationRequests = is_array($recentConsultationRequests ?? null) ? $recentConsultationRequests : [];
$latestUsers = is_array($latestUsers ?? null) ? $latestUsers : [];
$charts = is_array($charts ?? null) ? $charts : [];
$weeklyChart = $charts['weekly_requests'] ?? null;
$statusChart = $charts['status_distribution'] ?? null;
$consultationStatusBadgeMap = [
    'Pending' => 'ux-badge--pending',
    'Approved' => 'ux-badge--approved',
    'Rejected' => 'ux-badge--rejected',
    'Cancelled' => 'ux-badge--rejected',
    'Completed' => 'ux-badge--approved',
];
?>

<?php require __DIR__ . '/../partials/shared/alerts.php'; ?>

<?php
$adminPending = 0; $adminApproved = 0; $adminRejected = 0; $adminRecent = 0;
foreach ($stats as $statItem) {
    $lblLower = strtolower(trim((string) ($statItem['label'] ?? '')));
    $valInt = (int) ($statItem['value'] ?? 0);
    if (str_contains($lblLower, 'pending')) { $adminPending = $valInt; }
    elseif (str_contains($lblLower, 'approved')) { $adminApproved = $valInt; }
    elseif (str_contains($lblLower, 'rejected')) { $adminRejected = $valInt; }
    elseif (str_contains($lblLower, 'recent') || str_contains($lblLower, 'activity')) { $adminRecent = $valInt; }
}
$adminToday = (new DateTimeImmutable())->format('l, d F Y');
?>

<div class="ux-welcome ux-welcome--admin">
  <div class="ux-welcome__grid">
    <div>
      <span class="ux-welcome__eyebrow">Welcome back, Administrator</span>
      <h1 class="ux-welcome__title">Administrator Dashboard</h1>
      <p class="ux-welcome__description">Review pending consultation requests, manage user accounts, and keep the service running.</p>

      <div class="ux-welcome__meta-pill-row">
        <span class="ux-welcome__meta-pill">
          <i class="bi bi-hourglass-split"></i>
          <span><?= \App\Helpers\Helper::escape((string) $adminPending) ?> pending request<?= $adminPending === 1 ? '' : 's' ?></span>
        </span>
        <span class="ux-welcome__meta-pill">
          <i class="bi bi-calendar3"></i>
          <span><?= \App\Helpers\Helper::escape($adminToday) ?></span>
        </span>
      </div>
    </div>

    <div class="ux-welcome__actions">
      <a href="<?= \App\Helpers\Helper::url('/admin/consultation-requests') ?>" class="btn-primary-xl">
        <i class="bi bi-clipboard2-check me-2"></i>Review Requests
      </a>
      <a href="<?= \App\Helpers\Helper::url('/admin/users') ?>" class="ux-welcome__cta-secondary">
        <i class="bi bi-people"></i>
        <span>Manage Users</span>
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
            <h3 class="h5 mb-1">Pending Consultation Requests</h3>
            <p class="text-muted mb-0 small">Requests requiring administrator review</p>
          </div>
          <a href="<?= \App\Helpers\Helper::url('/admin/consultation-requests') ?>" class="btn btn-outline-primary btn-sm">View All</a>
        </div>
        <div class="ux-card__body">
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
                <?php if ($recentConsultationRequests === []): ?>
                  <tr>
                    <td colspan="5">
                      <div class="ux-empty py-5 text-center">
                        <div class="ux-empty__icon mx-auto mb-3"><i class="bi bi-clipboard2-x"></i></div>
                        <h4 class="h6 mb-2">No pending consultation requests yet.</h4>
                        <p class="text-muted mb-4">All new submissions will appear here for review.</p>
                        <a href="<?= \App\Helpers\Helper::url('/admin/consultation-requests') ?>" class="btn btn-primary btn-sm">Go to Consultation Requests</a>
                      </div>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($recentConsultationRequests as $request): ?>
                    <?php
                    $status = (string) ($request['status'] ?? 'Pending');
                    $badgeClass = $consultationStatusBadgeMap[$status] ?? 'ux-badge--pending';
                    $statusIcons = [
                        'Pending' => 'bi-clock',
                        'Approved' => 'bi-check-circle-fill',
                        'Rejected' => 'bi-x-circle-fill',
                        'Cancelled' => 'bi-slash-circle',
                        'Completed' => 'bi-check2-circle',
                    ];
                    $statusIcon = $statusIcons[$status] ?? 'bi-dash-circle';
                    ?>
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
                      <td><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($request['consultation_date'] ?? ''), 'd M Y', 'Not scheduled')) ?></td>
                      <td><span class="ux-badge <?= $badgeClass ?>"><i class="bi <?= $statusIcon ?> me-1"></i><?= \App\Helpers\Helper::escape($status) ?></span></td>
                      <td class="text-end">
                        <a href="<?= \App\Helpers\Helper::url('/admin/consultation-requests/' . (string) ((int) ($request['id'] ?? 0))) ?>" class="btn btn-outline-primary btn-sm">Open</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="ux-card h-100">
        <div class="ux-card__header mb-3">
          <h3 class="h5 mb-1">Request Status Distribution</h3>
          <p class="text-muted mb-0 small">Live breakdown of request statuses</p>
        </div>
        <div class="ux-card__body">
          <canvas height="280" data-chart="<?= $statusChart !== null ? \App\Helpers\Helper::escape((string) json_encode($statusChart, JSON_UNESCAPED_SLASHES)) : '' ?>"></canvas>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="mb-4">
  <div class="row g-4">
    <div class="col-xl-8">
      <div class="ux-card h-100">
        <div class="ux-card__header mb-3">
          <h3 class="h5 mb-1">Weekly Consultation Trends</h3>
          <p class="text-muted mb-0 small">Request volume over the last 7 days</p>
        </div>
        <div class="ux-card__body">
          <canvas height="300" data-chart="<?= $weeklyChart !== null ? \App\Helpers\Helper::escape((string) json_encode($weeklyChart, JSON_UNESCAPED_SLASHES)) : '' ?>"></canvas>
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="ux-card h-100">
        <div class="ux-card__header d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
          <div>
            <h3 class="h5 mb-1">Latest Registered Users</h3>
            <p class="text-muted mb-0 small">Recent accounts across all roles</p>
          </div>
          <a href="<?= \App\Helpers\Helper::url('/admin/users') ?>" class="btn btn-outline-primary btn-sm">View All Users</a>
        </div>
        <div class="ux-card__body">
          <div class="ux-list">
            <?php if ($latestUsers === []): ?>
              <div class="ux-list__item">
                <div>
                  <strong class="d-block">No recent users available</strong>
                  <span class="small text-muted">Recent account activity will appear here.</span>
                </div>
              </div>
            <?php else: ?>
              <?php foreach ($latestUsers as $latestUser): ?>
                <?php $isActive = ($latestUser['status'] ?? '') === 'active'; ?>
                <div class="ux-list__item d-flex justify-content-between align-items-center gap-3">
                  <?php
                  $personName = (string) ($latestUser['full_name'] ?? 'User');
                  $personPhoto = $latestUser['profile_photo_path'] ?? null;
                  $personMeta = (string) ($latestUser['email'] ?? '');
                  $personSize = 'sm';
                  require __DIR__ . '/../partials/shared/person_row.php';
                  ?>
                  <div class="text-end">
                    <span class="ux-badge <?= $isActive ? 'ux-badge--approved' : 'ux-badge--pending' ?> mb-2">
                      <i class="bi <?= $isActive ? 'bi-check-circle-fill' : 'bi-clock' ?> me-1"></i>
                      <?= \App\Helpers\Helper::escape(ucfirst((string) ($latestUser['status'] ?? 'unknown'))) ?>
                    </span>
                    <span class="d-block small text-muted"><?= \App\Helpers\Helper::escape(ucfirst((string) ($latestUser['role_name'] ?? 'user'))) ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
