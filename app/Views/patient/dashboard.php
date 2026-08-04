<?php
$stats = is_array($stats ?? null) ? $stats : [];
$bookingSummary = is_array($bookingSummary ?? null) ? $bookingSummary : [];
$slotPreview = is_array($slotPreview ?? null) ? $slotPreview : [];
$recentRequests = is_array($recentRequests ?? null) ? $recentRequests : [];
$charts = is_array($charts ?? null) ? $charts : [];
$statusChart = $charts['status_distribution'] ?? null;
$monthlyChart = $charts['monthly_requests'] ?? null;
$latestRequest = is_array($bookingSummary['latest_request'] ?? null) ? $bookingSummary['latest_request'] : null;
?>

<?php require __DIR__ . '/../partials/shared/alerts.php'; ?>

<section class="dashboard-banner mb-4" data-aos="fade-up">
  <div class="row g-4 align-items-center">
    <div class="col-lg-8">
      <span class="section-badge mb-3">
        <i class="bi bi-heart-pulse"></i>
        Patient Workspace
      </span>
      <h2 class="h3 mb-3">Good day, <?= \App\Helpers\Helper::escape($user->full_name ?? 'Patient') ?>.</h2>
      <p class="text-muted mb-0">Book an available consultation slot, track requests, and review appointment updates from one secure patient dashboard.</p>
      <div class="dashboard-banner-actions">
        <a href="<?= \App\Helpers\Helper::url('/patient/available-slots') ?>" class="btn btn-primary rounded-pill px-4">
          <i class="bi bi-calendar2-plus me-2"></i>
          Book Consultation
        </a>
        <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests') ?>" class="btn btn-outline-primary rounded-pill px-4">
          <i class="bi bi-clipboard2-check me-2"></i>
          My Consultations
        </a>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="dashboard-spotlight-card">
        <span class="dashboard-spotlight-label">Latest Status</span>
        <strong class="d-block mb-2"><?= \App\Helpers\Helper::escape((string) ($bookingSummary['latest_status_display'] ?? 'No requests yet')) ?></strong>
        <?php if ($latestRequest !== null): ?>
          <p class="text-muted small mb-0">
            <?= \App\Helpers\Helper::escape((string) ($latestRequest['doctor_name'] ?? 'Doctor')) ?>
            · <?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($latestRequest['consultation_date'] ?? ''), 'd M Y', 'Not scheduled')) ?>
          </p>
        <?php else: ?>
          <p class="text-muted small mb-0">Submit a booking request to begin the Week 5 consultation workflow.</p>
        <?php endif; ?>
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
          <span class="stats-pill">Updated for you</span>
          <p class="text-muted mb-0 small"><?= \App\Helpers\Helper::escape((string) ($stat['description'] ?? '')) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="mb-4">
  <div class="row g-4">
    <div class="col-xl-7">
      <div class="card border-0 shadow-sm rounded-4 dashboard-widget-shell h-100" data-aos="fade-up">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
              <span class="dashboard-info-label">Consultation Analytics</span>
              <h3 class="h5 mb-1">Status distribution</h3>
              <p class="text-muted mb-0">A live breakdown of your consultation request statuses.</p>
            </div>
            <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests') ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">
              View History
            </a>
          </div>

          <div class="dashboard-chart-grid">
            <div class="dashboard-chart-shell" data-chart-shell>
              <canvas
                height="240"
                data-chart="<?= $statusChart !== null ? \App\Helpers\Helper::escape((string) json_encode($statusChart, JSON_UNESCAPED_SLASHES)) : '' ?>"
              ></canvas>
            </div>
            <div class="dashboard-chart-shell" data-chart-shell>
              <canvas
                height="240"
                data-chart="<?= $monthlyChart !== null ? \App\Helpers\Helper::escape((string) json_encode($monthlyChart, JSON_UNESCAPED_SLASHES)) : '' ?>"
              ></canvas>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-5">
      <div class="card border-0 shadow-sm rounded-4 dashboard-widget-shell h-100" data-aos="fade-up">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
              <span class="dashboard-info-label">Quick Booking</span>
              <h3 class="h5 mb-1">Next available slots</h3>
              <p class="text-muted mb-0">Book directly from the first available consultation windows.</p>
            </div>
            <span class="badge badge-soft-info rounded-pill px-3 py-2"><?= \App\Helpers\Helper::escape((string) count($slotPreview)) ?></span>
          </div>

          <div class="schedule-slot-list">
            <?php if ($slotPreview === []): ?>
              <div class="schedule-slot-card">
                <div>
                  <strong class="d-block">No slots available</strong>
                  <span class="small text-muted">When doctors publish availability, slots will appear here automatically.</span>
                </div>
              </div>
            <?php else: ?>
              <?php foreach ($slotPreview as $slot): ?>
                <div class="schedule-slot-card">
                  <div>
                    <strong class="d-block"><?= \App\Helpers\Helper::escape((string) ($slot['full_name'] ?? 'Doctor')) ?></strong>
                    <span class="small text-muted"><?= \App\Helpers\Helper::escape((string) ($slot['specialization'] ?? 'General Practice')) ?></span>
                  </div>
                  <div class="text-end">
                    <strong class="d-block"><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($slot['consultation_date'] ?? ''), 'd M', '')) ?></strong>
                    <span class="small text-muted"><?= \App\Helpers\Helper::escape(substr((string) ($slot['start_time'] ?? ''), 0, 5)) ?></span>
                    <div class="mt-2">
                      <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests/book/' . (string) ((int) ($slot['id'] ?? 0))) ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                        Book
                      </a>
                    </div>
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

