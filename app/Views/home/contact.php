<?php
defined('APP_VERSION') or define('APP_VERSION', '1.0.0');

$pageKicker = 'Contact';
$pageTitleHtml = 'Platform inquiries for <span class="pp-intro__emphasis">MBPHA TeleHealth.</span>';
$pageLedeHtml = 'Use this page for general questions about the system. Consultation bookings are made after you <span class="pp-intro__emphasis">register or sign in</span> — this form is not a booking request.';
$pageLedeShortHtml = 'General questions only. Book consultations after you register or sign in.';

$old = $contactOldInput ?? [];
$errors = $contactErrors ?? [];
$status = $contactStatus ?? null;
$csrfTokenValue = $csrfToken ?? '';

$val = static function (string $key, string $default = '') use ($old): string {
    $v = $old[$key] ?? $default;
    return is_string($v) ? $v : $default;
};

$statusType = null;
$statusMessage = null;
if (is_array($status) && isset($status['type'], $status['message'])) {
    $statusType = $status['type'];
    $statusMessage = (string) $status['message'];
} elseif (is_string($status) && $status !== '') {
    $statusType = $status;
}

$hasValidationErrors = !empty($errors);
?>
<?php require __DIR__ . '/../partials/public/navbar.php'; ?>

<main class="pp-page" aria-label="Contact MBPHA TeleHealth">
  <?php require __DIR__ . '/../partials/public/page_intro.php'; ?>

  <section class="pp-body">
    <div class="pp-wrap pp-contact">
      <aside class="pp-panel pp-contact__info" aria-label="Contact information">
        <h2>How to reach the platform team</h2>
        <p>
          Inquiries submitted here are reviewed by the MBPHA TeleHealth platform team.
          Do not include sensitive medical details in this form.
        </p>

        <dl class="pp-contact-list">
          <div>
            <dt>Platform provider</dt>
            <dd>Milne Bay Provincial Health Authority</dd>
          </div>
          <div>
            <dt>Location</dt>
            <dd>Milne Bay Province, Papua New Guinea</dd>
          </div>
          <div>
            <dt>Inquiry type</dt>
            <dd>General platform questions and feedback</dd>
          </div>
          <div>
            <dt>Consultation bookings</dt>
            <dd>Managed inside your patient account after login</dd>
          </div>
        </dl>

        <p class="pp-contact__hint">
          For clinical questions or appointment updates, sign in and use the consultation
          tools in your dashboard.
        </p>
      </aside>

      <article class="pp-panel pp-contact__form" aria-label="Contact inquiry form">
        <?php if ($statusType === 'success'): ?>
          <div class="pp-alert pp-alert--success" role="alert">
            <strong>Your inquiry has been received.</strong>
            <p><?= \App\Helpers\Helper::escape($statusMessage ?? 'The MBPHA TeleHealth team will review your message.') ?></p>
          </div>
        <?php elseif ($statusType === 'danger'): ?>
          <div class="pp-alert pp-alert--danger" role="alert">
            <strong>Your message could not be delivered.</strong>
            <p><?= \App\Helpers\Helper::escape($statusMessage ?? 'Please try again in a moment.') ?></p>
          </div>
        <?php endif; ?>

        <?php if ($hasValidationErrors): ?>
          <div class="pp-alert pp-alert--danger" role="alert">
            <strong>Please correct the following before submitting again:</strong>
            <ul>
              <?php foreach ($errors as $message): ?>
                <li><?= \App\Helpers\Helper::escape((string) $message) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <h2>Send a message</h2>
        <p class="pp-contact__form-copy">
          Fields marked with <span class="pp-required">*</span> are required.
        </p>

        <form action="<?= \App\Helpers\Helper::url('/contact') ?>" method="post" novalidate class="pp-form">
          <input type="hidden" name="_token" value="<?= \App\Helpers\Helper::escape($csrfTokenValue) ?>">

          <div class="pp-form__row">
            <div class="pp-form__field">
              <label for="contact_name">Full name <span class="pp-required">*</span></label>
              <input
                type="text"
                id="contact_name"
                name="name"
                maxlength="255"
                required
                value="<?= \App\Helpers\Helper::escape($val('name')) ?>"
                autocomplete="name"
              >
            </div>
            <div class="pp-form__field">
              <label for="contact_email">Email address <span class="pp-required">*</span></label>
              <input
                type="email"
                id="contact_email"
                name="email"
                maxlength="255"
                required
                value="<?= \App\Helpers\Helper::escape($val('email')) ?>"
                autocomplete="email"
              >
            </div>
          </div>

          <div class="pp-form__row">
            <div class="pp-form__field">
              <label for="contact_phone">Phone number <span class="pp-optional">(optional)</span></label>
              <input
                type="tel"
                id="contact_phone"
                name="phone"
                maxlength="30"
                value="<?= \App\Helpers\Helper::escape($val('phone')) ?>"
                autocomplete="tel"
              >
            </div>
            <div class="pp-form__field">
              <label for="contact_subject">Subject <span class="pp-required">*</span></label>
              <input
                type="text"
                id="contact_subject"
                name="subject"
                maxlength="255"
                required
                value="<?= \App\Helpers\Helper::escape($val('subject')) ?>"
              >
            </div>
          </div>

          <div class="pp-form__field">
            <label for="contact_message">Message <span class="pp-required">*</span></label>
            <textarea
              id="contact_message"
              name="message"
              rows="6"
              maxlength="2000"
              required
            ><?= \App\Helpers\Helper::escape($val('message')) ?></textarea>
            <p class="pp-form__hint">Maximum 2,000 characters. Do not submit sensitive medical information.</p>
          </div>

          <div class="pp-form__actions">
            <button type="submit" class="pp-cta__primary">Submit inquiry</button>
            <button type="reset" class="pp-cta__secondary pp-cta__secondary--light">Clear form</button>
          </div>
        </form>
      </article>
    </div>
  </section>
</main>

<?php require __DIR__ . '/../partials/public/footer.php'; ?>
