<?php

namespace App\Services;

use App\Models\User;

class AdminUserService
{
    public const PER_PAGE = 10;

    public static function getDashboardData(): array
    {
        $summary = User::getUserManagementSummary();
        $latestUsers = User::getLatestUsers(5);

        return [
            'summary' => $summary,
            'latestUsers' => $latestUsers,
            'stats' => [
                [
                    'label' => 'Total Users',
                    'value' => (string) $summary['total_users'],
                    'icon' => 'bi-people',
                    'description' => 'All patient, doctor, and administrator accounts currently stored in the system.',
                ],
                [
                    'label' => 'Active Accounts',
                    'value' => (string) $summary['active_users'],
                    'icon' => 'bi-person-check',
                    'description' => 'Users who can currently authenticate through the shared MBPHA TeleHealth login.',
                ],
                [
                    'label' => 'Inactive Accounts',
                    'value' => (string) $summary['inactive_users'],
                    'icon' => 'bi-person-x',
                    'description' => 'Accounts retained in the system but currently unavailable for secure login access.',
                ],
                [
                    'label' => 'Administrators',
                    'value' => (string) $summary['admin_users'],
                    'icon' => 'bi-shield-lock',
                    'description' => 'Administrative accounts with governance access to secure user oversight.',
                ],
            ],
            'quickActions' => [
                [
                    'title' => 'Open User Management',
                    'description' => 'Review all platform accounts with search, role filters, status filters, and responsive table support.',
                    'icon' => 'bi-people-fill',
                    'status' => 'Available now',
                ],
                [
                    'title' => 'Review Active Access',
                    'description' => 'Confirm that administrator, doctor, and patient accounts are visible under the correct roles and statuses.',
                    'icon' => 'bi-person-lines-fill',
                    'status' => 'Available now',
                ],
                [
                    'title' => 'Inspect User Details',
                    'description' => 'Open a full user profile summary to review role-aligned data without changing account records.',
                    'icon' => 'bi-search',
                    'status' => 'Available now',
                ],
                [
                    'title' => 'Prepare Future Governance',
                    'description' => 'This foundation is ready for later enhancement into controlled account administration workflows.',
                    'icon' => 'bi-kanban',
                    'status' => 'Week 3 foundation',
                ],
            ],
            'recentActivity' => self::buildRecentActivity($latestUsers),
        ];
    }

    public static function getUserManagementPageData(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $filters = self::normalizeFilters($query);
        $totalUsers = User::countForManagement($filters);
        $totalPages = max(1, (int) ceil($totalUsers / self::PER_PAGE));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * self::PER_PAGE;
        $users = User::findForManagement($filters, self::PER_PAGE, $offset);
        $summary = User::getUserManagementSummary();

        return [
            'filters' => $filters,
            'users' => $users,
            'summary' => $summary,
            'pagination' => [
                'current_page' => $page,
                'per_page' => self::PER_PAGE,
                'total_items' => $totalUsers,
                'total_pages' => $totalPages,
            ],
            'roleOptions' => self::getRoleOptions(),
            'statusOptions' => self::getStatusOptions(),
        ];
    }

    public static function getUserDetail(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        return User::findManagementDetailById($userId);
    }

    public static function getRoleOptions(): array
    {
        return ['admin', 'doctor', 'patient'];
    }

    public static function getStatusOptions(): array
    {
        return ['active', 'inactive'];
    }

    private static function normalizeFilters(array $query): array
    {
        $search = trim((string) ($query['search'] ?? ''));
        $role = strtolower(trim((string) ($query['role'] ?? '')));
        $status = strtolower(trim((string) ($query['status'] ?? '')));

        if (!in_array($role, self::getRoleOptions(), true)) {
            $role = '';
        }

        if (!in_array($status, self::getStatusOptions(), true)) {
            $status = '';
        }

        return [
            'search' => $search,
            'role' => $role,
            'status' => $status,
        ];
    }

    private static function buildRecentActivity(array $latestUsers): array
    {
        if ($latestUsers === []) {
            return [
                [
                    'title' => 'User records will appear here as the platform grows',
                    'description' => 'Recent account activity is ready to surface once additional users are available in the database.',
                    'meta' => 'Awaiting more seeded or registered accounts',
                ],
            ];
        }

        $activity = [];

        foreach ($latestUsers as $user) {
            $activity[] = [
                'title' => $user['full_name'] . ' account visible',
                'description' => 'Role: ' . ucfirst((string) $user['role_name']) . ' | Status: ' . ucfirst((string) $user['status']),
                'meta' => 'Joined ' . date('d M Y', strtotime((string) $user['created_at'])),
            ];
        }

        return $activity;
    }
}
