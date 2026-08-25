<?php

namespace App\Helpers;

/**
 * Canonical authenticated navigation for MBPHA TeleHealth.
 * Menu visibility is not authorization — RoleMiddleware remains authoritative.
 */
class DashboardNav
{
    /**
     * @return array{
     *   roleLabel:string,
     *   home:string,
     *   profile:string,
     *   settings:string,
     *   groups:list<array{caption:string,items:list<array<string,mixed>>}>,
     *   account:list<array<string,mixed>>
     * }
     */
    public static function forRole(string $role): array
    {
        return match ($role) {
            'admin' => [
                'roleLabel' => 'Administrator',
                'home' => '/admin/dashboard',
                'profile' => '/admin/profile',
                'settings' => '/admin/profile',
                'groups' => [
                    [
                        'caption' => 'Overview',
                        'items' => [
                            ['path' => '/admin/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2'],
                        ],
                    ],
                    [
                        'caption' => 'Operations',
                        'items' => [
                            ['path' => '/admin/consultation-requests', 'label' => 'Consultation Requests', 'icon' => 'bi-clipboard2-check'],
                        ],
                    ],
                    [
                        'caption' => 'Management',
                        'items' => [
                            ['path' => '/admin/users', 'label' => 'Users', 'icon' => 'bi-people'],
                            ['path' => '/admin/doctors', 'label' => 'Doctors', 'icon' => 'bi-person-badge'],
                            ['path' => '/admin/patients', 'label' => 'Patients', 'icon' => 'bi-heart-pulse'],
                        ],
                    ],
                    [
                        'caption' => 'Records',
                        'items' => [
                            ['path' => '/admin/audit-logs', 'label' => 'Audit Logs', 'icon' => 'bi-journal-text'],
                        ],
                    ],
                    [
                        'caption' => 'Updates',
                        'items' => [
                            ['path' => '/notifications', 'label' => 'Notifications', 'icon' => 'bi-bell'],
                        ],
                    ],
                ],
                'account' => [
                    ['path' => '/admin/profile', 'label' => 'Profile', 'icon' => 'bi-person'],
                    ['path' => '/account/security', 'label' => 'Security', 'icon' => 'bi-shield-lock'],
                    ['path' => '/account/notifications/preferences', 'label' => 'Preferences', 'icon' => 'bi-sliders'],
                ],
            ],
            'doctor' => [
                'roleLabel' => 'Doctor',
                'home' => '/doctor/dashboard',
                'profile' => '/doctor/profile',
                'settings' => '/doctor/profile/edit',
                'groups' => [
                    [
                        'caption' => 'Overview',
                        'items' => [
                            ['path' => '/doctor/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2'],
                        ],
                    ],
                    [
                        'caption' => 'Schedule',
                        'items' => [
                            ['path' => '/doctor/availability', 'label' => 'Availability', 'icon' => 'bi-calendar-week'],
                            ['path' => '/doctor/consultations', 'label' => 'Consultations', 'icon' => 'bi-calendar2-check'],
                        ],
                    ],
                    [
                        'caption' => 'Updates',
                        'items' => [
                            ['path' => '/notifications', 'label' => 'Notifications', 'icon' => 'bi-bell'],
                        ],
                    ],
                ],
                'account' => [
                    ['path' => '/doctor/profile', 'label' => 'Profile', 'icon' => 'bi-person'],
                    ['path' => '/account/security', 'label' => 'Security', 'icon' => 'bi-shield-lock'],
                    ['path' => '/account/notifications/preferences', 'label' => 'Preferences', 'icon' => 'bi-sliders'],
                ],
            ],
            'patient' => [
                'roleLabel' => 'Patient',
                'home' => '/patient/dashboard',
                'profile' => '/patient/profile',
                'settings' => '/patient/profile/edit',
                'groups' => [
                    [
                        'caption' => 'Overview',
                        'items' => [
                            ['path' => '/patient/dashboard', 'label' => 'Dashboard', 'icon' => 'bi-grid-1x2'],
                        ],
                    ],
                    [
                        'caption' => 'Care',
                        'items' => [
                            ['path' => '/patient/available-slots', 'label' => 'Book Consultation', 'icon' => 'bi-calendar2-plus'],
                            ['path' => '/patient/doctors', 'label' => 'Doctors', 'icon' => 'bi-person-badge'],
                        ],
                    ],
                    [
                        'caption' => 'Records',
                        'items' => [
                            ['path' => '/patient/consultation-requests', 'label' => 'My Consultations', 'icon' => 'bi-clipboard2-check'],
                        ],
                    ],
                    [
                        'caption' => 'Updates',
                        'items' => [
                            ['path' => '/notifications', 'label' => 'Notifications', 'icon' => 'bi-bell'],
                        ],
                    ],
                ],
                'account' => [
                    ['path' => '/patient/profile', 'label' => 'Profile', 'icon' => 'bi-person'],
                    ['path' => '/account/security', 'label' => 'Security', 'icon' => 'bi-shield-lock'],
                    ['path' => '/account/notifications/preferences', 'label' => 'Preferences', 'icon' => 'bi-sliders'],
                ],
            ],
            default => [
                'roleLabel' => 'Dashboard',
                'home' => '/',
                'profile' => '#',
                'settings' => '#',
                'groups' => [],
                'account' => [],
            ],
        };
    }

    /**
     * Flattened primary items used for active-state sibling comparison.
     *
     * @return list<array<string, mixed>>
     */
    public static function allItems(string $role): array
    {
        $config = self::forRole($role);
        $items = [];
        foreach ($config['groups'] as $group) {
            foreach ($group['items'] as $item) {
                $items[] = $item;
            }
        }
        foreach ($config['account'] as $item) {
            $items[] = $item;
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $siblings
     */
    public static function isActive(string $itemPath, string $currentPath, array $siblings = []): bool
    {
        if ($itemPath === '' || $itemPath === '#') {
            return false;
        }

        if ($currentPath === $itemPath) {
            return true;
        }

        if ($itemPath === '/' || !str_starts_with($currentPath, $itemPath . '/')) {
            return false;
        }

        foreach ($siblings as $sibling) {
            $siblingPath = (string) ($sibling['path'] ?? '');
            if ($siblingPath === '' || $siblingPath === $itemPath) {
                continue;
            }
            if ($currentPath === $siblingPath || str_starts_with($currentPath, $siblingPath . '/')) {
                if (strlen($siblingPath) > strlen($itemPath)) {
                    return false;
                }
            }
        }

        return true;
    }

    public static function quickAction(string $role): ?array
    {
        return match ($role) {
            'patient' => ['label' => 'Book Consultation', 'url' => '/patient/available-slots', 'icon' => 'bi-calendar2-plus'],
            'doctor' => ['label' => 'Create Slot', 'url' => '/doctor/availability/create', 'icon' => 'bi-plus-lg'],
            'admin' => ['label' => 'Review Requests', 'url' => '/admin/consultation-requests', 'icon' => 'bi-clipboard2-check'],
            default => null,
        };
    }
}
