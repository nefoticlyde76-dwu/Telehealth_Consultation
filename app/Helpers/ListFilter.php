<?php

namespace App\Helpers;

/**
 * Shared search, date-range, sort whitelist, pagination, and query-string
 * helpers used by list pages. SQL identifiers must still be chosen in the
 * calling model — this class never interpolates user input into ORDER BY.
 */
class ListFilter
{
    public const SEARCH_MAX_LENGTH = 100;
    public const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * @var list<string>
     */
    public const DATE_PRESETS = [
        'today',
        'tomorrow',
        'this_week',
        'this_month',
        'upcoming',
        'future',
        'past',
        'custom',
    ];

    public static function normalizeSearch(string $search): string
    {
        $search = trim((string) preg_replace('/\s+/u', ' ', $search));

        if ($search === '') {
            return '';
        }

        if (mb_strlen($search) > self::SEARCH_MAX_LENGTH) {
            return mb_substr($search, 0, self::SEARCH_MAX_LENGTH);
        }

        return $search;
    }

    public static function isValidDate(string $date): bool
    {
        $date = trim($date);
        if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        $parsed = \DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    /**
     * Escape LIKE wildcards so a user cannot broaden a contains-search.
     */
    public static function likeContains(string $search): string
    {
        $search = self::normalizeSearch($search);
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);

        return '%' . $escaped . '%';
    }

    /**
     * Resolve a date preset (and optional custom/legacy exact date) into an
     * inclusive from/to range. Preset names are whitelisted.
     *
     * @return array{preset:string,from:string,to:string}
     */
    public static function resolveDateRange(
        string $preset,
        string $from = '',
        string $to = '',
        string $legacyExact = ''
    ): array {
        $preset = strtolower(trim($preset));
        $from = trim($from);
        $to = trim($to);
        $legacyExact = trim($legacyExact);

        if ($preset !== '' && !in_array($preset, self::DATE_PRESETS, true)) {
            $preset = '';
        }

        if ($preset === '' && $legacyExact !== '' && self::isValidDate($legacyExact)) {
            return [
                'preset' => 'custom',
                'from' => $legacyExact,
                'to' => $legacyExact,
            ];
        }

        $today = new \DateTimeImmutable('today');

        switch ($preset) {
            case 'today':
                $day = $today->format('Y-m-d');
                return ['preset' => 'today', 'from' => $day, 'to' => $day];

            case 'tomorrow':
                $day = $today->modify('+1 day')->format('Y-m-d');
                return ['preset' => 'tomorrow', 'from' => $day, 'to' => $day];

            case 'this_week':
                $weekday = (int) $today->format('N');
                $monday = $today->modify('-' . ($weekday - 1) . ' days');
                $sunday = $monday->modify('+6 days');
                return [
                    'preset' => 'this_week',
                    'from' => $monday->format('Y-m-d'),
                    'to' => $sunday->format('Y-m-d'),
                ];

            case 'this_month':
                return [
                    'preset' => 'this_month',
                    'from' => $today->format('Y-m-01'),
                    'to' => $today->format('Y-m-t'),
                ];

            case 'upcoming':
            case 'future':
                return [
                    'preset' => $preset,
                    'from' => $today->format('Y-m-d'),
                    'to' => '',
                ];

            case 'past':
                return [
                    'preset' => 'past',
                    'from' => '',
                    'to' => $today->modify('-1 day')->format('Y-m-d'),
                ];

            case 'custom':
                if ($from !== '' && !self::isValidDate($from)) {
                    $from = '';
                }
                if ($to !== '' && !self::isValidDate($to)) {
                    $to = '';
                }
                if ($from !== '' && $to !== '' && $from > $to) {
                    [$from, $to] = [$to, $from];
                }

                return ['preset' => 'custom', 'from' => $from, 'to' => $to];

            default:
                return ['preset' => '', 'from' => '', 'to' => ''];
        }
    }

    /**
     * Append an inclusive date range using a trusted SQL column name.
     *
     * @param array<string, mixed> $range
     * @param list<string> $conditions
     * @param array<string, mixed> $parameters
     */
    public static function appendDateRange(
        string $column,
        array $range,
        array &$conditions,
        array &$parameters,
        string $prefix = 'date'
    ): void {
        $from = trim((string) ($range['from'] ?? ''));
        $to = trim((string) ($range['to'] ?? ''));

        if ($from !== '' && !self::isValidDate($from)) {
            $from = '';
        }
        if ($to !== '' && !self::isValidDate($to)) {
            $to = '';
        }

        if ($from !== '' && $to !== '') {
            $conditions[] = $column . ' BETWEEN :' . $prefix . '_from AND :' . $prefix . '_to';
            $parameters[':' . $prefix . '_from'] = $from;
            $parameters[':' . $prefix . '_to'] = $to;
            return;
        }

        if ($from !== '') {
            $conditions[] = $column . ' >= :' . $prefix . '_from';
            $parameters[':' . $prefix . '_from'] = $from;
            return;
        }

        if ($to !== '') {
            $conditions[] = $column . ' <= :' . $prefix . '_to';
            $parameters[':' . $prefix . '_to'] = $to;
        }
    }

