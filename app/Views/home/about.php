<?php
defined('APP_VERSION') or define('APP_VERSION', '1.0.0');

$pageKicker = 'About MBPHA TeleHealth';
$pageTitleHtml = 'A provincial telehealth service for <span class="pp-intro__emphasis">Milne Bay.</span>';
$pageLedeHtml = 'MBPHA TeleHealth is the <span class="pp-intro__emphasis">Milne Bay Provincial Health Authority</span> platform for requesting, attending, and reviewing online consultations with <span class="pp-intro__emphasis">MBPHA doctors</span>.';
$pageLedeShortHtml = 'Official MBPHA platform for requesting and attending online consultations.';
?>
<?php require __DIR__ . '/../partials/public/navbar.php'; ?>

<main class="pp-page" aria-label="About MBPHA TeleHealth">
  <?php require __DIR__ . '/../partials/public/page_intro.php'; ?>

  <section class="pp-body">
    <div class="pp-wrap pp-about">
      <article class="pp-panel">
        <h2>What MBPHA TeleHealth is</h2>
        <p>
          It is a secure web system used by the Milne Bay Provincial Health Authority to run
          scheduled video consultations. Patients request a slot against a doctor’s published
          availability. An administrator reviews the request. The assigned doctor then conducts
          the consultation and records the outcome.
        </p>
        <p>
          The platform does not replace in-person care. It extends MBPHA consultation access
          for patients who can attend an approved online appointment instead of travelling
          into a facility.
        </p>

        <h2>Why it exists</h2>
        <p>
          Distance, travel cost, and limited clinic time make it harder for some patients in
          Milne Bay Province to see a doctor. MBPHA TeleHealth gives the health authority a
          controlled way to offer remote consultations while keeping booking, approval, and
          records inside one official system.
        </p>

        <h2>How it supports patients</h2>
        <ul class="pp-list">
          <li>Register once and sign in from a browser.</li>
          <li>View published doctor availability before requesting a slot.</li>
          <li>Track whether a booking is pending, approved, or cancelled.</li>
          <li>Join the approved video consultation at the scheduled time.</li>
          <li>Read the consultation record and prescription after the session, including PDF download where provided.</li>
        </ul>
      </article>

      <aside class="pp-aside" aria-label="Who the service is for">
        <h2>Who it serves</h2>
        <div class="pp-aside__item">
          <h3>Patients</h3>
          <p>People in Milne Bay Province who need to request and attend an MBPHA teleconsultation.</p>
        </div>
        <div class="pp-aside__item">
          <h3>MBPHA doctors</h3>
          <p>Clinicians who publish availability, join assigned consultations, and write consultation notes and prescriptions.</p>
        </div>
        <div class="pp-aside__item">
          <h3>Administrators</h3>
          <p>MBPHA staff who manage doctor accounts and approve, reject, or cancel booking requests.</p>
        </div>

        <div class="pp-aside__note">
          <h3>Role of MBPHA doctors</h3>
          <p>
            Consultations are doctor-led. Doctors do not approve bookings. They work from
            confirmed appointments, complete the clinical record, and close the consultation
            in the system.
          </p>
        </div>
      </aside>
    </div>
  </section>

  <?php require __DIR__ . '/../partials/home/cta.php'; ?>
</main>

<?php require __DIR__ . '/../partials/public/footer.php'; ?>
