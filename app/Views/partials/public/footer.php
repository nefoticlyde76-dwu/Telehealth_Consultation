<footer class="site-footer pt-5 pb-4">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-4">
        <div class="mb-3">
          <?php
          $brandVariant = 'footer';
          $brandSubtitle = '';
          $brandShowTitle = false;
          $brandLink = \App\Helpers\Helper::url('/');
          require __DIR__ . '/../shared/brand_logo.php';
          ?>
        </div>
        <p class="text-white-50 mb-0">
          Secure, patient-centred telehealth access designed to improve consultation workflows,
          accessibility, and continuity of care across Milne Bay Province.
        </p>
      </div>

      <div class="col-sm-6 col-lg-2">
        <h3 class="footer-title">Platform</h3>
        <ul class="list-unstyled footer-links mb-0">
          <li><a href="<?= \App\Helpers\Helper::url('/#about') ?>">About</a></li>
          <li><a href="<?= \App\Helpers\Helper::url('/#services') ?>">Services</a></li>
          <li><a href="<?= \App\Helpers\Helper::url('/#features') ?>">Features</a></li>
          <li><a href="<?= \App\Helpers\Helper::url('/#faq') ?>">FAQ</a></li>
        </ul>
      </div>

      <div class="col-sm-6 col-lg-3">
        <h3 class="footer-title">Access</h3>
        <ul class="list-unstyled footer-links mb-0">
          <li><a href="<?= \App\Helpers\Helper::url('/register') ?>">Patient Registration</a></li>
          <li><a href="<?= \App\Helpers\Helper::url('/login') ?>">Secure Login</a></li>
          <li><a href="<?= \App\Helpers\Helper::url('/#how-it-works') ?>">Consultation Journey</a></li>
          <li><a href="<?= \App\Helpers\Helper::url('/#contact') ?>">Support Contact</a></li>
        </ul>
      </div>

      <div class="col-lg-3">
        <h3 class="footer-title">Contact</h3>
        <ul class="list-unstyled footer-contact mb-0">
          <li><i class="bi bi-geo-alt"></i> Alotau, Milne Bay Province, Papua New Guinea</li>
          <li><i class="bi bi-envelope"></i> info@telehealth.local</li>
          <li><i class="bi bi-telephone"></i> +675 000 0000</li>
          <li><i class="bi bi-shield-check"></i> Secure patient-first digital care</li>
        </ul>
      </div>
    </div>

    <div class="footer-bottom d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-5 pt-4">
      <p class="text-white-50 mb-0">© <span data-current-year></span> MBPHA TeleHealth. All rights reserved.</p>
      <div class="d-flex align-items-center gap-3 text-white-50 small">
        <span>Healthcare-grade privacy mindset</span>
        <span class="dot-separator"></span>
        <span>Production-quality capstone implementation</span>
      </div>
    </div>
  </div>
</footer>
