<?php

use App\Helpers\Helper;

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
$timezoneLabel = (string) ($timezoneLabel ?? Helper::appTimezoneLabel());
$csrfToken = (string) ($csrfToken ?? '');
$gridStart = (string) ($gridStart ?? '08:00');
$gridEnd = (string) ($gridEnd ?? '22:00');
$todayDate = (string) ($todayDate ?? '');
$nowHm = (string) ($nowHm ?? '');
$intervalCount = max(1, count($intervals));

$availUrl = static function (array $query = []): string {
    $path = '/doctor/availability';
    if ($query === []) {
        return Helper::url($path);
    }

    return Helper::url($path . '?' . http_build_query($query));
};

$formatGridTime = static function (string $hm): string {
    $parsed = DateTimeImmutable::createFromFormat('H:i', $hm);
    return $parsed instanceof DateTimeImmutable ? $parsed->format('g:i A') : $hm;
};

$createUrl = Helper::url('/doctor/availability/create' . ($weekStart !== '' ? '?week=' . rawurlencode($weekStart) : ''));
?>

<section class="mb-4">
  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= Helper::url('/doctor/dashboard') ?>">Dashboard</a></li>
        <li class="active">Availability</li>
      </ol>
      <h2 class="ux-page-header__title">Doctor Availability</h2>
      <p class="ux-page-header__subtitle">
        Click a time on the grid for a quick start, or add any custom start and end time.
        Saved hours are placed on the schedule from their actual times. Booked appointments stay locked.
      </p>
    </div>
    <div class="ux-page-header__right">
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
        Slot list
      </a>
    </div>
  </div>

  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>
</section>

<div class="mbpha-avail" data-mbpha-avail data-today="<?= Helper::escape($todayDate) ?>" data-now="<?= Helper::escape($nowHm) ?>">
  <div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-4">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--navy">
          <i class="bi bi-calendar-check"></i>
        </div>
        <div>
          <div class="ux-stat__value"><?= (int) ($counts['available'] ?? 0) ?></div>
          <div class="ux-stat__label">Available this week</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--pending">
          <i class="bi bi-calendar2-event"></i>
        </div>
        <div>
          <div class="ux-stat__value"><?= (int) ($counts['booked'] ?? 0) ?></div>
          <div class="ux-stat__label">Booked appointments</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-4">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--success">
          <i class="bi bi-calendar3"></i>
        </div>
        <div>
          <div class="ux-stat__value"><?= (int) ($summary['upcoming_slots'] ?? 0) ?></div>
          <div class="ux-stat__label">Upcoming saved slots</div>
        </div>
      </div>
    </div>
  </div>

  <div class="mbpha-avail__card">
    <div class="mbpha-avail__header">
      <div>
        <h3 class="mbpha-avail__title">Weekly Schedule</h3>
        <p class="mbpha-avail__hint mb-0">
          The time labels are a guide only. Availability can start or end at any valid time.
          Hours shown: <?= Helper::escape($formatGridTime($gridStart)) ?> – <?= Helper::escape($formatGridTime($gridEnd)) ?>.
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

    <div class="mbpha-avail__legend" aria-label="Schedule legend">
      <span class="mbpha-avail__legend-item"><span class="mbpha-avail__legend-swatch is-available"></span>Available</span>
      <span class="mbpha-avail__legend-item"><span class="mbpha-avail__legend-swatch is-booked"></span>Booked</span>
      <span class="mbpha-avail__legend-item"><span class="mbpha-avail__legend-swatch is-empty"></span>Open time — click to add</span>
    </div>

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
                  ?>
                  <button
                    type="button"
                    class="mbpha-avail__block is-<?= Helper::escape($blockState) ?><?= $blockPast ? ' is-past' : '' ?>"
                    style="top: <?= Helper::escape((string) ($block['top_pct'] ?? 0)) ?>%; height: <?= Helper::escape((string) ($block['height_pct'] ?? 0)) ?>%;"
                    data-avail-block
                    data-id="<?= $blockId ?>"
                    data-date="<?= Helper::escape((string) ($block['date'] ?? $date)) ?>"
                    data-start="<?= Helper::escape((string) ($block['start'] ?? '')) ?>"
                    data-end="<?= Helper::escape((string) ($block['end'] ?? '')) ?>"
                    data-notes="<?= Helper::escape((string) ($block['notes'] ?? '')) ?>"
                    data-status="<?= Helper::escape((string) ($block['status'] ?? 'Available')) ?>"
                    data-locked="<?= $blockLocked ? '1' : '0' ?>"
                    <?php if ($blockState === 'available' && (string) ($block['expires_at'] ?? '') !== ''): ?>
                    data-slot-expires-at="<?= Helper::escape((string) $block['expires_at']) ?>"
                    <?php endif; ?>
                    aria-label="<?= Helper::escape((string) ($block['range_label'] ?? '') . ', ' . $blockState) ?>"
                  >
                    <strong><?= Helper::escape((string) ($block['range_label'] ?? '')) ?></strong>
                    <span><?= $blockState === 'booked' ? 'Booked' : 'Available' ?></span>
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

<div class="modal fade" id="availEditorModal" tabindex="-1" aria-labelledby="availEditorModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-3">
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
        <div class="modal-header border-bottom">
          <h2 class="modal-title h5" id="availEditorModalLabel">Add Availability</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted small mb-3" data-avail-modal-help>
            Enter any start and end time. The schedule will place this block using those times.
          </p>
          <div class="alert alert-warning d-none mb-3" role="alert" data-avail-booked-note>
            This time is booked and cannot be edited.
          </div>
          <div class="mb-3">
            <label class="form-label" for="avail-modal-date">Date</label>
            <input type="date" class="form-control" id="avail-modal-date" name="consultation_date" min="<?= Helper::escape($todayDate) ?>" required>
          </div>
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label" for="avail-modal-start">Start time</label>
              <input type="time" class="form-control" id="avail-modal-start" name="start_time" step="60" required>
            </div>
            <div class="col-sm-6">
              <label class="form-label" for="avail-modal-end">End time</label>
              <input type="time" class="form-control" id="avail-modal-end" name="end_time" step="60" required>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label" for="avail-modal-notes">Notes (optional)</label>
            <textarea class="form-control" id="avail-modal-notes" name="notes" rows="2" maxlength="1000"></textarea>
          </div>
        </div>
        <div class="modal-footer flex-wrap">
          <button type="submit" form="avail-delete-form" class="btn btn-outline-danger me-auto d-none" data-avail-delete>
            Delete
          </button>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <a class="btn btn-outline-primary d-none" data-avail-full-form href="<?= Helper::escape($createUrl) ?>" data-base-href="<?= Helper::escape($createUrl) ?>">Open full form</a>
          <button type="submit" class="btn btn-primary" data-avail-save>Save Availability</button>
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
