<?php

use App\Helpers\Helper;
use App\Helpers\Status;

if (!function_exists('user_profile_value')) {
    function user_profile_value(?string $value, string $empty = 'Not assigned'): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : $empty;
    }
}

if (!function_exists('user_profile_action_icon')) {
    function user_profile_action_icon(string $key): string
    {
        return match ($key) {
            'edit' => 'bi-pencil',
            'reset_password' => 'bi-lock',
            'suspend' => 'bi-pause-circle',
            'deactivate' => 'bi-person-x',
            'reactivate' => 'bi-arrow-counterclockwise',
            'delete' => 'bi-trash',
            'security' => 'bi-shield-lock',
            default => 'bi-gear',
        };
    }
}

if (!function_exists('user_profile_status_tone')) {
    function user_profile_status_tone(string $status): string
    {
        return match (Status::normalizeKey(Status::DOMAIN_USER, $status)) {
            Status::USER_ACTIVE => 'success',
            Status::USER_SUSPENDED => 'warning',
            Status::USER_INACTIVE => 'danger',
            Status::USER_INVITATION_PENDING => 'pending',
            default => 'muted',
        };
    }
}

if (!function_exists('user_profile_account_tiles')) {
    /**
     * @param array<string, mixed> $user
     * @return list<array{label:string,value:string,icon:string,tone?:string,wide?:bool}>
     */
    function user_profile_account_tiles(array $user, string $roleLabel): array
    {
        $status = (string) ($user['status'] ?? '');
        $forceReset = !empty($user['force_password_reset']);

        return [
            [
                'label' => 'Email',
                'value' => user_profile_value($user['email'] ?? null, 'Not available'),
                'icon' => 'bi-envelope',
            ],
            [
                'label' => 'Role',
                'value' => user_profile_value($roleLabel, 'Not available'),
                'icon' => 'bi-person',
            ],
            [
                'label' => 'Account Status',
                'value' => Status::label($status, Status::DOMAIN_USER),
                'icon' => 'bi-shield-check',
                'tone' => user_profile_status_tone($status),
            ],
            [
                'label' => 'Created',
                'value' => Helper::formatDate((string) ($user['created_at'] ?? ''), 'd M Y, g:i A', 'Not available'),
                'icon' => 'bi-calendar3',
            ],
            [
                'label' => 'Last Login',
                'value' => Helper::formatDate((string) ($user['last_login_at'] ?? ''), 'd M Y, g:i A', 'Never'),
                'icon' => 'bi-clock',
            ],
            [
                'label' => 'Password Reset on Next Login',
                'value' => $forceReset ? 'Required' : 'Not required',
                'icon' => 'bi-lock',
                'tone' => $forceReset ? 'warning' : 'success',
            ],
        ];
    }
}

if (!function_exists('user_profile_role_label')) {
    function user_profile_role_label(string $roleName): string
    {
        $nav = \App\Helpers\DashboardNav::forRole($roleName);
        $label = trim((string) ($nav['roleLabel'] ?? ''));
        if ($label !== '' && $label !== 'Dashboard') {
            return $label;
        }

        return $roleName !== '' ? ucfirst($roleName) : 'User';
    }
}
