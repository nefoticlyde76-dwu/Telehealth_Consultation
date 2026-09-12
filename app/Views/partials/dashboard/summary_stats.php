<?php

/**
 * Dashboard summary cards. Linked cards open filtered lists — they are not authorization.
 *
 * @var list<array{label:string,value:string|int,description?:string,icon?:string,tone?:string,url?:string}> $summaryStats
 * @var bool $summaryStatsCompact Horizontal metric cards with a trailing chevron.
 * @var int $summaryStatsColumns 3 or 4 column grid.
 */
use App\Helpers\Helper;

$summaryStats = is_array($summaryStats ?? null) ? $summaryStats : [];
$summaryStatsCompact = !empty($summaryStatsCompact);
$summaryStatsColumns = (int) ($summaryStatsColumns ?? 0);
if ($summaryStatsColumns !== 3) {
    $summaryStatsColumns = 4;
}
$summaryColClass = $summaryStatsColumns === 3 ? 'col-sm-6 col-xl-4' : 'col-sm-6 col-xl-3';
$toneMap = [
    'surface' => 'ux-stat__icon--surface',
    'mint' => 'ux-stat__icon--mint',
    'success' => 'ux-stat__icon--mint',
    'amber' => 'ux-stat__icon--amber',
    'warning' => 'ux-stat__icon--amber',
    'pending' => 'ux-stat__icon--pending',
    'navy' => 'ux-stat__icon--navy',
    'danger' => 'ux-stat__icon--danger',
    'info' => 'ux-stat__icon--purple',
    'cyan' => 'ux-stat__icon--cyan',
    'purple' => 'ux-stat__icon--purple',
];
$cardToneMap = [
    'surface' => 'ux-stat--navy',
    'mint' => 'ux-stat--success',
    'success' => 'ux-stat--success',
    'amber' => 'ux-stat--pending',
    'warning' => 'ux-stat--pending',
    'pending' => 'ux-stat--pending',
    'navy' => 'ux-stat--navy',
    'danger' => 'ux-stat--danger',
    'info' => 'ux-stat--info',
    'cyan' => 'ux-stat--info',
    'purple' => 'ux-stat--info',
];
?>

<?php if ($summaryStats !== []): ?>
<section class="mb-4 ux-summary-stats" aria-label="Priority summary">
  <div class="row g-3">
    <?php foreach ($summaryStats as $stat): ?>
      <?php
      $statLabel = (string) ($stat['label'] ?? 'Metric');
      $statValue = (string) ($stat['value'] ?? '0');
      $statDescription = (string) ($stat['description'] ?? '');
      $statIcon = (string) ($stat['icon'] ?? 'bi-graph-up');
      $statToneKey = (string) ($stat['tone'] ?? 'surface');
      $statTone = $toneMap[$statToneKey] ?? 'ux-stat__icon--surface';
      $statCardTone = $cardToneMap[$statToneKey] ?? 'ux-stat--pending';
      $statUrl = trim((string) ($stat['url'] ?? ''));
      $statTag = $statUrl !== '' ? 'a' : 'div';
      $statHref = '';
      if ($statUrl !== '') {
          $resolvedUrl = preg_match('#^https?://#i', $statUrl) === 1 ? $statUrl : Helper::url($statUrl);
          $statHref = ' href="' . Helper::escape($resolvedUrl) . '"';
      }
      $statAria = $statUrl !== ''
          ? ' aria-label="' . Helper::escape($statLabel . ': ' . $statValue . '. Open related records.') . '"'
          : '';
      $statClass = 'ux-stat h-100 ' . $statCardTone;
      $statClass .= $summaryStatsCompact ? ' compact' : ' ux-stat--stack';
      if ($statUrl !== '') {
          $statClass .= ' ux-stat--link';
      }
      ?>
      <div class="<?= $summaryColClass ?>">
        <<?= $statTag ?> class="<?= Helper::escape($statClass) ?>"<?= $statHref ?><?= $statAria ?>>
          <span class="ux-stat__icon <?= $statTone ?>" aria-hidden="true">
            <i class="bi <?= Helper::escape($statIcon) ?>"></i>
          </span>
          <div class="ux-stat__body">
            <?php if ($summaryStatsCompact): ?>
              <p class="ux-stat__value mb-0"<?= is_numeric($statValue) ? ' data-counter="' . Helper::escape($statValue) . '"' : '' ?>>
                <?= Helper::escape($statValue) ?>
              </p>
              <span class="ux-stat__label"><?= Helper::escape($statLabel) ?></span>
              <?php if ($statDescription !== ''): ?>
                <p class="ux-stat__meta"><?= Helper::escape($statDescription) ?></p>
              <?php endif; ?>
            <?php else: ?>
              <span class="ux-stat__label"><?= Helper::escape($statLabel) ?></span>
              <p class="ux-stat__value mb-0"<?= is_numeric($statValue) ? ' data-counter="' . Helper::escape($statValue) . '"' : '' ?>>
                <?= Helper::escape($statValue) ?>
              </p>
              <?php if ($statDescription !== ''): ?>
                <p class="ux-stat__meta"><?= Helper::escape($statDescription) ?></p>
              <?php endif; ?>
              <?php if ($statUrl !== ''): ?>
                <span class="ux-stat__cta">
                  View details
                  <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </span>
              <?php endif; ?>
            <?php endif; ?>
          </div>
          <?php if ($summaryStatsCompact && $statUrl !== ''): ?>
            <span class="ux-stat__chevron" aria-hidden="true">
              <i class="bi bi-chevron-right"></i>
            </span>
          <?php endif; ?>
        </<?= $statTag ?>>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php
endif;
$summaryStatsCompact = false;
$summaryStatsColumns = 4;
?>
