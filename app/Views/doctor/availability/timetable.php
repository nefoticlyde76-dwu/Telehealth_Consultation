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
$cells = is_array($cells ?? null) ? $cells : [];
$timeOptions = is_array($timeOptions ?? null) ? $timeOptions : [];
$applyWeekOptions = is_array($applyWeekOptions ?? null) ? $applyWeekOptions : [];
$counts = is_array($counts ?? null) ? $counts : [];
$summary = is_array($summary ?? null) ? $summary : [];
$timezoneLabel = (string) ($timezoneLabel ?? Helper::appTimezoneLabel());
$csrfToken = (string) ($csrfToken ?? '');
$gridStart = (string) ($gridStart ?? '06:00');
$gridEnd = (string) ($gridEnd ?? '20:00');

$availUrl = static function (array $query = []) : string {
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
        Select the 30-minute time slots when patients can book consultations.
        This is your weekly schedule. Existing appointments automatically override available slots.
      </p>
    </div>
    <div class="ux-page-header__right">
      <span class="ux-chip ux-badge--dotless">
        <i class="bi bi-clock"></i>
        <span><?= Helper::escape($timezoneLabel) ?></span>
      </span>
      <a href="<?= $availUrl(['view' => 'list']) ?>" class="btn btn-outline-primary">
        <i class="bi bi-list-ul me-2"></i>
        Slot list
      </a>
    </div>
  </div>

  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>
</section>

<form
  method="POST"
  action="<?= Helper::url('/doctor/availability/week') ?>"
  class="mbpha-avail"
  data-mbpha-avail
  novalidate
>
  <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">
  <input type="hidden" name="week_start" value="<?= Helper::escape($weekStart) ?>">
  <div data-avail-slots>
    <?php foreach ($cells as $cell): ?>
      <?php if (($cell['state'] ?? '') === 'available' || ($cell['state'] ?? '') === 'custom'): ?>
        <input
          type="hidden"
          name="slots[]"
          value="<?= Helper::escape((string) ($cell['date'] ?? '') . '|' . (string) ($cell['start'] ?? '')) ?>"
        >
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--navy">
          <i class="bi bi-calendar-check"></i>
        </div>
        <div>
          <div class="ux-stat__value" data-avail-count="available"><?= (int) ($counts['available'] ?? 0) ?></div>
          <div class="ux-stat__label">Available this week</div>
        </div>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
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
    <div class="col-sm-6 col-xl-3">
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
    <div class="col-sm-6 col-xl-3">
      <div class="ux-stat compact d-flex align-items-center gap-3">
        <div class="ux-stat__icon ux-stat__icon--info">
          <i class="bi bi-hourglass-split"></i>
        </div>
        <div>
          <div class="ux-stat__value" data-avail-dirty>Saved</div>
          <div class="ux-stat__label">Schedule status</div>
        </div>
      </div>
    </div>
  </div>

  <div class="mbpha-avail__card">
    <div class="mbpha-avail__header">
      <div>
        <h3 class="mbpha-avail__title">Weekly Schedule</h3>
        <p class="mbpha-avail__hint mb-0">
          Click a cell to toggle a 30-minute slot. Drag down a day to select a range.
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
      <span class="mbpha-avail__legend-item"><span class="mbpha-avail__legend-swatch is-dirty"></span>Unsaved change</span>
      <span class="mbpha-avail__legend-item"><span class="mbpha-avail__legend-swatch is-booked"></span>Booked</span>
      <span class="mbpha-avail__legend-item"><span class="mbpha-avail__legend-swatch is-custom"></span>Existing custom slot</span>
      <span class="mbpha-avail__legend-item"><span class="mbpha-avail__legend-swatch is-empty"></span>Unavailable</span>
    </div>

    <div class="mbpha-avail__tools">
      <div class="mbpha-avail__tool">
        <span class="mbpha-avail__tool-label">Set working hours</span>
        <label class="visually-hidden" for="avail-range-day">Day</label>
        <select id="avail-range-day" class="form-select form-select-sm" data-avail-range-day>
          <?php foreach ($days as $day): ?>
            <option value="<?= Helper::escape((string) ($day['date'] ?? '')) ?>" <?= !empty($day['is_today']) ? 'selected' : '' ?>>
              <?= Helper::escape((string) ($day['name'] ?? '')) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <label class="visually-hidden" for="avail-range-start">Start time</label>
        <select id="avail-range-start" class="form-select form-select-sm" data-avail-range-start>
          <?php foreach ($timeOptions as $option): ?>
            <option value="<?= Helper::escape((string) ($option['value'] ?? '')) ?>" <?= (($option['value'] ?? '') === '08:00') ? 'selected' : '' ?>>
              <?= Helper::escape((string) ($option['label'] ?? '')) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <label class="visually-hidden" for="avail-range-end">End time</label>
        <select id="avail-range-end" class="form-select form-select-sm" data-avail-range-end>
          <?php foreach ($timeOptions as $option): ?>
            <option value="<?= Helper::escape((string) ($option['value'] ?? '')) ?>" <?= (($option['value'] ?? '') === '16:00') ? 'selected' : '' ?>>
              <?= Helper::escape((string) ($option['label'] ?? '')) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="button" class="btn btn-sm btn-outline-primary" data-avail-apply-range>Apply</button>
      </div>

      <div class="mbpha-avail__tool">
        <span class="mbpha-avail__tool-label">Copy day</span>
        <label class="visually-hidden" for="avail-copy-from">Copy from</label>
        <select id="avail-copy-from" class="form-select form-select-sm" data-avail-copy-from>
          <?php foreach ($days as $day): ?>
            <option value="<?= Helper::escape((string) ($day['date'] ?? '')) ?>">
              <?= Helper::escape((string) ($day['name'] ?? '')) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <span class="mbpha-avail__tool-sep">to</span>
        <label class="visually-hidden" for="avail-copy-to">Copy to</label>
        <select id="avail-copy-to" class="form-select form-select-sm" data-avail-copy-to>
          <?php foreach ($days as $index => $day): ?>
            <option value="<?= Helper::escape((string) ($day['date'] ?? '')) ?>" <?= $index === 1 ? 'selected' : '' ?>>
              <?= Helper::escape((string) ($day['name'] ?? '')) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="button" class="btn btn-sm btn-outline-primary" data-avail-copy-day>Copy</button>
      </div>

      <div class="mbpha-avail__tool">
        <span class="mbpha-avail__tool-label">Clear</span>
        <label class="visually-hidden" for="avail-clear-day">Clear day</label>
        <select id="avail-clear-day" class="form-select form-select-sm" data-avail-clear-day>
          <?php foreach ($days as $day): ?>
            <option value="<?= Helper::escape((string) ($day['date'] ?? '')) ?>">
              <?= Helper::escape((string) ($day['name'] ?? '')) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-avail-clear-one>Clear day</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-avail-clear-week>Clear week</button>
      </div>
    </div>

    <div class="mbpha-avail__scroller">
      <table class="mbpha-avail__table">
        <thead>
          <tr>
            <th scope="col">Time</th>
            <?php foreach ($days as $day): ?>
              <th
                scope="col"
                class="<?= !empty($day['is_today']) ? 'is-today' : '' ?><?= !empty($day['is_past']) ? ' is-past' : '' ?>"
              >
                <span class="mbpha-avail__day-name"><?= Helper::escape((string) ($day['short'] ?? '')) ?></span>
                <span class="mbpha-avail__day-date"><?= Helper::escape((string) ($day['day_num'] ?? '')) ?> <?= Helper::escape((string) ($day['month_short'] ?? '')) ?></span>
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($intervals as $interval): ?>
            <tr>
              <th scope="row">
                <span><?= Helper::escape((string) ($interval['label'] ?? '')) ?></span>
              </th>
              <?php foreach ($days as $day): ?>
                <?php
                $cellKey = ((string) ($day['date'] ?? '')) . '|' . ((string) ($interval['start'] ?? ''));
                $cell = $cells[$cellKey] ?? [];
                $state = (string) ($cell['state'] ?? 'empty');
                $locked = !empty($cell['locked']);
                $isPast = !empty($cell['past']);
                $dayName = (string) ($day['name'] ?? '');
                $startLabel = (string) ($interval['label'] ?? '');
                $endLabel = (string) ($interval['end_label'] ?? '');
                $stateLabel = (string) ($cell['label'] ?? 'unavailable');
                $ariaLabel = $dayName . ', ' . $startLabel . ' to ' . $endLabel . ', ' . $stateLabel;
                $isTodayCol = !empty($day['is_today']);
                $selected = $state === 'available';
                ?>
                <td class="<?= $isTodayCol ? 'is-today-col' : '' ?>">
                  <button
                    type="button"
                    class="mbpha-avail__cell is-<?= Helper::escape($state) ?><?= $state === 'empty' ? ' is-empty' : '' ?><?= $isPast ? ' is-past' : '' ?>"
                    data-avail-cell
                    data-date="<?= Helper::escape((string) ($day['date'] ?? '')) ?>"
                    data-start="<?= Helper::escape((string) ($interval['start'] ?? '')) ?>"
                    data-end="<?= Helper::escape((string) ($interval['end'] ?? '')) ?>"
                    data-day="<?= Helper::escape($dayName) ?>"
                    data-start-label="<?= Helper::escape($startLabel) ?>"
                    data-end-label="<?= Helper::escape($endLabel) ?>"
                    data-state="<?= Helper::escape($state) ?>"
                    data-saved="<?= $selected ? '1' : '0' ?>"
                    <?= $locked ? 'data-locked="1" disabled' : '' ?>
                    aria-pressed="<?= $selected ? 'true' : 'false' ?>"
                    aria-label="<?= Helper::escape($ariaLabel) ?>"
                  >
                    <?php if ($state === 'available'): ?>
                      <i class="bi bi-check-lg" aria-hidden="true"></i>
                      <span>Available</span>
                    <?php elseif ($state === 'booked'): ?>
                      <i class="bi bi-lock-fill" aria-hidden="true"></i>
                      <span>Booked</span>
                    <?php elseif ($state === 'custom'): ?>
                      <i class="bi bi-slash-circle" aria-hidden="true"></i>
                      <span>Custom</span>
                    <?php else: ?>
                      <span class="visually-hidden">Unavailable</span>
                    <?php endif; ?>
                  </button>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="mbpha-avail__footer">
      <div class="mbpha-avail__apply">
        <label class="form-label mb-0" for="avail-apply-weeks">Also apply to</label>
        <select id="avail-apply-weeks" name="apply_weeks" class="form-select form-select-sm">
          <?php foreach ($applyWeekOptions as $option): ?>
            <option value="<?= (int) ($option['value'] ?? 1) ?>">
              <?= Helper::escape((string) ($option['label'] ?? '')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-primary-xl" data-avail-save>
        <i class="bi bi-check2-circle me-2" aria-hidden="true"></i>
        Save Availability
      </button>
    </div>
  </div>
</form>
