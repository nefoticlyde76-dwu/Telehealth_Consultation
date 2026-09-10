<?php

/**
 * Dashboard summary cards. Linked cards open filtered lists — they are not authorization.
 *
 * @var list<array{label:string,value:string|int,description?:string,icon?:string,tone?:string,url?:string}> $summaryStats
 */
use App\Helpers\Helper;

$summaryStats = is_array($summaryStats ?? null) ? $summaryStats : [];
$toneMap = [
    'surface' => 'ux-stat__icon--surface',
    'mint' => 'ux-stat__icon--mint',
    'success' => 'ux-stat__icon--mint',
    'amber' => 'ux-stat__icon--pending',
    'warning' => 'ux-stat__icon--pending',
    'pending' => 'ux-stat__icon--pending',
    'navy' => 'ux-stat__icon--navy',
    'danger' => 'ux-stat__icon--danger',
    'info' => 'ux-stat__icon--cyan',
    'cyan' => 'ux-stat__icon--cyan',
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
      $statHref = $statUrl !== '' ? ' href="' . Helper::escape(Helper::url($statUrl)) . '"' : '';
      $statAria = $statUrl !== ''
          ? ' aria-label="' . Helper::escape($statLabel . ': ' . $statValue . '. Open related records.') . '"'
          : '';
      ?>
      <div class="col-6 col-xl-3">
        <<?= $statTag ?> class="ux-stat h-100 <?= $statCardTone ?><?= $statUrl !== '' ? ' ux-stat--link' : '' ?>"<?= $statHref ?><?= $statAria ?>>
          <span class="ux-stat__icon <?= $statTone ?>" aria-hidden="true">
            <i class="bi <?= Helper::escape($statIcon) ?>"></i>
          </span>
          <span class="ux-stat__label"><?= Helper::escape($statLabel) ?></span>
          <p class="ux-stat__value mb-0"<?= is_numeric($statValue) ? ' data-counter="' . Helper::escape($statValue) . '"' : '' ?>>
            <?= Helper::escape($statValue) ?>
          </p>
          <?php if ($statDescription !== ''): ?>
            <p class="ux-stat__meta text-muted mb-0"><?= Helper::escape($statDescription) ?></p>
          <?php endif; ?>
          <?php if ($statUrl !== ''): ?>
            <span class="ux-stat__cta">
              View details
              <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </span>
          <?php endif; ?>
        </<?= $statTag ?>>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
