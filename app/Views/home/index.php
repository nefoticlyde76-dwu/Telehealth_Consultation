<?php
/**
 * Public landing / home page view.
 *
 * Implements the approved 6-section landing structure:
 *  1. Hero (Login + Register primary CTAs)
 *  2. What MBPHA TeleHealth provides (actual telehealth workflow only)
 *  3. How It Works (clean 6-step visual flow)
 *  4. About MBPHA TeleHealth (concise, authority-appropriate)
 *  5. Contact (retained existing inquiry form; NOT a booking form)
 *  6. Login / Register final CTA banner
 *
 *  Content is accurate to the implemented system (no invented doctors,
 *  statistics, partnerships, testimonials or services).
 *
 * This view is rendered by HomeController::index().
 * The public navbar/footer partials are rendered by the layout shell.
 *
 * Expected view variables (from HomeController):
 *   $csrf_token  — CSRF token for the contact form
 *   $contact_errors  ?array  — validation errors keyed by field (last submit)
 *   $contact_status  ?string — 'success' for a successful prior POST
 *   $contact_old_input  ?array — previously submitted values for re-population
 */

defined('APP_VERSION') or define('APP_VERSION', '1.0.0');

$helperClass = \App\Helpers\Helper::class;
$old = $contactOldInput ?? $contact_old_input ?? [];
$errors = $contactErrors ?? $contact_errors ?? [];
$status = $contactStatus ?? $contact_status ?? null;
$csrf_token_in_view = $csrfToken ?? $csrf_token ?? '';

$has = static function (string $key, array $bag = null): bool {
    return (bool)($bag[$key] ?? false);
};
$val = static function (string $key, string $default = '') use ($old): string {
    $v = $old[$key] ?? $default;
    return is_string($v) ? $v : $default;
};
$_u = static function (string $path = '') use ($helperClass): string {
    return $helperClass::url($path);
};
$_e = static function (string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
};

$statusType = null;
$statusMessage = null;
if (is_array($status) && isset($status['type'], $status['message'])) {
    $statusType = $status['type'];
    $statusMessage = (string) $status['message'];
} elseif (is_string($status) && $status !== '') {
    $statusType = $status;
    $statusMessage = null;
}
$hasValidationErrors = !empty($errors) && (isset($errors[0]) || !array_is_list($errors));
?>

<?php require __DIR__ . '/../partials/public/navbar.php'; ?>

