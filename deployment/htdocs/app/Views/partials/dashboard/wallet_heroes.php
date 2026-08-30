<?php
/**
 * Two consultation activity cards: sparkline + status mix ring.
 * Pure CSS and inline SVG — no chart library.
 *
 * @var array<string, mixed> $charts
 * @var string $dashboardRole
 */
use App\Helpers\DashboardHero;
use App\Helpers\Helper;
use App\Helpers\Palette;

$walletHero = is_array($walletHero ?? null)
    ? $walletHero
    : DashboardHero::fromCharts(
        is_array($charts ?? null) ? $charts : [],
        (string) ($dashboardRole ?? 'patient')
    );

$sparkLine = (string) ($walletHero['spark_line'] ?? 'M0 34 L120 34');
$sparkArea = (string) ($walletHero['spark_area'] ?? 'M0 34 L120 34 V40 H0 Z');
$assets = is_array($walletHero['assets'] ?? null) ? $walletHero['assets'] : [];
$legend = is_array($walletHero['legend'] ?? null) ? $walletHero['legend'] : [];
$actions = is_array($walletHero['actions'] ?? null) ? $walletHero['actions'] : [];
$deltaClass = trim((string) ($walletHero['delta_class'] ?? ''));
$ringGradient = (string) ($walletHero['ring_gradient'] ?? '#D9E2EC 0% 100%');
$balanceSmall = (string) ($walletHero['balance_small'] ?? '');
?>
<section class="glz-05-group mb-4" aria-label="Consultation activity heroes">
  <section class="glz-05a" aria-label="Consultation balance card">
    <article class="glz-05a__card">
      <header class="glz-05a__head">
        <span class="glz-05a__label"><?= Helper::escape((string) ($walletHero['balance_label'] ?? 'Consultations')) ?></span>
        <span class="glz-05a__net"><?= Helper::escape((string) ($walletHero['network'] ?? 'MBPHA')) ?></span>
      </header>
      <p class="glz-05a__bal"><?= Helper::escape((string) ($walletHero['balance'] ?? '0')) ?><?php if ($balanceSmall !== ''): ?><small><?= Helper::escape($balanceSmall) ?></small><?php endif; ?></p>
      <p class="glz-05a__delta">
        <b class="<?= Helper::escape($deltaClass) ?>"><?= Helper::escape((string) ($walletHero['delta_mark'] ?? '▲')) ?> <?= Helper::escape((string) ($walletHero['delta_text'] ?? '0%')) ?></b>
        <?= Helper::escape((string) ($walletHero['delta_period'] ?? '')) ?>
      </p>
      <svg class="glz-05a__spark" viewBox="0 0 120 40" aria-hidden="true" preserveAspectRatio="none">
        <defs>
          <linearGradient id="glz05aG" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="<?= Helper::escape(Palette::MEDICAL_BLUE) ?>" stop-opacity=".18"/>
            <stop offset="1" stop-color="<?= Helper::escape(Palette::MEDICAL_BLUE) ?>" stop-opacity="0"/>
          </linearGradient>
        </defs>
        <path class="glz-05a__area" d="<?= Helper::escape($sparkArea) ?>" fill="url(#glz05aG)"/>
        <path class="glz-05a__line" pathLength="340" d="<?= Helper::escape($sparkLine) ?>" fill="none" stroke="<?= Helper::escape(Palette::MEDICAL_BLUE) ?>" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>
      </svg>
      <ul class="glz-05a__assets">
        <?php foreach ($assets as $asset): ?>
          <?php
          $assetColor = (string) ($asset['color'] ?? Palette::MEDICAL_BLUE);
          $assetIcon = trim((string) ($asset['icon'] ?? ''));
          $assetSymbol = (string) ($asset['symbol'] ?? '');
          ?>
          <li>
            <span class="glz-05a__coin" style="--c:<?= Helper::escape($assetColor) ?>">
              <?php if ($assetIcon !== ''): ?>
                <i class="bi <?= Helper::escape($assetIcon) ?>" aria-hidden="true"></i>
              <?php else: ?>
                <?= Helper::escape($assetSymbol) ?>
              <?php endif; ?>
            </span>
            <span><?= Helper::escape((string) ($asset['name'] ?? '')) ?><em><?= Helper::escape((string) ($asset['meta'] ?? '0')) ?></em></span>
            <div class="glz-05a__bar" style="--w:<?= Helper::escape((string) ((int) ($asset['bar'] ?? 0))) ?>%;--c:<?= Helper::escape($assetColor) ?>"><i></i></div>
            <b class="glz-05a__<?= ($asset['tone'] ?? 'up') === 'dn' ? 'dn' : 'up' ?>"><?= Helper::escape((string) ($asset['delta'] ?? '0%')) ?></b>
          </li>
        <?php endforeach; ?>
      </ul>
    </article>
  </section>

  <section class="glz-05b" aria-label="Consultation allocation ring card">
    <article class="glz-05b__card">
      <header class="glz-05b__head">
        <span class="glz-05b__label">Status mix</span>
      </header>
      <div class="glz-05b__body">
        <div class="glz-05b__ringwrap">
          <div
            class="glz-05b__ring"
            role="img"
            aria-label="<?= Helper::escape((string) ($walletHero['ring_aria'] ?? 'Consultation status mix')) ?>"
            style="--glz-05b-stops: <?= Helper::escape($ringGradient) ?>"
          ></div>
          <div class="glz-05b__center">
            <em><?= Helper::escape((string) ($walletHero['ring_label'] ?? 'Caseload')) ?></em>
            <b><?= Helper::escape((string) ($walletHero['ring_value'] ?? '0')) ?></b>
          </div>
        </div>
        <ul class="glz-05b__legend">
          <?php foreach ($legend as $item): ?>
            <li>
              <i style="--c:<?= Helper::escape((string) ($item['color'] ?? Palette::MEDICAL_BLUE)) ?>"></i><?= Helper::escape((string) ($item['label'] ?? '')) ?><b><?= Helper::escape((string) ($item['percent'] ?? '0%')) ?></b>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="glz-05b__row">
        <?php foreach ($actions as $action): ?>
          <?php $actionIcon = trim((string) ($action['icon'] ?? '')); ?>
          <a href="<?= Helper::escape(Helper::url((string) ($action['url'] ?? '/'))) ?>">
            <?php if ($actionIcon !== ''): ?>
              <i class="bi <?= Helper::escape($actionIcon) ?>" aria-hidden="true"></i>
            <?php endif; ?>
            <?= Helper::escape((string) ($action['label'] ?? '')) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </article>
  </section>
</section>
