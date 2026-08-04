<?php
$stats = is_array($stats ?? null) ? $stats : [];
$consultationSummary = is_array($consultationSummary ?? null) ? $consultationSummary : [];
$recentConsultationRequests = is_array($recentConsultationRequests ?? null) ? $recentConsultationRequests : [];
$latestUsers = is_array($latestUsers ?? null) ? $latestUsers : [];
$charts = is_array($charts ?? null) ? $charts : [];
$weeklyChart = $charts['weekly_requests'] ?? null;
$statusChart = $charts['status_distribution'] ?? null;
$consultationStatusBadgeMap = [
    'Pending' => 'badge-soft-warning',
    'Approved' => 'badge-soft-success',
    'Rejected' => 'badge-soft-danger',
    'Cancelled' => 'badge-soft-danger',
    'Completed' => 'badge-soft-success',
];
?>

<?php require __DIR__ . '/../partials/shared/alerts.php'; ?>

<section class="dashboard-banner mb-4" data-aos="fade-up">
  <div class="row g-4 align-items-center">
    <div class="col-lg-8">
      <span class="section-badge mb-3">
        <i class="bi bi-shield-lock"></i>
        Administration
      </span>
      <h2 class="h3 mb-3">System overview for MBPHA TeleHealth.</h2>
      <p class="text-muted mb-0">Monitor consultation workflow activity, manage accounts, and keep platform governance aligned to MBPHA standards.</p>
      <div class="dashboard-banner-actions">
        <a href="<?= \App\Helpers\Helper::url('/admin/consultation-requests') ?>" class="btn btn-primary rounded-pill px-4">
          <i class="bi bi-clipboard2-check me-2"></i>
          Review Requests
        </a>
        <a href="<?= \App\Helpers\Helper::url('/admin/users') ?>" class="btn btn-outline-primary rounded-pill px-4">
          <i class="bi bi-people me-2"></i>
          Manage Users
        </a>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="dashboard-spotlight-card">
        <span class="dashboard-spotlight-label">Pending queue</span>
        <strong class="d-block mb-2"><?= \App\Helpers\Helper::escape((string) ((int) ($consultationSummary['pending_requests'] ?? 0))) ?> requests</strong>
        <p class="text-muted small mb-0">Ready for approval, rejection, or cancellation actions.</p>
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
          <span class="stats-pill">Governance metrics</span>
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
              <span class="dashboard-info-label">Consultation Analytics</span>
              <h3 class="h5 mb-1">Weekly consultation requests</h3>
              <p class="text-muted mb-0">Request volume across the last seven days.</p>
            </div>
          </div>
          <div class="dashboard-chart-shell dashboard-chart-shell--lg" data-chart-shell>
            <canvas height="320" data-chart="<?= $weeklyChart !== null ? \App\Helpers\Helper::escape((string) json_encode($weeklyChart, JSON_UNESCAPED_SLASHES)) : '' ?>"></canvas>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-4">
      <div class="card border-0 shadow-sm rounded-4 dashboard-widget-shell h-100" data-aos="fade-up">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
              <span class="dashboard-info-label">Status Mix</span>
              <h3 class="h5 mb-1">Request distribution</h3>
              <p class="text-muted mb-0">Pending, approved, rejected, cancelled, and completed statuses.</p>
            </div>
          </div>
          <div class="dashboard-chart-shell" data-chart-shell>
            <canvas height="320" data-chart="<?= $statusChart !== null ? \App\Helpers\Helper::escape((string) json_encode($statusChart, JSON_UNESCAPED_SLASHES)) : '' ?>"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="mb-4">
  <div class="card border-0 shadow-sm rounded-4 dashboard-widget-shell" data-aos="fade-up">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
          <span class="dashboard-info-label">Recent Consultation Requests</span>
          <h3 class="h5 mb-1">Latest workflow submissions</h3>
          <p class="text-muted mb-0">Review newly submitted consultation requests and move directly into approval actions.</p>
        </div>
        <a href="<?= \App\Helpers\Helper::url('/admin/consultation-requests') ?>" class="btn btn-outline-primary rounded-pill px-4">View All</a>
      </div>

      <div class="table-responsive">
        <table class="table admin-user-table align-middle mb-0">
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
                  <div class="admin-table-empty text-center py-5">
                    <div class="empty-state-icon mx-auto mb-3"><i class="bi bi-clipboard2-x"></i></div>
                    <h4 class="h5 mb-2">No consultation requests yet</h4>
                    <p class="text-muted mb-0">Consultation requests will appear here once patients begin booking available slots.</p>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($recentConsultationRequests as $request): ?>
                <?php
                $status = (string) ($request['status'] ?? 'Pending');
                $badgeClass = $consultationStatusBadgeMap[$status] ?? 'badge-soft-neutral';
                ?>
                <tr>
                  <td><strong><?= \App\Helpers\Helper::escape((string) ($request['patient_name'] ?? 'Patient')) ?></strong></td>
                  <td><?= \App\Helpers\Helper::escape((string) ($request['doctor_name'] ?? 'Doctor')) ?></td>
                  <td><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($request['consultation_date'] ?? ''), 'd M Y', 'Not scheduled')) ?></td>
                  <td><span class="badge <?= \App\Helpers\Helper::escape($badgeClass) ?> rounded-pill px-3 py-2"><?= \App\Helpers\Helper::escape($status) ?></span></td>
                  <td class="text-end">
                    <a href="<?= \App\Helpers\Helper::url('/admin/consultation-requests/' . (string) ((int) ($request['id'] ?? 0))) ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">Open</a>
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

<section>
  <div class="card border-0 shadow-sm rounded-4 dashboard-widget-shell" data-aos="fade-up">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
          <span class="dashboard-info-label">Latest Users</span>
          <h3 class="h5 mb-1">Recently registered accounts</h3>
          <p class="text-muted mb-0">A compact feed of the most recent user registrations.</p>
        </div>
        <a href="<?= \App\Helpers\Helper::url('/admin/users') ?>" class="btn btn-outline-primary rounded-pill px-4">View Users</a>
      </div>

      <div class="admin-user-preview-list">
        <?php if ($latestUsers === []): ?>
          <div class="admin-user-preview-item">
            <div>
              <strong class="d-block">No recent users available</strong>
              <span class="small text-muted">Recent account activity will appear here as more records are added to the platform.</span>
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($latestUsers as $latestUser): ?>
            <div class="admin-user-preview-item">
              <div>
                <strong class="d-block"><?= \App\Helpers\Helper::escape((string) ($latestUser['full_name'] ?? 'User')) ?></strong>
                <span class="small text-muted"><?= \App\Helpers\Helper::escape((string) ($latestUser['email'] ?? '')) ?></span>
              </div>
              <div class="text-end">
                <span class="badge <?= ($latestUser['status'] ?? '') === 'active' ? 'badge-soft-success' : 'badge-soft-warning' ?> rounded-pill mb-2">
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
</section>
