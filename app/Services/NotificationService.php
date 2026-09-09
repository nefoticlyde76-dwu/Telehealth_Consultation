<?php

namespace App\Services;

use App\Helpers\Helper;
use App\Helpers\ListFilter;
use App\Models\ConsultationRequest;
use App\Models\Notification;
use App\Models\User;

/**
 * Informational notification layer. Application state remains in
 * consultation_requests, consultation_records, and prescriptions.
 */
class NotificationService
{
    public const PER_PAGE = 10;
    public const HEADER_LIMIT = 8;
    public const TOAST_LIMIT = 3;
    public const UPCOMING_WINDOW_MINUTES = 60;

    public const TYPE_REQUEST_CREATED = 'consultation_request_created';
    public const TYPE_APPROVED = 'consultation_approved';
    public const TYPE_ASSIGNED = 'consultation_assigned';
    public const TYPE_CANCELLED = 'consultation_cancelled';
    public const TYPE_RESCHEDULED = 'consultation_rescheduled';
    public const TYPE_REJECTED = 'consultation_rejected';
    public const TYPE_COMPLETED = 'consultation_completed';
    public const TYPE_PRESCRIPTION = 'prescription_created';
    public const TYPE_UPCOMING = 'upcoming_consultation';

    public const ENTITY_CONSULTATION_REQUEST = Notification::ENTITY_CONSULTATION_REQUEST;

