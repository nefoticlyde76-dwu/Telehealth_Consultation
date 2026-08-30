<?php

/**
 * Compact dashboard attention strip. One message, one next step.
 *
 * @var string $attentionText
 * @var string $attentionIcon
 * @var array{label:string,url:string,icon?:string}|null $attentionAction
 */
use App\Helpers\Helper;

$attentionText = trim((string) ($attentionText ?? ''));
$attentionIcon = (string) ($attentionIcon ?? 'bi-info-circle');
$attentionAction = is_array($attentionAction ?? null) ? $attentionAction : null;
$attentionTone = (string) ($attentionTone ?? 'info');
if (!in_array($attentionTone, ['info', 'success', 'warning', 'pending'], true)) {
    $attentionTone = 'info';
}
?>

<?php if ($attentionText !== ''): ?>
  <div class="ux-attention ux-attention--<?= Helper::escape($attentionTone) ?> mb-4" role="status">
    <span class="ux-attention__icon" aria-hidden="true">
      <i class="bi <?= Helper::escape($attentionIcon) ?>"></i>
    </span>
    <p class="ux-attention__text mb-0"><?= Helper::escape($attentionText) ?></p>
    <?php if ($attentionAction !== null && trim((string) ($attentionAction['url'] ?? '')) !== ''): ?>
      <a href="<?= Helper::url((string) $attentionAction['url']) ?>" class="btn btn-primary btn-sm">
        <?php if (!empty($attentionAction['icon'])): ?>
          <i class="bi <?= Helper::escape((string) $attentionAction['icon']) ?> me-1" aria-hidden="true"></i>
        <?php endif; ?>
        <?= Helper::escape((string) ($attentionAction['label'] ?? 'Continue')) ?>
      </a>
    <?php endif; ?>
  </div>
<?php endif; ?>