<main class="public-shell" aria-label="MBPHA TeleHealth home">

    <!-- ======================================================
         SECTION 1 — HERO / WELCOME
         Strongest section of the page. Primary CTA = Register,
         secondary = Login. No invented content.
         ====================================================== -->
    <section id="hero" class="hero-section hero-section-agh reveal-on-scroll reveal-slide-up" aria-labelledby="hero-title">
        <div class="container px-4">
            <div class="row g-4 align-items-center">
                <div class="col-xl-7 col-lg-7">
                    <span class="public-eyebrow public-eyebrow--inverse mb-3">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                        Milne Bay Provincial Health Authority
                    </span>

                    <h1 id="hero-title" class="hero-title">
                        TeleHealth consultation with MBPHA doctors, online.
                    </h1>

                    <p class="hero-copy">
                        A secure, simple way for patients in Milne Bay Province to request an appointment
                        with an MBPHA doctor, attend an approved online consultation, and access their
                        consultation records — without travelling into a health facility.
                    </p>

                    <div class="hero-trust-row" aria-label="Trust markers">
                        <span class="hero-trust-chip"><i class="bi bi-lock"></i> Secure platform access</span>
                        <span class="hero-trust-chip"><i class="bi bi-person-video3"></i> Doctor-led consultations</span>
                        <span class="hero-trust-chip"><i class="bi bi-clipboard2-check"></i> Consultation records</span>
                    </div>

                    <div class="hero-cta-row">
                        <a href="<?= $_u('/register') ?>" class="hero-cta-primary">
                            <i class="bi bi-person-plus" aria-hidden="true"></i>
                            Register as a Patient
                        </a>
                        <a href="<?= $_u('/login') ?>" class="hero-cta-secondary">
                            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                            Login
                        </a>
                    </div>

                    <div class="hero-support" aria-label="Additional service information">
                        <span class="hero-support-item">
                            <i class="bi bi-calendar2-week" aria-hidden="true"></i>
                            <strong>Doctor availability</strong> &nbsp;viewed before booking
                        </span>
                        <span class="hero-support-item">
                            <i class="bi bi-telephone-outbound" aria-hidden="true"></i>
                            <strong>Online consultations</strong> &nbsp;conducted by MBPHA doctors
                        </span>
                    </div>
                </div>

                <div class="col-xl-5 col-lg-5 d-none d-lg-block">
                    <div class="hero-visual-card reveal-on-scroll reveal-fade" aria-label="Consultation overview">
                        <div class="mini-label mb-2">Consultation at a glance</div>
                        <h2>What you can do today</h2>
                        <p>
                            Everything from finding an available doctor to attending your consultation
                            and reviewing the outcomes afterward — completed inside one system.
                        </p>
                        <div class="hero-visual-grid" role="list">
                            <div class="hero-visual-item" role="listitem">
                                <span>Availability</span>
                                <strong>Doctor schedule</strong>
                            </div>
                            <div class="hero-visual-item" role="listitem">
                                <span>Booking</span>
                                <strong>Request a slot</strong>
                            </div>
                            <div class="hero-visual-item" role="listitem">
                                <span>Approval</span>
                                <strong>Status updates</strong>
                            </div>
                            <div class="hero-visual-item" role="listitem">
                                <span>Consultation</span>
                                <strong>Online session</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ======================================================
         SECTION 2 — WHAT MBPHA TELEHEALTH PROVIDES
         Only the ACTUAL implemented telehealth workflow steps.
         ====================================================== -->
    <section id="services" class="public-section public-section--soft reveal-on-scroll reveal-fade" aria-labelledby="services-title">
        <div class="container px-4">
            <div class="row justify-content-center section-heading">
                <div class="col-md-9 col-xl-7 text-center">
                    <span class="public-eyebrow"><i class="bi bi-lungs" aria-hidden="true"></i> What the platform provides</span>
                    <h2 id="services-title" class="public-section-title mx-auto">The complete telehealth consultation workflow.</h2>
                    <p class="public-section-copy mx-auto text-center">
                        From the moment you look for an available doctor, through to joining your
                        approved consultation and reading the record afterward.
                    </p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card feature-card--navy">
                        <div class="feature-card__icon"><i class="bi bi-person-lines-fill" aria-hidden="true"></i></div>
                        <h3 class="feature-card__title">1. Find available doctors</h3>
                        <p class="feature-card__text">
                            Browse the list of MBPHA doctors who have published consultation
                            availability for the current and coming week.
                        </p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card feature-card--teal">
                        <div class="feature-card__icon"><i class="bi bi-calendar3" aria-hidden="true"></i></div>
                        <h3 class="feature-card__title">2. Select an available consultation slot</h3>
                        <p class="feature-card__text">
                            Choose a date and time that matches a published doctor availability
                            window for the doctor you wish to consult with.
                        </p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card feature-card--amber">
                        <div class="feature-card__icon"><i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i></div>
                        <h3 class="feature-card__title">3. Submit a consultation request</h3>
                        <p class="feature-card__text">
                            Complete the request with the consultation details required by
                            the health authority for triage and approval.
                        </p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card feature-card--navy">
                        <div class="feature-card__icon"><i class="bi bi-check-square" aria-hidden="true"></i></div>
                        <h3 class="feature-card__title">4. Receive consultation approval</h3>
                        <p class="feature-card__text">
                            Track your request status as it is reviewed. You will be able to
                            see whether it is pending, confirmed, cancelled or completed.
                        </p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card feature-card--green">
                        <div class="feature-card__icon"><i class="bi bi-camera-video-fill" aria-hidden="true"></i></div>
                        <h3 class="feature-card__title">5. Join an online consultation</h3>
                        <p class="feature-card__text">
                            On the scheduled day and time, join the approved online
                            consultation session directly from your patient dashboard.
                        </p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature-card feature-card--teal">
                        <div class="feature-card__icon"><i class="bi bi-journal-medical" aria-hidden="true"></i></div>
                        <h3 class="feature-card__title">6. Access records and prescriptions</h3>
                        <p class="feature-card__text">
                            After your consultation, revisit your consultation history to
                            review the doctor's notes, records and prescriptions.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ======================================================
         SECTION 3 — HOW IT WORKS
         6-step visual flow. Minimal text, strong visual hierarchy.
         ====================================================== -->
    <section id="how-it-works" class="public-section public-section--alt reveal-on-scroll reveal-fade" aria-labelledby="how-title">
        <div class="container px-4">
            <div class="row justify-content-center section-heading">
                <div class="col-md-9 col-xl-7 text-center">
                    <span class="public-eyebrow"><i class="bi bi-diagram-3" aria-hidden="true"></i> How the system works</span>
                    <h2 id="how-title" class="public-section-title mx-auto">A clear path from registration to consultation.</h2>
                    <p class="public-section-copy mx-auto text-center">
                        Follow these six simple steps to complete your first MBPHA TeleHealth
                        consultation — every step is managed inside the same system.
                    </p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="flow-step">
                        <span class="flow-step__num" aria-hidden="true">1</span>
                        <h3 class="flow-step__title">Register or login</h3>
                        <p class="flow-step__text">
                            Create a patient account, or login to your existing MBPHA TeleHealth
                            account — including doctor or administrator accounts.
                        </p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="flow-step flow-step--teal">
                        <span class="flow-step__num" aria-hidden="true">2</span>
                        <h3 class="flow-step__title">Find a doctor</h3>
                        <p class="flow-step__text">
                            Review the published availability list and pick the doctor you
                            want to consult with based on when they are online.
                        </p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="flow-step flow-step--navy">
                        <span class="flow-step__num" aria-hidden="true">3</span>
                        <h3 class="flow-step__title">Select a consultation slot</h3>
                        <p class="flow-step__text">
                            Choose a date and time from the doctor's available consultation
                            windows that fits your schedule.
                        </p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="flow-step flow-step--amber">
                        <span class="flow-step__num" aria-hidden="true">4</span>
                        <h3 class="flow-step__title">Submit your request</h3>
                        <p class="flow-step__text">
                            Complete and submit the consultation request form. Your request
                            will be reviewed before a slot is confirmed.
                        </p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="flow-step flow-step--navy">
                        <span class="flow-step__num" aria-hidden="true">5</span>
                        <h3 class="flow-step__title">Await approval status</h3>
                        <p class="flow-step__text">
                            Check your bookings list to see whether your request is pending,
                            has been confirmed, or has been cancelled.
                        </p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="flow-step flow-step--green">
                        <span class="flow-step__num" aria-hidden="true">6</span>
                        <h3 class="flow-step__title">Join the consultation</h3>
                        <p class="flow-step__text">
                            When your confirmed appointment time arrives, join the online
                            consultation from your dashboard and follow the doctor's guidance.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ======================================================
         SECTION 4 — ABOUT MBPHA TELEHEALTH
         Authority-appropriate, realistic content only.
         No invented statistics, partnerships or outcomes claims.
         ====================================================== -->
    <section id="about" class="public-section public-section--soft reveal-on-scroll reveal-fade" aria-labelledby="about-title">
        <div class="container px-4">
            <div class="about-shell">
                <div class="row g-0">
                    <div class="col-lg-7">
                        <div class="about-shell__body">
                            <span class="public-eyebrow"><i class="bi bi-info-circle" aria-hidden="true"></i> About MBPHA TeleHealth</span>
                            <h2 id="about-title" class="public-section-title" style="margin-top: 0.5rem;">Bringing MBPHA consultations online for Milne Bay Province.</h2>
                            <p class="public-section-copy" style="max-width: 62ch;">
                                MBPHA TeleHealth is the Milne Bay Provincial Health Authority's digital
                                consultation platform. It is designed to extend the reach of MBPHA doctors
                                by enabling patients within the province to request, attend and review
                                consultations remotely — reducing travel burden while maintaining
                                a clear, auditable consultation record.
                            </p>
                            <div class="about-list" role="list">
                                <div class="about-item" role="listitem">
                                    <div class="about-item__icon"><i class="bi bi-person-video2" aria-hidden="true"></i></div>
                                    <div>
                                        <strong>Online doctor consultations</strong>
                                        <span>Scheduled video consultations with doctors from the health authority.</span>
                                    </div>
                                </div>
                                <div class="about-item" role="listitem">
                                    <div class="about-item__icon"><i class="bi bi-calendar-check" aria-hidden="true"></i></div>
                                    <div>
                                        <strong>Structured booking</strong>
                                        <span>All consultations are booked against published doctor availability.</span>
                                    </div>
                                </div>
                                <div class="about-item" role="listitem">
                                    <div class="about-item__icon"><i class="bi bi-person-lock" aria-hidden="true"></i></div>
                                    <div>
                                        <strong>Role-based access</strong>
                                        <span>Patients, doctors and administrators each use dedicated dashboards.</span>
                                    </div>
                                </div>
                                <div class="about-item" role="listitem">
                                    <div class="about-item__icon"><i class="bi bi-folder-symlink" aria-hidden="true"></i></div>
                                    <div>
                                        <strong>Consultation records</strong>
                                        <span>Appointment outcomes and notes are stored for later review.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <aside class="about-highlight" aria-label="What MBPHA TeleHealth supports">
                            <h3>Built for the Milne Bay Province community.</h3>
                            <p>
                                This system is used by the Milne Bay Provincial Health Authority to manage
                                the lifecycle of a remote consultation, including the publication of
                                doctor availability, the approval of patient requests, and the review of
                                records after each appointment.
                            </p>
                            <div class="about-highlight-meta">
                                <span><i class="bi bi-hospital" aria-hidden="true"></i> MBPHA-led service</span>
                                <span><i class="bi bi-globe-central-south-asia" aria-hidden="true"></i> Milne Bay Province</span>
                                <span><i class="bi bi-people-fill" aria-hidden="true"></i> Patients, Doctors, Administrators</span>
                            </div>
                        </aside>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ======================================================
         SECTION 5 — CONTACT
         Retained existing contact/inquiry form.
         - POSTs to /contact via HomeController::contact()
         - Writes to logs/contact.log
         - CSRF protected
         - Validated on the server
         This IS a general inquiry, NOT a consultation booking.
         ====================================================== -->
    <section id="contact" class="public-section public-section--alt reveal-on-scroll reveal-fade" aria-labelledby="contact-title">
        <div class="container px-4">
            <div class="row justify-content-center section-heading">
                <div class="col-md-9 col-xl-7 text-center">
                    <span class="public-eyebrow"><i class="bi bi-envelope" aria-hidden="true"></i> Contact the platform team</span>
                    <h2 id="contact-title" class="public-section-title mx-auto">Have a question about MBPHA TeleHealth?</h2>
                    <p class="public-section-copy mx-auto text-center">
                        Use the form below for general inquiries about the system.
                        To request a consultation, please register or login and submit a booking
                        from your patient dashboard.
                    </p>
                </div>
            </div>

            <div class="row g-4 justify-content-center">
                <div class="col-lg-5 order-2 order-lg-1">
                    <div class="contact-card card border-0 h-100" aria-label="Contact information">
                        <div class="card-body">
                            <h3 class="card-title mb-3" style="font-size: 1.15rem; letter-spacing: -0.01em; color: #0F2744; font-weight: 800;">
                                Contacting MBPHA TeleHealth
                            </h3>
                            <p class="text-muted mb-4" style="font-size: 0.9rem; line-height: 1.7;">
                                The platform team reviews all general inquiries submitted through
                                this form. For clinical questions or appointment updates, please login
                                and use the consultation booking and messaging features inside your
                                account.
                            </p>

                            <div class="contact-item">
                                <i class="bi bi-building" aria-hidden="true"></i>
                                <div>
                                    <strong>Platform provider</strong>
                                    <span>Milne Bay Provincial Health Authority</span>
                                </div>
                            </div>
                            <div class="contact-item">
                                <i class="bi bi-envelope" aria-hidden="true"></i>
                                <div>
                                    <strong>Inquiry type</strong>
                                    <span>General platform questions and feedback</span>
                                </div>
                            </div>
                            <div class="contact-item">
                                <i class="bi bi-calendar-event" aria-hidden="true"></i>
                                <div>
                                    <strong>Consultation bookings</strong>
                                    <span>Managed inside your patient account</span>
                                </div>
                            </div>
                            <div class="contact-item">
                                <i class="bi bi-shield-lock" aria-hidden="true"></i>
                                <div>
                                    <strong>Submission</strong>
                                    <span>Form inquiries are stored securely and reviewed by the team</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 order-1 order-lg-2">
                    <div class="contact-card card border-0 h-100" aria-label="Contact inquiry form">
                        <div class="card-body">
                            <?php if ($statusType === 'success'): ?>
                                <div class="alert alert-success border-0 d-flex align-items-start gap-2 mb-4" role="alert" style="border-radius: var(--r-input); background: #ECFDF5; color: #065F46;">
                                    <i class="bi bi-check-circle-fill me-1 mt-1" style="font-size: 1rem; color: #10B981;"></i>
                                    <div>
                                        <strong class="d-block mb-1">Thank you — your inquiry has been received.</strong>
                                        <span style="font-size: 0.86rem; color: #047857;">
                                            <?= $_e($statusMessage ?? 'The MBPHA TeleHealth team will review your message.') ?>
                                        </span>
                                    </div>
                                </div>
                            <?php elseif ($statusType === 'danger'): ?>
                                <div class="alert alert-danger border-0 mb-4" role="alert" style="border-radius: var(--r-input); background: #FEF2F2; color: #991B1B;">
                                    <div class="d-flex align-items-start gap-2 mb-2">
                                        <i class="bi bi-exclamation-triangle-fill mt-1" style="font-size: 1rem; color: #EF4444;"></i>
                                        <div>
                                            <strong class="d-block mb-1">We couldn't deliver your message right now.</strong>
                                        </div>
                                    </div>
                                    <p class="mb-0" style="font-size: 0.86rem; line-height: 1.6;">
                                        <?= $_e($statusMessage ?? 'Please try again in a moment, or use the contact information on this page.') ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <?php if ($hasValidationErrors): ?>
                                <div class="alert alert-danger border-0 mb-4" role="alert" style="border-radius: var(--r-input); background: #FEF2F2; color: #991B1B;">
                                    <div class="d-flex align-items-start gap-2 mb-2">
                                        <i class="bi bi-exclamation-triangle-fill mt-1" style="font-size: 1rem; color: #EF4444;"></i>
                                        <div>
                                            <strong class="d-block mb-1">Please correct the following before submitting again:</strong>
                                        </div>
                                    </div>
                                    <ul class="mb-0 ps-3" style="font-size: 0.86rem; line-height: 1.6;">
                                        <?php if (array_is_list($errors)): ?>
                                            <?php foreach ($errors as $message): ?>
                                                <li><?= $_e($message) ?></li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <?php foreach ($errors as $field => $message): ?>
                                                <?php $fieldLabel = is_string($field) ? ucwords(str_replace('_', ' ', $field)) : 'Error'; ?>
                                                <li>
                                                    <strong><?= $_e($fieldLabel) ?>:</strong>
                                                    <?= $_e($message) ?>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <h3 class="card-title mb-3" style="font-size: 1.15rem; letter-spacing: -0.01em; color: #0F2744; font-weight: 800;">
                                Send a message
                            </h3>
                            <p class="text-muted mb-4" style="font-size: 0.88rem; line-height: 1.6;">
                                Fields marked with <span class="text-danger">*</span> are required.
                                Do not include sensitive medical information in this form — this
                                channel is for general inquiries only.
                            </p>

                            <form action="<?= $_u('/contact') ?>" method="post" novalidate aria-describedby="contact-help">
                                <input type="hidden" name="_token" value="<?= $_e($csrf_token_in_view) ?>">
                                <input type="hidden" name="csrf_token" value="<?= $_e($csrf_token_in_view) ?>">
                                <p id="contact-help" class="visually-hidden">
                                    Use this form for general platform questions. For medical appointments,
                                    register and submit a consultation request from the patient dashboard.
                                </p>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label" for="contact_name">Full name <span class="text-danger">*</span></label>
                                        <input type="text"
                                               id="contact_name"
                                               name="name"
                                               class="form-control <?php if ($has('name', $errors)): ?>is-invalid<?php endif; ?>"
                                               maxlength="255"
                                               required
                                               value="<?= $_e($val('name')) ?>"
                                               placeholder="Enter your full name"
                                               aria-invalid="<?= $has('name', $errors) ? 'true' : 'false' ?>"
                                               aria-describedby="<?= $has('name', $errors) ? 'contact_name_error' : '' ?>">
                                        <?php if ($has('name', $errors)): ?>
                                            <div id="contact_name_error" class="invalid-feedback" style="font-size: 0.82rem;">
                                                <?= $_e($errors['name']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label" for="contact_email">Email address <span class="text-danger">*</span></label>
                                        <input type="email"
                                               id="contact_email"
                                               name="email"
                                               class="form-control <?php if ($has('email', $errors)): ?>is-invalid<?php endif; ?>"
                                               maxlength="255"
                                               required
                                               value="<?= $_e($val('email')) ?>"
                                               placeholder="name@example.com"
                                               aria-invalid="<?= $has('email', $errors) ? 'true' : 'false' ?>"
                                               aria-describedby="<?= $has('email', $errors) ? 'contact_email_error' : '' ?>">
                                        <?php if ($has('email', $errors)): ?>
                                            <div id="contact_email_error" class="invalid-feedback" style="font-size: 0.82rem;">
                                                <?= $_e($errors['email']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label" for="contact_phone">Phone number <span class="text-muted fw-normal" style="font-size: 0.78rem;">(optional)</span></label>
                                        <input type="tel"
                                               id="contact_phone"
                                               name="phone"
                                               class="form-control <?php if ($has('phone', $errors)): ?>is-invalid<?php endif; ?>"
                                               maxlength="30"
                                               value="<?= $_e($val('phone')) ?>"
                                               placeholder="Primary contact number"
                                               aria-invalid="<?= $has('phone', $errors) ? 'true' : 'false' ?>"
                                               aria-describedby="<?= $has('phone', $errors) ? 'contact_phone_error' : '' ?>">
                                        <?php if ($has('phone', $errors)): ?>
                                            <div id="contact_phone_error" class="invalid-feedback" style="font-size: 0.82rem;">
                                                <?= $_e($errors['phone']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label" for="contact_subject">Subject <span class="text-danger">*</span></label>
                                        <input type="text"
                                               id="contact_subject"
                                               name="subject"
                                               class="form-control <?php if ($has('subject', $errors)): ?>is-invalid<?php endif; ?>"
                                               maxlength="255"
                                               required
                                               value="<?= $_e($val('subject')) ?>"
                                               placeholder="Briefly describe your inquiry"
                                               aria-invalid="<?= $has('subject', $errors) ? 'true' : 'false' ?>"
                                               aria-describedby="<?= $has('subject', $errors) ? 'contact_subject_error' : '' ?>">
                                        <?php if ($has('subject', $errors)): ?>
                                            <div id="contact_subject_error" class="invalid-feedback" style="font-size: 0.82rem;">
                                                <?= $_e($errors['subject']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label" for="contact_message">Message <span class="text-danger">*</span></label>
                                        <textarea id="contact_message"
                                                  name="message"
                                                  rows="5"
                                                  maxlength="2000"
                                                  required
                                                  class="form-control <?php if ($has('message', $errors)): ?>is-invalid<?php endif; ?>"
                                                  placeholder="Describe your question or feedback in detail."
                                                  style="min-height: 130px; resize: vertical;"
                                                  aria-invalid="<?= $has('message', $errors) ? 'true' : 'false' ?>"
                                                  aria-describedby="contact_message_hint <?php if ($has('message', $errors)): ?>contact_message_error<?php endif; ?>"><?= $_e($val('message')) ?></textarea>
                                        <div id="contact_message_hint" class="form-text mt-1" style="font-size: 0.76rem;">
                                            Maximum 2,000 characters. Do not submit sensitive medical information through this form.
                                        </div>
                                        <?php if ($has('message', $errors)): ?>
                                            <div id="contact_message_error" class="invalid-feedback" style="font-size: 0.82rem;">
                                                <?= $_e($errors['message']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-12 d-flex flex-wrap gap-3 align-items-center pt-2">
                                        <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
                                            <i class="bi bi-send" aria-hidden="true"></i>
                                            Submit inquiry
                                        </button>
                                        <button type="reset" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" style="border-radius: var(--r-btn); border-color: #E5E7EB;">
                                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                                            Clear form
                                        </button>
                                        <span class="small text-muted ms-auto" style="font-size: 0.78rem;">
                                            <i class="bi bi-shield-lock" aria-hidden="true"></i>
                                            Protected by CSRF token and server-side validation.
                                        </span>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ======================================================
         SECTION 6 — LOGIN / REGISTER CTA BANNER
         Last visible section above footer.
         ====================================================== -->
    <section id="cta" class="public-section public-section--soft reveal-on-scroll reveal-fade" aria-labelledby="cta-title">
        <div class="container px-4">
            <div class="public-cta">
                <div class="row align-items-center g-4">
                    <div class="col-md-7">
                        <span class="public-eyebrow public-eyebrow--inverse mb-3">
                            <i class="bi bi-play-circle" aria-hidden="true"></i> Ready to begin
                        </span>
                        <h2 id="cta-title">Request your first MBPHA TeleHealth consultation.</h2>
                        <p>
                            If you are a patient you can register now and submit your first
                            consultation request. Returning patients, doctors and administrators
                            can login directly.
                        </p>
                    </div>
                    <div class="col-md-5">
                        <div class="public-cta-actions">
                            <a href="<?= $_u('/register') ?>" class="public-cta__primary">
                                <i class="bi bi-person-plus" aria-hidden="true"></i> Register
                            </a>
                            <a href="<?= $_u('/login') ?>" class="public-cta__secondary">
                                <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<?php require __DIR__ . '/../partials/public/footer.php'; ?>
