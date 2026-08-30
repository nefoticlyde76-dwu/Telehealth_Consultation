<?php

use App\Helpers\Status;

if (!isset($GLOBALS['UX_SYSTEM_STATUS_MAP_DEFINED'])) {
    $GLOBALS['UX_SYSTEM_STATUS_MAP_DEFINED'] = true;

    if (!function_exists('ux_status_badge')) {
        /**
         * Render a consistent status badge. Label text is always present for accessibility.
         *
         * @param array<string, mixed> $options
         */
        function ux_status_badge(string $status, string $domain = Status::DOMAIN_CONSULTATION, array $options = []): string
        {
            return Status::badgeHtml($status, $domain, $options);
        }
    }

    if (!function_exists('ux_status_label')) {
        function ux_status_label(string $status, string $domain = Status::DOMAIN_CONSULTATION): string
        {
            return Status::label($status, $domain);
        }
    }

    if (!function_exists('ux_status_badge_class')) {
        function ux_status_badge_class(string $status, string $default = 'ux-badge--neutral'): string
        {
            $domain = ux_status_guess_domain($status);
            $resolved = Status::resolve($status, $domain);
            if ($resolved['known']) {
                return $resolved['badge'];
            }

            return $default !== '' ? $default : $resolved['badge'];
        }
    }

    if (!function_exists('ux_status_icon_class')) {
        function ux_status_icon_class(string $status, string $default = 'bi-circle-fill'): string
        {
            $domain = ux_status_guess_domain($status);
            $resolved = Status::resolve($status, $domain);
            if ($resolved['known']) {
                return $resolved['icon'];
            }

            return $default !== '' ? $default : $resolved['icon'];
        }
    }

    if (!function_exists('ux_active_badge_class')) {
        function ux_active_badge_class($value, bool $numericActive = true): string
        {
            if (is_bool($value)) {
                return Status::badgeClass($value ? Status::USER_ACTIVE : Status::USER_INACTIVE, Status::DOMAIN_USER);
            }
            if (is_numeric($value)) {
                $isActive = $numericActive ? ((int) $value === 1) : ((int) $value === 0);
                return Status::badgeClass($isActive ? Status::USER_ACTIVE : Status::USER_INACTIVE, Status::DOMAIN_USER);
            }

            return Status::badgeClass((string) $value, Status::DOMAIN_USER);
        }
    }

    if (!function_exists('ux_slot_status_badge_class')) {
        function ux_slot_status_badge_class($status, ?bool $isBooked = null): string
        {
            if ($isBooked === true) {
                return Status::badgeClass(Status::SLOT_BOOKED, Status::DOMAIN_SLOT);
            }
            if ($isBooked === false) {
                return Status::badgeClass(Status::SLOT_AVAILABLE, Status::DOMAIN_SLOT);
            }

            return Status::badgeClass(is_string($status) ? $status : '', Status::DOMAIN_SLOT);
        }
    }

    if (!function_exists('ux_stat_color_class')) {
        function ux_stat_color_class(string $variant): string
        {
            $variant = strtolower(trim($variant));
            $palette = [
                'navy' => 'ux-stat__icon--navy',
                'blue' => 'ux-stat__icon--blue',
                'primary' => 'ux-stat__icon--navy',
                'success' => 'ux-stat__icon--success',
                'green' => 'ux-stat__icon--green',
                'mint' => 'ux-stat__icon--mint',
                'amber' => 'ux-stat__icon--pending',
                'warning' => 'ux-stat__icon--pending',
                'pending' => 'ux-stat__icon--pending',
                'cyan' => 'ux-stat__icon--cyan',
                'info' => 'ux-stat__icon--cyan',
                'purple' => 'ux-stat__icon--purple',
                'slate' => 'ux-stat__icon--slate',
                'secondary' => 'ux-stat__icon--slate',
                'surface' => 'ux-stat__icon--surface',
                'danger' => 'ux-stat__icon--danger',
                'red' => 'ux-stat__icon--danger',
                'completed' => 'ux-stat__icon--cyan',
                'cancelled' => 'ux-stat__icon--slate',
            ];
            return $palette[$variant] ?? 'ux-stat__icon--navy';
        }
    }

    if (!function_exists('ux_status_guess_domain')) {
        function ux_status_guess_domain(string $status): string
        {
            $lower = strtolower(trim($status));
            if (in_array($lower, ['active', 'inactive', 'invitation_pending', 'suspended', 'deleted'], true)) {
                return Status::DOMAIN_USER;
            }
            if (in_array($lower, ['available', 'booked'], true)) {
                return Status::DOMAIN_SLOT;
            }
            if (in_array($lower, ['draft', 'final'], true)) {
                return Status::DOMAIN_RECORD;
            }

            return Status::DOMAIN_CONSULTATION;
        }
    }
}
