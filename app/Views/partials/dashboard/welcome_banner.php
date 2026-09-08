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

$welcomeTitle = $welcomeFirstName !== ''
    ? 'Welcome back, ' . $welcomeFirstName
    : 'Welcome back';

$welcomeDefaults = [
    'admin' => [
        'eyebrow' => 'Administrator',
        'message' => 'Review consultation requests, manage doctor and patient accounts, and keep the service operating smoothly.',
        'icon' => 'bi-heart-pulse',
    ],
    'doctor' => [
        'eyebrow' => 'Doctor',
        'message' => "Here's your day at a glance. Stay on top of your consultations and keep making a difference.",
        'icon' => 'bi-heart-pulse',
    ],
    'patient' => [
        'eyebrow' => 'Patient',
        'message' => 'Your health matters. Book a consultation, manage your appointments, and stay on top of your care — all in one place.',
        'icon' => 'bi-heart-pulse',
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
$welcomePhoto = Helper::asset('images/stethoscope-banner.jpg');
?>

<section class="ux-welcome ux-welcome--hero ux-welcome--<?= Helper::escape($welcomeRole) ?>" aria-label="Welcome">
  <div class="ux-welcome__media" aria-hidden="true">
    <img
      class="ux-welcome__photo"
      src="<?= Helper::escape($welcomePhoto) ?>"
      alt=""
      width="1290"
      height="861"
      decoding="async"
    >
  </div>
  <div class="ux-welcome__grid">
    <div class="ux-welcome__copy">
      <p class="ux-welcome__eyebrow"><?= Helper::escape($welcomeEyebrow) ?></p>
      <h2 class="ux-welcome__title"><?= Helper::escape($welcomeTitle) ?></h2>
      <p class="ux-welcome__description"><?= Helper::escape($welcomeDescription) ?></p>
      <?php if ($welcomePills !== []): ?>
        <div class="ux-welcome__meta-pill-row">
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
      <?php endif; ?>
    </div>
    <div class="ux-welcome__visual" aria-hidden="true">
      <div class="ux-welcome__lockup">
        <p class="ux-welcome__motto">Better access.<br>Healthier communities.</p>
        <svg class="ux-welcome__pulse" viewBox="0 0 132 28" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
          <path d="M2 16 H36 L42 16 L48 6 L56 24 L64 10 L70 16 H130" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
    </div>
  </div>
</section>
