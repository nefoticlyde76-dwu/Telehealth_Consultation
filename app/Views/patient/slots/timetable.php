<?php

use App\Helpers\Helper;

$filters = is_array($filters ?? null) ? $filters : ['doctor_id' => 0, 'specialization' => '', 'consultation_date' => ''];
$weekStart = (string) ($weekStart ?? '');
$weekLabel = (string) ($weekLabel ?? '');
$isCurrentWeek = (bool) ($isCurrentWeek ?? false);
$prevWeek = (string) ($prevWeek ?? '');
$nextWeek = (string) ($nextWeek ?? '');
$thisWeek = (string) ($thisWeek ?? '');
$days = is_array($days ?? null) ? $days : [];
$intervals = is_array($intervals ?? null) ? $intervals : [];
$cells = is_array($cells ?? null) ? $cells : [];
$doctorOptions = is_array($doctorOptions ?? null) ? $doctorOptions : [];
$specializationOptions = is_array($specializationOptions ?? null) ? $specializationOptions : [];
$summary = is_array($summary ?? null) ? $summary : [];
$openCells = (int) ($openCells ?? 0);
$weekSlotCount = (int) ($weekSlotCount ?? 0);
$timezoneLabel = (string) ($timezoneLabel ?? Helper::appTimezoneLabel());

$slotsUrl = static function (array $query = []) use ($filters): string {
    $merged = [
        'doctor_id' => (int) ($query['doctor_id'] ?? $filters['doctor_id'] ?? 0),
        'specialization' => (string) ($query['specialization'] ?? $filters['specialization'] ?? ''),
        'week' => (string) ($query['week'] ?? ''),
    ];
    $merged = array_filter($merged, static fn ($value) => $value !== '' && $value !== 0);
    $path = '/patient/available-slots';
    if ($merged === []) {
        return Helper::url($path);
    }

    return Helper::url($path . '?' . http_build_query($merged));
};
?>

<section class="mb-4">
  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= Helper::url('/patient/dashboard') ?>">Dashboard</a></li>
        <li class="active">Available Slots</li>
      </ol>
      <h2 class="ux-page-header__title">Book a consultation</h2>
      <p class="ux-page-header__subtitle">
        Open hours are highlighted. Choose the time you want — you will confirm the booking on the next page.
      </p>
    </div>
    <div class="ux-page-header__right">
      <span class="ux-chip ux-badge--dotless">
        <i class="bi bi-clock"></i>
        <span><?= Helper::escape($timezoneLabel) ?></span>
      </span>
      <a href="<?= Helper::url('/patient/available-slots?view=list') ?>" class="btn btn-outline-primary">
        <i class="bi bi-list-ul me-2"></i>
        Slot list
      </a>
      <a href="<?= Helper::url('/patient/doctors') ?>" class="btn btn-outline-primary">
        <i class="bi bi-person-badge me-2"></i>
        Doctors
      </a>
    </div>
  </div>

  <?php require __DIR__ . '/../../partials/shared/alerts.php'; ?>
</section>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-xl-4">
    <div class="ux-stat compact d-flex align-items-center gap-3">
      <div class="ux-stat__icon ux-stat__icon--navy">
        <i class="bi bi-calendar2-check"></i>
      </div>
      <div>
        <div class="ux-stat__value"><?= $weekSlotCount ?></div>
        <div class="ux-stat__label">Open slots this week</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-4">
    <div class="ux-stat compact d-flex align-items-center gap-3">
      <div class="ux-stat__icon ux-stat__icon--success">
        <i class="bi bi-person-badge"></i>
      </div>
      <div>
        <div class="ux-stat__value"><?= (int) ($summary['available_doctors'] ?? 0) ?></div>
        <div class="ux-stat__label">Doctors with openings</div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-4">
    <div class="ux-stat compact d-flex align-items-center gap-3">
      <div class="ux-stat__icon ux-stat__icon--info">
        <i class="bi bi-grid-3x3"></i>
      </div>
      <div>
        <div class="ux-stat__value"><?= $openCells ?></div>
        <div class="ux-stat__label">Bookable times on this grid</div>
      </div>
    </div>
  </div>
</div>

