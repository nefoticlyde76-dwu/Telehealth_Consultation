<?php
$printType = (string) ($printType ?? 'record');
$documentHeading = $printType === 'prescription' ? 'Prescription' : 'Consultation Record';
?>

<div class="cr-page print-document-page">
  <div class="cr-print-hide">
    <header class="cr-header">
      <div class="cr-header__copy">
        <h1 class="cr-title"><?= \App\Helpers\Helper::escape($documentHeading) ?></h1>
        <p class="cr-description">Use Print or save as PDF to keep a copy of this document.</p>
      </div>
    </header>
  </div>

  <?php require __DIR__ . '/../partials/shared/_consultation_record_details.php'; ?>
</div>
