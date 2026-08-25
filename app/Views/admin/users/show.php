<?php

use App\Helpers\Helper;
use App\Helpers\Status;
use App\Services\AccountSecurityService;

$managedUser = is_array($managedUser ?? null) ? $managedUser : [];
$csrfToken = (string) ($csrfToken ?? '');
$confirmationPhrase = (string) ($confirmationPhrase ?? AccountSecurityService::CONFIRMATION_PHRASE);
$actions = is_array($managedUser['actions'] ?? null) ? $managedUser['actions'] : [];
$counts = is_array($managedUser['protected_counts'] ?? null) ? $managedUser['protected_counts'] : [];
$roleName = (string) ($managedUser['role_name'] ?? '');
$statusKey = (string) ($managedUser['status'] ?? '');
$userId = (int) ($managedUser['id'] ?? 0);
$userName = (string) ($managedUser['full_name'] ?? 'User');
$isDeleted = Status::isDeletedUserStatus($statusKey);
$isInvitationPending = Status::isInvitationPendingUserStatus($statusKey);

require __DIR__ . '/../../partials/profile/_helpers.php';

$roleLabel = user_profile_role_label($roleName);
$editUrl = (string) ($managedUser['edit_url'] ?? '');
if ($editUrl === '') {
    foreach ($actions as $action) {
        if (($action['key'] ?? '') === 'edit') {
            $editUrl = (string) ($action['url'] ?? '');
            break;
        }
    }
}

$overflowActions = [];
foreach ($actions as $action) {
    if (($action['key'] ?? '') === 'edit' && ($action['method'] ?? 'GET') === 'GET') {
        $overflowActions[] = $action;
    }
}

$roleTiles = [];
if ($roleName === 'admin') {
    $roleTiles[] = [
        'label' => 'Employee ID',
        'value' => user_profile_value($managedUser['employee_id'] ?? null),
        'icon' => 'bi-person-badge',
    ];
} elseif ($roleName === 'doctor') {
    $roleTiles[] = [
        'label' => 'Specialization',
        'value' => user_profile_value($managedUser['specialization'] ?? null),
        'icon' => 'bi-heart-pulse',
    ];
    $roleTiles[] = [
        'label' => 'License Number',
        'value' => user_profile_value($managedUser['license_number'] ?? null),
        'icon' => 'bi-shield-check',
    ];
} elseif ($roleName === 'patient') {
    $roleTiles[] = [
        'label' => 'Date of birth',
        'value' => Helper::formatDate($managedUser['dob'] ?? null, 'd M Y', 'Not assigned'),
        'icon' => 'bi-calendar-event',
    ];
    $roleTiles[] = [
        'label' => 'Gender',
        'value' => user_profile_value(!empty($managedUser['gender']) ? ucfirst((string) $managedUser['gender']) : null),
        'icon' => 'bi-gender-ambiguous',
    ];
}

$profilePage = [
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'url' => '/admin/dashboard'],
        ['label' => 'Users', 'url' => '/admin/users'],
        ['label' => $userName],
    ],
    'title' => $userName,
    'subtitle' => 'Non-clinical account details. Consultation notes and prescriptions are not available here.',
    'back' => ['label' => 'Back to users', 'url' => '/admin/users'],
    'photo' => $managedUser['profile_photo_path'] ?? null,
    'photo_edit_url' => $editUrl,
    'name' => $userName,
    'email' => (string) ($managedUser['email'] ?? ''),
    'role_label' => $roleLabel,
    'status' => $statusKey,
    'overflow_actions' => $overflowActions,
    'account_tiles' => user_profile_account_tiles($managedUser, $roleLabel),
    'actions' => $actions,
    'csrf_token' => $csrfToken,
    'user_id' => $userId,
    'return_to' => '/admin/users/' . $userId,
    'force_password_reset' => [
        'show' => !$isDeleted && !$isInvitationPending && $userId > 0,
        'url' => '/admin/users/' . $userId . '/force-password-reset',
        'csrf' => $csrfToken,
        'checked' => !empty($managedUser['force_password_reset']),
    ],
    'role_tiles' => $roleTiles,
    'role_permissions' => [
        'show' => true,
        'text' => 'Access level and permissions granted to this role.',
        'details_url' => $roleName !== '' ? '/admin/users?role=' . rawurlencode($roleName) : '',
        'details_label' => 'View role details',
    ],
    'protected_history' => [
        'show' => true,
        'subtitle' => 'These counts confirm related operational records. Their contents are not displayed.',
        'notice' => 'Sensitive operational data is hidden for non-clinical accounts.',
        'stats' => [
            [
                'label' => 'Consultation records retained',
                'value' => (string) (int) ($counts['consultation_records'] ?? 0),
                'icon' => 'bi-file-earmark-person',
                'tone' => 'blue',
            ],
            [
                'label' => 'Prescriptions retained',
                'value' => (string) (int) ($counts['prescriptions'] ?? 0),
                'icon' => 'bi-capsule',
                'tone' => 'green',
            ],
            [
                'label' => 'Completed appointments retained',
                'value' => (string) (int) ($counts['completed_requests'] ?? 0),
                'icon' => 'bi-calendar2-check',
                'tone' => 'purple',
            ],
            [
                'label' => 'Open bookings that would be cancelled',
                'value' => (string) (int) ($counts['open_requests'] ?? 0),
                'icon' => 'bi-calendar2-x',
                'tone' => 'orange',
            ],
        ],
    ],
    'show_delete_modal' => true,
    'is_deleted' => $isDeleted,
    'confirmation_phrase' => $confirmationPhrase,
];

require __DIR__ . '/../../partials/profile/layout.php';
