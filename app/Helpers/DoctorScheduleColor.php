<?php

namespace App\Helpers;

/**
 * Stable per-doctor colours for the shared availability calendar.
 *
 * Assignment is based on doctor_id so the same doctor keeps the same colour
 * on doctor and patient views. No database change is required.
 */
class DoctorScheduleColor
{
    /**
     * Professional swatches that remain readable on white calendar cards.
     *
     * @var list<array{key:string,label:string,bg:string,border:string,accent:string,text:string}>
     */
    public const SWATCHES = [
        ['key' => 'blue', 'label' => 'Blue', 'bg' => '#E8F1F8', 'border' => '#9BB8D3', 'accent' => '#0F4C81', 'text' => '#0B3B63'],
        ['key' => 'teal', 'label' => 'Teal', 'bg' => '#E6F6F5', 'border' => '#7EC8C3', 'accent' => '#0D9488', 'text' => '#115E59'],
        ['key' => 'purple', 'label' => 'Purple', 'bg' => '#F3EAFB', 'border' => '#C4A5E8', 'accent' => '#6D28D9', 'text' => '#4C1D95'],
        ['key' => 'green', 'label' => 'Green', 'bg' => '#E7F6EE', 'border' => '#86C9A4', 'accent' => '#15803D', 'text' => '#14532D'],
        ['key' => 'coral', 'label' => 'Coral', 'bg' => '#FDECE6', 'border' => '#F0B09A', 'accent' => '#C2410C', 'text' => '#7C2D12'],
        ['key' => 'indigo', 'label' => 'Indigo', 'bg' => '#EBEEFB', 'border' => '#A5B4E8', 'accent' => '#1D4ED8', 'text' => '#1E3A8A'],
        ['key' => 'cyan', 'label' => 'Cyan', 'bg' => '#E5F6FA', 'border' => '#7CC8D8', 'accent' => '#0E7490', 'text' => '#155E75'],
        ['key' => 'rose', 'label' => 'Rose', 'bg' => '#FCE8F0', 'border' => '#E8A0BC', 'accent' => '#BE185D', 'text' => '#831843'],
        ['key' => 'violet', 'label' => 'Violet', 'bg' => '#EEEAFC', 'border' => '#B7A8E8', 'accent' => '#5B21B6', 'text' => '#4C1D95'],
        ['key' => 'forest', 'label' => 'Forest', 'bg' => '#E8F4EC', 'border' => '#8ECAA3', 'accent' => '#047857', 'text' => '#064E3B'],
        ['key' => 'slate', 'label' => 'Slate', 'bg' => '#E8EEF3', 'border' => '#9AAEBD', 'accent' => '#334155', 'text' => '#1E293B'],
        ['key' => 'sky', 'label' => 'Sky', 'bg' => '#E6F3FA', 'border' => '#8EC5E0', 'accent' => '#0369A1', 'text' => '#0C4A6E'],
    ];

    /**
     * @return array{key:string,label:string,bg:string,border:string,accent:string,text:string,index:int}
     */
    public static function forDoctorId(int $doctorId): array
    {
        $swatches = self::SWATCHES;
        $count = count($swatches);
        $index = $count > 0 ? abs($doctorId) % $count : 0;
        $swatch = $swatches[$index] ?? $swatches[0];

        return $swatch + ['index' => $index];
    }

    /**
     * Assign colours to a set of doctor IDs. Preferred index is doctor_id modulo
     * the palette; collisions within the set step to the next free swatch.
     *
     * @param list<int> $doctorIds
     * @return array<int, array{key:string,label:string,bg:string,border:string,accent:string,text:string,index:int}>
     */
    public static function assign(array $doctorIds): array
    {
        $unique = [];
        foreach ($doctorIds as $doctorId) {
            $id = (int) $doctorId;
            if ($id > 0) {
                $unique[$id] = $id;
            }
        }

        $ids = array_values($unique);
        sort($ids, SORT_NUMERIC);

        $count = count(self::SWATCHES);
        $used = [];
        $map = [];

        foreach ($ids as $id) {
            $preferred = $count > 0 ? abs($id) % $count : 0;
            $index = $preferred;
            $guard = 0;
            while (isset($used[$index]) && $guard < $count) {
                $index = ($index + 1) % $count;
                $guard++;
            }
            $used[$index] = true;
            $swatch = self::SWATCHES[$index] ?? self::SWATCHES[0];
            $map[$id] = $swatch + ['index' => $index];
        }

        return $map;
    }

    /**
     * Inline style for a positioned, colour-coded calendar block.
     *
     * @param array<string, mixed> $color
     * @param array<string, mixed> $layout
     */
    public static function inlineBlockStyle(array $color, array $layout): string
    {
        $topRows = (float) ($layout['top_rows'] ?? 0);
        $spanRows = (float) ($layout['span_rows'] ?? 0);
        $left = (float) ($layout['left_pct'] ?? 0);
        $width = (float) ($layout['width_pct'] ?? 100);

        return sprintf(
            'top: calc(%s * var(--avail-row-h)); height: calc(%s * var(--avail-row-h) - 2px); left: %s%%; width: %s%%; --slot-bg: %s; --slot-border: %s; --slot-accent: %s; --slot-text: %s;',
            self::formatUnit($topRows),
            self::formatUnit($spanRows > 0 ? $spanRows : 1.0),
            self::formatUnit($left),
            self::formatUnit($width),
            (string) ($color['bg'] ?? '#E8F1F8'),
            (string) ($color['border'] ?? '#9BB8D3'),
            (string) ($color['accent'] ?? '#0F4C81'),
            (string) ($color['text'] ?? '#0B3B63')
        );
    }

    /**
     * Inline CSS variables for legend swatches and list markers.
     *
     * @param array<string, mixed> $color
     */
    public static function inlineSwatchStyle(array $color): string
    {
        return sprintf(
            '--slot-bg: %s; --slot-border: %s; --slot-accent: %s; --slot-text: %s;',
            (string) ($color['bg'] ?? '#E8F1F8'),
            (string) ($color['border'] ?? '#9BB8D3'),
            (string) ($color['accent'] ?? '#0F4C81'),
            (string) ($color['text'] ?? '#0B3B63')
        );
    }

    private static function formatUnit(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
}
