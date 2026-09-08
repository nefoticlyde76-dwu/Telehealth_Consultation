<?php

use App\Helpers\Helper;

$profile = is_array($profile ?? null) ? $profile : [];
$statusMessage = $statusMessage ?? null;
$signatureEmbed = \App\Services\DoctorSignatureService::embed(
    (int) ($profile['id'] ?? 0),
    (string) ($profile['signature_path'] ?? '')
);
$signatureSrc = (string) ($signatureEmbed['src'] ?? '');

require __DIR__ . '/../../partials/profile/_helpers.php';

$userName = (string) ($profile['full_name'] ?? 'Doctor');
$roleLabel = user_profile_role_label('doctor');

ob_start();
?>
<div class="user-profile-signature">
  <h4 class="user-profile-signature__title">Digital signature</h4>
  <?php if ($signatureSrc !== ''): ?>
    <img class="doctor-signature-preview" src="<?= Helper::escape($signatureSrc) ?>" alt="Doctor digital signature">
  <?php else: ?>
    <div class="doctor-signature-placeholder">
      <i class="bi bi-pen" aria-hidden="true"></i>
      <span>Not assigned</span>
    </div>
  <?php endif; ?>
  <p class="user-profile-card__lede mb-0 mt-3">Your signature is attached automatically when you issue a prescription. Another doctor's signature cannot be used.</p>
</div>
<?php
$roleExtraHtml = ob_get_clean();

$profilePage = [
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'url' => '/doctor/dashboard'],
        ['label' => 'Profile'],
    ],
    'title' => $userName,
    'subtitle' => 'Review your identity and uploaded assets as they will appear in clinical workflows.',
    'back' => ['label' => 'Back to dashboard', 'url' => '/doctor/dashboard'],
    'photo' => $profile['profile_photo_path'] ?? null,
    'photo_user_id' => (int) ($profile['id'] ?? 0),
    'photo_edit_url' => '/doctor/profile/edit',
    'name' => $userName,
    'email' => (string) ($profile['email'] ?? ''),
    'role_label' => $roleLabel,
    'status' => (string) ($profile['status'] ?? 'active'),
    'overflow_actions' => [
        ['label' => 'Edit', 'url' => '/doctor/profile/edit'],
    ],
    'account_tiles' => user_profile_account_tiles($profile, $roleLabel),
    'actions' => [
        [
            'key' => 'edit',
            'label' => 'Edit',
            'url' => '/doctor/profile/edit',
            'method' => 'GET',
            'tone' => 'neutral',
        ],
        [
            'key' => 'security',
            'label' => 'Security',
            'url' => '/account/security',
            'method' => 'GET',
            'tone' => 'neutral',
        ],
    ],
    'role_tiles' => [
        [
            'label' => 'Specialization',
            'value' => user_profile_value($profile['specialization'] ?? null),
            'icon' => 'bi-heart-pulse',
        ],
        [
            'label' => 'License Number',
            'value' => user_profile_value($profile['license_number'] ?? null),
            'icon' => 'bi-shield-check',
        ],
        [
            'label' => 'Professional Title',
            'value' => user_profile_value($profile['professional_title'] ?? null),
            'icon' => 'bi-award',
        ],
        [
            'label' => 'Employee ID',
            'value' => user_profile_value($profile['employee_id'] ?? null),
            'icon' => 'bi-person-badge',
        ],
        [
            'label' => 'Phone',
            'value' => user_profile_value($profile['phone'] ?? null),
            'icon' => 'bi-telephone',
        ],
    ],
    'role_extra_html' => $roleExtraHtml,
    'role_permissions' => [
        'show' => true,
        'text' => 'Access level and permissions granted to this role.',
    ],
    'protected_history' => ['show' => false],
];

require __DIR__ . '/../../partials/profile/layout.php';
