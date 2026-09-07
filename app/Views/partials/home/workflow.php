<?php
/**
 * Landing workflow — three-step telehealth path.
 */
defined('APP_VERSION') or define('APP_VERSION', '1.0.0');
?>
<section id="services" class="lp-workflow" aria-labelledby="services-title">
    <div class="container lp-workflow__inner">
        <div class="lp-workflow__heading">
            <span class="lp-workflow__badge">
                <i class="bi bi-plus-circle" aria-hidden="true"></i>
                What the platform provides
            </span>
            <h2 id="services-title" class="lp-workflow__title">
                <span class="lp-workflow__title-full">The complete telehealth consultation workflow.</span>
                <span class="lp-workflow__title-short">How a consultation works</span>
            </h2>
            <p class="lp-workflow__copy">
                From finding an available doctor, through an approved consultation, to reading the record afterward.
            </p>
        </div>

        <div class="lp-workflow__track">
            <article class="lp-workflow__card">
                <div class="lp-workflow__icon lp-workflow__icon--blue" aria-hidden="true">
                    <i class="bi bi-person-badge"></i>
                </div>
                <h3>Find a Doctor</h3>
                <p>View available MBPHA doctors and their published consultation times.</p>
            </article>
            <article class="lp-workflow__card">
                <div class="lp-workflow__icon lp-workflow__icon--green" aria-hidden="true">
                    <i class="bi bi-calendar2-plus"></i>
                </div>
                <h3>Book an Appointment</h3>
                <p>Request a slot from the doctor’s published availability for administrator review.</p>
            </article>
            <article class="lp-workflow__card">
                <div class="lp-workflow__icon lp-workflow__icon--purple" aria-hidden="true">
                    <i class="bi bi-journal-medical"></i>
                </div>
                <h3>Access Your Records</h3>
                <p>View and download your consultation records after the session is complete.</p>
            </article>
        </div>

        <p class="lp-workflow__more">
            <a href="<?= \App\Helpers\Helper::url('/how-it-works') ?>">See the full process</a>
        </p>
    </div>
</section>
