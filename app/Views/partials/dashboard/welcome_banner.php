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
 */
use App\Helpers\Helper;
use App\Helpers\Status;

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

$welcomeNameLabel = $welcomeFirstName;
if ($welcomeRole === 'doctor' && $welcomeFirstName !== '') {
    $welcomeNameLabel = 'Dr. ' . $welcomeFirstName;
}

$welcomeDefaults = [
    'admin' => [
        'eyebrow' => 'Administrator',
        'message' => 'Monitor the platform, manage users, and oversee telehealth operations.',
        'icon' => 'bi-shield-check',
        'role_description' => 'Manage the MBPHA TeleHealth platform',
        'primary_label' => 'Manage System',
        'primary_url' => '/admin/users',
        'primary_icon' => 'bi-gear',
        'health_title' => 'Platform',
        'health_value' => 'Stay on track',
        'health_description' => 'Keep users, doctors, and requests in order',
        'health_url' => '/admin/consultation-requests',
        'health_icon' => 'bi-shield-plus',
    ],
    'doctor' => [
        'eyebrow' => 'Doctor',
        'message' => 'Manage consultations, review appointments, and stay connected with your patients.',
        'icon' => 'bi-heart-pulse',
        'role_description' => 'Manage consultations and patient care',
        'primary_label' => 'View Appointments',
        'primary_url' => '/doctor/consultations',
        'primary_icon' => 'bi-calendar2-check',
        'health_title' => "Today's Schedule",
        'health_value' => 'Stay on track',
        'health_description' => 'Review today’s booked and open slots',
        'health_url' => '/doctor/consultations?date=today',
        'health_icon' => 'bi-clipboard2-pulse',
    ],
    'patient' => [
        'eyebrow' => 'Patient',
        'message' => 'Browse doctors, choose an available slot, and submit a consultation request.',
        'icon' => 'bi-person',
        'role_description' => 'Access your health services and consultations',
        'primary_label' => 'Book Appointment',
        'primary_url' => '/patient/available-slots',
        'primary_icon' => 'bi-calendar2-plus',
        'health_title' => 'Your Health',
        'health_value' => 'Stay on track',
        'health_description' => 'Regular checkups keep you healthier',
        'health_url' => '/patient/consultation-requests',
        'health_icon' => 'bi-shield-plus',
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

$roleDescription = $welcomeDefaults[$welcomeRole]['role_description'];

$bannerAppointment = null;
if (is_array($nextAppointment ?? null) && ($nextAppointment['id'] ?? null)) {
    $bannerAppointment = $nextAppointment;
} elseif ($welcomeRole === 'patient' && is_array($latestRequest ?? null) && ($latestRequest['status'] ?? '') === Status::APPROVED) {
    $bannerAppointment = $latestRequest;
} elseif ($welcomeRole === 'doctor') {
    $upcomingApprovedAppointments = is_array($upcomingApprovedAppointments ?? null) ? $upcomingApprovedAppointments : [];
    if (isset($upcomingApprovedAppointments[0]) && is_array($upcomingApprovedAppointments[0])) {
        $bannerAppointment = $upcomingApprovedAppointments[0];
    }
}

$appointmentValue = 'No upcoming appointments';
$appointmentUrl = match ($welcomeRole) {
    'doctor' => '/doctor/consultations',
    'admin' => Status::filteredListUrl('/admin/consultation-requests', Status::PENDING),
    default => '/patient/consultation-requests',
};
$appointmentTitle = 'Next Appointment';
$appointmentDescription = 'Book a consultation when you need it';

if ($welcomeRole === 'admin') {
    $consultationSummary = is_array($consultationSummary ?? null) ? $consultationSummary : [];
    $pendingCount = (int) ($consultationSummary['pending_requests'] ?? 0);
    if ($pendingCount === 0 && is_array($stats ?? null)) {
        foreach ($stats as $stat) {
            if (!is_array($stat)) {
                continue;
            }
            $statLabel = strtolower((string) ($stat['label'] ?? ''));
            if (str_contains($statLabel, 'pending')) {
                $pendingCount = (int) preg_replace('/\D+/', '', (string) ($stat['value'] ?? '0'));
                break;
            }
        }
    }
    $appointmentTitle = 'Pending Reviews';
    $appointmentValue = $pendingCount === 1 ? '1 request waiting' : $pendingCount . ' requests waiting';
    $appointmentDescription = 'Consultation requests that need a decision';
} elseif (is_array($bannerAppointment)) {
    $appointmentDate = Helper::formatDate((string) ($bannerAppointment['consultation_date'] ?? ''), 'D, d M Y', '');
    $appointmentTime = substr((string) ($bannerAppointment['start_time'] ?? ''), 0, 5);
    $appointmentBits = array_values(array_filter([$appointmentDate, $appointmentTime !== '' ? $appointmentTime : '']));
    if ($appointmentBits !== []) {
        $appointmentValue = implode(' · ', $appointmentBits);
    }
    $appointmentId = (int) ($bannerAppointment['id'] ?? 0);
    if ($appointmentId > 0) {
        $appointmentUrl = $welcomeRole === 'doctor'
            ? '/doctor/consultations/' . $appointmentId
            : '/patient/consultation-requests/' . $appointmentId;
    }
    $appointmentDescription = 'Your next approved consultation';
}

$todaySummary = is_array($todaySummary ?? null) ? $todaySummary : [];
$healthTitle = $welcomeDefaults[$welcomeRole]['health_title'];
$healthValue = $welcomeDefaults[$welcomeRole]['health_value'];
$healthDescription = $welcomeDefaults[$welcomeRole]['health_description'];
$healthUrl = $welcomeDefaults[$welcomeRole]['health_url'];
$healthIcon = $welcomeDefaults[$welcomeRole]['health_icon'];

if ($welcomeRole === 'doctor') {
    $bookedToday = (int) ($todaySummary['booked_today_slots'] ?? 0);
    $healthValue = $bookedToday === 1 ? '1 booked today' : $bookedToday . ' booked today';
}

$primaryLabel = $welcomeDefaults[$welcomeRole]['primary_label'];
$primaryUrl = $welcomeDefaults[$welcomeRole]['primary_url'];
$primaryIcon = $welcomeDefaults[$welcomeRole]['primary_icon'];

$logoUrl = Helper::asset('images/LOGOS.png');
$visualUrl = Helper::asset('images/stetescope.png');
?>

<section class="dashboard-welcome-banner dashboard-welcome-banner--<?= Helper::escape($welcomeRole) ?>" aria-label="Welcome">
  <div class="dashboard-welcome-hero">
    <div class="row align-items-center g-3 g-xl-4 welcome-banner-hero-row">
      <div class="col-12 col-md-7 col-xl-6 welcome-banner-content">
        <p class="welcome-badge">
          <i class="bi bi-person" aria-hidden="true"></i>
          <span>Welcome Back</span>
        </p>
        <h1 class="welcome-greeting">
          <?= Helper::escape($welcomeGreeting) ?><?php if ($welcomeNameLabel !== ''): ?>, <span class="welcome-greeting__name"><?= Helper::escape($welcomeNameLabel) ?></span><?php endif; ?>
        </h1>
        <p class="welcome-description"><?= Helper::escape($welcomeDescription) ?></p>
      </div>

      <div class="col-12 col-md-5 col-xl-5 welcome-role-slot">
        <aside class="welcome-role-card">
          <span class="welcome-role-icon" aria-hidden="true">
            <i class="bi <?= Helper::escape($welcomeIcon) ?>"></i>
          </span>
          <div class="welcome-role-copy">
            <p class="welcome-role-card__title"><?= Helper::escape($welcomeEyebrow) ?></p>
            <p class="welcome-role-card__text"><?= Helper::escape($roleDescription) ?></p>
          </div>
        </aside>
      </div>
    </div>

    <div class="welcome-banner-visual" aria-hidden="true">
      <img class="welcome-banner-photo" src="<?= Helper::escape($visualUrl) ?>" alt="">
      <span class="welcome-banner-visual__fade"></span>
      <img class="welcome-banner-logo" src="<?= Helper::escape($logoUrl) ?>" alt="">
    </div>
  </div>

  <div class="welcome-banner-actions">
    <a href="<?= Helper::url($appointmentUrl) ?>" class="welcome-action-card welcome-action-card--appointment appointment-card">
      <span class="welcome-action-icon" aria-hidden="true"><i class="bi bi-calendar2"></i></span>
      <span class="welcome-action-content">
        <span class="welcome-action-title"><?= Helper::escape($appointmentTitle) ?></span>
        <span class="welcome-action-value"><?= Helper::escape($appointmentValue) ?></span>
        <span class="welcome-action-description"><?= Helper::escape($appointmentDescription) ?></span>
      </span>
      <i class="bi bi-chevron-right welcome-action-arrow" aria-hidden="true"></i>
    </a>

    <a href="<?= Helper::url($healthUrl) ?>" class="welcome-action-card welcome-action-card--health health-card">
      <span class="welcome-action-icon" aria-hidden="true"><i class="bi <?= Helper::escape($healthIcon) ?>"></i></span>
      <span class="welcome-action-content">
        <span class="welcome-action-title"><?= Helper::escape($healthTitle) ?></span>
        <span class="welcome-action-value"><?= Helper::escape($healthValue) ?></span>
        <span class="welcome-action-description"><?= Helper::escape($healthDescription) ?></span>
      </span>
      <i class="bi bi-chevron-right welcome-action-arrow" aria-hidden="true"></i>
    </a>

    <a href="<?= Helper::url('/contact') ?>" class="welcome-action-card welcome-action-card--support support-card">
      <span class="welcome-action-icon" aria-hidden="true"><i class="bi bi-headset"></i></span>
      <span class="welcome-action-content">
        <span class="welcome-action-title">Need Help?</span>
        <span class="welcome-action-value">Contact Support</span>
        <span class="welcome-action-description">We’re here for you</span>
      </span>
      <i class="bi bi-chevron-right welcome-action-arrow" aria-hidden="true"></i>
    </a>

    <a href="<?= Helper::url($primaryUrl) ?>" class="welcome-primary-action">
      <i class="bi <?= Helper::escape($primaryIcon) ?>" aria-hidden="true"></i>
      <span><?= Helper::escape($primaryLabel) ?></span>
      <i class="bi bi-arrow-right" aria-hidden="true"></i>
    </a>
  </div>
</section>
