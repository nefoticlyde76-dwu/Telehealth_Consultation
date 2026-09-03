<?php

namespace App\Helpers;

/**
 * Central status registry for MBPHA TeleHealth.
 *
 * Presentation, filter labels, and visual classes live here.
 * Database values and service-layer authorization remain authoritative.
 * Do not treat a badge or filter label as permission to change state.
 */
class Status
{
    public const DOMAIN_CONSULTATION = 'consultation_request';
    public const DOMAIN_RECORD = 'clinical_record';
    public const DOMAIN_SLOT = 'availability_slot';
    public const DOMAIN_USER = 'user_account';
    public const DOMAIN_PRESENCE = 'presence';

    public const PENDING = 'Pending';
    public const APPROVED = 'Approved';
    public const REJECTED = 'Rejected';
    public const CANCELLED = 'Cancelled';
    public const COMPLETED = 'Completed';

    public const RECORD_DRAFT = 'Draft';
    public const RECORD_FINAL = 'Final';

    public const SLOT_AVAILABLE = 'Available';
    public const SLOT_BOOKED = 'Booked';
    public const SLOT_EXPIRED = 'Expired';

    public const USER_INVITATION_PENDING = 'invitation_pending';
    public const USER_ACTIVE = 'active';
    public const USER_SUSPENDED = 'suspended';
    public const USER_INACTIVE = 'inactive';
    public const USER_DELETED = 'deleted';

