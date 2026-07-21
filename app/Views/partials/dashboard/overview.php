<section class="dashboard-banner mb-4">
  <div class="row g-4 align-items-center">
    <div class="col-lg-8">
      <span class="section-badge mb-3">
        <i class="bi bi-speedometer2"></i>
        <?= \App\Helpers\Helper::escape($dashboardRoleLabel ?? 'Dashboard Overview') ?>
      </span>
      <h2 class="h3 mb-3">Welcome back, <?= \App\Helpers\Helper::escape($user->full_name ?? 'User') ?>.</h2>
      <p class="text-muted mb-0">
        <?= \App\Helpers\Helper::escape($welcomeMessage ?? 'Your role-specific workspace is ready.') ?>
      </p>
    </div>
    <div class="col-lg-4">
      <div class="dashboard-banner-card">
        <span class="small text-uppercase text-muted d-block mb-2">Current Focus</span>
        <strong class="d-block mb-2"><?= \App\Helpers\Helper::escape($focusTitle ?? 'Platform readiness') ?></strong>
        <p class="mb-0 text-muted small"><?= \App\Helpers\Helper::escape($focusDescription ?? 'Additional data will appear as more modules become active.') ?></p>
      </div>
    </div>
  </div>
</section>

<section class="mb-4">
  <div class="row g-4">
    <?php foreach (($stats ?? []) as $stat): ?>
      <div class="col-sm-6 col-xl-3">
        <div class="stats-card h-100">
          <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
              <span class="stats-label"><?= \App\Helpers\Helper::escape($stat['label'] ?? '') ?></span>
              <h3 class="stats-value"><?= \App\Helpers\Helper::escape($stat['value'] ?? '0') ?></h3>
            </div>
            <span class="stats-icon">
              <i class="bi <?= \App\Helpers\Helper::escape($stat['icon'] ?? 'bi-graph-up') ?>"></i>
            </span>
          </div>
          <p class="text-muted mb-0 small"><?= \App\Helpers\Helper::escape($stat['description'] ?? '') ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="mb-4">
  <div class="row g-4">
    <div class="col-xl-6">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
              <h3 class="h5 mb-1">Quick Actions</h3>
              <p class="text-muted mb-0">Primary entry points prepared for this role.</p>
            </div>
            <span class="badge text-bg-light rounded-pill border"><?= count($quickActions ?? []) ?> actions</span>
          </div>

          <div class="row g-3">
            <?php foreach (($quickActions ?? []) as $action): ?>
              <div class="col-md-6">
                <div class="quick-action-card h-100">
                  <div class="quick-action-icon">
                    <i class="bi <?= \App\Helpers\Helper::escape($action['icon'] ?? 'bi-arrow-right') ?>"></i>
                  </div>
                  <h4 class="h6"><?= \App\Helpers\Helper::escape($action['title'] ?? '') ?></h4>
                  <p class="text-muted small mb-3"><?= \App\Helpers\Helper::escape($action['description'] ?? '') ?></p>
                  <span class="text-primary fw-semibold small"><?= \App\Helpers\Helper::escape($action['status'] ?? 'Ready') ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-6">
      <div class="card border-0 shadow-sm rounded-4 h-100">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
              <h3 class="h5 mb-1">Recent Activity</h3>
              <p class="text-muted mb-0">Role-specific visibility prepared for future modules.</p>
            </div>
            <span class="badge text-bg-light rounded-pill border">Live when data is available</span>
          </div>

          <div class="activity-list">
            <?php foreach (($recentActivity ?? []) as $activity): ?>
              <div class="activity-item">
                <span class="activity-dot"></span>
                <div>
                  <strong class="d-block"><?= \App\Helpers\Helper::escape($activity['title'] ?? '') ?></strong>
                  <p class="text-muted small mb-1"><?= \App\Helpers\Helper::escape($activity['description'] ?? '') ?></p>
                  <span class="small text-muted"><?= \App\Helpers\Helper::escape($activity['meta'] ?? '') ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4 p-lg-5">
      <div class="empty-state text-center">
        <div class="empty-state-icon">
          <i class="bi <?= \App\Helpers\Helper::escape($emptyState['icon'] ?? 'bi-inbox') ?>"></i>
        </div>
        <h3 class="h4 mb-3"><?= \App\Helpers\Helper::escape($emptyState['title'] ?? 'No data available yet') ?></h3>
        <p class="text-muted mx-auto mb-0 empty-state-copy">
          <?= \App\Helpers\Helper::escape($emptyState['description'] ?? 'Additional modules will populate this area as new workflows are activated.') ?>
        </p>
      </div>
    </div>
  </div>
</section>
