<?php
/**
 * Landing hero — copy and consultation glance card.
 */
defined('APP_VERSION') or define('APP_VERSION', '1.0.0');
?>
<section id="home" class="lp-hero-section" aria-labelledby="hero-title">
    <div class="lp-hero">
        <div class="lp-hero__surface" aria-hidden="true"></div>

        <div class="lp-hero__layout">
            <div class="lp-hero__copy">
                <span class="lp-hero__badge">
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    <span class="lp-hero__badge-full">Milne Bay Provincial Health Authority</span>
                    <span class="lp-hero__badge-short">MBPHA</span>
                </span>

                <h1 id="hero-title" class="lp-hero__title">
                    TeleHealth consultation with MBPHA doctors, <span class="lp-hero__emphasis">online.</span>
                </h1>

                <p class="lp-hero__lede lp-hero__lede--full">
                    A secure, simple way for patients in <span class="lp-hero__emphasis">Milne Bay Province</span> to request an appointment
                    with an <span class="lp-hero__emphasis">MBPHA doctor</span>, attend an approved online consultation, and access their
                    consultation records — without travelling into a health facility.
                </p>
                <p class="lp-hero__lede lp-hero__lede--short">
                    Request a consultation with an MBPHA doctor without travelling to a facility.
                </p>

                <ul class="lp-hero__pills" aria-label="Platform features">
                    <li>Secure platform access</li>
                    <li>Doctor-led consultations</li>
                    <li>Consultation records</li>
                </ul>

                <div class="lp-hero__actions">
                    <a href="<?= $_u('/register') ?>" class="lp-hero__cta lp-hero__cta--primary">
                        <i class="bi bi-person-plus-fill" aria-hidden="true"></i>
                        Register<span class="lp-hero__cta-rest"> as a Patient</span>
                    </a>
                    <a href="<?= $_u('/login') ?>" class="lp-hero__cta lp-hero__cta--secondary">
                        <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                        Login
                    </a>
                </div>

                <div class="lp-hero__support" aria-label="Additional service information">
                    <p class="lp-hero__support-row">
                        <i class="bi bi-person-check" aria-hidden="true"></i>
                        <strong>Doctor availability</strong>
                        <span class="lp-hero__sep" aria-hidden="true"></span>
                        <span>Viewed before booking</span>
                    </p>
                    <p class="lp-hero__support-row">
                        <i class="bi bi-broadcast" aria-hidden="true"></i>
                        <strong>Online consultations</strong>
                        <span class="lp-hero__sep" aria-hidden="true"></span>
                        <span>Conducted by MBPHA doctors</span>
                    </p>
                </div>
            </div>

            <aside class="lp-hero__glance" aria-label="Consultation overview">
                <p class="lp-hero__glance-kicker">Consultation at a glance</p>
                <h2 class="lp-hero__glance-title">What you can do today</h2>
                <p class="lp-hero__glance-copy">
                    Find an available doctor, request a slot, track approval, and join your online session in one place.
                </p>
                <div class="lp-hero__glance-grid" role="list">
                    <div class="lp-hero__glance-item" role="listitem">
                        <span class="lp-hero__glance-icon" aria-hidden="true"><i class="bi bi-calendar3"></i></span>
                        <span>
                            <span class="lp-hero__glance-label">Availability</span>
                            <strong>Doctor schedule</strong>
                        </span>
                    </div>
                    <div class="lp-hero__glance-item" role="listitem">
                        <span class="lp-hero__glance-icon" aria-hidden="true"><i class="bi bi-calendar-plus"></i></span>
                        <span>
                            <span class="lp-hero__glance-label">Booking</span>
                            <strong>Request a slot</strong>
                        </span>
                    </div>
                    <div class="lp-hero__glance-item" role="listitem">
                        <span class="lp-hero__glance-icon lp-hero__glance-icon--green" aria-hidden="true"><i class="bi bi-calendar2-check"></i></span>
                        <span>
                            <span class="lp-hero__glance-label">Approval</span>
                            <strong>Status updates</strong>
                        </span>
                    </div>
                    <div class="lp-hero__glance-item" role="listitem">
                        <span class="lp-hero__glance-icon" aria-hidden="true"><i class="bi bi-chat-dots"></i></span>
                        <span>
                            <span class="lp-hero__glance-label">Consultation</span>
                            <strong>Online session</strong>
                        </span>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</section>