    /** @var array<string, bool> */
    private static array $loggedUnknown = [];

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    public static function catalog(): array
    {
        return [
            self::DOMAIN_CONSULTATION => [
                self::PENDING => [
                    'label' => 'Pending',
                    'category' => 'pending',
                    'badge' => 'ux-badge--pending',
                    'icon' => 'bi-clock-fill',
                    'chart' => Palette::MEDICAL_BLUE,
                    'description' => 'Awaiting administrator review.',
                ],
                self::APPROVED => [
                    'label' => 'Approved',
                    'category' => 'approved',
                    'badge' => 'ux-badge--approved',
                    'icon' => 'bi-check-circle-fill',
                    'chart' => Palette::EMERALD_GREEN,
                    'description' => 'Approved and scheduled for consultation.',
                ],
                self::REJECTED => [
                    'label' => 'Rejected',
                    'category' => 'rejected',
                    'badge' => 'ux-badge--rejected',
                    'icon' => 'bi-x-circle-fill',
                    'chart' => Palette::DANGER,
                    'description' => 'Rejected during administrator review.',
                ],
                self::COMPLETED => [
                    'label' => 'Completed',
                    'category' => 'completed',
                    'badge' => 'ux-badge--completed',
                    'icon' => 'bi-check2-circle',
                    'chart' => Palette::EMERALD_GREEN,
                    'description' => 'Consultation completed. Historical record is available.',
                ],
                self::CANCELLED => [
                    'label' => 'Cancelled',
                    'category' => 'cancelled',
                    'badge' => 'ux-badge--cancelled',
                    'icon' => 'bi-slash-circle',
                    'chart' => Palette::DARK_GRAY,
                    'description' => 'Cancelled. This consultation is no longer active.',
                ],
            ],
            self::DOMAIN_RECORD => [
                self::RECORD_DRAFT => [
                    'label' => 'Draft',
                    'category' => 'draft',
                    'badge' => 'ux-badge--draft',
                    'icon' => 'bi-pencil-fill',
                    'chart' => Palette::DARK_GRAY,
                    'description' => 'Clinical notes are still being written.',
                ],
                self::RECORD_FINAL => [
                    'label' => 'Final',
                    'category' => 'final',
                    'badge' => 'ux-badge--final',
                    'icon' => 'bi-file-earmark-check-fill',
                    'chart' => Palette::DARK_NAVY,
                    'description' => 'Clinical record has been finalized.',
                ],
            ],
            self::DOMAIN_SLOT => [
                self::SLOT_AVAILABLE => [
                    'label' => 'Available',
                    'category' => 'available',
                    'badge' => 'ux-badge--available',
                    'icon' => 'bi-calendar2-check-fill',
                    'chart' => Palette::EMERALD_GREEN,
                    'description' => 'Open for patient booking.',
                ],
                self::SLOT_BOOKED => [
                    'label' => 'Booked',
                    'category' => 'booked',
                    'badge' => 'ux-badge--booked',
                    'icon' => 'bi-calendar2-event-fill',
                    'chart' => Palette::MEDICAL_BLUE,
                    'description' => 'Reserved by a consultation request.',
                ],
                self::SLOT_EXPIRED => [
                    'label' => 'Expired',
                    'category' => 'expired',
                    'badge' => 'ux-badge--neutral',
                    'icon' => 'bi-calendar-x',
                    'chart' => Palette::DARK_GRAY,
                    'description' => 'Unbooked slot whose end time has passed. Hidden from active availability.',
                ],
            ],
            self::DOMAIN_USER => [
                self::USER_INVITATION_PENDING => [
                    'label' => 'Invitation pending',
                    'category' => 'pending',
                    'badge' => 'ux-badge--pending',
                    'icon' => 'bi-envelope',
                    'chart' => Palette::MEDICAL_BLUE,
                    'description' => 'Doctor account has been created but the doctor has not yet completed password setup.',
                ],
                self::USER_ACTIVE => [
                    'label' => 'Active',
                    'category' => 'active',
                    'badge' => 'ux-badge--approved',
                    'icon' => 'bi-person-check-fill',
                    'chart' => Palette::EMERALD_GREEN,
                    'description' => 'Normal access. The account can sign in.',
                ],
                self::USER_SUSPENDED => [
                    'label' => 'Suspended',
                    'category' => 'suspended',
                    'badge' => 'ux-badge--pending',
                    'icon' => 'bi-pause-circle-fill',
                    'chart' => Palette::WARNING,
                    'description' => 'Temporarily blocked. The account cannot sign in.',
                ],
                self::USER_INACTIVE => [
                    'label' => 'Deactivated',
                    'category' => 'deactivated',
                    'badge' => 'ux-badge--rejected',
                    'icon' => 'bi-person-dash-fill',
                    'chart' => Palette::DANGER,
                    'description' => 'Account disabled. Data is retained for operational history.',
                ],
                self::USER_DELETED => [
                    'label' => 'Deleted',
                    'category' => 'deleted',
                    'badge' => 'ux-badge--neutral',
                    'icon' => 'bi-person-x-fill',
                    'chart' => Palette::DARK_GRAY,
                    'description' => 'Account removed. Clinical and audit history is retained.',
                ],
            ],
            self::DOMAIN_PRESENCE => [
                'Issued' => [
                    'label' => 'Issued',
                    'category' => 'issued',
                    'badge' => 'ux-badge--completed',
                    'icon' => 'bi-capsule',
                    'chart' => Palette::SOFT_CYAN,
                    'description' => 'A prescription is linked to this consultation.',
                ],
                'Final' => [
                    'label' => 'Final',
                    'category' => 'final',
                    'badge' => 'ux-badge--final',
                    'icon' => 'bi-file-earmark-check-fill',
                    'chart' => Palette::DARK_NAVY,
                    'description' => 'A finalized clinical record is available.',
                ],
            ],
        ];
    }

    /**
     * Allowed administrator transitions for consultation requests.
     * Doctor completion (Approved → Completed) is handled separately.
     *
     * @return array<string, list<string>>
     */
    public static function adminConsultationTransitions(): array
    {
        return [
            self::PENDING => [self::APPROVED, self::REJECTED, self::CANCELLED],
            self::APPROVED => [self::CANCELLED],
        ];
    }

    /**
     * Statuses an administrator may POST. Completed is doctor-only.
     *
     * @return list<string>
     */
    public static function adminActionableStatuses(): array
    {
        return [self::APPROVED, self::REJECTED, self::CANCELLED];
    }

    public static function canAdminTransition(string $from, string $to): bool
    {
        $from = self::normalizeKey(self::DOMAIN_CONSULTATION, $from);
        $to = self::normalizeKey(self::DOMAIN_CONSULTATION, $to);
        $map = self::adminConsultationTransitions();

        return isset($map[$from]) && in_array($to, $map[$from], true);
    }

