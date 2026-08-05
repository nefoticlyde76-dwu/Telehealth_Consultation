<?php

namespace App\Services;

use App\Core\Csrf;
use App\Core\Database;
use App\Models\AuditLog;
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
                    'title' => 'Manage Patient Accounts',
                    'description' => 'Review patient registrations, search accounts, update profile information, and control access status.',
                    'icon' => 'bi-people-fill',
                    'status' => 'Available now',
                    'url' => '/admin/patients',
                    'action_label' => 'Open Patient Management',
                ],
                [
                    'title' => 'Review Consultation Requests',
                    'description' => 'Approve or reject booked consultation requests, reserve slots, and monitor consultation statuses securely.',
                    'icon' => 'bi-clipboard2-check',
                    'status' => 'Week 5',
                    'url' => '/admin/consultation-requests',
                    'action_label' => 'Open Consultation Requests',
                ],
                [
                    'title' => 'Open User Management',
                    'description' => 'Review all platform accounts with search, role filters, status filters, and responsive table support.',
                    'icon' => 'bi-diagram-3-fill',
                    'status' => 'Available now',
                    'url' => '/admin/users',
                    'action_label' => 'Open User Management',
                ],
                [
                    'title' => 'Manage Doctor Accounts',
                    'description' => 'Create doctor accounts, update clinician profiles, control status, and reset passwords securely.',
                    'icon' => 'bi-person-badge-fill',
                    'status' => 'Available now',
                    'url' => '/admin/doctors',
                    'action_label' => 'Open Doctor Management',
                ],
                [
                    'title' => 'Inspect User Details',
                    'description' => 'Open a full user profile summary to review role-aligned data without changing account records.',
                    'icon' => 'bi-search',
                    'status' => 'Available now',
                ],
                [
                    'title' => 'Update Administrator Profile',
                    'description' => 'Maintain administrator identity details and change the current administrator password securely.',
                    'icon' => 'bi-person-gear',
                    'status' => 'Available now',
                    'url' => '/admin/profile',
                    'action_label' => 'Open Profile Settings',
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

    public static function deleteUserAccount(int $targetUserId, int $actorUserId, string $csrfToken): array
    {
        if (!Csrf::verify($csrfToken)) {
            return [
                'success' => false,
                'message' => 'Unable to verify the request. Please refresh the page and try again.',
                'type' => 'danger',
            ];
        }

        $targetUser = User::findDeletionContextById($targetUserId);

        if ($targetUser === null) {
            return [
                'success' => false,
                'message' => 'The selected user account could not be found.',
                'type' => 'warning',
            ];
        }

        $actorUser = User::findById($actorUserId);

        if ($actorUser === null) {
            return [
                'success' => false,
                'message' => 'The administrator account performing this action could not be verified.',
                'type' => 'danger',
            ];
        }

        $targetRole = (string) ($targetUser['role_name'] ?? '');

        if ($targetUserId === $actorUserId) {
            return [
                'success' => false,
                'message' => 'Administrators cannot permanently delete their own account.',
                'type' => 'warning',
            ];
        }

        if ($targetRole === 'admin' && User::countAdministrators() <= 1) {
            return [
                'success' => false,
                'message' => 'The final administrator account cannot be deleted.',
                'type' => 'warning',
            ];
        }

        $db = Database::getInstance();
        $assetPaths = self::collectDeletionAssetPaths($targetUser);

        try {
            $db->beginTransaction();

            if (!AuditLog::create([
                'actor_user_id' => $actorUserId,
                'actor_name' => $actorUser->full_name ?? 'Administrator',
                'action' => 'user_deleted',
                'subject_name' => (string) ($targetUser['full_name'] ?? 'Unknown User'),
                'subject_role' => $targetRole,
                'description' => sprintf(
                    '%s permanently deleted %s (%s).',
                    (string) ($actorUser->full_name ?? 'Administrator'),
                    (string) ($targetUser['full_name'] ?? 'Unknown User'),
                    ucfirst($targetRole)
                ),
            ])) {
                throw new \RuntimeException('Unable to write the audit log entry.');
            }

            if (!User::deleteById($targetUserId)) {
                throw new \RuntimeException('Unable to delete the user account.');
            }

            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log('User account deletion failed: ' . $exception->getMessage());

            return [
                'success' => false,
                'message' => 'The user account could not be deleted right now. Please try again later.',
                'type' => 'danger',
            ];
        }

        self::deleteStoredAssets($assetPaths);

        return [
            'success' => true,
            'message' => ucfirst($targetRole) . ' account for ' . (string) ($targetUser['full_name'] ?? 'the selected user') . ' was permanently deleted.',
            'type' => 'success',
        ];
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

    private static function collectDeletionAssetPaths(array $targetUser): array
    {
        return array_values(array_filter([
            (string) ($targetUser['admin_profile_photo_path'] ?? ''),
            (string) ($targetUser['doctor_profile_photo_path'] ?? ''),
            (string) ($targetUser['signature_path'] ?? ''),
            (string) ($targetUser['patient_profile_photo_path'] ?? ''),
        ], static fn (string $path): bool => trim($path) !== ''));
    }

    private static function deleteStoredAssets(array $assetPaths): void
    {
        foreach ($assetPaths as $assetPath) {
            ProfilePhotoService::deleteStoredPath((string) $assetPath);
        }
    }
}
