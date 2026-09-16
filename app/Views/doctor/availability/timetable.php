<?php

use App\Helpers\DoctorScheduleColor;
use App\Helpers\Helper;

$filters = is_array($filters ?? null) ? $filters : ['doctor_id' => 0, 'specialization' => ''];
$weekStart = (string) ($weekStart ?? '');
$weekLabel = (string) ($weekLabel ?? '');
$isCurrentWeek = (bool) ($isCurrentWeek ?? false);
$prevWeek = (string) ($prevWeek ?? '');
$nextWeek = (string) ($nextWeek ?? '');
$thisWeek = (string) ($thisWeek ?? '');
$days = is_array($days ?? null) ? $days : [];
$intervals = is_array($intervals ?? null) ? $intervals : [];
$blocks = is_array($blocks ?? null) ? $blocks : [];
$counts = is_array($counts ?? null) ? $counts : [];
$summary = is_array($summary ?? null) ? $summary : [];
$doctorOptions = is_array($doctorOptions ?? null) ? $doctorOptions : [];
$specializationOptions = is_array($specializationOptions ?? null) ? $specializationOptions : [];
$timezoneLabel = (string) ($timezoneLabel ?? Helper::appTimezoneLabel());
$csrfToken = (string) ($csrfToken ?? '');
$gridStart = (string) ($gridStart ?? '08:00');
$gridEnd = (string) ($gridEnd ?? '16:30');
$todayDate = (string) ($todayDate ?? '');
$nowHm = (string) ($nowHm ?? '');
$currentDoctorId = (int) ($currentDoctorId ?? 0);
$intervalCount = max(1, count($intervals));

$availUrl = static function (array $query = []) use ($filters): string {
    $merged = [
        'doctor_id' => (int) ($query['doctor_id'] ?? $filters['doctor_id'] ?? 0),
        'specialization' => (string) ($query['specialization'] ?? $filters['specialization'] ?? ''),
        'week' => (string) ($query['week'] ?? ''),
    ];
    if (isset($query['view'])) {
        $merged['view'] = $query['view'];
    }
    $merged = array_filter($merged, static fn ($value) => $value !== '' && $value !== 0);
    $path = '/doctor/availability';
    if ($merged === []) {
        return Helper::url($path);
    }

    return Helper::url($path . '?' . http_build_query($merged));
};

$formatGridTime = static function (string $hm): string {
    $parsed = DateTimeImmutable::createFromFormat('H:i', $hm);
    return $parsed instanceof DateTimeImmutable ? $parsed->format('g:i A') : $hm;
};

$createUrl = Helper::url('/doctor/availability/create' . ($weekStart !== '' ? '?week=' . rawurlencode($weekStart) : ''));
$hasSharedFilters = (int) ($filters['doctor_id'] ?? 0) > 0 || ($filters['specialization'] ?? '') !== '';
$hasVisibleSlots = false;
foreach ($blocks as $dayBlocks) {
    if (is_array($dayBlocks) && $dayBlocks !== []) {
        $hasVisibleSlots = true;
        break;
    }
}
?>

<section class="mb-4">
  <?php
  $pageHeaderTitle = 'Shared Availability Calendar';
  $pageHeaderSubtitle = 'See every approved doctor\'s hours so you can avoid overlapping consultations. You can add, edit, or delete only your own slots.';
  $pageHeaderIcon = 'bi-calendar-week';
  $pageHeaderHeadingTag = 'h2';
  $pageHeaderBreadcrumbs = [
      ['label' => 'Dashboard', 'url' => '/doctor/dashboard'],
      ['label' => 'Availability', 'active' => true],
  ];
  ob_start();
  ?>
  <span class="ux-chip ux-badge--dotless">
    <i class="bi bi-clock"></i>
    <span><?= Helper::escape($timezoneLabel) ?></span>
  </span>
  <button type="button" class="btn btn-primary" data-avail-add>
    <i class="bi bi-plus-lg me-2"></i>
    Add Availability
  </button>
  <a href="<?= $availUrl(['view' => 'list']) ?>" class="btn btn-outline-primary">
    <i class="bi bi-list-ul me-2"></i>
    My Slot List
  </a>
  <?php
  $pageHeaderActions = ob_get_clean();
  require __DIR__ . '/../../partials/dashboard/page_header.php';
  ?>

  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>
