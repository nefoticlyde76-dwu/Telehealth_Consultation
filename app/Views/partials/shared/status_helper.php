<?php

if (!isset($GLOBALS['UX_SYSTEM_STATUS_MAP_DEFINED'])) {
    $GLOBALS['UX_SYSTEM_STATUS_MAP_DEFINED'] = true;

    $GLOBALS['UX_STATUS_BADGE_MAP'] = [
        'Pending' => 'ux-badge--pending',
        'Approved' => 'ux-badge--approved',
        'Completed' => 'ux-badge--approved',
        'Active' => 'ux-badge--approved',
        'Available' => 'ux-badge--approved',
        'Rejected' => 'ux-badge--rejected',
        'Cancelled' => 'ux-badge--rejected',
        'Canceled' => 'ux-badge--rejected',
        'Inactive' => 'ux-badge--rejected',
        'Unavailable' => 'ux-badge--rejected',
        'Booked' => 'ux-badge--booked',
        'Scheduled' => 'ux-badge--booked',
        'Assigned' => 'ux-badge--booked',
        'Expired' => 'ux-badge--rejected',
    ];

    $GLOBALS['UX_STATUS_ICON_MAP'] = [
        'Pending' => 'bi-clock-fill',
        'Approved' => 'bi-check-circle-fill',
        'Completed' => 'bi-check2-circle',
        'Active' => 'bi-check-circle-fill',
        'Available' => 'bi-calendar2-check-fill',
        'Rejected' => 'bi-x-circle-fill',
        'Cancelled' => 'bi-x-circle-fill',
        'Canceled' => 'bi-x-circle-fill',
        'Inactive' => 'bi-person-dash-fill',
        'Unavailable' => 'bi-calendar2-x-fill',
        'Booked' => 'bi-calendar2-event-fill',
        'Scheduled' => 'bi-calendar3-event-fill',
        'Assigned' => 'bi-calendar3-event-fill',
        'Expired' => 'bi-calendar-x-fill',
    ];

    if (!function_exists('ux_status_badge_class')) {
        function ux_status_badge_class(string $status, string $default = 'ux-badge--neutral'): string {
            $map = $GLOBALS['UX_STATUS_BADGE_MAP'] ?? [];
            $key = trim($status);
            if (isset($map[$key])) {
                return (string) $map[$key];
            }
            if ($key === '') {
                return $default;
            }
            $lower = strtolower($key);
            foreach ($map as $label => $cls) {
                if (strtolower((string) $label) === $lower) {
                    return (string) $cls;
                }
            }
            return $default;
        }
    }

    if (!function_exists('ux_status_icon_class')) {
        function ux_status_icon_class(string $status, string $default = 'bi-circle-fill'): string {
            $map = $GLOBALS['UX_STATUS_ICON_MAP'] ?? [];
            $key = trim($status);
            if (isset($map[$key])) {
                return (string) $map[$key];
            }
            if ($key === '') {
                return $default;
            }
            $lower = strtolower($key);
            foreach ($map as $label => $cls) {
                if (strtolower((string) $label) === $lower) {
                    return (string) $cls;
                }
            }
            return $default;
        }
    }

    if (!function_exists('ux_active_badge_class')) {
        function ux_active_badge_class($value, bool $numericActive = true): string {
            if (is_bool($value)) {
                return $value ? 'ux-badge--approved' : 'ux-badge--rejected';
            }
            if (is_numeric($value)) {
                return $numericActive
                    ? ((int) $value === 1 ? 'ux-badge--approved' : 'ux-badge--rejected')
                    : ((int) $value === 0 ? 'ux-badge--approved' : 'ux-badge--rejected');
            }
            $v = strtolower(trim((string) $value));
            if (in_array($v, ['active', 'available', 'approved', 'completed', '1', 'true', 'yes'], true)) {
                return 'ux-badge--approved';
            }
            if (in_array($v, ['inactive', 'unavailable', 'rejected', 'cancelled', 'canceled', '0', 'false', 'no'], true)) {
                return 'ux-badge--rejected';
            }
            return ux_status_badge_class((string) $value, 'ux-badge--neutral');
        }
    }

    if (!function_exists('ux_slot_status_badge_class')) {
        function ux_slot_status_badge_class($status, ?bool $isBooked = null): string {
            if ($isBooked === true) {
                return 'ux-badge--booked';
            }
            if ($isBooked === false) {
                return 'ux-badge--approved';
            }
            $label = is_string($status) ? $status : '';
            return ux_status_badge_class($label, 'ux-badge--approved');
        }
    }

    if (!function_exists('ux_stat_color_class')) {
        function ux_stat_color_class(string $variant): string {
            $variant = strtolower(trim($variant));
            $palette = [
                'navy' => 'ux-stat__icon--navy',
                'blue' => 'ux-stat__icon--blue',
                'primary' => 'ux-stat__icon--navy',
                'success' => 'ux-stat__icon--success',
                'green' => 'ux-stat__icon--green',
                'mint' => 'ux-stat__icon--mint',
                'amber' => 'ux-stat__icon--amber',
                'warning' => 'ux-stat__icon--amber',
                'cyan' => 'ux-stat__icon--cyan',
                'info' => 'ux-stat__icon--cyan',
                'purple' => 'ux-stat__icon--purple',
                'slate' => 'ux-stat__icon--slate',
                'secondary' => 'ux-stat__icon--slate',
                'surface' => 'ux-stat__icon--surface',
                'danger' => 'ux-stat__icon--danger',
                'red' => 'ux-stat__icon--danger',
            ];
            return $palette[$variant] ?? 'ux-stat__icon--navy';
        }
    }
}
