<?php

namespace App\Services;

use App\Helpers\ListFilter;
use App\Models\AuditLog;
use App\Models\User;

class AuditLogService
{
    public const PER_PAGE = 25;
    public const ENTITY_CONSULTATION_REQUEST = 'consultation_request';
    public const ENTITY_CONSULTATION_RECORD = 'consultation_record';
    public const ENTITY_PRESCRIPTION = 'prescription';
    public const ENTITY_AVAILABILITY = 'doctor_availability';
    public const ENTITY_USER = 'user';
    public const ENTITY_AUTH = 'auth';

    /**
     * @return array<string, array{label:string,category:string,severity:string}>
     */
    public static function catalog(): array
    {
        return [
            'login_success' => ['label' => 'Login Success', 'category' => 'authentication', 'severity' => 'info'],
            'login_failed' => ['label' => 'Login Failed', 'category' => 'authentication', 'severity' => 'warning'],
            'logout' => ['label' => 'Logout', 'category' => 'authentication', 'severity' => 'info'],
            'consultation_request_created' => ['label' => 'Consultation Request Created', 'category' => 'consultation', 'severity' => 'info'],
            'consultation_approved' => ['label' => 'Consultation Approved', 'category' => 'consultation', 'severity' => 'success'],
            'consultation_rejected' => ['label' => 'Consultation Rejected', 'category' => 'consultation', 'severity' => 'warning'],
            'consultation_cancelled' => ['label' => 'Consultation Cancelled', 'category' => 'consultation', 'severity' => 'warning'],
            'consultation_completed' => ['label' => 'Consultation Completed', 'category' => 'consultation', 'severity' => 'success'],
            'clinical_record_finalized' => ['label' => 'Clinical Record Finalized', 'category' => 'clinical', 'severity' => 'success'],
            'prescription_created' => ['label' => 'Prescription Created', 'category' => 'prescription', 'severity' => 'success'],
            'availability_created' => ['label' => 'Availability Created', 'category' => 'availability', 'severity' => 'info'],
            'availability_updated' => ['label' => 'Availability Updated', 'category' => 'availability', 'severity' => 'info'],
            'availability_deleted' => ['label' => 'Availability Deleted', 'category' => 'availability', 'severity' => 'warning'],
            'doctor_account_created' => ['label' => 'Doctor Account Created', 'category' => 'administration', 'severity' => 'info'],
            'doctor_invitation_resent' => ['label' => 'Doctor Invitation Resent', 'category' => 'administration', 'severity' => 'info'],
            'doctor_password_setup_completed' => ['label' => 'Doctor Password Setup Completed', 'category' => 'authentication', 'severity' => 'info'],
            'doctor_account_updated' => ['label' => 'Doctor Account Updated', 'category' => 'administration', 'severity' => 'info'],
            'doctor_status_updated' => ['label' => 'Doctor Status Updated', 'category' => 'administration', 'severity' => 'warning'],
            'doctor_password_reset' => ['label' => 'Doctor Password Reset', 'category' => 'administration', 'severity' => 'warning'],
            'patient_account_updated' => ['label' => 'Patient Account Updated', 'category' => 'administration', 'severity' => 'info'],
            'patient_status_updated' => ['label' => 'Patient Status Updated', 'category' => 'administration', 'severity' => 'warning'],
            'user_deleted' => ['label' => 'User Account Deleted', 'category' => 'administration', 'severity' => 'warning'],
            'user_status_updated' => ['label' => 'User Status Updated', 'category' => 'administration', 'severity' => 'warning'],
            'user_password_reset' => ['label' => 'User Password Reset', 'category' => 'administration', 'severity' => 'warning'],
            'user_force_password_reset' => ['label' => 'Force Password Reset', 'category' => 'administration', 'severity' => 'warning'],
            'password_changed' => ['label' => 'Password Changed', 'category' => 'authentication', 'severity' => 'info'],
            'sessions_revoked' => ['label' => 'Other Sessions Signed Out', 'category' => 'authentication', 'severity' => 'info'],
        ];
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function record(string $eventType, string $description, ?string $entityType = null, ?int $entityId = null, string $outcome = 'success', array $context = []): void
    {
        self::safeRun(function () use ($eventType, $description, $entityType, $entityId, $outcome, $context): void {
            $actor = !empty($context['system_actor']) ? null : AuthService::getUser();
            $meta = self::catalog()[$eventType] ?? ['label' => 'System Event', 'category' => 'system', 'severity' => 'info'];

            AuditLog::create([
                'actor_user_id' => $actor?->id,
                'actor_name' => self::actorName($actor, (string) ($context['actor_name'] ?? 'System')),
                'actor_role' => $actor?->getRole() ?? (string) ($context['actor_role'] ?? ''),
                'action' => $eventType,
                'event_type' => $eventType,
                'event_label' => $meta['label'],
                'event_category' => $meta['category'],
                'severity' => $meta['severity'],
                'subject_name' => (string) ($context['subject_name'] ?? ''),
                'subject_role' => (string) ($context['subject_role'] ?? ''),
                'entity_type' => $entityType ?? '',
                'entity_id' => $entityId,
                'description' => $description,
                'outcome' => in_array($outcome, ['success', 'failed'], true) ? $outcome : 'success',
            ]);
        });
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function getPageData(array $query): array
    {
        $filters = self::normalizeFilters($query);
        $totalItems = AuditLog::countForAdmin($filters);
        $pagination = ListFilter::paginate(max(1, (int) ($query['page'] ?? 1)), $totalItems, (int) $filters['per_page']);
        $offset = ($pagination['current_page'] - 1) * (int) $filters['per_page'];

        return [
            'filters' => $filters,
            'logs' => AuditLog::findForAdmin($filters, (int) $filters['per_page'], $offset),
            'pagination' => $pagination,
            'filterActive' => ListFilter::isActive($filters, ['sort' => 'newest', 'per_page' => self::PER_PAGE]),
            'actionOptions' => self::actionOptions(),
            'roleOptions' => self::roleOptions(),
            'dateOptions' => self::dateOptions(),
            'sortOptions' => self::sortOptions(),
            'userOptions' => User::findAdminAuditUserOptions(),
        ];
    }

    public static function findDetailForAdmin(int $auditId): ?array
    {
        return AuditLog::findByIdForAdmin($auditId);
    }

    /**
     * Limited recent events for the administrator dashboard. Not a substitute
     * for the full audit log workspace.
     *
     * @return list<array{title:string,description:string,meta:string,url:string,icon:string}>
     */
    public static function getDashboardRecent(int $limit = 5): array
    {
        $limit = max(1, min(8, $limit));

        try {
            $rows = AuditLog::findForAdmin([], $limit, 0);
        } catch (\Throwable $exception) {
            error_log('[AuditLogService::getDashboardRecent] ' . $exception->getMessage());
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row['event_label'] ?? ''));
            if ($label === '') {
                $label = trim((string) ($row['action'] ?? 'Activity'));
            }
            $actor = trim((string) ($row['actor_name'] ?? 'System'));
            $subject = trim((string) ($row['subject_name'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            if ($description === '') {
                $description = $subject !== '' ? $actor . ' · ' . $subject : $actor;
            }

            $items[] = [
                'title' => $label,
                'description' => $description,
                'meta' => NotificationService::relativeTime((string) ($row['created_at'] ?? '')),
                'url' => '/admin/audit-logs/' . (int) ($row['id'] ?? 0),
                'icon' => 'bi-journal-text',
            ];
        }

        return $items;
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function normalizeFilters(array $query): array
    {
        $search = ListFilter::normalizeSearch((string) ($query['search'] ?? ''));
        $action = ListFilter::allowedValue(trim((string) ($query['action'] ?? '')), array_keys(self::catalog()), '');
        $role = ListFilter::allowedValue(trim((string) ($query['role'] ?? '')), ['admin', 'doctor', 'patient'], '');
        $sort = ListFilter::allowedValue(trim((string) ($query['sort'] ?? 'newest')), ['newest', 'oldest'], 'newest');
        $userId = max(0, (int) ($query['user_id'] ?? 0));
        $perPage = ListFilter::allowedPerPage($query['per_page'] ?? self::PER_PAGE, self::PER_PAGE);
        $range = ListFilter::resolveDateRange((string) ($query['date'] ?? ''), (string) ($query['date_from'] ?? ''), (string) ($query['date_to'] ?? ''));

        return [
            'search' => $search,
            'action' => $action,
            'role' => $role,
            'user_id' => $userId,
            'date' => $range['preset'],
            'date_from' => $range['preset'] === 'custom' ? $range['from'] : '',
            'date_to' => $range['preset'] === 'custom' ? $range['to'] : '',
            'date_range' => ['from' => $range['from'], 'to' => $range['to']],
            'sort' => $sort,
            'per_page' => $perPage,
        ];
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    public static function actionOptions(): array
    {
        $options = [];
        foreach (self::catalog() as $value => $meta) {
            $options[] = ['value' => $value, 'label' => $meta['label']];
        }
        return $options;
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    public static function roleOptions(): array
    {
        return [
            ['value' => 'admin', 'label' => 'Administrator'],
            ['value' => 'doctor', 'label' => 'Doctor'],
            ['value' => 'patient', 'label' => 'Patient'],
        ];
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    public static function dateOptions(): array
    {
        return [
            ['value' => 'today', 'label' => 'Today'],
            ['value' => 'this_week', 'label' => 'This week'],
            ['value' => 'this_month', 'label' => 'This month'],
            ['value' => 'custom', 'label' => 'Custom range'],
        ];
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    public static function sortOptions(): array
    {
        return [
            ['value' => 'newest', 'label' => 'Newest first'],
            ['value' => 'oldest', 'label' => 'Oldest first'],
        ];
    }

    private static function actorName(?\App\Models\User $actor, string $fallback): string
    {
        $name = trim((string) ($actor?->full_name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $fallback = trim($fallback);
        return $fallback !== '' ? $fallback : 'System';
    }

    /**
     * @param callable():void $callback
     */
    private static function safeRun(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            error_log('[AuditLogService] ' . $exception->getMessage());
        }
    }
}
