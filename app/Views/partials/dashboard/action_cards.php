<?php

/**
 * Primary next-step action cards.
 *
 * @var list<array{title:string,description?:string,url:string,icon?:string,action_label?:string,emphasis?:string}> $actionCards
 */
use App\Helpers\Helper;

$actionCards = is_array($actionCards ?? null) ? $actionCards : [];
$actionColClass = count($actionCards) >= 4 ? 'col-md-6 col-xl-3' : 'col-md-6 col-xl-4';
?>

<?php if ($actionCards !== []): ?>
<section class="mb-4" aria-label="Primary actions">
  <div class="row g-3">
    <?php foreach ($actionCards as $action): ?>
      <?php
      $actionTitle = (string) ($action['title'] ?? '');
      $actionDescription = (string) ($action['description'] ?? '');
      $actionUrl = trim((string) ($action['url'] ?? ''));
      $actionIcon = (string) ($action['icon'] ?? 'bi-arrow-right');
      $actionLabel = (string) ($action['action_label'] ?? 'Open');
      $actionEmphasis = (string) ($action['emphasis'] ?? 'secondary');
      $buttonClass = $actionEmphasis === 'primary' ? 'btn btn-primary btn-sm' : 'btn btn-outline-primary btn-sm';
      $cardClass = $actionEmphasis === 'primary' ? 'ux-action-card ux-action-card--primary h-100' : 'ux-action-card h-100';
      if ($actionUrl === '') {
          continue;
      }
      ?>
      <div class="<?= $actionColClass ?>">
        <div class="<?= $cardClass ?>">
          <div class="ux-action-card__icon" aria-hidden="true">
            <i class="bi <?= Helper::escape($actionIcon) ?>"></i>
          </div>
          <div class="ux-action-card__body">
            <h3 class="ux-action-card__title"><?= Helper::escape($actionTitle) ?></h3>
            <?php if ($actionDescription !== ''): ?>
              <p class="ux-action-card__text"><?= Helper::escape($actionDescription) ?></p>
            <?php endif; ?>
          </div>
          <a href="<?= Helper::url($actionUrl) ?>" class="<?= $buttonClass ?>">
            <?= Helper::escape($actionLabel) ?>
            <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