<div class="mbpha-avail mbpha-avail--book">
  <div class="mbpha-avail__card">
    <div class="mbpha-avail__header">
      <div>
        <h3 class="mbpha-avail__title">Weekly schedule</h3>
        <p class="mbpha-avail__hint mb-0">Filter by doctor if you already have a preference. Empty cells are not available.</p>
      </div>
      <div class="mbpha-avail__week-nav" role="group" aria-label="Week navigation">
        <a class="mbpha-avail__week-btn" href="<?= $slotsUrl(['week' => $prevWeek]) ?>" aria-label="Previous week">
          <i class="bi bi-chevron-left" aria-hidden="true"></i>
        </a>
        <span class="mbpha-avail__week-label">
          <?= Helper::escape($isCurrentWeek ? 'This week' : 'Week of') ?>
          <?= Helper::escape($weekLabel) ?>
        </span>
        <a class="mbpha-avail__week-btn" href="<?= $slotsUrl(['week' => $thisWeek]) ?>">This week</a>
        <a class="mbpha-avail__week-btn" href="<?= $slotsUrl(['week' => $nextWeek]) ?>" aria-label="Next week">
          <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </a>
      </div>
    </div>

    <form method="GET" action="<?= Helper::url('/patient/available-slots') ?>" class="mbpha-avail__tools">
      <?php if ($weekStart !== ''): ?>
        <input type="hidden" name="week" value="<?= Helper::escape($weekStart) ?>">
      <?php endif; ?>
      <div class="mbpha-avail__tool">
        <label class="mbpha-avail__tool-label" for="book-doctor">Doctor</label>
        <select id="book-doctor" class="form-select form-select-sm" name="doctor_id">
          <option value="">All doctors</option>
          <?php foreach ($doctorOptions as $doctorOption): ?>
            <?php $optionDoctorId = (int) ($doctorOption['doctor_id'] ?? 0); ?>
            <option value="<?= $optionDoctorId ?>" <?= (int) ($filters['doctor_id'] ?? 0) === $optionDoctorId ? 'selected' : '' ?>>
              <?= Helper::escape((string) ($doctorOption['full_name'] ?? 'Doctor')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mbpha-avail__tool">
        <label class="mbpha-avail__tool-label" for="book-spec">Specialization</label>
        <select id="book-spec" class="form-select form-select-sm" name="specialization">
          <option value="">All specializations</option>
          <?php foreach ($specializationOptions as $specializationOption): ?>
            <option value="<?= Helper::escape((string) $specializationOption) ?>" <?= ($filters['specialization'] ?? '') === $specializationOption ? 'selected' : '' ?>>
              <?= Helper::escape((string) $specializationOption) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mbpha-avail__tool">
        <button type="submit" class="btn btn-sm btn-primary">Apply</button>
        <a href="<?= Helper::url('/patient/available-slots') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
      </div>
    </form>

    <?php if ($weekSlotCount === 0): ?>
      <div class="px-4 pb-3">
        <div class="alert alert-info mb-0" role="status">
          No open consultation hours this week<?= (int) ($filters['doctor_id'] ?? 0) > 0 || ($filters['specialization'] ?? '') !== '' ? ' for the selected filter' : '' ?>.
          Try another week, another doctor, or check the slot list.
        </div>
      </div>
    <?php endif; ?>

    <div class="mbpha-avail__legend" aria-label="Schedule legend">
      <span class="mbpha-avail__legend-item"><span class="mbpha-avail__legend-swatch is-available"></span>Available — tap to book</span>
      <span class="mbpha-avail__legend-item"><span class="mbpha-avail__legend-swatch is-empty"></span>Unavailable</span>
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
                $options = is_array($cell['options'] ?? null) ? $cell['options'] : [];
                $isPast = !empty($cell['past']) || !empty($day['is_past']);
                $isTodayCol = !empty($day['is_today']);
                $dayName = (string) ($day['name'] ?? '');
                $startLabel = (string) ($interval['label'] ?? '');
                $endLabel = (string) ($interval['end_label'] ?? '');
                ?>
                <td class="<?= $isTodayCol ? 'is-today-col' : '' ?>">
                  <?php if ($options === []): ?>
                    <span
                      class="mbpha-avail__cell is-empty<?= $isPast ? ' is-past' : '' ?>"
                      aria-label="<?= Helper::escape($dayName . ', ' . $startLabel . ' to ' . $endLabel . ', unavailable') ?>"
                    >
                      <span class="visually-hidden">Unavailable</span>
                    </span>
                  <?php else: ?>
                    <div class="mbpha-avail__book-stack">
                      <?php foreach ($options as $option): ?>
                        <?php
                        $slotId = (int) ($option['id'] ?? 0);
                        $doctorName = (string) ($option['full_name'] ?? 'Doctor');
                        $bookLabel = $dayName . ', ' . (string) ($option['start_label'] ?? $startLabel) . ' to ' . (string) ($option['end_label'] ?? $endLabel) . ', available with ' . $doctorName;
                        ?>
                        <a
                          class="mbpha-avail__cell is-available"
                          href="<?= Helper::url('/patient/consultation-requests/book/' . $slotId) ?>"
                          aria-label="<?= Helper::escape($bookLabel) ?>"
                        >
                          <span class="mbpha-avail__book-time"><?= Helper::escape((string) ($option['start_label'] ?? $startLabel)) ?></span>
                          <span class="mbpha-avail__book-name"><?= Helper::escape($doctorName) ?></span>
                          <span class="mbpha-avail__book-cta">Book</span>
                        </a>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
