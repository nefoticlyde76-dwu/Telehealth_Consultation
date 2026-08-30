<?php

$calendarEvents = is_array($calendarEvents ?? null) ? $calendarEvents : [];
$calendarTitle = (string) ($calendarTitle ?? 'Upcoming Appointments');
$calendarEventsJson = json_encode(
    $calendarEvents,
    JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
);
if (!is_string($calendarEventsJson)) {
    $calendarEventsJson = '[]';
}
?>
<div
  class="mbpha-cal"
  data-mbpha-calendar
  data-calendar-events="<?= $calendarEventsJson ?>"
  role="application"
  aria-label="Consultation calendar"
>
  <div class="mbpha-cal__hd">
    <button type="button" class="mbpha-cal__nav" data-calendar-prev aria-label="Previous month">
      <i class="bi bi-chevron-left" aria-hidden="true"></i>
    </button>
    <div class="mbpha-cal__month-wrap">
      <p class="mbpha-cal__month" data-calendar-month></p>
      <p class="mbpha-cal__year" data-calendar-year></p>
    </div>
    <button type="button" class="mbpha-cal__nav" data-calendar-next aria-label="Next month">
      <i class="bi bi-chevron-right" aria-hidden="true"></i>
    </button>
  </div>

  <div class="mbpha-cal__weekdays" aria-hidden="true">
    <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
  </div>

  <div class="mbpha-cal__grid" data-calendar-grid role="grid" aria-label="Calendar dates"></div>

  <div class="mbpha-cal__events-hd"><?= \App\Helpers\Helper::escape($calendarTitle) ?></div>
  <div class="mbpha-cal__events" data-calendar-events-list aria-live="polite"></div>
</div>
