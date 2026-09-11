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
  <div class="ux-welcome__atmosphere" aria-hidden="true">
    <span class="ux-welcome__land"></span>
    <span class="ux-welcome__plus ux-welcome__plus--1">+</span>
    <span class="ux-welcome__plus ux-welcome__plus--2">+</span>
    <span class="ux-welcome__plus ux-welcome__plus--3">+</span>
    <span class="ux-welcome__plus ux-welcome__plus--4">+</span>
  </div>

  <div class="ux-welcome__grid">
    <div class="ux-welcome__copy">
      <p class="ux-welcome__eyebrow">
        <i class="bi <?= Helper::escape($welcomeIcon) ?>" aria-hidden="true"></i>
        <span><?= Helper::escape($welcomeEyebrow) ?></span>
      </p>
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
      <svg class="ux-welcome__motif" viewBox="0 0 220 200" width="220" height="200" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path class="ux-welcome__motif-line" d="M52 58c0 46 28 72 68 72"/>
        <path class="ux-welcome__motif-line" d="M168 58c0 22-8 40-22 52"/>
        <path class="ux-welcome__motif-line" d="M120 130v22"/>
        <circle class="ux-welcome__motif-node" cx="52" cy="36" r="20"/>
        <circle class="ux-welcome__motif-node" cx="168" cy="36" r="20"/>
        <circle class="ux-welcome__motif-hub" cx="120" cy="172" r="22"/>
        <path class="ux-welcome__motif-cross" d="M120 160v24M108 172h24"/>
      </svg>
    </div>
  </div>
</section>
