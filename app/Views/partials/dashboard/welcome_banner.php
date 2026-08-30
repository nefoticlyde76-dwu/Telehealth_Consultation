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
        'message' => 'Review pending requests, manage accounts, and monitor recent activity.',
        'icon' => 'bi-shield-check',
    ],
    'doctor' => [
        'eyebrow' => 'Doctor',
        'message' => "See today's workload, upcoming consultations, and what needs your attention.",
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
?>

<section class="ux-welcome ux-welcome--<?= Helper::escape($welcomeRole) ?>" aria-label="Welcome">
  <div class="ux-welcome__grid">
    <div>
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
      <span class="ux-welcome__icon">
        <i class="bi <?= Helper::escape($welcomeIcon) ?>"></i>
      </span>
    </div>
  </div>
</section>
