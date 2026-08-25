<?php

namespace App\Helpers;

/**
 * Builds the two CSS/SVG dashboard hero cards.
 *
 * The sparkline is an inline SVG path animated with stroke-dasharray.
 * The allocation ring is a conic-gradient masked to a donut.
 * Colours come from Status::chartColor() so they match dashboard charts.
 * No chart library is used.
 */
class DashboardHero
{
    private const SPARK_WIDTH = 120;
    private const SPARK_HEIGHT = 40;

    private const EMPTY_RING = '#D9E2EC 0% 100%';

    /**
     * @param array<string, mixed> $charts
     * @return array<string, mixed>
     */
    public static function fromCharts(array $charts, string $role): array
    {
        $trendKey = isset($charts['monthly_requests']) ? 'monthly_requests' : 'weekly_requests';
        $trendChart = is_array($charts[$trendKey] ?? null) ? $charts[$trendKey] : null;
        $statusChart = is_array($charts['status_distribution'] ?? null) ? $charts['status_distribution'] : null;

        $trendValues = self::chartValues($trendChart);
        $spark = self::sparklinePaths($trendValues);
        $delta = self::seriesDelta($trendValues);
        $slices = self::statusSlices($statusChart);
        $total = $slices['total'];
        $period = $trendKey === 'monthly_requests' ? 'past 6 months' : 'past 7 days';

        $deltaMark = $delta['up'] ? '▲' : '▼';
        $deltaSign = $delta['percent'] > 0 ? '+' : '';
        $deltaClass = $delta['flat'] ? '' : ($delta['up'] ? 'glz-05a__up' : 'glz-05a__dn');

        return [
            'balance_label' => 'Consultations',
            'network' => 'MBPHA',
            'balance' => number_format($total),
            'balance_small' => '',
            'delta_mark' => $deltaMark,
            'delta_text' => $deltaSign . $delta['percent'] . '%',
            'delta_class' => $deltaClass,
            'delta_period' => $period,
            'spark_line' => $spark['line'],
            'spark_area' => $spark['area'],
            'assets' => self::assetRows($slices),
            'ring_label' => 'Caseload',
            'ring_value' => self::compactNumber($total),
            'ring_aria' => self::ringAria($slices),
            'ring_gradient' => $slices['gradient'],
            'legend' => $slices['legend'],
            'actions' => self::actionsForRole($role),
        ];
    }

    /**
     * Map numeric series to SVG line/area paths in a 120×40 viewBox.
     *
     * @param list<int|float> $values
     * @return array{line:string,area:string}
     */
    public static function sparklinePaths(array $values): array
    {
        if ($values === []) {
            $values = [0, 0];
        } elseif (count($values) === 1) {
            $values[] = $values[0];
        }

        $count = count($values);
        $max = max($values);
        $max = $max > 0 ? (float) $max : 1.0;
        $last = $count - 1;
        $topPad = 6.0;
        $bottomPad = 4.0;
        $usable = self::SPARK_HEIGHT - $topPad - $bottomPad;

        $commands = [];
        foreach ($values as $i => $value) {
            $x = $last === 0 ? 0.0 : round(self::SPARK_WIDTH * $i / $last, 2);
            $y = round(self::SPARK_HEIGHT - $bottomPad - ((float) $value / $max) * $usable, 2);
            $commands[] = ($i === 0 ? 'M' : 'L') . $x . ' ' . $y;
        }

        $line = implode(' ', $commands);

        return [
            'line' => $line,
            'area' => $line . ' V' . self::SPARK_HEIGHT . ' H0 Z',
        ];
    }

    /**
     * @param list<int|float> $values
     * @return array{percent:float,up:bool,flat:bool}
     */
    public static function seriesDelta(array $values): array
    {
        $count = count($values);
        if ($count < 2) {
            return ['percent' => 0.0, 'up' => true, 'flat' => true];
        }

        $current = (float) $values[$count - 1];
        $previous = (float) $values[$count - 2];

        if ($previous === 0.0 && $current === 0.0) {
            return ['percent' => 0.0, 'up' => true, 'flat' => true];
        }

        if ($previous === 0.0) {
            return ['percent' => 100.0, 'up' => true, 'flat' => false];
        }

        $percent = round((($current - $previous) / $previous) * 100, 1);

        return [
            'percent' => $percent,
            'up' => $percent >= 0,
            'flat' => $percent === 0.0,
        ];
    }

    /**
     * @param array<string, mixed>|null $chart
     * @return list<int>
     */
    public static function chartValues(?array $chart): array
    {
        $values = $chart['data']['datasets'][0]['data'] ?? [];
        if (!is_array($values)) {
            return [];
        }

        $out = [];
        foreach ($values as $value) {
            $out[] = (int) $value;
        }

        return $out;
    }

