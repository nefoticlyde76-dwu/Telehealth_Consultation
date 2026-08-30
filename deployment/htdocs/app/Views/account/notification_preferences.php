<?php
use App\Helpers\Helper;

$csrfToken = (string) ($csrfToken ?? '');
$preferences = is_array($preferences ?? null) ? $preferences : [];
$dashboardRole = (string) ($dashboardRole ?? '');
$dashboardHome = match ($dashboardRole) {
    'admin' => '/admin/dashboard',
    'doctor' => '/doctor/dashboard',
    'patient' => '/patient/dashboard',
    default => '/',
};
?>

<section class="mb-4">
  <?php require __DIR__ . '/../partials/shared/alerts.php'; ?>

  <div class="ux-page-header">
    <div class="ux-page-header__left">
      <ol class="ux-breadcrumb">
        <li><a href="<?= Helper::url($dashboardHome) ?>">Dashboard</a></li>
        <li class="active">Notification preferences</li>
      </ol>
      <h2 class="ux-page-header__title">Notification preferences</h2>
      <p class="ux-page-header__subtitle">Control optional in-app notices. Security and system notices stay on.</p>
    </div>
  </div>
</section>

<div class="ux-card" style="max-width: 640px;">
  <div class="card-body p-4">
    <form method="POST" action="<?= Helper::url('/account/notifications/preferences') ?>">
      <input type="hidden" name="_token" value="<?= Helper::escape($csrfToken) ?>">

      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="pref_system" checked disabled>
        <label class="form-check-label" for="pref_system">
          System and security notices
          <span class="d-block small text-muted">Required. These cannot be turned off.</span>
        </label>
      </div>

      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" value="1" id="appointment_in_app" name="appointment_in_app" <?= !empty($preferences['appointment_in_app']) ? 'checked' : '' ?>>
        <label class="form-check-label" for="appointment_in_app">
          Appointment notices
          <span class="d-block small text-muted">Booking created, approved, assigned, rejected, and upcoming reminders.</span>
        </label>
      </div>

      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" value="1" id="consultation_in_app" name="consultation_in_app" <?= !empty($preferences['consultation_in_app']) ? 'checked' : '' ?>>
        <label class="form-check-label" for="consultation_in_app">
          Consultation notices
          <span class="d-block small text-muted">Completed visits and available prescriptions.</span>
        </label>
      </div>

      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" value="1" id="email_enabled" name="email_enabled" <?= !empty($preferences['email_enabled']) ? 'checked' : '' ?>>
        <label class="form-check-label" for="email_enabled">
          Email notices where the system already sends email
          <span class="d-block small text-muted">Does not disable in-app notices.</span>
        </label>
      </div>

      <div class="form-check mb-4">
        <input class="form-check-input" type="checkbox" value="1" id="sms_enabled" name="sms_enabled" <?= !empty($preferences['sms_enabled']) ? 'checked' : '' ?>>
        <label class="form-check-label" for="sms_enabled">
          SMS notices (reserved)
          <span class="d-block small text-muted">Stored for future use. This application does not currently send SMS.</span>
        </label>
      </div>

      <button type="submit" class="btn btn-primary btn-sm">Save preferences</button>
    </form>
  </div>
</div>