    /**
     * @param list<string> $allowed
     */
    public static function allowedValue(string $value, array $allowed, string $default = ''): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }

    public static function allowedPerPage(mixed $perPage, int $default = 10): int
    {
        $perPage = (int) $perPage;
        if (in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            return $perPage;
        }

        return in_array($default, self::PER_PAGE_OPTIONS, true) ? $default : 10;
    }

    /**
     * @return array{
     *   current_page:int,
     *   per_page:int,
     *   total_items:int,
     *   total_pages:int,
     *   from:int,
     *   to:int
     * }
     */
    public static function paginate(int $page, int $totalItems, int $perPage): array
    {
        $perPage = max(1, $perPage);
        $totalItems = max(0, $totalItems);
        $totalPages = max(1, (int) ceil(max($totalItems, 1) / $perPage));
        $page = max(1, $page);
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $from = $totalItems === 0 ? 0 : (($page - 1) * $perPage) + 1;
        $to = min($page * $perPage, $totalItems);

        return [
            'current_page' => $page,
            'per_page' => $perPage,
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @param list<string> $skip
     * @return array<string, scalar>
     */
    public static function publicQuery(array $filters, array $skip = ['date_range']): array
    {
        $query = [];

        foreach ($filters as $key => $value) {
            if (!is_string($key) || $key === '' || in_array($key, $skip, true)) {
                continue;
            }
            if (is_array($value) || is_object($value)) {
                continue;
            }
            if ($value === '' || $value === null) {
                continue;
            }
            if (is_int($value) && $value === 0) {
                continue;
            }
            if (is_bool($value)) {
                continue;
            }
            $query[$key] = $value;
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $overrides
     */
    public static function queryString(array $filters, array $overrides = [], array $skip = ['date_range']): string
    {
        $merged = array_merge($filters, $overrides);
        if (array_key_exists('page', $merged) && (int) $merged['page'] <= 1) {
            unset($merged['page']);
        }

        return http_build_query(self::publicQuery($merged, $skip));
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $overrides
     */
    public static function url(string $path, array $filters, array $overrides = [], array $skip = ['date_range']): string
    {
        $queryString = self::queryString($filters, $overrides, $skip);

        return Helper::url($path) . ($queryString !== '' ? '?' . $queryString : '');
    }

    /**
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $defaults Keys that count as "not filtering"
     */
    public static function isActive(array $filters, array $defaults = []): bool
    {
        foreach ($filters as $key => $value) {
            if (in_array($key, ['page', 'per_page', 'selected', 'date_range'], true)) {
                continue;
            }
            $default = $defaults[$key] ?? '';
            if ($value === $default) {
                continue;
            }
            if ($value === '' || $value === null || $value === 0) {
                continue;
            }
            if (is_array($value)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Compact page list for numbered pagination. 0 represents an ellipsis.
     *
     * @return list<int>
     */
    public static function pageNumbers(int $current, int $total, int $radius = 1): array
    {
        $total = max(1, $total);
        $current = min(max(1, $current), $total);

        if ($total <= 7) {
            return range(1, $total);
        }

        $pages = [1];
        $start = max(2, $current - $radius);
        $end = min($total - 1, $current + $radius);

        if ($start > 2) {
            $pages[] = 0;
        }

        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }

        if ($end < $total - 1) {
            $pages[] = 0;
        }

        $pages[] = $total;

        return $pages;
    }

    /**
     * @param list<array{value:string,label:string}>|array<string,string> $options
     * @return list<array{value:string,label:string}>
     */
    public static function selectOptions(array $options): array
    {
        $normalized = [];

        foreach ($options as $key => $option) {
            if (is_array($option)) {
                $normalized[] = [
                    'value' => (string) ($option['value'] ?? ''),
                    'label' => (string) ($option['label'] ?? $option['value'] ?? ''),
                ];
                continue;
            }

            $normalized[] = [
                'value' => is_int($key) ? (string) $option : (string) $key,
                'label' => (string) $option,
            ];
        }

        return $normalized;
    }
}
