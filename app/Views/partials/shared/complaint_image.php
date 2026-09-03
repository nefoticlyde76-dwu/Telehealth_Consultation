<?php

$complaintImageUrl = trim((string) ($complaintImageUrl ?? ''));
$complaintImageAlt = trim((string) ($complaintImageAlt ?? 'Complaint image'));
$complaintImageClass = trim((string) ($complaintImageClass ?? ''));

if ($complaintImageUrl === '') {
    return;
}
?>
<figure class="complaint-image <?= $complaintImageClass !== '' ? \App\Helpers\Helper::escape($complaintImageClass) : '' ?>">
  <a href="<?= \App\Helpers\Helper::escape($complaintImageUrl) ?>" target="_blank" rel="noopener noreferrer">
    <img
      src="<?= \App\Helpers\Helper::escape($complaintImageUrl) ?>"
      alt="<?= \App\Helpers\Helper::escape($complaintImageAlt) ?>"
      loading="lazy"
      decoding="async"
    >
  </a>
  <figcaption class="complaint-image__caption">Open the image in a new tab to view it at full size.</figcaption>
</figure>