</section>

<?php
$summaryStats = [
    [
        'label' => 'Open slots this week',
        'value' => (int) ($counts['available'] ?? 0),
        'icon' => 'bi-calendar-check',
        'tone' => 'navy',
    ],
    [
        'label' => 'Booked this week',
        'value' => (int) ($counts['booked'] ?? 0),
        'icon' => 'bi-person',
        'tone' => 'info',
    ],
    [
        'label' => 'My upcoming slots',
        'value' => (int) ($summary['upcoming_slots'] ?? 0),
        'icon' => 'bi-clock',
        'tone' => 'success',
    ],
];
$summaryStatsCompact = true;
$summaryStatsColumns = 3;
require __DIR__ . '/../../partials/dashboard/summary_stats.php';
?>

<div class="mbpha-avail" data-mbpha-avail data-today="<?= Helper::escape($todayDate) ?>" data-now="<?= Helper::escape($nowHm) ?>">
  <div class="mbpha-avail__card">
    <div class="mbpha-avail__header">
      <div>
        <h3 class="mbpha-avail__title">Weekly Schedule</h3>
        <p class="mbpha-avail__hint mb-0">
          Coloured blocks belong to individual doctors. Click an open time to add your own hours,
          or click your block to edit it. Hours shown: <?= Helper::escape($formatGridTime($gridStart)) ?> – <?= Helper::escape($formatGridTime($gridEnd)) ?>.
        </p>
      </div>
      <div class="mbpha-avail__week-nav" role="group" aria-label="Week navigation">
        <a class="mbpha-avail__week-btn" href="<?= $availUrl(['week' => $prevWeek]) ?>" aria-label="Previous week">
          <i class="bi bi-chevron-left" aria-hidden="true"></i>
        </a>
        <span class="mbpha-avail__week-label">
          <?= Helper::escape($isCurrentWeek ? 'This week' : 'Week of') ?>
          <?= Helper::escape($weekLabel) ?>
        </span>
        <a class="mbpha-avail__week-btn" href="<?= $availUrl(['week' => $thisWeek]) ?>">This week</a>
        <a class="mbpha-avail__week-btn" href="<?= $availUrl(['week' => $nextWeek]) ?>" aria-label="Next week">
          <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </a>
      </div>
    </div>

    <form method="GET" action="<?= Helper::url('/doctor/availability') ?>" class="mbpha-avail__tools">
      <?php if ($weekStart !== ''): ?>
        <input type="hidden" name="week" value="<?= Helper::escape($weekStart) ?>">
      <?php endif; ?>
      <div class="mbpha-avail__tool">
        <label class="mbpha-avail__tool-label" for="avail-doctor">Doctor</label>
        <select id="avail-doctor" class="form-select form-select-sm" name="doctor_id" onchange="this.form.submit()">
          <option value="">All doctors</option>
          <?php foreach ($doctorOptions as $doctorOption): ?>
            <?php $optionDoctorId = (int) ($doctorOption['doctor_id'] ?? 0); ?>
            <option value="<?= $optionDoctorId ?>" <?= (int) ($filters['doctor_id'] ?? 0) === $optionDoctorId ? 'selected' : '' ?>><?= Helper::escape((string) ($doctorOption['full_name'] ?? 'Doctor')) ?><?= $optionDoctorId === $currentDoctorId ? ' (You)' : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mbpha-avail__tool">
        <label class="mbpha-avail__tool-label" for="avail-spec">Specialization</label>
        <select id="avail-spec" class="form-select form-select-sm" name="specialization" onchange="this.form.submit()">
          <option value="">All specializations</option>
          <?php foreach ($specializationOptions as $specializationOption): ?>
            <option value="<?= Helper::escape((string) $specializationOption) ?>" <?= ($filters['specialization'] ?? '') === $specializationOption ? 'selected' : '' ?>>
              <?= Helper::escape((string) $specializationOption) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mbpha-avail__tool">
        <noscript>
          <button type="submit" class="btn btn-sm btn-primary">Apply</button>
        </noscript>
        <a href="<?= Helper::url('/doctor/availability' . ($weekStart !== '' ? '?week=' . rawurlencode($weekStart) : '')) ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
      </div>
    </form>

    <div class="mbpha-avail__legend" aria-label="Schedule status">
      <span class="mbpha-avail__legend-item"><span class="mbpha-avail__legend-swatch is-available"></span>Available</span>
      <span class="mbpha-avail__legend-item"><span class="mbpha-avail__legend-swatch is-booked"></span>Booked</span>
      <span class="mbpha-avail__legend-item"><span class="mbpha-avail__legend-swatch is-empty"></span>Open time — click to add yours</span>
    </div>

    <?php if (!$hasVisibleSlots): ?>
      <div class="px-4 pb-3">
        <div class="alert alert-info mb-0" role="status">
          No consultation hours this week<?= $hasSharedFilters ? ' for the selected filter' : '' ?>.
          Try another week or add your own availability.
        </div>
      </div>
    <?php endif; ?>

    <div class="mbpha-avail__scroller">
      <div
        class="mbpha-avail__cal"
        style="--avail-rows: <?= (int) $intervalCount ?>;"
      >
        <div class="mbpha-avail__cal-head">
          <div class="mbpha-avail__cal-time-head">Time</div>
          <?php foreach ($days as $day): ?>
            <div class="mbpha-avail__cal-day-head<?= !empty($day['is_today']) ? ' is-today' : '' ?><?= !empty($day['is_past']) ? ' is-past' : '' ?><?= !empty($day['is_weekend']) ? ' is-weekend' : '' ?>">
              <span class="mbpha-avail__day-name"><?= Helper::escape((string) ($day['short'] ?? '')) ?></span>
              <span class="mbpha-avail__day-date"><?= Helper::escape((string) ($day['day_num'] ?? '')) ?> <?= Helper::escape((string) ($day['month_short'] ?? '')) ?></span>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="mbpha-avail__cal-body">
          <div class="mbpha-avail__cal-times" aria-hidden="true">
            <?php foreach ($intervals as $interval): ?>
              <div class="mbpha-avail__cal-time">
                <span><?= Helper::escape((string) ($interval['label'] ?? '')) ?></span>
              </div>
            <?php endforeach; ?>
          </div>

          <?php foreach ($days as $day): ?>
            <?php
            $date = (string) ($day['date'] ?? '');
            $dayBlocks = is_array($blocks[$date] ?? null) ? $blocks[$date] : [];
            $dayPast = !empty($day['is_past']);
            $isTodayCol = !empty($day['is_today']);
            $isWeekendCol = !empty($day['is_weekend']);
            $dayName = (string) ($day['name'] ?? '');
            ?>
            <div class="mbpha-avail__cal-day<?= $isTodayCol ? ' is-today-col' : '' ?><?= $isWeekendCol ? ' is-weekend-col' : '' ?><?= $dayPast ? ' is-past' : '' ?>">
              <div class="mbpha-avail__cal-lanes">
                <?php foreach ($intervals as $interval): ?>
                  <?php
                  $laneStart = (string) ($interval['start'] ?? '');
                  $laneEnd = (string) ($interval['end'] ?? '');
                  $laneEnded = $isTodayCol && $laneEnd !== '' && $nowHm !== '' && $laneEnd <= $nowHm;
                  $lanePast = $dayPast;
                  ?>
                  <button
                    type="button"
                    class="mbpha-avail__cal-lane<?= $lanePast ? ' is-past' : '' ?>"
                    data-avail-lane
                    data-date="<?= Helper::escape($date) ?>"
                    data-start="<?= Helper::escape($laneStart) ?>"
                    data-end="<?= Helper::escape($laneEnd) ?>"
                    data-day="<?= Helper::escape($dayName) ?>"
                    <?= $laneEnded ? 'data-ended="1"' : '' ?>
                    <?= $lanePast ? 'disabled' : '' ?>
                    aria-label="<?= Helper::escape('Add availability on ' . $dayName . ' from ' . (string) ($interval['label'] ?? '') . ' to ' . (string) ($interval['end_label'] ?? '')) ?>"
                  ></button>
                <?php endforeach; ?>
              </div>

              <div class="mbpha-avail__cal-blocks">
                <?php foreach ($dayBlocks as $block): ?>
                  <?php
                  $blockId = (int) ($block['id'] ?? 0);
                  $blockState = (string) ($block['state'] ?? 'available');
                  $blockLocked = !empty($block['locked']);
                  $blockPast = !empty($block['past']);
                  $blockOwned = !empty($block['owned']);
                  $blockColor = is_array($block['color'] ?? null) ? $block['color'] : [];
                  $doctorName = (string) ($block['full_name'] ?? 'Doctor');
                  $specialization = (string) ($block['specialization'] ?? '');
                  $statusLabel = $blockState === 'booked' ? 'Booked' : 'Available';
                  $ariaParts = array_filter([
                      (string) ($block['range_label'] ?? ''),
                      $doctorName,
                      $specialization,
                      $statusLabel,
                      $blockOwned ? 'your slot' : 'colleague slot',
                  ]);
                  ?>
                  <button
                    type="button"
                    class="mbpha-avail__block is-colored is-<?= Helper::escape($blockState) ?><?= $blockPast ? ' is-past' : '' ?><?= $blockOwned ? ' is-own' : ' is-foreign' ?>"
                    style="<?= Helper::escape(DoctorScheduleColor::inlineBlockStyle($blockColor, $block)) ?>"
                    data-avail-block
                    data-id="<?= $blockId ?>"
                    data-owned="<?= $blockOwned ? '1' : '0' ?>"
                    data-doctor-name="<?= Helper::escape($doctorName) ?>"
                    data-specialization="<?= Helper::escape($specialization) ?>"
                    data-date="<?= Helper::escape((string) ($block['date'] ?? $date)) ?>"
                    data-start="<?= Helper::escape((string) ($block['start'] ?? '')) ?>"
                    data-end="<?= Helper::escape((string) ($block['end'] ?? '')) ?>"
                    data-notes="<?= Helper::escape((string) ($block['notes'] ?? '')) ?>"
                    data-status="<?= Helper::escape((string) ($block['status'] ?? 'Available')) ?>"
                    data-locked="<?= $blockLocked ? '1' : '0' ?>"
                    <?php if ($blockState === 'available' && (string) ($block['expires_at'] ?? '') !== ''): ?>
                    data-slot-expires-at="<?= Helper::escape((string) $block['expires_at']) ?>"
                    <?php endif; ?>
                    aria-label="<?= Helper::escape(implode(', ', $ariaParts)) ?>"
                  >
                    <strong><?= Helper::escape((string) ($block['range_label'] ?? '')) ?></strong>
                    <span><?= Helper::escape($doctorName) ?></span>
                    <?php if ($specialization !== ''): ?>
                      <span class="mbpha-avail__block-spec"><?= Helper::escape($specialization) ?></span>
                    <?php endif; ?>
                    <span class="mbpha-avail__block-status"><?= Helper::escape($statusLabel) ?></span>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade availability-modal" id="availEditorModal" tabindex="-1" aria-labelledby="availEditorModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered availability-modal-dialog">
    <div class="modal-content availability-modal-content">
      <form
        method="POST"
        action="<?= Helper::url('/doctor/availability/create') ?>"
        data-avail-form
        data-create-action="<?= Helper::url('/doctor/availability/create') ?>"
        data-edit-action="<?= Helper::url('/doctor/availability/__ID__/edit') ?>"
        data-delete-action="<?= Helper::url('/doctor/availability/__ID__/delete') ?>"
      >
        <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
        <input type="hidden" name="return_week" value="<?= Helper::escape($weekStart) ?>">
        <input type="hidden" name="status" value="Available">
        <div class="modal-header availability-modal-header">
          <div class="availability-modal-heading">
            <span class="availability-modal-icon" aria-hidden="true">
              <i class="bi bi-calendar3"></i>
            </span>
            <h2 class="modal-title availability-modal-title" id="availEditorModalLabel">Add Availability</h2>
          </div>
          <button type="button" class="availability-modal-close" data-bs-dismiss="modal" aria-label="Close">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
          </button>
        </div>
        <div class="modal-body availability-modal-body">
          <p class="availability-helper" data-avail-modal-help>
            Enter any start and end time. The schedule will place this block using those times.
          </p>
          <div class="alert alert-warning d-none mb-3" role="alert" data-avail-booked-note>
            This time is booked and cannot be edited.
          </div>
          <div class="alert alert-info d-none mb-3" role="alert" data-avail-foreign-note>
            This slot belongs to another doctor. You can view it to avoid overlapping hours, but only they can change it.
          </div>
          <div class="availability-form">
            <div class="availability-field">
              <label class="form-label availability-label" for="avail-modal-date">
                <i class="bi bi-calendar3" aria-hidden="true"></i>
                Date<span class="availability-required">*</span>
              </label>
              <input type="date" class="form-control availability-input" id="avail-modal-date" name="consultation_date" min="<?= Helper::escape($todayDate) ?>" required>
            </div>
            <div class="availability-time-grid">
              <div class="availability-field">
                <label class="form-label availability-label" for="avail-modal-start">
                  <i class="bi bi-clock" aria-hidden="true"></i>
                  Start time<span class="availability-required">*</span>
                </label>
                <input type="time" class="form-control availability-input" id="avail-modal-start" name="start_time" step="60" required>
              </div>
              <div class="availability-field">
                <label class="form-label availability-label" for="avail-modal-end">
                  <i class="bi bi-clock" aria-hidden="true"></i>
                  End time<span class="availability-required">*</span>
                </label>
                <input type="time" class="form-control availability-input" id="avail-modal-end" name="end_time" step="60" required>
              </div>
            </div>
            <div class="availability-field">
              <label class="form-label availability-label" for="avail-modal-notes">
                <i class="bi bi-journal-text" aria-hidden="true"></i>
                Notes (optional)
              </label>
              <textarea class="form-control availability-notes" id="avail-modal-notes" name="notes" rows="3" maxlength="1000" placeholder="Add any additional notes here..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer availability-modal-footer">
          <button type="submit" form="avail-delete-form" class="btn availability-delete d-none" data-avail-delete>
            Delete
          </button>
          <button type="button" class="btn availability-cancel" data-bs-dismiss="modal">Cancel</button>
          <a class="btn availability-open-form d-none" data-avail-full-form href="<?= Helper::escape($createUrl) ?>" data-base-href="<?= Helper::escape($createUrl) ?>">Open full form</a>
          <button type="submit" class="btn availability-save" data-avail-save>
            <i class="bi bi-calendar-check" aria-hidden="true"></i>
            Save Availability
          </button>
        </div>
      </form>
      <form
        id="avail-delete-form"
        method="POST"
        action="#"
        class="d-none"
        data-avail-delete-form
        data-confirm-title="Delete availability"
        data-confirm-body="Remove this availability from your schedule?"
        data-confirm-action="Delete"
      >
        <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
        <input type="hidden" name="return_week" value="<?= Helper::escape($weekStart) ?>">
      </form>
    </div>
  </div>
</div>
