<?php

/**
 * Consistent dashboard card/section heading with a single Bootstrap icon.
 *
 * @var string $sectionTitle
 * @var string $sectionSubtitle
 * @var string $sectionIcon
 * @var string $sectionTone
 */
use App\Helpers\Helper;

$sectionTitle = (string) ($sectionTitle ?? '');
$sectionSubtitle = (string) ($sectionSubtitle ?? '');
$sectionIcon = trim((string) ($sectionIcon ?? 'bi-grid'));
$sectionTone = (string) ($sectionTone ?? 'navy');
$sectionToneClass = match ($sectionTone) {
    'success', 'mint' => 'ux-section-heading__icon--success',
    'pending', 'warning', 'amber' => 'ux-section-heading__icon--pending',
    'info', 'cyan' => 'ux-section-heading__icon--info',
    'danger' => 'ux-section-heading__icon--danger',
    default => 'ux-section-heading__icon--navy',
};
?>
<div class="ux-section-heading">
  <span class="ux-section-heading__icon <?= $sectionToneClass ?>" aria-hidden="true">
    <i class="bi <?= Helper::escape($sectionIcon) ?>"></i>
  </span>
  <div class="ux-section-heading__copy">
    <h2 class="h5 mb-1"><?= Helper::escape($sectionTitle) ?></h2>
    <?php if ($sectionSubtitle !== ''): ?>
      <p class="text-muted mb-0 small"><?= Helper::escape($sectionSubtitle) ?></p>
    <?php endif; ?>
  </div>
</div>
<?php
$sectionTitle = '';
$sectionSubtitle = '';
$sectionIcon = '';
$sectionTone = 'navy';
?>
