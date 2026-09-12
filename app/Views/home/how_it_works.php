<?php
defined('APP_VERSION') or define('APP_VERSION', '1.0.0');

$pageKicker = 'How It Works';
$pageTitleHtml = 'From registration to <span class="pp-intro__emphasis">consultation records.</span>';
$pageLedeHtml = 'MBPHA TeleHealth follows a fixed process. Each step below is part of the <span class="pp-intro__emphasis">live system</span> — nothing extra has been added.';
$pageLedeShortHtml = 'Eight steps from registration to your consultation record.';
$pageIntroVisual = '';

$steps = [
    [
        'title' => 'Register',
        'icon' => 'bi-person',
        'text' => 'Create a patient account with your name, email, and a strong password. Doctors and administrators are issued accounts by MBPHA.',
    ],
    [
        'title' => 'Login',
        'icon' => 'bi-box-arrow-in-right',
        'text' => 'Sign in with the same email and password. Patients, doctors, and administrators each open a role-specific dashboard.',
    ],
    [
        'title' => 'Check doctor availability',
        'icon' => 'bi-calendar3',
        'text' => 'Patients view published doctor schedules before choosing a consultation time.',
    ],
    [
        'title' => 'Request a slot',
        'icon' => 'bi-calendar-plus',
        'text' => 'Submit a booking against an available window. The request is stored for administrator review — it is not confirmed automatically.',
    ],
    [
        'title' => 'Track approval',
        'icon' => 'bi-shield-check',
        'text' => 'Watch the request status from your bookings list: pending, approved, rejected, or cancelled.',
    ],
    [
        'title' => 'Join consultation',
        'icon' => 'bi-camera-video',
        'text' => 'When the appointment is approved and the time arrives, join the video session from the dashboard.',
    ],
    [
        'title' => 'Complete consultation',
        'icon' => 'bi-clipboard2-check',
        'text' => 'The assigned doctor conducts the session, writes the consultation record and prescription, and marks the consultation complete.',
    ],
    [
        'title' => 'Access consultation records',
        'icon' => 'bi-file-earmark-medical',
        'text' => 'After the doctor completes the session, patients can view the consultation record and prescription, and download the PDFs provided by the system.',
    ],
];
?>
<?php require __DIR__ . '/../partials/public/navbar.php'; ?>

<main class="pp-page" aria-label="How MBPHA TeleHealth works">
  <?php require __DIR__ . '/../partials/public/page_intro.php'; ?>

  <section class="pp-body">
    <div class="pp-wrap">
      <ol class="pp-steps">
        <?php foreach ($steps as $index => $step): ?>
          <li class="pp-step pp-step--<?= $index + 1 ?>">
            <span class="pp-step__num" aria-hidden="true"><?= $index + 1 ?></span>
            <span class="pp-step__icon" aria-hidden="true"><i class="bi <?= \App\Helpers\Helper::escape($step['icon']) ?>"></i></span>
            <div class="pp-step__body">
              <h2><?= \App\Helpers\Helper::escape($step['title']) ?></h2>
              <p><?= \App\Helpers\Helper::escape($step['text']) ?></p>
            </div>
            <i class="bi bi-chevron-right pp-step__arrow" aria-hidden="true"></i>
          </li>
        <?php endforeach; ?>
      </ol>

      <div class="pp-steps-cta">
        <a href="<?= \App\Helpers\Helper::url('/register') ?>" class="pp-cta__primary">Register as a patient</a>
        <a href="<?= \App\Helpers\Helper::url('/login') ?>" class="pp-cta__secondary pp-cta__secondary--light">Already registered? Login</a>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/../partials/public/footer.php'; ?>