    /**
     * @param array<string, mixed>|null $chart
     * @return array{total:int,gradient:string,legend:list<array{label:string,percent:string,color:string}>,rows:list<array<string,mixed>>}
     */
    public static function statusSlices(?array $chart): array
    {
        $labels = is_array($chart['data']['labels'] ?? null) ? $chart['data']['labels'] : [];
        $values = is_array($chart['data']['datasets'][0]['data'] ?? null) ? $chart['data']['datasets'][0]['data'] : [];

        $rows = [];
        $total = 0;
        foreach (Status::consultationKeys() as $index => $status) {
            $label = Status::label($status);
            $count = (int) ($values[$index] ?? 0);
            if (isset($labels[$index]) && (string) $labels[$index] !== '') {
                $label = (string) $labels[$index];
            }
            $total += $count;
            $rows[] = [
                'key' => $status,
                'label' => $label,
                'count' => $count,
                'color' => Status::chartColor($status),
                'icon' => Status::iconClass($status),
                'symbol' => strtoupper(substr($label, 0, 1)),
            ];
        }

        $legend = [];
        foreach ($rows as $index => $row) {
            $percent = $total > 0 ? (int) round(($row['count'] / $total) * 100) : 0;
            $row['percent'] = $percent;
            $row['percent_label'] = $percent . '%';
            $rows[$index] = $row;

            if (in_array($row['key'], [Status::PENDING, Status::APPROVED, Status::COMPLETED], true) || $row['count'] > 0) {
                $legend[] = [
                    'label' => $row['label'],
                    'percent' => $row['percent_label'],
                    'color' => $row['color'],
                ];
            }
        }

        $stops = [];
        $painted = array_values(array_filter($rows, static fn(array $row): bool => (int) $row['count'] > 0));
        if ($painted === []) {
            $stops[] = self::EMPTY_RING;
        } else {
            $cursor = 0.0;
            $lastPaint = count($painted) - 1;
            foreach ($painted as $index => $row) {
                $share = ($row['count'] / $total) * 100;
                $next = $index === $lastPaint ? 100.0 : round($cursor + $share, 4);
                $stops[] = $row['color'] . ' ' . $cursor . '% ' . $next . '%';
                $cursor = $next;
            }
        }

        return [
            'total' => $total,
            'gradient' => implode(',', $stops),
            'legend' => $legend,
            'rows' => $rows,
        ];
    }

    /**
     * @param array{total:int,rows:list<array<string,mixed>>} $slices
     * @return list<array<string,mixed>>
     */
    private static function assetRows(array $slices): array
    {
        $focus = [Status::PENDING, Status::APPROVED, Status::COMPLETED];
        $picked = [];
        foreach ($slices['rows'] as $row) {
            if (in_array($row['key'], $focus, true)) {
                $picked[] = $row;
            }
        }

        $max = 0;
        foreach ($picked as $row) {
            $max = max($max, (int) $row['count']);
        }

        $assets = [];
        foreach ($picked as $row) {
            $bar = $max > 0 ? (int) round(((int) $row['count'] / $max) * 100) : 0;
            $isDown = $row['key'] === Status::REJECTED || $row['key'] === Status::CANCELLED;
            $assets[] = [
                'symbol' => (string) $row['symbol'],
                'name' => (string) $row['label'],
                'meta' => number_format((int) $row['count']),
                'bar' => $bar,
                'delta' => (string) $row['percent_label'],
                'tone' => $isDown ? 'dn' : 'up',
                'color' => (string) $row['color'],
                'icon' => (string) ($row['icon'] ?? ''),
            ];
        }

        return $assets;
    }

    /**
     * @param array{legend:list<array{label:string,percent:string}>} $slices
     */
    private static function ringAria(array $slices): string
    {
        $parts = [];
        foreach ($slices['legend'] as $item) {
            $parts[] = $item['label'] . ' ' . $item['percent'];
        }

        if ($parts === []) {
            return 'Consultation status mix';
        }

        return 'Consultation status mix: ' . implode(', ', $parts);
    }

    private static function compactNumber(int $value): string
    {
        if ($value >= 1000) {
            return round($value / 1000, 1) . 'k';
        }

        return (string) $value;
    }

    /**
     * @return list<array{label:string,url:string,icon:string}>
     */
    private static function actionsForRole(string $role): array
    {
        return match ($role) {
            'doctor' => [
                ['label' => 'Availability', 'url' => '/doctor/availability', 'icon' => 'bi-calendar2-week'],
                ['label' => 'Consultations', 'url' => '/doctor/consultations', 'icon' => 'bi-journal-medical'],
                ['label' => 'Today', 'url' => '/doctor/consultations?date=today', 'icon' => 'bi-calendar-event'],
            ],
            'admin' => [
                ['label' => 'Review', 'url' => Status::filteredListUrl('/admin/consultation-requests', Status::PENDING), 'icon' => 'bi-clipboard-check'],
                ['label' => 'Queue', 'url' => '/admin/consultation-requests', 'icon' => 'bi-list-ul'],
                ['label' => 'Doctors', 'url' => '/admin/doctors', 'icon' => 'bi-heart-pulse'],
            ],
            default => [
                ['label' => 'Book', 'url' => '/patient/available-slots', 'icon' => 'bi-calendar2-plus'],
                ['label' => 'History', 'url' => '/patient/consultation-requests', 'icon' => 'bi-clock-history'],
                ['label' => 'Doctors', 'url' => '/patient/doctors', 'icon' => 'bi-heart-pulse'],
            ],
        };
    }
}
