<?php

namespace App\Services;

use App\Core\Csrf;
use App\Core\Database;
use App\Helpers\Status;
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
                    'description' => 'Administrator accounts that manage users and consultation requests.',
                ],
            ],
            'quickActions' => [
                [
                    'title' => 'Manage Patient Accounts',
                    'description' => 'Review patient registrations, search accounts, update profile information, and control access status.',
                    'icon' => 'bi-people-fill',
                    'status' => 'Available',
                    'url' => '/admin/patients',
                    'action_label' => 'Open Patient Management',
                ],
                [
                    'title' => 'Review Consultation Requests',
                    'description' => 'Approve or reject booked consultation requests, reserve slots, and monitor consultation statuses.',
                    'icon' => 'bi-clipboard2-check',
                    'status' => 'Active',
                    'url' => '/admin/consultation-requests',
                    'action_label' => 'Open Consultation Requests',
                ],
                [
                    'title' => 'Open User Management',
                    'description' => 'Review all platform accounts with search, role filters, and status filters.',
                    'icon' => 'bi-diagram-3-fill',
                    'status' => 'Available',
                    'url' => '/admin/users',
                    'action_label' => 'Open User Management',
                ],
                [
                    'title' => 'Manage Doctor Accounts',
                    'description' => 'Create doctor accounts, update clinician profiles, control status, and reset passwords.',
                    'icon' => 'bi-person-badge-fill',
                    'status' => 'Available',
                    'url' => '/admin/doctors',
                    'action_label' => 'Open Doctor Management',
                ],
                [
                    'title' => 'Update Administrator Profile',
                    'description' => 'Update administrator identity details and change the current administrator password.',
                    'icon' => 'bi-person-gear',
                    'status' => 'Available',
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
        $actorUserId = (int) (AuthService::getUserId() ?? 0);
        foreach ($users as $index => $row) {
            $users[$index]['actions'] = self::availableActions($row, $actorUserId);
        }

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
            'sortOptions' => self::getSortOptions(),
        ];
    }

    public static function getUserDetail(int $userId, int $actorUserId = 0): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $user = User::findManagementDetailById($userId);
        if ($user === null || Status::isDeletedUserStatus((string) ($user['status'] ?? ''))) {
            return null;
        }

        $role = (string) ($user['role_name'] ?? '');
        $user['actions'] = self::availableActions($user, $actorUserId);
        $user['protected_counts'] = UserDeletionService::countProtectedRecords(
            Database::getInstance(),
            $userId,
            $role
        );
        $user['edit_url'] = self::editUrlForRole($role, $userId);

        return $user;
    }

    /**
     * @param array<string, mixed> $user
     * @return list<array{key:string,label:string,url:string,method:string,tone:string}>
     */
    public static function availableActions(array $user, int $actorUserId): array
    {
        $userId = (int) ($user['id'] ?? 0);
        $status = Status::normalizeKey(Status::DOMAIN_USER, (string) ($user['status'] ?? ''));
        $role = (string) ($user['role_name'] ?? '');
        $isSelf = $userId > 0 && $userId === $actorUserId;
        $isDeleted = $status === Status::USER_DELETED;

        $actions = [];
        if ($userId <= 0) {
            return $actions;
        }

        $actions[] = [
            'key' => 'view',
            'label' => 'View',
            'url' => '/admin/users/' . $userId,
            'method' => 'GET',
            'tone' => 'primary',
        ];

        $editUrl = self::editUrlForRole($role, $userId);
        if ($editUrl !== null && !$isDeleted) {
            $actions[] = [
                'key' => 'edit',
                'label' => 'Edit',
                'url' => $editUrl,
                'method' => 'GET',
                'tone' => 'neutral',
            ];
        }

        if (!$isDeleted && !$isSelf && !Status::isInvitationPendingUserStatus($status)) {
            $actions[] = [
                'key' => 'reset_password',
                'label' => 'Reset password',
                'url' => '/admin/users/' . $userId . '/reset-password',
                'method' => 'GET',
                'tone' => 'neutral',
            ];
        }

        if ($status === Status::USER_ACTIVE && !$isSelf) {
            $actions[] = [
                'key' => 'suspend',
                'label' => 'Suspend',
                'url' => '/admin/users/' . $userId . '/suspend',
                'method' => 'POST',
                'tone' => 'warning',
            ];
            $actions[] = [
                'key' => 'deactivate',
                'label' => 'Deactivate',
                'url' => '/admin/users/' . $userId . '/deactivate',
                'method' => 'POST',
                'tone' => 'danger',
            ];
        } elseif ($status === Status::USER_SUSPENDED && !$isSelf) {
            $actions[] = [
                'key' => 'reactivate',
                'label' => 'Reactivate',
                'url' => '/admin/users/' . $userId . '/reactivate',
                'method' => 'POST',
                'tone' => 'success',
            ];
            $actions[] = [
                'key' => 'deactivate',
                'label' => 'Deactivate',
                'url' => '/admin/users/' . $userId . '/deactivate',
                'method' => 'POST',
                'tone' => 'danger',
            ];
        } elseif ($status === Status::USER_INACTIVE && !$isSelf) {
            $actions[] = [
                'key' => 'reactivate',
                'label' => 'Reactivate',
                'url' => '/admin/users/' . $userId . '/reactivate',
                'method' => 'POST',
                'tone' => 'success',
            ];
        }

        if (!$isDeleted && !$isSelf) {
            $actions[] = [
                'key' => 'delete',
                'label' => 'Delete permanently',
                'url' => '/admin/users/' . $userId . '/delete',
                'method' => 'POST',
                'tone' => 'destroy',
            ];
        }

        return $actions;
    }

    public static function changeAccountStatus(
        int $targetUserId,
        int $actorUserId,
        string $targetStatus,
        string $csrfToken
    ): array {
        if (!Csrf::verify($csrfToken)) {
            return [
                'success' => false,
                'message' => 'Unable to verify the request. Please refresh the page and try again.',
                'type' => 'danger',
            ];
        }

        if (!self::actorIsAdmin($actorUserId)) {
            return [
                'success' => false,
                'message' => 'Only administrators can change account status.',
                'type' => 'danger',
            ];
        }

        $targetStatus = Status::normalizeKey(Status::DOMAIN_USER, $targetStatus);
        if (!Status::canAssignUserStatus($targetStatus)) {
            return [
                'success' => false,
                'message' => 'The requested account status is invalid.',
                'type' => 'danger',
            ];
        }

        $targetUser = User::findManagementDetailById($targetUserId);
        if ($targetUser === null) {
            return [
                'success' => false,
                'message' => 'The selected user account could not be found.',
                'type' => 'warning',
            ];
        }

        if ($targetUserId === $actorUserId) {
            return [
                'success' => false,
                'message' => 'Administrators cannot change the status of their own account from this screen.',
                'type' => 'warning',
            ];
        }

        $currentStatus = Status::normalizeKey(Status::DOMAIN_USER, (string) ($targetUser['status'] ?? ''));
        if ($currentStatus === Status::USER_DELETED) {
            return [
                'success' => false,
                'message' => 'A permanently deleted account cannot be reactivated from user management.',
                'type' => 'warning',
            ];
        }

        if (Status::isInvitationPendingUserStatus($currentStatus)) {
            return [
                'success' => false,
                'message' => 'This account cannot be activated or deactivated until password setup is complete.',
                'type' => 'warning',
            ];
        }

        if ($currentStatus === $targetStatus) {
            return [
                'success' => true,
                'message' => 'This account is already ' . Status::label($targetStatus, Status::DOMAIN_USER) . '.',
                'type' => 'info',
            ];
        }

        $role = (string) ($targetUser['role_name'] ?? '');
        if (
            $role === 'admin'
            && $currentStatus === Status::USER_ACTIVE
            && $targetStatus !== Status::USER_ACTIVE
            && User::countActiveAdministrators() <= 1
        ) {
            return [
                'success' => false,
                'message' => 'The final active administrator account cannot be suspended or deactivated.',
                'type' => 'warning',
            ];
        }

        try {
            if (!User::updateStatus($targetUserId, $targetStatus)) {
                throw new \RuntimeException('Account status update did not persist.');
            }

            if ($targetStatus !== Status::USER_ACTIVE) {
                SessionService::revokeAll($targetUserId);
            }

            AuditLogService::record(
                'user_status_updated',
                'Administrator changed account status to ' . $targetStatus . '.',
                AuditLogService::ENTITY_USER,
                $targetUserId,
                'success',
                [
                    'subject_name' => (string) ($targetUser['full_name'] ?? ''),
                    'subject_role' => $role,
                ]
            );

            return [
                'success' => true,
                'message' => 'Account status updated to ' . Status::label($targetStatus, Status::DOMAIN_USER) . '.',
                'type' => 'success',
            ];
        } catch (\Throwable $exception) {
            error_log('[AdminUserService::changeAccountStatus] ' . $exception->getMessage());

            return [
                'success' => false,
                'message' => 'Account status could not be updated right now.',
                'type' => 'danger',
            ];
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success:bool,message?:string,errors:list<string>,fieldErrors:array<string,string>,user?:array<string,mixed>}
     */
    public static function resetUserPassword(int $targetUserId, int $actorUserId, array $input): array
    {
        $user = User::findManagementDetailById($targetUserId);
        if ($user === null) {
            return [
                'success' => false,
                'errors' => ['The selected user account could not be found.'],
                'fieldErrors' => [],
            ];
        }

        $fieldErrors = [];
        $errors = [];

        if (!Csrf::verify((string) ($input['_token'] ?? ''))) {
            $fieldErrors['_token'] = 'Unable to verify the request. Please refresh the page and try again.';
        }

        if (!self::actorIsAdmin($actorUserId)) {
            $errors[] = 'Only administrators can reset another user password.';
        }

        if ($targetUserId === $actorUserId) {
            $errors[] = 'Use Security settings to change your own password.';
        }

        $status = Status::normalizeKey(Status::DOMAIN_USER, (string) ($user['status'] ?? ''));
        if ($status === Status::USER_DELETED) {
            $errors[] = 'A permanently deleted account cannot receive a password reset.';
        }

        if (Status::isInvitationPendingUserStatus($status)) {
            $errors[] = 'A password cannot be reset until invitation password setup is complete.';
        }

        $password = (string) ($input['password'] ?? '');
        $confirmPassword = (string) ($input['confirm_password'] ?? '');
        $forceReset = isset($input['force_password_reset']);

        if ($password === '') {
            $fieldErrors['password'] = 'New password is required.';
        } elseif (!\App\Helpers\Helper::isStrongPassword($password)) {
            $fieldErrors['password'] = 'Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.';
        }

        if ($confirmPassword === '') {
            $fieldErrors['confirm_password'] = 'Please confirm the new password.';
        } elseif ($confirmPassword !== $password) {
            $fieldErrors['confirm_password'] = 'Passwords do not match.';
        }

        if ($fieldErrors !== [] || $errors !== []) {
            if (isset($fieldErrors['_token'])) {
                array_unshift($errors, $fieldErrors['_token']);
            }
            if ($fieldErrors !== []) {
                $errors[] = 'Please correct the highlighted password reset fields.';
            }

            return [
                'success' => false,
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
                'user' => $user,
            ];
        }

        try {
            if (!User::updatePasswordHash($targetUserId, password_hash($password, PASSWORD_DEFAULT))) {
                throw new \RuntimeException('Password reset did not persist.');
            }

            User::setForcePasswordReset($targetUserId, $forceReset);
            SessionService::revokeAll($targetUserId);
            AuditLogService::record(
                'user_password_reset',
                'Administrator reset a user account password.',
                AuditLogService::ENTITY_USER,
                $targetUserId,
                'success',
                [
                    'subject_name' => (string) ($user['full_name'] ?? ''),
                    'subject_role' => (string) ($user['role_name'] ?? ''),
                ]
            );

            return [
                'success' => true,
                'message' => 'Password reset successfully. The user must sign in with the new password.',
                'errors' => [],
                'fieldErrors' => [],
                'user' => $user,
            ];
        } catch (\Throwable $exception) {
            error_log('[AdminUserService::resetUserPassword] ' . $exception->getMessage());

            return [
                'success' => false,
                'errors' => ['Password reset is temporarily unavailable. Please try again later.'],
                'fieldErrors' => [],
                'user' => $user,
            ];
        }
    }

    public static function forcePasswordReset(int $targetUserId, int $actorUserId, string $csrfToken): array
    {
        if (!Csrf::verify($csrfToken)) {
            return [
                'success' => false,
                'message' => 'Unable to verify the request. Please refresh the page and try again.',
                'type' => 'danger',
            ];
        }

        if (!self::actorIsAdmin($actorUserId)) {
            return [
                'success' => false,
                'message' => 'Only administrators can require a password reset.',
                'type' => 'danger',
            ];
        }

        $user = User::findManagementDetailById($targetUserId);
        if ($user === null) {
            return [
                'success' => false,
                'message' => 'The selected user account could not be found.',
                'type' => 'warning',
            ];
        }

        if ($targetUserId === $actorUserId) {
            return [
                'success' => false,
                'message' => 'Use Security settings to change your own password.',
                'type' => 'warning',
            ];
        }

        $status = Status::normalizeKey(Status::DOMAIN_USER, (string) ($user['status'] ?? ''));
        if ($status === Status::USER_DELETED) {
            return [
                'success' => false,
                'message' => 'A permanently deleted account cannot be required to reset a password.',
                'type' => 'warning',
            ];
        }

        if (Status::isInvitationPendingUserStatus($status)) {
            return [
                'success' => false,
                'message' => 'A password change cannot be required until invitation password setup is complete.',
                'type' => 'warning',
            ];
        }

        try {
            if (!User::setForcePasswordReset($targetUserId, true)) {
                throw new \RuntimeException('Force password reset flag did not persist.');
            }

            AuditLogService::record(
                'user_force_password_reset',
                'Administrator required a password change on next login.',
                AuditLogService::ENTITY_USER,
                $targetUserId,
                'success',
                [
                    'subject_name' => (string) ($user['full_name'] ?? ''),
                    'subject_role' => (string) ($user['role_name'] ?? ''),
                ]
            );

            return [
                'success' => true,
                'message' => 'This user will be required to choose a new password at next login.',
                'type' => 'success',
            ];
        } catch (\Throwable $exception) {
            error_log('[AdminUserService::forcePasswordReset] ' . $exception->getMessage());

            return [
                'success' => false,
                'message' => 'The force password reset flag could not be saved right now.',
                'type' => 'danger',
            ];
        }
    }

    public static function deleteUserAccount(
        int $targetUserId,
        int $actorUserId,
        string $csrfToken,
        string $confirmationPhrase = '',
        string $actorPassword = ''
    ): array {
        return UserDeletionService::permanentlyDelete(
            $targetUserId,
            $actorUserId,
            $csrfToken,
            $confirmationPhrase,
            $actorPassword
        );
    }

    /**
     * @param list<mixed> $targetUserIds
     * @return array{success:bool,message:string,type:string,deleted?:int,skipped?:int}
     */
    public static function deleteSelectedUserAccounts(
        array $targetUserIds,
        int $actorUserId,
        string $csrfToken,
        string $confirmationPhrase = '',
        string $actorPassword = ''
    ): array {
        return UserDeletionService::permanentlyDeleteMany(
            $targetUserIds,
            $actorUserId,
            $csrfToken,
            $confirmationPhrase,
            $actorPassword
        );
    }

    public static function getRoleOptions(): array
    {
        return ['admin', 'doctor', 'patient'];
    }

    public static function getStatusOptions(): array
    {
        return Status::assignableUserKeys();
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    public static function getSortOptions(): array
    {
        return [
            ['value' => 'newest', 'label' => 'Newest first'],
            ['value' => 'oldest', 'label' => 'Oldest first'],
            ['value' => 'name', 'label' => 'Name'],
            ['value' => 'email', 'label' => 'Email'],
            ['value' => 'last_login', 'label' => 'Last login'],
        ];
    }

    private static function editUrlForRole(string $role, int $userId): ?string
    {
        return match ($role) {
            'doctor' => '/admin/doctors/' . $userId . '/edit',
            'patient' => '/admin/patients/' . $userId . '/edit',
            default => null,
        };
    }

    private static function normalizeFilters(array $query): array
    {
        $search = trim((string) ($query['search'] ?? ''));
        $role = strtolower(trim((string) ($query['role'] ?? '')));
        $status = Status::normalizeKey(Status::DOMAIN_USER, (string) ($query['status'] ?? ''));
        $sort = strtolower(trim((string) ($query['sort'] ?? 'newest')));
        $allowedSort = array_column(self::getSortOptions(), 'value');

        if (!in_array($role, self::getRoleOptions(), true)) {
            $role = '';
        }

        if (!in_array($status, self::getStatusOptions(), true)) {
            $status = '';
        }

        if (!in_array($sort, $allowedSort, true)) {
            $sort = 'newest';
        }

        return [
            'search' => $search,
            'role' => $role,
            'status' => $status,
            'sort' => $sort,
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
                'description' => 'Role: ' . ucfirst((string) $user['role_name']) . ' | Status: ' . Status::label((string) $user['status'], Status::DOMAIN_USER),
                'meta' => 'Joined ' . date('d M Y', strtotime((string) $user['created_at'])),
            ];
        }

        return $activity;
    }

    private static function actorIsAdmin(int $actorUserId): bool
    {
        if ($actorUserId <= 0) {
            return false;
        }

        $actor = User::findById($actorUserId);

        return $actor !== null && $actor->getRole() === 'admin';
    }

}