    /**
     * @return list<string>
     */
    public static function consultationKeys(): array
    {
        return [self::PENDING, self::APPROVED, self::REJECTED, self::COMPLETED, self::CANCELLED];
    }

    /**
     * @return list<string>
     */
    public static function slotKeys(): array
    {
        return [self::SLOT_AVAILABLE, self::SLOT_BOOKED];
    }

    /**
     * Statuses shown on doctor/admin availability filters.
     * Expired slots are processed automatically and are not listed.
     *
     * @return list<string>
     */
    public static function activeSlotKeys(): array
    {
        return [self::SLOT_AVAILABLE, self::SLOT_BOOKED];
    }

    /**
     * @return list<string>
     */
    public static function userKeys(): array
    {
        return [
            self::USER_INVITATION_PENDING,
            self::USER_ACTIVE,
            self::USER_SUSPENDED,
            self::USER_INACTIVE,
            self::USER_DELETED,
        ];
    }

    /**
     * Statuses an administrator may assign through ordinary account controls.
     * Deleted is reserved for the permanent-deletion transaction.
     * invitation_pending is reserved for the doctor invitation workflow.
     *
     * @return list<string>
     */
    public static function assignableUserKeys(): array
    {
        return [self::USER_ACTIVE, self::USER_SUSPENDED, self::USER_INACTIVE];
    }

    public static function canAssignUserStatus(string $status): bool
    {
        return in_array(self::normalizeKey(self::DOMAIN_USER, $status), self::assignableUserKeys(), true);
    }

    public static function isDeletedUserStatus(string $status): bool
    {
        return self::normalizeKey(self::DOMAIN_USER, $status) === self::USER_DELETED;
    }

    public static function isInvitationPendingUserStatus(string $status): bool
    {
        return self::normalizeKey(self::DOMAIN_USER, $status) === self::USER_INVITATION_PENDING;
    }

    /**
     * Statuses an administrator may use to filter account lists.
     * Includes invitation_pending. Excludes deleted.
     *
     * @return list<string>
     */
    public static function filterableUserKeys(): array
    {
        return array_values(array_filter(
            self::userKeys(),
            static fn (string $key): bool => $key !== self::USER_DELETED
        ));
    }

    public static function canAuthenticateUserStatus(string $status): bool
    {
        return self::normalizeKey(self::DOMAIN_USER, $status) === self::USER_ACTIVE;
    }

    /**
     * @return list<string>
     */
    public static function recordKeys(): array
    {
        return [self::RECORD_DRAFT, self::RECORD_FINAL];
    }

    /**
     * @param list<string>|null $keys
     * @return list<array{value:string,label:string}>
     */
    public static function filterOptions(string $domain, ?array $keys = null): array
    {
        $keys = $keys ?? array_keys(self::catalog()[$domain] ?? []);
        $options = [];

        foreach ($keys as $key) {
            $options[] = [
                'value' => (string) $key,
                'label' => self::label((string) $key, $domain),
            ];
        }

        return $options;
    }

    /**
     * @return array{
     *   key:string,
     *   label:string,
     *   category:string,
     *   badge:string,
     *   icon:string,
     *   chart:string,
     *   description:string,
     *   known:bool
     * }
     */
    public static function resolve(string $status, string $domain = self::DOMAIN_CONSULTATION): array
    {
        $raw = trim($status);
        $key = self::normalizeKey($domain, $raw);
        $catalog = self::catalog()[$domain] ?? [];

        if ($key !== '' && isset($catalog[$key])) {
            return $catalog[$key] + ['key' => $key, 'known' => true];
        }

        if ($raw !== '') {
            self::logUnknown($domain, $raw);
        }

        return self::unknownDefinition();
    }

    public static function isKnown(string $status, string $domain = self::DOMAIN_CONSULTATION): bool
    {
        return self::resolve($status, $domain)['known'] === true;
    }

    public static function label(string $status, string $domain = self::DOMAIN_CONSULTATION): string
    {
        return self::resolve($status, $domain)['label'];
    }

    public static function badgeClass(string $status, string $domain = self::DOMAIN_CONSULTATION): string
    {
        return self::resolve($status, $domain)['badge'];
    }

