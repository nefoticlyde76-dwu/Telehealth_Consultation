<?php

$filters = $filters ?? [
    'search' => '',
    'status' => 'Pending',
    'doctor_id' => 0,
    'date' => '',
    'date_from' => '',
    'date_to' => '',
    'sort' => 'date_asc',
];
$pagination = $pagination ?? ['current_page' => 1, 'total_pages' => 1, 'total_items' => 0, 'per_page' => 8, 'from' => 0, 'to' => 0];
$summary = $summary ?? [];
$requests = $requests ?? [];
$statusOptions = $statusOptions ?? [];
$selectedId = (int) ($selectedId ?? 0);
$selectedRequest = is_array($selectedRequest ?? null) ? $selectedRequest : null;
$queuePosition = (int) ($queuePosition ?? 0);
$csrfToken = $csrfToken ?? '';
$filterActive = (bool) ($filterActive ?? false);

require_once __DIR__ . '/../../partials/shared/status_helper.php';

$totalItems = (int) ($pagination['total_items'] ?? 0);
$buildQueueUrl = static function (array $overrides = []) use ($filters, $pagination): string {
    $page = (int) ($overrides['page'] ?? ($pagination['current_page'] ?? 1));
    $selected = array_key_exists('selected', $overrides) ? (int) $overrides['selected'] : null;
    $merged = array_merge($filters, $overrides);
    unset($merged['page'], $merged['selected']);

    return \App\Helpers\Helper::url(\App\Services\AdminConsultationService::workspacePath($merged, $selected, $page));
};

$statusFieldOptions = \App\Helpers\Status::filterOptions(
    \App\Helpers\Status::DOMAIN_CONSULTATION,
    $statusOptions
);

$filterTabs = array_merge(
    [['value' => '', 'label' => 'All']],
    $statusFieldOptions
);
$filterTabCurrent = (string) ($filters['status'] ?? '');
$filterTabAria = 'Filter queue by status';
$filterTabUrl = static function (string $value) use ($buildQueueUrl): string {
    return $buildQueueUrl(['status' => $value, 'selected' => 0, 'page' => 1]);
};

$queueNoun = ((string) ($filters['status'] ?? '')) === 'Pending'
    ? 'pending requests'
    : 'matching requests';
$emptyTitle = ((string) ($filters['status'] ?? '')) === 'Pending' && !$filterActive
    ? 'No pending requests'
    : 'No matching requests';
$emptyText = ((string) ($filters['status'] ?? '')) === 'Pending' && !$filterActive
    ? 'All consultation requests have been reviewed.'
    : 'There are no requests in this view.';
?>

