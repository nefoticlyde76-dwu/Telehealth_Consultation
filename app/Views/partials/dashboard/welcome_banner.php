<?php

/**
 * Shared dashboard welcome banner. Greeting and supporting copy only —
 * it does not change authorization or data.
 *
 * @var object|array|null $user
 * @var string $welcomeMessage
 * @var string $dashboardRole
 * @var string $dashboardRoleLabel
 * @var string $welcomeIcon
 * @var list<array{icon?:string,label:string}> $welcomePills
 */
use App\Helpers\Helper;

$welcomeRole = strtolower(trim((string) ($dashboardRole ?? '')));
if (!in_array($welcomeRole, ['admin', 'doctor', 'patient'], true)) {
    $welcomeRole = 'patient';
}

$welcomeUser = $user ?? null;
$welcomeFullName = '';
if (is_object($welcomeUser)) {
    $welcomeFullName = trim((string) ($welcomeUser->full_name ?? ''));
} elseif (is_array($welcomeUser)) {
    $welcomeFullName = trim((string) ($welcomeUser['full_name'] ?? ''));
}

$welcomeFirstName = $welcomeFullName;
if ($welcomeFullName !== '') {
    $nameParts = preg_split('/\s+/', $welcomeFullName) ?: [];
    $welcomeFirstName = (string) ($nameParts[0] ?? $welcomeFullName);
    $titleTokens = ['dr', 'mr', 'mrs', 'ms', 'prof'];
    $firstToken = strtolower(rtrim($welcomeFirstName, '.'));
    if (count($nameParts) > 1 && in_array($firstToken, $titleTokens, true)) {
        $welcomeFirstName = (string) $nameParts[1];
    }
}

$welcomeHour = (int) Helper::now()->format('G');
$welcomeGreeting = $welcomeHour < 12
    ? 'Good morning'
    : ($welcomeHour < 17 ? 'Good afternoon' : 'Good evening');

$welcomeTitle = $welcomeFirstName !== ''
    ? $welcomeGreeting . ', ' . $welcomeFirstName
    : $welcomeGreeting;

$welcomeDefaults = [
    'admin' => [
        'eyebrow' => 'Administrator',
        'message' => 'Review pending requests, manage accounts, and keep provincial care moving.',
        'icon' => 'bi-shield-check',
    ],
    'doctor' => [
        'eyebrow' => 'Doctor',
        'message' => "Here's what's happening with your practice today.",
        'icon' => 'bi-heart-pulse',
    ],
    'patient' => [
        'eyebrow' => 'Patient',
        'message' => 'Check your next consultation, track request status, and book care when you need it.',
        'icon' => 'bi-calendar2-heart',
    ],
];

$welcomeEyebrow = trim((string) ($dashboardRoleLabel ?? ''));
if ($welcomeEyebrow === '' || str_contains(strtolower($welcomeEyebrow), 'dashboard')) {
    $welcomeEyebrow = $welcomeDefaults[$welcomeRole]['eyebrow'];
}

$welcomeDescription = trim((string) ($welcomeMessage ?? ''));
if ($welcomeDescription === '') {
    $welcomeDescription = $welcomeDefaults[$welcomeRole]['message'];
}

$welcomeIcon = trim((string) ($welcomeIcon ?? ''));
if ($welcomeIcon === '') {
    $welcomeIcon = $welcomeDefaults[$welcomeRole]['icon'];
}

$welcomePills = is_array($welcomePills ?? null) ? $welcomePills : [];
$welcomeNow = Helper::now();
?>

<section class="ux-welcome ux-welcome--<?= Helper::escape($welcomeRole) ?>" aria-label="Welcome">
  <div class="ux-welcome__grid">
    <div class="ux-welcome__copy">
      <p class="ux-welcome__eyebrow"><?= Helper::escape($welcomeEyebrow) ?></p>
      <h2 class="ux-welcome__title"><?= Helper::escape($welcomeTitle) ?></h2>
      <p class="ux-welcome__description"><?= Helper::escape($welcomeDescription) ?></p>
      <div class="ux-welcome__meta-pill-row">
        <span class="ux-welcome__meta-pill">
          <i class="bi bi-calendar2" aria-hidden="true"></i>
          <span data-dashboard-datetime="longdate"><?= Helper::escape($welcomeNow->format('F j, Y')) ?></span>
        </span>
        <span class="ux-welcome__meta-pill ux-welcome__meta-pill--online">
          <i class="bi bi-circle-fill" aria-hidden="true"></i>
          You're online
        </span>
        <span class="ux-welcome__meta-pill">
          <i class="bi bi-clock" aria-hidden="true"></i>
          <span data-dashboard-datetime="time"><?= Helper::escape($welcomeNow->format('g:i A')) ?></span>
        </span>
        <?php foreach ($welcomePills as $pill): ?>
          <?php
          $pillLabel = trim((string) ($pill['label'] ?? ''));
          $pillIcon = trim((string) ($pill['icon'] ?? ''));
          if ($pillLabel === '') {
              continue;
          }
          ?>
          <span class="ux-welcome__meta-pill">
            <?php if ($pillIcon !== ''): ?>
              <i class="bi <?= Helper::escape($pillIcon) ?>" aria-hidden="true"></i>
            <?php endif; ?>
            <?= Helper::escape($pillLabel) ?>
          </span>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="ux-welcome__visual" aria-hidden="true">
      <p class="ux-welcome__tagline">Better Access. Healthier Communities.</p>
      <svg class="ux-welcome__motif" viewBox="0 0 160 120" width="180" height="135" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M38 18c0 8-6 14-14 14S10 26 10 18 16 4 24 4s14 6 14 14Z" stroke="#0A6FB6" stroke-width="4"/>
        <path d="M122 18c0 8-6 14-14 14s-14-6-14-14 6-14 14-14 14 6 14 14Z" stroke="#0A6FB6" stroke-width="4"/>
        <path d="M38 18v10c0 22 18 36 42 36" stroke="#17375E" stroke-width="4" stroke-linecap="round"/>
        <path d="M122 18v10c0 10-4 18-10 24" stroke="#17375E" stroke-width="4" stroke-linecap="round"/>
        <path d="M80 64v18" stroke="#17375E" stroke-width="4" stroke-linecap="round"/>
        <circle cx="80" cy="96" r="14" stroke="#18A558" stroke-width="4"/>
        <circle cx="80" cy="96" r="6" fill="#40C4FF"/>
      </svg>
    </div>
  </div>
</section>
