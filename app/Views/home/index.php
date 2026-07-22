<?php require __DIR__ . '/../partials/public/navbar.php'; ?>

<main id="home">
  <section class="hero-section hero-section-agh py-5 py-xl-6">
    <div class="container hero-shell position-relative">
      <div class="row align-items-center g-4 g-xl-5 content-row">
        <div class="col-xl-7">
          <div class="hero-content-panel">
            <div class="mb-4">
              <?php
              $brandVariant = 'hero';
              $brandSubtitle = '';
              $brandShowTitle = false;
              $brandLink = \App\Helpers\Helper::url('/');
              require __DIR__ . '/../partials/shared/brand_logo.php';
              ?>
            </div>

            <span class="section-badge section-badge-inverse mb-3">
              <i class="bi bi-buildings"></i>
              Milne Bay Provincial Health Authority
            </span>

            <h1 class="display-4 fw-bold hero-title text-white mb-4">
              Connected digital healthcare for Alotau General Hospital and communities across Milne Bay Province.
            </h1>

            <p class="hero-copy hero-copy-light mb-4">
              MBPHA TeleHealth brings secure access, trusted coordination, and professional digital consultation workflows
              into one modern healthcare platform designed for patients, doctors, and administrators.
            </p>

            <div class="d-flex flex-column flex-sm-row gap-3 mb-4">
              <a href="<?= \App\Helpers\Helper::url('/register') ?>" class="btn btn-success btn-lg rounded-pill px-4">
                Register As Patient
              </a>
              <a href="<?= \App\Helpers\Helper::url('/login') ?>" class="btn btn-hero-outline btn-lg rounded-pill px-4">
                Secure Login
              </a>
            </div>

            <div class="row g-3 hero-highlights">
              <div class="col-sm-4">
                <div class="metric-card metric-card-hero">
                  <strong>Trusted Access</strong>
                  <span>Secure sessions and protected authentication flows</span>
                </div>
              </div>
              <div class="col-sm-4">
                <div class="metric-card metric-card-hero">
                  <strong>Hospital Identity</strong>
                  <span>Aligned visually with MBPHA and Alotau General Hospital</span>
                </div>
              </div>
              <div class="col-sm-4">
                <div class="metric-card metric-card-hero">
                  <strong>Responsive Care</strong>
                  <span>Modern healthcare access across desktop, tablet, and mobile</span>
                </div>
              </div>
            </div>

            <a href="<?= \App\Helpers\Helper::url('/#about') ?>" class="hero-scroll-indicator mt-4">
              <span class="hero-scroll-icon"><i class="bi bi-chevron-double-down"></i></span>
              <span>Explore the platform</span>
            </a>
          </div>
        </div>

        <div class="col-xl-5 d-none d-xl-block hero-spotlight-column">
          <div class="hero-spotlight-card">
            <span class="mini-label">Healthcare Mission</span>
            <h2 class="h3 text-white mb-3">Designed for confidence, continuity, and coordinated patient care.</h2>
            <p class="hero-spotlight-copy mb-4">
              The official AGH environment is blended into a professional MBPHA interface so the platform feels grounded
              in the real healthcare context it serves.
            </p>

            <div class="hero-spotlight-grid">
              <div class="hero-spotlight-item">
                <span class="hero-spotlight-label">Facility</span>
                <strong>Alotau General Hospital</strong>
              </div>
              <div class="hero-spotlight-item">
                <span class="hero-spotlight-label">Brand System</span>
                <strong>Official MBPHA TeleHealth</strong>
              </div>
              <div class="hero-spotlight-item">
                <span class="hero-spotlight-label">Access Layer</span>
                <strong>Patient, Doctor, Admin</strong>
              </div>
              <div class="hero-spotlight-item">
                <span class="hero-spotlight-label">Security</span>
                <strong>CSRF, Sessions, Role Protection</strong>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="about" class="py-5">
    <div class="container">
      <div class="row g-4 align-items-center content-row">
        <div class="col-lg-5">
          <span class="section-badge mb-3">
            <i class="bi bi-hospital"></i>
            About The Platform
          </span>
          <h2 class="section-title">A professional telehealth entry point for modern healthcare delivery.</h2>
          <p class="section-copy">
            This platform supports secure digital consultation workflows for patients, doctors, and
            administrators while preserving a clean healthcare-first experience appropriate for a real deployment.
          </p>
        </div>
        <div class="col-lg-7">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="feature-panel h-100">
                <div class="icon-wrap"><i class="bi bi-person-heart"></i></div>
                <h3 class="h5">Patient-Centred Access</h3>
                <p class="mb-0">Simple registration and guided access for patients seeking timely digital care.</p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="feature-panel h-100">
                <div class="icon-wrap"><i class="bi bi-clipboard2-pulse"></i></div>
                <h3 class="h5">Clinical Workflow Readiness</h3>
                <p class="mb-0">Structured role-based pathways prepare the system for scheduling and consultation management.</p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="feature-panel h-100">
                <div class="icon-wrap"><i class="bi bi-lock"></i></div>
                <h3 class="h5">Security Foundation</h3>
                <p class="mb-0">Secure sessions, CSRF protection, password hashing, and PDO-backed database access.</p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="feature-panel h-100">
                <div class="icon-wrap"><i class="bi bi-grid-1x2"></i></div>
                <h3 class="h5">Maintainable Architecture</h3>
                <p class="mb-0">Built on an MVC structure with reusable views, services, middleware, and configuration layers.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="services" class="py-5 bg-soft">
    <div class="container">
      <div class="section-heading text-center mx-auto">
        <span class="section-badge justify-content-center mb-3">
          <i class="bi bi-activity"></i>
          Services
        </span>
        <h2 class="section-title">Healthcare services supported through a single professional platform.</h2>
        <p class="section-copy">The Week 2 experience prepares core access paths for patients, clinicians, and administrators.</p>
      </div>

      <div class="row g-4 content-row">
        <div class="col-md-6 col-xl-3">
          <div class="service-card h-100">
            <div class="service-icon"><i class="bi bi-person-plus"></i></div>
            <h3 class="h5">Patient Onboarding</h3>
            <p class="mb-0">Secure patient self-registration with validation, password confirmation, and responsive forms.</p>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="service-card h-100">
            <div class="service-icon"><i class="bi bi-box-arrow-in-right"></i></div>
            <h3 class="h5">Role-Based Access</h3>
            <p class="mb-0">Single login experience for patients, doctors, and administrators with appropriate dashboard routing.</p>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="service-card h-100">
            <div class="service-icon"><i class="bi bi-columns-gap"></i></div>
            <h3 class="h5">Operational Dashboards</h3>
            <p class="mb-0">Dedicated dashboard layouts provide role-specific visibility, navigation, and action entry points.</p>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="service-card h-100">
            <div class="service-icon"><i class="bi bi-headset"></i></div>
            <h3 class="h5">Public Support Contact</h3>
            <p class="mb-0">A validated contact channel helps visitors send inquiries through a secure public-facing form.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="features" class="py-5">
    <div class="container">
      <div class="row g-4 align-items-center content-row">
        <div class="col-lg-5">
          <span class="section-badge mb-3">
            <i class="bi bi-stars"></i>
            Platform Features
          </span>
          <h2 class="section-title">Purpose-built features for a premium healthcare SaaS experience.</h2>
          <p class="section-copy">The interface prioritises clarity, trust, and maintainability without sacrificing polish.</p>
        </div>
        <div class="col-lg-7">
          <div class="row g-3">
            <div class="col-sm-6">
              <div class="feature-list-card h-100">
                <i class="bi bi-patch-check"></i>
                <div>
                  <h3 class="h6">Secure Authentication</h3>
                  <p class="mb-0">Password hashing, active-account checks, session regeneration, and CSRF verification.</p>
                </div>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="feature-list-card h-100">
                <i class="bi bi-layout-sidebar-inset"></i>
                <div>
                  <h3 class="h6">Reusable Dashboard Shell</h3>
                  <p class="mb-0">Shared sidebar, top navigation, quick actions, empty states, and responsive offcanvas support.</p>
                </div>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="feature-list-card h-100">
                <i class="bi bi-ui-checks-grid"></i>
                <div>
                  <h3 class="h6">Client-Side Validation</h3>
                  <p class="mb-0">Progressive enhancement for required fields, password strength, and confirmation feedback.</p>
                </div>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="feature-list-card h-100">
                <i class="bi bi-phone"></i>
                <div>
                  <h3 class="h6">Responsive Design</h3>
                  <p class="mb-0">Bootstrap 5 grid, cards, accordions, and mobile-friendly navigation patterns throughout.</p>
                </div>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="feature-list-card h-100">
                <i class="bi bi-window-stack"></i>
                <div>
                  <h3 class="h6">Reusable Partials</h3>
                  <p class="mb-0">Shared public navigation, footer, alert handling, and dashboard overview templates.</p>
                </div>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="feature-list-card h-100">
                <i class="bi bi-database-check"></i>
                <div>
                  <h3 class="h6">PDO and Transaction Safety</h3>
                  <p class="mb-0">Patient registration writes are wrapped consistently to protect referential integrity.</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="how-it-works" class="py-5 bg-soft">
    <div class="container">
      <div class="section-heading text-center mx-auto">
        <span class="section-badge justify-content-center mb-3">
          <i class="bi bi-diagram-3"></i>
          How It Works
        </span>
        <h2 class="section-title">A clear workflow from first visit to role-specific access.</h2>
      </div>

      <div class="row g-4 content-row">
        <div class="col-md-6 col-xl-3">
          <div class="workflow-card h-100">
            <span class="workflow-step">01</span>
            <h3 class="h5">Visit The Home Page</h3>
            <p class="mb-0">Patients and staff begin from a polished public landing page with clear navigation and support paths.</p>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="workflow-card h-100">
            <span class="workflow-step">02</span>
            <h3 class="h5">Register Or Sign In</h3>
            <p class="mb-0">Patients create secure accounts, while doctors and administrators access the shared login portal.</p>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="workflow-card h-100">
            <span class="workflow-step">03</span>
            <h3 class="h5">Authenticate Securely</h3>
            <p class="mb-0">The authentication layer validates user credentials, role assignments, and session security controls.</p>
          </div>
        </div>
        <div class="col-md-6 col-xl-3">
          <div class="workflow-card h-100">
            <span class="workflow-step">04</span>
            <h3 class="h5">Access The Right Dashboard</h3>
            <p class="mb-0">Each user is redirected to a professional dashboard aligned to patient, doctor, or administrator workflows.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="faq" class="py-5">
    <div class="container">
      <div class="row g-5 align-items-start content-row">
        <div class="col-lg-5">
          <span class="section-badge mb-3">
            <i class="bi bi-question-circle"></i>
            Frequently Asked Questions
          </span>
          <h2 class="section-title">Answers that help users understand access, security, and support.</h2>
          <p class="section-copy">The FAQ area provides concise guidance without overwhelming first-time visitors.</p>
        </div>
        <div class="col-lg-7">
          <div class="accordion faq-accordion" id="faqAccordion">
            <div class="accordion-item">
              <h3 class="accordion-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faqOne" aria-expanded="true" aria-controls="faqOne">
                  Who can register through the public website?
                </button>
              </h3>
              <div id="faqOne" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                <div class="accordion-body">Patients can register directly through the public registration page, while doctors and administrators use secure login access provided by the authority.</div>
              </div>
            </div>
            <div class="accordion-item">
              <h3 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqTwo" aria-expanded="false" aria-controls="faqTwo">
                  How is account security handled?
                </button>
              </h3>
              <div id="faqTwo" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">The system uses password hashing, CSRF protection, secure session handling, and role-based route protection as part of the authentication foundation.</div>
              </div>
            </div>
            <div class="accordion-item">
              <h3 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqThree" aria-expanded="false" aria-controls="faqThree">
                  What happens after login?
                </button>
              </h3>
              <div id="faqThree" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">Users are redirected automatically to the appropriate patient, doctor, or administrator dashboard based on their assigned role.</div>
              </div>
            </div>
            <div class="accordion-item">
              <h3 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faqFour" aria-expanded="false" aria-controls="faqFour">
                  Can visitors send a general inquiry?
                </button>
              </h3>
              <div id="faqFour" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                <div class="accordion-body">Yes. The contact section includes a validated inquiry form for public-facing communication and support.</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="contact" class="py-5 bg-soft">
    <div class="container">
      <div class="row g-4 content-row">
        <div class="col-lg-5">
          <span class="section-badge mb-3">
            <i class="bi bi-envelope-paper"></i>
            Contact
          </span>
          <h2 class="section-title">Reach the MBPHA TeleHealth team through a validated public contact channel.</h2>
          <p class="section-copy">
            Use the contact form for general support, onboarding questions, or implementation-related communication.
          </p>

          <div class="contact-card mt-4">
            <div class="contact-item">
              <i class="bi bi-geo-alt"></i>
              <div>
                <strong>Location</strong>
                <span>Alotau, Milne Bay Province, Papua New Guinea</span>
              </div>
            </div>
            <div class="contact-item">
              <i class="bi bi-envelope"></i>
              <div>
                <strong>Email</strong>
                <span>info@telehealth.local</span>
              </div>
            </div>
            <div class="contact-item">
              <i class="bi bi-telephone"></i>
              <div>
                <strong>Phone</strong>
                <span>+675 000 0000</span>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-7">
          <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4 p-lg-5">
              <?php
              $errors = $contactErrors ?? [];
              $statusMessage = $contactStatus ?? null;
              require __DIR__ . '/../partials/shared/alerts.php';
              ?>

              <form action="<?= \App\Helpers\Helper::url('/contact') ?>" method="POST" class="row g-3 needs-validation" novalidate>
                <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfToken ?? '') ?>">

                <div class="col-md-6">
                  <label for="contactName" class="form-label">Full Name</label>
                  <input
                    type="text"
                    class="form-control form-control-lg"
                    id="contactName"
                    name="name"
                    value="<?= \App\Helpers\Helper::escape($contactOldInput['name'] ?? '') ?>"
                    required
                  >
                  <div class="invalid-feedback">Please enter your full name.</div>
                </div>

                <div class="col-md-6">
                  <label for="contactEmail" class="form-label">Email Address</label>
                  <input
                    type="email"
                    class="form-control form-control-lg"
                    id="contactEmail"
                    name="email"
                    value="<?= \App\Helpers\Helper::escape($contactOldInput['email'] ?? '') ?>"
                    required
                  >
                  <div class="invalid-feedback">Please enter a valid email address.</div>
                </div>

                <div class="col-md-6">
                  <label for="contactPhone" class="form-label">Phone Number</label>
                  <input
                    type="text"
                    class="form-control form-control-lg"
                    id="contactPhone"
                    name="phone"
                    value="<?= \App\Helpers\Helper::escape($contactOldInput['phone'] ?? '') ?>"
                  >
                </div>

                <div class="col-md-6">
                  <label for="contactSubject" class="form-label">Subject</label>
                  <input
                    type="text"
                    class="form-control form-control-lg"
                    id="contactSubject"
                    name="subject"
                    value="<?= \App\Helpers\Helper::escape($contactOldInput['subject'] ?? '') ?>"
                    required
                  >
                  <div class="invalid-feedback">Please provide a subject.</div>
                </div>

                <div class="col-12">
                  <label for="contactMessage" class="form-label">Message</label>
                  <textarea
                    class="form-control"
                    id="contactMessage"
                    name="message"
                    rows="5"
                    required
                  ><?= \App\Helpers\Helper::escape($contactOldInput['message'] ?? '') ?></textarea>
                  <div class="invalid-feedback">Please enter your message.</div>
                </div>

                <div class="col-12 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 pt-2">
                  <p class="text-muted small mb-0">Submissions are validated and recorded through the project contact log.</p>
                  <button type="submit" class="btn btn-primary btn-lg rounded-pill px-4">Send Inquiry</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<?php require __DIR__ . '/../partials/public/footer.php'; ?>