    /**
     * @return array<string, array{
     *   title:string,
     *   icon:string,
     *   audience:list<string>,
     *   action:string,
     *   tone:string
     * }>
     */
    public static function catalog(): array
    {
        return [
            self::TYPE_REQUEST_CREATED => [
                'title' => 'New Consultation Request',
                'icon' => 'bi-clipboard-plus',
                'audience' => ['admin'],
                'action' => 'Review',
                'tone' => 'info',
            ],
            self::TYPE_APPROVED => [
                'title' => 'Consultation Approved',
                'icon' => 'bi-check-circle',
                'audience' => ['patient'],
                'action' => 'View',
                'tone' => 'ok',
            ],
            self::TYPE_ASSIGNED => [
                'title' => 'New Consultation',
                'icon' => 'bi-calendar2-check',
                'audience' => ['doctor'],
                'action' => 'Open',
                'tone' => 'info',
            ],
            self::TYPE_CANCELLED => [
                'title' => 'Consultation Cancelled',
                'icon' => 'bi-slash-circle',
                'audience' => ['doctor'],
                'action' => 'Open',
                'tone' => 'warn',
            ],
            self::TYPE_RESCHEDULED => [
                'title' => 'Consultation Rescheduled',
                'icon' => 'bi-calendar2-week',
                'audience' => ['doctor'],
                'action' => 'Open',
                'tone' => 'info',
            ],
            self::TYPE_REJECTED => [
                'title' => 'Consultation Request Rejected',
                'icon' => 'bi-x-circle',
                'audience' => ['patient'],
                'action' => 'View',
                'tone' => 'danger',
            ],
            self::TYPE_COMPLETED => [
                'title' => 'Consultation Completed',
                'icon' => 'bi-clipboard2-check',
                'audience' => ['patient'],
                'action' => 'View',
                'tone' => 'ok',
            ],
            self::TYPE_PRESCRIPTION => [
                'title' => 'Prescription Available',
                'icon' => 'bi-capsule',
                'audience' => ['patient'],
                'action' => 'Open',
                'tone' => 'ok',
            ],
            self::TYPE_UPCOMING => [
                'title' => 'Upcoming Consultation',
                'icon' => 'bi-clock',
                'audience' => ['doctor'],
                'action' => 'Open',
                'tone' => 'warn',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function typeKeys(): array
    {
        return array_keys(self::catalog());
    }

    /**
     * @return list<array{value:string,label:string}>
     */
    public static function typeFilterOptions(string $role): array
    {
        $options = [];
        foreach (self::catalog() as $type => $meta) {
            if (!in_array($role, $meta['audience'], true)) {
                continue;
            }
            $options[] = [
                'value' => $type,
                'label' => $meta['title'],
            ];
        }

        return $options;
    }

    public static function typeTitle(string $type): string
    {
        return self::catalog()[$type]['title'] ?? 'Notification';
    }

    public static function typeIcon(string $type): string
    {
        return self::catalog()[$type]['icon'] ?? 'bi-bell';
    }

    public static function typeAction(string $type): string
    {
        return self::catalog()[$type]['action'] ?? 'View';
    }

    public static function typeTone(string $type): string
    {
        $tone = (string) (self::catalog()[$type]['tone'] ?? 'info');

        return in_array($tone, ['ok', 'info', 'warn', 'danger'], true) ? $tone : 'info';
    }

    public static function dayGroup(?string $timestamp): string
    {
        if ($timestamp === null || trim($timestamp) === '') {
            return 'Earlier';
        }

        try {
            $created = new \DateTimeImmutable($timestamp);
        } catch (\Throwable) {
            return 'Earlier';
        }

        $now = new \DateTimeImmutable('now');
        if ($created->format('Y-m-d') === $now->format('Y-m-d')) {
            return 'Today';
        }

        $yesterday = $now->sub(new \DateInterval('P1D'))->format('Y-m-d');
        if ($created->format('Y-m-d') === $yesterday) {
            return 'Yesterday';
        }

        return 'Earlier';
    }

    public static function notifyConsultationRequestCreated(int $requestId): void
    {
        self::safeRun(function () use ($requestId): void {
            $context = self::requestContext($requestId);
            if ($context === null) {
                return;
            }

            $patientName = self::personName((string) ($context['patient_name'] ?? ''), 'a patient');
            $title = self::typeTitle(self::TYPE_REQUEST_CREATED);
            $message = 'A new consultation request from ' . $patientName . ' requires your review.';

            foreach (self::activeAdminIds() as $adminId) {
                self::createForUser(
                    $adminId,
                    self::TYPE_REQUEST_CREATED,
                    $title,
                    $message,
                    $requestId
                );
            }
        });
    }

    public static function notifyConsultationApproved(int $requestId): void
    {
        self::safeRun(function () use ($requestId): void {
            $context = self::requestContext($requestId);
            if ($context === null) {
                return;
            }

            $patientId = (int) ($context['patient_id'] ?? 0);
            $doctorId = (int) ($context['doctor_id'] ?? 0);
            $when = self::appointmentPhrase(
                (string) ($context['consultation_date'] ?? ''),
                (string) ($context['start_time'] ?? '')
            );
            $doctorName = self::clinicianName((string) ($context['doctor_name'] ?? ''));
            $patientName = self::personName((string) ($context['patient_name'] ?? ''), 'a patient');

            if ($patientId > 0) {
                $patientMessage = $when !== ''
                    ? 'Your consultation with ' . $doctorName . ' has been approved for ' . $when . '.'
                    : 'Your consultation with ' . $doctorName . ' has been approved.';
                self::createForUser(
                    $patientId,
                    self::TYPE_APPROVED,
                    self::typeTitle(self::TYPE_APPROVED),
                    $patientMessage,
                    $requestId
                );
            }

            if ($doctorId > 0) {
                $doctorMessage = $when !== ''
                    ? 'A consultation with ' . $patientName . ' has been approved for ' . $when . '.'
                    : 'A consultation with ' . $patientName . ' has been approved.';
                self::createForUser(
                    $doctorId,
                    self::TYPE_ASSIGNED,
                    self::typeTitle(self::TYPE_ASSIGNED),
                    $doctorMessage,
                    $requestId
                );
            }
        });
    }

    public static function notifyConsultationRejected(int $requestId): void
    {
        self::safeRun(function () use ($requestId): void {
            $context = self::requestContext($requestId);
            if ($context === null) {
                return;
            }

            $patientId = (int) ($context['patient_id'] ?? 0);
            if ($patientId <= 0) {
                return;
            }

            self::createForUser(
                $patientId,
                self::TYPE_REJECTED,
                self::typeTitle(self::TYPE_REJECTED),
                'Your consultation request has been rejected.',
                $requestId
            );
        });
    }

    public static function notifyPatientCancelledConsultation(int $requestId): void
    {
        self::safeRun(function () use ($requestId): void {
            $context = self::requestContext($requestId);
            if ($context === null) {
                return;
            }

            $doctorId = (int) ($context['doctor_id'] ?? 0);
            if ($doctorId <= 0) {
                return;
            }

            $patientName = self::personName((string) ($context['patient_name'] ?? ''), 'A patient');
            $when = self::appointmentPhrase(
                (string) ($context['consultation_date'] ?? ''),
                (string) ($context['start_time'] ?? '')
            );
            $message = $when !== ''
                ? $patientName . ' cancelled their consultation scheduled for ' . $when . '.'
                : $patientName . ' cancelled their consultation booking.';

            self::createForUser(
                $doctorId,
                self::TYPE_CANCELLED,
                self::typeTitle(self::TYPE_CANCELLED),
                $message,
                $requestId
            );
        });
    }

    public static function notifyPatientRescheduledConsultation(
        int $requestId,
        string $previousDate = '',
        string $previousTime = ''
    ): void {
        self::safeRun(function () use ($requestId, $previousDate, $previousTime): void {
            $context = self::requestContext($requestId);
            if ($context === null) {
                return;
            }

            $doctorId = (int) ($context['doctor_id'] ?? 0);
            if ($doctorId <= 0) {
                return;
            }

            $patientName = self::personName((string) ($context['patient_name'] ?? ''), 'A patient');
            $newWhen = self::appointmentPhrase(
                (string) ($context['consultation_date'] ?? ''),
                (string) ($context['start_time'] ?? '')
            );
            $oldWhen = self::appointmentPhrase($previousDate, $previousTime);

            if ($oldWhen !== '' && $newWhen !== '') {
                $message = $patientName . ' rescheduled their consultation from ' . $oldWhen . ' to ' . $newWhen . '.';
            } elseif ($newWhen !== '') {
                $message = $patientName . ' rescheduled their consultation to ' . $newWhen . '.';
            } else {
                $message = $patientName . ' rescheduled their consultation.';
            }

            self::createForUser(
                $doctorId,
                self::TYPE_RESCHEDULED,
                self::typeTitle(self::TYPE_RESCHEDULED),
                $message,
                $requestId,
                true
            );
        });
    }

    public static function notifyConsultationCompleted(int $requestId): void
    {
        self::safeRun(function () use ($requestId): void {
            $context = self::requestContext($requestId);
            if ($context === null) {
                return;
            }

            $patientId = (int) ($context['patient_id'] ?? 0);
            if ($patientId <= 0) {
                return;
            }

            $doctorName = self::clinicianName((string) ($context['doctor_name'] ?? ''));
            self::createForUser(
                $patientId,
                self::TYPE_COMPLETED,
                self::typeTitle(self::TYPE_COMPLETED),
                'Your consultation with ' . $doctorName . ' has been completed. Your consultation record is now available.',
                $requestId
            );
        });
    }

    public static function notifyPrescriptionCreated(int $requestId): void
    {
        self::safeRun(function () use ($requestId): void {
            $context = self::requestContext($requestId);
            if ($context === null) {
                return;
            }

            $patientId = (int) ($context['patient_id'] ?? 0);
            if ($patientId <= 0) {
                return;
            }

            $doctorName = self::clinicianName((string) ($context['doctor_name'] ?? ''));
            self::createForUser(
                $patientId,
                self::TYPE_PRESCRIPTION,
                self::typeTitle(self::TYPE_PRESCRIPTION),
                $doctorName . ' has issued a prescription for your completed consultation.',
                $requestId
            );
        });
    }

    /**
     * Opportunistic upcoming reminder for doctors. There is no background
     * scheduler in this PHP/XAMPP application, so this runs when a doctor
     * loads an authenticated page. The unique notification key prevents duplicates.
     */
    public static function maybeNotifyUpcomingForDoctor(int $doctorId): void
    {
        if ($doctorId <= 0) {
            return;
        }

        self::safeRun(function () use ($doctorId): void {
            $upcoming = ConsultationRequest::findApprovedStartingSoonForDoctor(
                $doctorId,
                self::UPCOMING_WINDOW_MINUTES
            );

            foreach ($upcoming as $row) {
                $requestId = (int) ($row['id'] ?? 0);
                if ($requestId <= 0) {
                    continue;
                }

                $patientName = self::personName((string) ($row['patient_name'] ?? ''), 'a patient');
                self::createForUser(
                    $doctorId,
                    self::TYPE_UPCOMING,
                    self::typeTitle(self::TYPE_UPCOMING),
                    'Your consultation with ' . $patientName . ' begins soon.',
                    $requestId
                );
            }
        });
    }

    /**
     * @return array{unread_count:int,total_count:int,recent:list<array<string,mixed>>}
     */
    public static function getHeaderData(int $userId, string $role = ''): array
    {
        if ($userId <= 0) {
            return [
                'unread_count' => 0,
                'total_count' => 0,
                'recent' => [],
            ];
        }

        try {
            if ($role === 'doctor') {
                self::maybeNotifyUpcomingForDoctor($userId);
            }

            $recent = Notification::findRecentForUser($userId, self::HEADER_LIMIT);

            return [
                'unread_count' => Notification::countUnreadForUser($userId),
                'total_count' => Notification::countForUser($userId),
                'recent' => self::presentMany($recent, $role),
            ];
        } catch (\Throwable $exception) {
            error_log('[NotificationService::getHeaderData] ' . $exception->getMessage());

            return [
                'unread_count' => 0,
                'total_count' => 0,
                'recent' => [],
            ];
        }
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public static function getPageData(int $userId, string $role, array $query): array
    {
        $filters = self::normalizeFilters($query, $role);
        $totalItems = Notification::countForUser($userId, $filters);
        $pagination = ListFilter::paginate(
            max(1, (int) ($query['page'] ?? 1)),
            $totalItems,
            self::PER_PAGE
        );
        $offset = ($pagination['current_page'] - 1) * self::PER_PAGE;
        $notifications = Notification::findForUser($userId, $filters, self::PER_PAGE, $offset);
        $presented = self::presentMany($notifications, $role);

        return [
            'filters' => $filters,
            'notifications' => $presented,
            'unreadItems' => array_values(array_filter($presented, static fn (array $item): bool => !empty($item['unread']))),
            'readItems' => array_values(array_filter($presented, static fn (array $item): bool => empty($item['unread']))),
            'pagination' => $pagination,
            'unreadCount' => Notification::countUnreadForUser($userId),
            'totalCount' => Notification::countForUser($userId),
            'filterActive' => ListFilter::isActive($filters),
            'typeOptions' => self::typeFilterOptions($role),
            'readStateOptions' => [
                ['value' => 'unread', 'label' => 'Unread'],
                ['value' => 'read', 'label' => 'Read'],
            ],
        ];
    }

    /**
     * Mark the notification read (if unread) and return the destination path.
     */
    public static function openForUser(int $userId, string $role, int $notificationId): ?array
    {
        $row = Notification::findByIdForUser($notificationId, $userId);
        if ($row === null) {
            return null;
        }

        if ((int) ($row['is_read'] ?? 0) === 0) {
            Notification::markReadForUser($notificationId, $userId);
        }

        return [
            'notification' => $row,
            'target' => self::resolveTarget($row, $role),
        ];
    }

    public static function markAllReadForUser(int $userId): int
    {
        return Notification::markAllReadForUser($userId);
    }

    public static function markReadForUser(int $userId, int $notificationId): bool
    {
        return Notification::markReadForUser($notificationId, $userId);
    }

    public static function markUnreadForUser(int $userId, int $notificationId): bool
    {
        return Notification::markUnreadForUser($notificationId, $userId);
    }

    public static function deleteForUser(int $userId, int $notificationId): bool
    {
        return Notification::deleteForUser($notificationId, $userId);
    }

    /**
     * @param list<int> $notificationIds
     */
    public static function deleteSelectedForUser(int $userId, array $notificationIds): int
    {
        return Notification::deleteManyForUser($userId, $notificationIds);
    }

    public static function clearAllForUser(int $userId): int
    {
        return Notification::deleteAllForUser($userId);
    }

    public static function belongsToUser(int $userId, int $notificationId): bool
    {
        return Notification::findByIdForUser($notificationId, $userId) !== null;
    }

    /**
     * Server-side destination. Never trust a stored URL.
     *
     * @param array<string, mixed> $notification
     */
    public static function resolveTarget(array $notification, string $role): string
    {
        $type = trim((string) ($notification['notification_type'] ?? ''));
        $entityId = (int) ($notification['related_entity_id'] ?? 0);
        $audience = self::catalog()[$type]['audience'] ?? [];

        if ($entityId <= 0 || !in_array($role, $audience, true)) {
            return '/notifications';
        }

        return match ($type) {
            self::TYPE_REQUEST_CREATED => '/admin/consultation-requests?selected=' . $entityId,
            self::TYPE_APPROVED, self::TYPE_REJECTED, self::TYPE_COMPLETED, self::TYPE_PRESCRIPTION
                => '/patient/consultation-requests/' . $entityId,
            self::TYPE_ASSIGNED, self::TYPE_CANCELLED, self::TYPE_RESCHEDULED, self::TYPE_UPCOMING
                => '/doctor/consultations/' . $entityId,
            default => '/notifications',
        };
    }

    public static function relativeTime(?string $timestamp): string
    {
        if ($timestamp === null || trim($timestamp) === '') {
            return '';
        }

        try {
            $created = new \DateTimeImmutable($timestamp);
        } catch (\Throwable) {
            return Helper::formatDate($timestamp, 'd M Y', '');
        }

        $now = new \DateTimeImmutable('now');
        $diff = $now->getTimestamp() - $created->getTimestamp();
        if ($diff < 45) {
            return 'Just now';
        }
        if ($diff < 3600) {
            $minutes = max(1, (int) floor($diff / 60));
            return $minutes === 1 ? '1 minute ago' : $minutes . ' minutes ago';
        }
        if ($created->format('Y-m-d') === $now->format('Y-m-d')) {
            $hours = max(1, (int) floor($diff / 3600));
            return $hours === 1 ? '1 hour ago' : $hours . ' hours ago';
        }

        $yesterday = $now->sub(new \DateInterval('P1D'))->format('Y-m-d');
        if ($created->format('Y-m-d') === $yesterday) {
            return 'Yesterday';
        }

        return $created->format('d M Y');
    }

    public static function clinicianName(string $fullName): string
    {
        $name = trim($fullName);
        if ($name === '') {
            return 'your doctor';
        }
        if (preg_match('/^dr\.?\s+/i', $name) === 1) {
            return $name;
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $last = (string) end($parts);

        return 'Dr. ' . ($last !== '' ? $last : $name);
    }

    public static function personName(string $fullName, string $fallback = 'a patient'): string
    {
        $name = trim($fullName);

        return $name !== '' ? $name : $fallback;
    }

    public static function appointmentPhrase(string $date, string $time): string
    {
        $dateLabel = Helper::formatDate($date, 'j F Y', '');
        $time = trim($time);
        $timeLabel = '';
        if ($time !== '') {
            $timestamp = strtotime($time);
            if ($timestamp !== false) {
                $timeLabel = date('g:i A', $timestamp);
            }
        }

        if ($dateLabel !== '' && $timeLabel !== '') {
            return $dateLabel . ' at ' . $timeLabel;
        }

        return $dateLabel;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function presentMany(array $rows, string $role): array
    {
        $presented = [];
        foreach ($rows as $row) {
            $presented[] = self::present($row, $role);
        }

        return $presented;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function present(array $row, string $role): array
    {
        $type = (string) ($row['notification_type'] ?? '');
        $unread = (int) ($row['is_read'] ?? 0) === 0;

        return [
            'id' => (int) ($row['id'] ?? 0),
            'type' => $type,
            'title' => (string) ($row['title'] ?? self::typeTitle($type)),
            'message' => (string) ($row['message'] ?? ''),
            'icon' => self::typeIcon($type),
            'action_label' => self::typeAction($type),
            'tone' => self::typeTone($type),
            'unread' => $unread,
            'day_group' => self::dayGroup((string) ($row['created_at'] ?? '')),
            'relative_time' => self::relativeTime((string) ($row['created_at'] ?? '')),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'open_url' => '/notifications/' . (int) ($row['id'] ?? 0),
            'target_url' => self::resolveTarget($row, $role),
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @return array{read_state:string,type:string,search:string}
     */
    public static function normalizeFilters(array $query, string $role): array
    {
        $readState = ListFilter::allowedValue(
            trim((string) ($query['read_state'] ?? '')),
            ['unread', 'read'],
            ''
        );
        $allowedTypes = array_column(self::typeFilterOptions($role), 'value');
        $type = ListFilter::allowedValue(
            trim((string) ($query['type'] ?? '')),
            $allowedTypes,
            ''
        );
        $search = ListFilter::normalizeSearch((string) ($query['search'] ?? ''));

        return [
            'read_state' => $readState,
            'type' => $type,
            'search' => $search,
        ];
    }

    private static function createForUser(
        int $userId,
        string $type,
        string $title,
        string $message,
        int $requestId,
        bool $replace = false
    ): int {
        if (!self::userAllowsType($userId, $type)) {
            return 0;
        }

        $payload = [
            'user_id' => $userId,
            'notification_type' => $type,
            'title' => $title,
            'message' => $message,
            'related_entity_type' => self::ENTITY_CONSULTATION_REQUEST,
            'related_entity_id' => $requestId,
        ];

        return $replace
            ? Notification::createOrReplace($payload)
            : Notification::createOnce($payload);
    }

    private static function userAllowsType(int $userId, string $type): bool
    {
        try {
            $prefs = \App\Models\NotificationPreference::findOrDefault($userId);
        } catch (\Throwable) {
            return true;
        }

        $appointmentTypes = [
            self::TYPE_REQUEST_CREATED,
            self::TYPE_APPROVED,
            self::TYPE_ASSIGNED,
            self::TYPE_CANCELLED,
            self::TYPE_RESCHEDULED,
            self::TYPE_REJECTED,
            self::TYPE_UPCOMING,
        ];
        $consultationTypes = [
            self::TYPE_COMPLETED,
            self::TYPE_PRESCRIPTION,
        ];

        if (in_array($type, $appointmentTypes, true)) {
            return (int) ($prefs['appointment_in_app'] ?? 1) === 1;
        }
        if (in_array($type, $consultationTypes, true)) {
            return (int) ($prefs['consultation_in_app'] ?? 1) === 1;
        }

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function requestContext(int $requestId): ?array
    {
        if ($requestId <= 0) {
            return null;
        }

        return ConsultationRequest::findByIdForAdmin($requestId);
    }

    /**
     * @return list<int>
     */
    private static function activeAdminIds(): array
    {
        return User::findActiveAdminIds();
    }

    /**
     * @param callable():void $callback
     */
    private static function safeRun(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $exception) {
            error_log('[NotificationService] ' . $exception->getMessage());
        }
    }
}
