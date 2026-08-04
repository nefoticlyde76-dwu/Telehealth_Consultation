<?php require __DIR__ . '/../shared/alerts.php'; ?>

<?php
$primaryAction = $quickActions[0] ?? null;
$secondaryAction = $quickActions[1] ?? null;
$spotlightStats = array_slice($stats ?? [], 0, 3);
$activityHeading = 'Recent Activity';
$activityDescription = 'Role-specific visibility prepared for future modules.';
$activityBadge = 'Live when data is available';

if (($dashboardRole ?? '') === 'admin') {
    $activityHeading = 'Recent System Activity';
    $activityDescription = 'Operational signals and account-level changes in the administration workspace.';
    $activityBadge = 'Governance feed';
} elseif (($dashboardRole ?? '') === 'doctor') {
    $activityHeading = 'Consultation Overview';
    $activityDescription = 'Clinician-facing updates tied to schedule readiness and profile completion.';
    $activityBadge = 'Clinical workspace';
} elseif (($dashboardRole ?? '') === 'patient') {
    $activityHeading = 'Notifications';
    $activityDescription = 'Patient-friendly updates about browsing, availability visibility, and next module readiness.';
    $activityBadge = 'Patient updates';
}
?>

<section class="dashboard-banner mb-4">
  <div class="row g-4 align-items-center">
    <div class="col-xl-7">
      <div class="dashboard-banner-intro mb-3">
        <?php
        $avatarPath = $user->profile_photo_path ?? null;
        $fullName = $user->full_name ?? 'User';
        $avatarClass = 'user-avatar user-avatar--lg';
        require __DIR__ . '/../shared/user_avatar.php';
        ?>
        <div>
          <span class="section-badge mb-3">
            <i class="bi bi-speedometer2"></i>
            <?= \App\Helpers\Helper::escape($dashboardRoleLabel ?? 'Dashboard Overview') ?>
          </span>
          <h2 class="h3 mb-3">Welcome back, <?= \App\Helpers\Helper::escape($user->full_name ?? 'User') ?>.</h2>
          <p class="text-muted mb-0">
            <?= \App\Helpers\Helper::escape($welcomeMessage ?? 'Your role-specific workspace is ready.') ?>
          </p>
        </div>
      </div>
      <div class="dashboard-banner-actions">
        <?php if (!empty($primaryAction['url'])): ?>
          <a href="<?= \App\Helpers\Helper::url((string) $primaryAction['url']) ?>" class="btn btn-primary rounded-pill px-4">
            <i class="bi <?= \App\Helpers\Helper::escape((string) ($primaryAction['icon'] ?? 'bi-arrow-right')) ?> me-2"></i>
            <?= \App\Helpers\Helper::escape((string) ($primaryAction['action_label'] ?? 'Open')) ?>
          </a>
        <?php endif; ?>

        <?php if (!empty($secondaryAction['url'])): ?>
          <a href="<?= \App\Helpers\Helper::url((string) $secondaryAction['url']) ?>" class="btn btn-outline-primary rounded-pill px-4">
            <i class="bi <?= \App\Helpers\Helper::escape((string) ($secondaryAction['icon'] ?? 'bi-grid')) ?> me-2"></i>
            <?= \App\Helpers\Helper::escape((string) ($secondaryAction['action_label'] ?? 'Open')) ?>
          </a>
        <?php endif; ?>
      </div>
    </div>
    <div class="col-xl-5">
      <div class="dashboard-spotlight-card">
        <div class="dashboard-spotlight-header">
          <div>
            <span class="dashboard-spotlight-label">Current Focus</span>
            <strong class="d-block mb-2"><?= \App\Helpers\Helper::escape($focusTitle ?? 'Platform readiness') ?></strong>
            <p class="mb-0 text-muted small"><?= \App\Helpers\Helper::escape($focusDescription ?? 'Additional data will appear as more modules become active.') ?></p>
          </div>
          <span class="badge badge-soft-success rounded-pill">Live Workspace</span>
        </div>

        <div class="dashboard-spotlight-grid mt-4">
          <?php foreach ($spotlightStats as $spotlightStat): ?>
            <?php $spotlightValue = (string) ($spotlightStat['value'] ?? '0'); ?>
            <div class="dashboard-spotlight-item">
              <span class="dashboard-spotlight-item-label"><?= \App\Helpers\Helper::escape((string) ($spotlightStat['label'] ?? 'Metric')) ?></span>
              <strong <?= is_numeric($spotlightValue) ? 'data-counter="' . \App\Helpers\Helper::escape($spotlightValue) . '"' : '' ?>>
                <?= \App\Helpers\Helper::escape($spotlightValue) ?>
              </strong>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="mb-4">
  <div class="row g-4">
    <?php foreach (($stats ?? []) as $stat): ?>
      <?php $statValue = (string) ($stat['value'] ?? '0'); ?>
      <div class="col-sm-6 col-xl-3">
        <div class="stats-card h-100 reveal-on-scroll reveal-slide-up">
          <div class="d-flex justify-content-between align-items-start mb-3 gap-3">
            <div>
              <span class="stats-label"><?= \App\Helpers\Helper::escape($stat['label'] ?? '') ?></span>
              <h3 class="stats-value" <?= is_numeric($statValue) ? 'data-counter="' . \App\Helpers\Helper::escape($statValue) . '"' : '' ?>><?= \App\Helpers\Helper::escape($statValue) ?></h3>
            </div>
            <span class="stats-icon">
              <i class="bi <?= \App\Helpers\Helper::escape($stat['icon'] ?? 'bi-graph-up') ?>"></i>
            </span>
          </div>
          <span class="stats-pill">Updated for this workspace</span>
          <p class="text-muted mb-0 small"><?= \App\Helpers\Helper::escape($stat['description'] ?? '') ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php if (($dashboardRole ?? '') === 'admin' && isset($doctorSummary, $patientSummary) && is_array($doctorSummary) && is_array($patientSummary)): ?>
  <section class="mb-4">
    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
          <div>
            <h3 class="h5 mb-1">Administrator Governance Snapshot</h3>
            <p class="text-muted mb-0">Week 3 administration controls for patient oversight, clinician onboarding, and secure account maintenance.</p>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <a href="<?= \App\Helpers\Helper::url('/admin/patients') ?>" class="btn btn-outline-primary rounded-pill px-4">
              <i class="bi bi-people me-2"></i>
              Manage Patients
            </a>
            <a href="<?= \App\Helpers\Helper::url('/admin/doctors') ?>" class="btn btn-outline-primary rounded-pill px-4">
              <i class="bi bi-person-badge me-2"></i>
              Manage Doctors
            </a>
            <a href="<?= \App\Helpers\Helper::url('/admin/profile') ?>" class="btn btn-primary rounded-pill px-4">
              <i class="bi bi-person-gear me-2"></i>
              Profile Settings
            </a>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-sm-6 col-xl-3">
            <div class="admin-summary-card h-100">
              <span class="admin-summary-label">Active Patients</span>
              <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($patientSummary['active_patients'] ?? 0)) ?></strong>
              <span class="admin-summary-meta">Patient accounts currently able to sign in through the shared login page.</span>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3">
            <div class="admin-summary-card h-100">
              <span class="admin-summary-label">Inactive Patients</span>
              <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($patientSummary['inactive_patients'] ?? 0)) ?></strong>
              <span class="admin-summary-meta">Patient accounts retained for future reactivation when required.</span>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3">
            <div class="admin-summary-card h-100">
              <span class="admin-summary-label">Active Doctors</span>
              <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($doctorSummary['active_doctors'] ?? 0)) ?></strong>
              <span class="admin-summary-meta">Clinicians who can currently authenticate through the shared login page.</span>
            </div>
          </div>
          <div class="col-sm-6 col-xl-3">
            <div class="admin-summary-card h-100">
              <span class="admin-summary-label">Inactive Doctors</span>
              <strong class="admin-summary-value"><?= \App\Helpers\Helper::escape((string) ($doctorSummary['inactive_doctors'] ?? 0)) ?></strong>
              <span class="admin-summary-meta">Accounts retained for future reactivation without deleting clinician records.</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
