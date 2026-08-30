<?php
defined('APP_VERSION') or define('APP_VERSION', '1.0.0');
?>
<section class="pp-cta" aria-labelledby="cta-title">
  <div class="pp-wrap">
    <div class="pp-cta__panel">
      <div class="pp-cta__copy">
        <p class="pp-cta__kicker">Ready to begin</p>
        <h2 id="cta-title">Request your first MBPHA TeleHealth consultation.</h2>
        <p>
          Patients can register and submit a consultation request. Returning patients,
          doctors, and administrators can sign in to their existing account.
        </p>
      </div>
      <div class="pp-cta__actions">
        <a href="<?= \App\Helpers\Helper::url('/register') ?>" class="pp-cta__primary">Register as a patient</a>
        <a href="<?= \App\Helpers\Helper::url('/login') ?>" class="pp-cta__secondary">Login</a>
      </div>
    </div>
  </div>
</section>