    public static function iconClass(string $status, string $domain = self::DOMAIN_CONSULTATION): string
    {
        return self::resolve($status, $domain)['icon'];
    }

    public static function description(string $status, string $domain = self::DOMAIN_CONSULTATION): string
    {
        return self::resolve($status, $domain)['description'];
    }

    public static function chartColor(string $status, string $domain = self::DOMAIN_CONSULTATION): string
    {
        return self::resolve($status, $domain)['chart'];
    }

    public static function category(string $status, string $domain = self::DOMAIN_CONSULTATION): string
    {
        return self::resolve($status, $domain)['category'];
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function badgeHtml(string $status, string $domain = self::DOMAIN_CONSULTATION, array $options = []): string
    {
        $def = self::resolve($status, $domain);
        $showIcon = (bool) ($options['icon'] ?? true);
        $title = trim((string) ($options['title'] ?? $def['description']));
        $extraClass = trim((string) ($options['class'] ?? ''));
        $classes = trim('ux-badge ' . $def['badge'] . ($extraClass !== '' ? ' ' . $extraClass : ''));

        $html = '<span class="' . Helper::escape($classes) . '"';
        if ($title !== '') {
            $html .= ' title="' . Helper::escape($title) . '"';
        }
        $html .= '>';
        if ($showIcon) {
            $html .= '<i class="bi ' . Helper::escape($def['icon']) . '" aria-hidden="true"></i>';
        }
        $html .= Helper::escape($def['label']);
        $html .= '</span>';

        return $html;
    }

    /**
     * Join-window button labels. These are not consultation-request statuses.
     */
    public static function joinButtonLabel(string $joinWindowStatus): string
    {
        return match (strtolower(trim($joinWindowStatus))) {
            'early' => 'Not Yet Open',
            'ended' => 'Consultation Ended',
            default => 'Join Consultation',
        };
    }

    /**
     * Presentation-only action hints. Backend authorization remains required.
     *
     * @param array{
     *   role?:string,
     *   has_final_record?:bool,
     *   has_prescription?:bool,
     *   join_status?:string,
     *   can_join?:bool,
     *   join_url?:string
     * } $context
     * @return array<string, bool|string>
     */
    public static function consultationUiActions(string $status, array $context = []): array
    {
        $key = self::normalizeKey(self::DOMAIN_CONSULTATION, $status);
        $role = (string) ($context['role'] ?? 'patient');
        $hasFinalRecord = (bool) ($context['has_final_record'] ?? false);
        $hasPrescription = (bool) ($context['has_prescription'] ?? false);
        $joinStatus = (string) ($context['join_status'] ?? 'unavailable');
        $canJoin = (bool) ($context['can_join'] ?? false);
        $joinUrl = trim((string) ($context['join_url'] ?? ''));

        $isPending = $key === self::PENDING;
        $isApproved = $key === self::APPROVED;
        $isCompleted = $key === self::COMPLETED;
        $isClosed = in_array($key, [self::REJECTED, self::CANCELLED], true);
        $joinWindow = $isApproved && $joinUrl !== '' && in_array($joinStatus, ['open', 'early', 'ended'], true);

        return [
            'review' => $role === 'admin' && $isPending,
            'approve' => $role === 'admin' && $isPending,
            'reject' => $role === 'admin' && $isPending,
            'cancel' => $role === 'admin' && ($isPending || $isApproved),
            'join' => $joinWindow && !$isClosed && !$isCompleted,
            'join_enabled' => $joinWindow && $canJoin,
            'join_label' => self::joinButtonLabel($joinStatus),
            'view_details' => !$isCompleted,
            'view_record' => $isCompleted,
            'download_record' => $isCompleted && $hasFinalRecord,
            'view_prescription' => $isCompleted && $hasPrescription,
            'create_prescription' => $role === 'doctor' && $isCompleted && !$hasPrescription,
            'download_prescription' => $isCompleted && $hasPrescription,
            'review_complete' => $role === 'doctor' && $isApproved,
        ];
    }

    /**
     * @param array<string, int|string> $distribution
     * @return array<string, mixed>
     */
    public static function consultationDistributionChart(array $distribution): array
    {
        $labels = [];
        $values = [];
        $background = [];

        foreach (self::consultationKeys() as $status) {
            $labels[] = self::label($status);
            $values[] = (int) ($distribution[$status] ?? 0);
            $background[] = self::chartColor($status);
        }

        return [
            'type' => 'doughnut',
            'data' => [
                'labels' => $labels,
                'datasets' => [
                    [
                        'data' => $values,
                        'backgroundColor' => $background,
                        'borderWidth' => 0,
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'cutout' => '68%',
                'plugins' => [
                    'legend' => [
                        'position' => 'bottom',
                        'labels' => [
                            'usePointStyle' => true,
                            'boxWidth' => 10,
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function filteredListUrl(string $basePath, string $status): string
    {
        $query = http_build_query(['status' => $status]);

        return $basePath . ($query !== '' ? '?' . $query : '');
    }

    public static function logUnknown(string $domain, string $raw): void
    {
        $token = $domain . ':' . $raw;
        if (isset(self::$loggedUnknown[$token])) {
            return;
        }

        self::$loggedUnknown[$token] = true;
        error_log(sprintf('[Status] Unknown %s value %s', $domain, var_export($raw, true)));
    }

    public static function normalizeKey(string $domain, string $raw): string
    {
        $value = trim($raw);
        if ($value === '') {
            return '';
        }

        if ($domain === self::DOMAIN_USER) {
            $lower = strtolower($value);
            $userAliases = [
                'deactivated' => self::USER_INACTIVE,
                'disabled' => self::USER_INACTIVE,
                'inactive' => self::USER_INACTIVE,
                'invitation_pending' => self::USER_INVITATION_PENDING,
                'active' => self::USER_ACTIVE,
                'suspended' => self::USER_SUSPENDED,
                'deleted' => self::USER_DELETED,
            ];

            return $userAliases[$lower] ?? $lower;
        }

        $aliases = [
            'canceled' => self::CANCELLED,
            'cancelled' => self::CANCELLED,
            'pending' => self::PENDING,
            'approved' => self::APPROVED,
            'rejected' => self::REJECTED,
            'completed' => self::COMPLETED,
            'available' => self::SLOT_AVAILABLE,
            'booked' => self::SLOT_BOOKED,
            'expired' => self::SLOT_EXPIRED,
            'draft' => self::RECORD_DRAFT,
            'final' => self::RECORD_FINAL,
        ];

        $lower = strtolower($value);
        if (isset($aliases[$lower])) {
            if ($domain === self::DOMAIN_SLOT && in_array($aliases[$lower], [self::SLOT_AVAILABLE, self::SLOT_BOOKED, self::SLOT_EXPIRED], true)) {
                return $aliases[$lower];
            }
            if ($domain === self::DOMAIN_RECORD && in_array($aliases[$lower], [self::RECORD_DRAFT, self::RECORD_FINAL], true)) {
                return $aliases[$lower];
            }
            if ($domain === self::DOMAIN_CONSULTATION && in_array($aliases[$lower], self::consultationKeys(), true)) {
                return $aliases[$lower];
            }
            if ($domain === self::DOMAIN_PRESENCE) {
                return $aliases[$lower] === self::RECORD_FINAL ? 'Final' : $value;
            }
        }

        $catalog = self::catalog()[$domain] ?? [];
        if (isset($catalog[$value])) {
            return $value;
        }

        foreach (array_keys($catalog) as $key) {
            if (strtolower((string) $key) === $lower) {
                return (string) $key;
            }
        }

        return $value;
    }

    /**
     * @return array{
     *   key:string,
     *   label:string,
     *   category:string,
     *   badge:string,
     *   icon:string,
     *   chart:string,
     *   description:string,
     *   known:bool
     * }
     */
    private static function unknownDefinition(): array
    {
        return [
            'key' => '',
            'label' => 'Unknown Status',
            'category' => 'unknown',
            'badge' => 'ux-badge--neutral',
            'icon' => 'bi-question-circle-fill',
            'chart' => Palette::DARK_GRAY,
            'description' => 'This status is not recognized.',
            'known' => false,
        ];
    }
}
