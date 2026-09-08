<?php

use App\Helpers\Helper;

$profile = is_array($profile ?? null) ? $profile : [];
$statusMessage = $statusMessage ?? null;

require __DIR__ . '/../../partials/profile/_helpers.php';

$userName = (string) ($profile['full_name'] ?? 'Patient');
$roleLabel = user_profile_role_label('patient');

$profilePage = [
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'url' => '/patient/dashboard'],
        ['label' => 'Profile'],
    ],
    'title' => $userName,
    'subtitle' => 'Review your account details. Clinical records are not managed on this page.',
    'back' => ['label' => 'Back to dashboard', 'url' => '/patient/dashboard'],
    'photo' => $profile['profile_photo_path'] ?? null,
    'photo_user_id' => (int) ($profile['id'] ?? 0),
    'photo_edit_url' => '/patient/profile/edit',
    'name' => $userName,
    'email' => (string) ($profile['email'] ?? ''),
    'role_label' => $roleLabel,
    'status' => (string) ($profile['status'] ?? 'active'),
    'overflow_actions' => [
        ['label' => 'Edit', 'url' => '/patient/profile/edit'],
    ],
    'account_tiles' => user_profile_account_tiles($profile, $roleLabel),
    'actions' => [
        [
            'key' => 'edit',
            'label' => 'Edit',
            'url' => '/patient/profile/edit',
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
            'label' => 'Date of birth',
            'value' => Helper::formatDate($profile['dob'] ?? null, 'd M Y', 'Not assigned'),
            'icon' => 'bi-calendar-event',
        ],
        [
            'label' => 'Gender',
            'value' => user_profile_value(!empty($profile['gender']) ? ucfirst((string) $profile['gender']) : null),
            'icon' => 'bi-gender-ambiguous',
        ],
        [
            'label' => 'Phone',
            'value' => user_profile_value($profile['phone'] ?? null),
            'icon' => 'bi-telephone',
        ],
        [
            'label' => 'Address',
            'value' => user_profile_value($profile['address'] ?? null),
            'icon' => 'bi-geo-alt',
            'wide' => true,
        ],
    ],
    'role_permissions' => [
        'show' => true,
        'text' => 'Access level and permissions granted to this role.',
    ],
    'protected_history' => ['show' => false],
];

require __DIR__ . '/../../partials/profile/layout.php';
