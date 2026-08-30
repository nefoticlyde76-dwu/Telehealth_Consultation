<?php
$authTab = $authTab ?? 'login';
$loginUrl = \App\Helpers\Helper::url('/login');
$registerUrl = \App\Helpers\Helper::url('/register');
$loginCurrent = $authTab === 'login';
$registerCurrent = $authTab === 'register';
?>
<nav class="auth-tabs auth-tabs--<?= \App\Helpers\Helper::escape($authTab) ?>" aria-label="Authentication">
  <a
    href="<?= $loginUrl ?>"
    class="auth-tabs__item<?= $loginCurrent ? ' is-active' : '' ?>"
    <?php if ($loginCurrent): ?>aria-current="page"<?php endif; ?>
  >Log In</a>
  <a
    href="<?= $registerUrl ?>"
    class="auth-tabs__item<?= $registerCurrent ? ' is-active' : '' ?>"
    <?php if ($registerCurrent): ?>aria-current="page"<?php endif; ?>
  >Register</a>
  <span class="auth-tabs__pill" aria-hidden="true"></span>
</nav>