<section>
  <div class="card border-0 shadow-sm rounded-4 dashboard-widget-shell" data-aos="fade-up">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
        <div>
          <span class="dashboard-info-label">Recent Consultations</span>
          <h3 class="h5 mb-1">Latest requests</h3>
          <p class="text-muted mb-0">Review your most recent consultation submissions and their current status.</p>
        </div>
        <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests') ?>" class="btn btn-outline-primary rounded-pill px-4">
          View All
        </a>
      </div>

      <div class="table-responsive">
        <table class="table admin-user-table align-middle mb-0">
          <thead>
            <tr>
              <th scope="col">Doctor</th>
              <th scope="col">Date</th>
              <th scope="col">Time</th>
              <th scope="col">Status</th>
              <th scope="col" class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($recentRequests === []): ?>
              <tr>
                <td colspan="5">
                  <div class="admin-table-empty text-center py-5">
                    <div class="empty-state-icon mx-auto mb-3">
                      <i class="bi bi-clipboard2-x"></i>
                    </div>
                    <h4 class="h5 mb-2">No consultation requests yet</h4>
                    <p class="text-muted mb-0">Book a consultation slot to start building your consultation history.</p>
                  </div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($recentRequests as $request): ?>
                <?php
                $status = (string) ($request['status'] ?? 'Pending');
                $badgeMap = [
                    'Pending' => 'badge-soft-warning',
                    'Approved' => 'badge-soft-success',
                    'Rejected' => 'badge-soft-danger',
                    'Cancelled' => 'badge-soft-danger',
                    'Completed' => 'badge-soft-success',
                ];
                $badgeClass = $badgeMap[$status] ?? 'badge-soft-neutral';
                ?>
                <tr>
                  <td>
                    <strong class="d-block"><?= \App\Helpers\Helper::escape((string) ($request['doctor_name'] ?? 'Doctor')) ?></strong>
                    <span class="text-muted small"><?= \App\Helpers\Helper::escape((string) ($request['specialization'] ?? '')) ?></span>
                  </td>
                  <td><?= \App\Helpers\Helper::escape(\App\Helpers\Helper::formatDate((string) ($request['consultation_date'] ?? ''), 'd M Y', 'Not scheduled')) ?></td>
                  <td class="text-muted small"><?= \App\Helpers\Helper::escape(substr((string) ($request['start_time'] ?? ''), 0, 5)) ?> - <?= \App\Helpers\Helper::escape(substr((string) ($request['end_time'] ?? ''), 0, 5)) ?></td>
                  <td><span class="badge <?= \App\Helpers\Helper::escape($badgeClass) ?> rounded-pill px-3 py-2"><?= \App\Helpers\Helper::escape($status) ?></span></td>
                  <td class="text-end">
                    <a href="<?= \App\Helpers\Helper::url('/patient/consultation-requests/' . (string) ((int) ($request['id'] ?? 0))) ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">View</a>
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