<section class="mb-4 ux-review-workspace" data-consultation-queue-workspace>
  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= \App\Helpers\Helper::url('/admin/dashboard') ?>">Dashboard</a></li>
        <li class="active">Consultation Requests</li>
      </ol>
      <h2 class="ux-page-header__title">Consultation Requests</h2>
      <p class="ux-page-header__subtitle">Review each request, then approve or reject without leaving this workspace.</p>
    </div>
    <div class="ux-page-header__right">
      <span class="ux-chip ux-badge--dotless">
        <i class="bi bi-list-check"></i>
        <span>
          <?php if ($queuePosition > 0 && $totalItems > 0): ?>
            <?= (int) $queuePosition ?> of <?= (int) $totalItems ?> <?= $queueNoun ?>
          <?php else: ?>
            <?= (int) $totalItems ?> <?= $queueNoun ?>
          <?php endif; ?>
        </span>
      </span>
    </div>
  </div>

  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>

  <div class="row g-3 mb-4 ux-review-workspace__stats">
    <div class="col-sm-4">
      <div class="ux-stat compact d-flex align-items-center gap-3 h-100">
        <div class="ux-stat__icon ux-stat__icon--pending"><i class="bi bi-hourglass-split"></i></div>
        <div>
          <div class="ux-stat__value"><?= (int) ($summary['pending_requests'] ?? 0) ?></div>
          <div class="ux-stat__label">Pending Requests</div>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="ux-stat compact d-flex align-items-center gap-3 h-100">
        <div class="ux-stat__icon ux-stat__icon--navy"><i class="bi bi-calendar2-day"></i></div>
        <div>
          <div class="ux-stat__value"><?= (int) ($summary['today_requests'] ?? 0) ?></div>
          <div class="ux-stat__label">Today’s Requests</div>
        </div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="ux-stat compact d-flex align-items-center gap-3 h-100">
        <div class="ux-stat__icon ux-stat__icon--mint"><i class="bi bi-check2-circle"></i></div>
        <div>
          <div class="ux-stat__value"><?= (int) ($summary['approved_requests'] ?? 0) ?></div>
          <div class="ux-stat__label">Approved</div>
        </div>
      </div>
    </div>
  </div>

  <div class="ux-review-workspace__grid">
    <aside class="ux-card ux-data-card ux-queue" aria-label="Consultation request queue">
      <div class="ux-card__header ux-queue__header">
        <h2 class="ux-data-card__title">Queue</h2>
        <?php require __DIR__ . '/../../partials/shared/table_filter_tabs.php'; ?>
      </div>
      <div class="ux-table-wrapper">
          <table class="ux-table ux-queue-table align-middle mb-0">
            <caption class="visually-hidden">Consultation request queue</caption>
            <colgroup>
              <col>
              <col>
              <col>
              <col>
            </colgroup>
            <thead>
              <tr>
                <th scope="col">Patient</th>
                <th scope="col">Doctor</th>
                <th scope="col">Date</th>
                <th scope="col">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($requests === []): ?>
                <tr>
                  <td colspan="4" class="ux-table__empty-state">
                    <?php
                    $emptyIcon = 'bi-clipboard2-check';
                    $emptyCompact = true;
                    require __DIR__ . '/../../partials/shared/empty_state.php';
                    ?>
                  </td>
                </tr>
              <?php else: ?>
              <?php foreach ($requests as $request): ?>
                <?php
                $requestId = (int) ($request['id'] ?? 0);
                $isActive = $requestId === $selectedId;
                $status = (string) ($request['status'] ?? 'Pending');
                $dateLabel = \App\Helpers\Helper::formatDate((string) ($request['consultation_date'] ?? ''), 'd M Y', 'Date TBD');
                $timeLabel = substr((string) ($request['start_time'] ?? ''), 0, 5);
                $selectUrl = $buildQueueUrl(['selected' => $requestId, 'page' => (int) ($pagination['current_page'] ?? 1)]);
                ?>
                <tr class="ux-queue__row position-relative<?= $isActive ? ' is-active' : '' ?>">
                  <td>
                    <a class="stretched-link text-decoration-none text-reset" href="<?= $selectUrl ?>" <?= $isActive ? 'aria-current="true"' : '' ?>>
                      <?php
                      $personName = (string) ($request['patient_name'] ?? 'Patient');
                      $personPhoto = $request['patient_photo_path'] ?? null;
                      $personMeta = '';
                      $personSize = 'sm';
                      require __DIR__ . '/../../partials/shared/person_row.php';
                      ?>
                    </a>
                  </td>
                  <td>
                    <?php
                    $personName = (string) ($request['doctor_name'] ?? 'Doctor');
                    $personPhoto = $request['doctor_photo_path'] ?? null;
                    $personMeta = (string) ($request['specialization'] ?? 'General');
                    $personSize = 'xs';
                    require __DIR__ . '/../../partials/shared/person_row.php';
                    ?>
                  </td>
                  <td class="text-muted small">
                    <?= \App\Helpers\Helper::escape($dateLabel) ?>
                    <?php if ($timeLabel !== ''): ?>
                      <span class="d-block"><?= \App\Helpers\Helper::escape($timeLabel) ?></span>
                    <?php endif; ?>
                  </td>
                  <td><?= ux_status_badge($status) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      <?php
      $paginationPath = '/admin/consultation-requests';
      $paginationFilters = $filters;
      $paginationLabel = $queueNoun;
      $paginationAria = 'Queue pagination';
      $paginationShowCount = true;
      $paginationBuildUrl = static function (int $page) use ($buildQueueUrl): string {
          return $buildQueueUrl(['page' => $page, 'selected' => 0]);
      };
      require __DIR__ . '/../../partials/shared/list_pagination.php';
      ?>
    </aside>

    <div class="ux-review-workspace__detail">
      <?php require __DIR__ . '/_queue_detail.php'; ?>
    </div>
  </div>
</section>