<?php endif; ?>

<section class="mb-4">
  <div class="row g-4">
    <div class="col-xl-6">
      <div class="card border-0 shadow-sm rounded-4 h-100 dashboard-widget-shell">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
              <h3 class="h5 mb-1">Quick Actions</h3>
              <p class="text-muted mb-0">Primary entry points prepared for this role.</p>
            </div>
            <span class="badge badge-soft-neutral rounded-pill border"><?= count($quickActions ?? []) ?> actions</span>
          </div>

          <div class="row g-3">
            <?php foreach (($quickActions ?? []) as $action): ?>
              <div class="col-md-6">
                <div class="quick-action-card h-100">
                  <div class="quick-action-icon">
                    <i class="bi <?= \App\Helpers\Helper::escape($action['icon'] ?? 'bi-arrow-right') ?>"></i>
                  </div>
                  <span class="quick-action-kicker"><?= \App\Helpers\Helper::escape((string) ($action['status'] ?? 'Ready')) ?></span>
                  <h4 class="h6"><?= \App\Helpers\Helper::escape($action['title'] ?? '') ?></h4>
                  <p class="text-muted small mb-3"><?= \App\Helpers\Helper::escape($action['description'] ?? '') ?></p>
                  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-auto">
                    <span class="quick-action-status small"><?= \App\Helpers\Helper::escape($action['status'] ?? 'Ready') ?></span>
                    <?php if (!empty($action['url'])): ?>
                      <a href="<?= \App\Helpers\Helper::url((string) $action['url']) ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                        <?= \App\Helpers\Helper::escape((string) ($action['action_label'] ?? 'Open')) ?>
                      </a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-6">
      <div class="card border-0 shadow-sm rounded-4 h-100 dashboard-widget-shell">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
              <h3 class="h5 mb-1"><?= \App\Helpers\Helper::escape($activityHeading) ?></h3>
              <p class="text-muted mb-0"><?= \App\Helpers\Helper::escape($activityDescription) ?></p>
            </div>
            <span class="badge badge-soft-neutral rounded-pill border"><?= \App\Helpers\Helper::escape($activityBadge) ?></span>
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
  <div class="card border-0 shadow-sm rounded-4 dashboard-widget-shell">
    <div class="card-body p-4 p-lg-5">
      <div class="empty-state text-center">
        <div class="empty-state-icon">
          <i class="bi <?= \App\Helpers\Helper::escape($emptyState['icon'] ?? 'bi-inbox') ?>"></i>
        </div>
        <h3 class="h4 mb-3"><?= \App\Helpers\Helper::escape($emptyState['title'] ?? 'No data available yet') ?></h3>
        <p class="text-muted mx-auto mb-0 empty-state-copy">
          <?= \App\Helpers\Helper::escape($emptyState['description'] ?? 'Additional modules will populate this area as new workflows are activated.') ?>
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
          <?php if (!empty($primaryAction['url'])): ?>
            <a href="<?= \App\Helpers\Helper::url((string) $primaryAction['url']) ?>" class="btn btn-primary rounded-pill px-4">
              <?= \App\Helpers\Helper::escape((string) ($primaryAction['action_label'] ?? 'Open')) ?>
            </a>
          <?php endif; ?>
          <?php if (!empty($secondaryAction['url'])): ?>
            <a href="<?= \App\Helpers\Helper::url((string) $secondaryAction['url']) ?>" class="btn btn-outline-primary rounded-pill px-4">
              <?= \App\Helpers\Helper::escape((string) ($secondaryAction['action_label'] ?? 'Review')) ?>
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>
