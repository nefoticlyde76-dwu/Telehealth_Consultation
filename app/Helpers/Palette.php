<?php

namespace App\Helpers;

/**
 * MBPHA TeleHealth color tokens for PHP surfaces that cannot read CSS variables
 * (Chart.js payloads, DomPDF documents, inline SVG). Keep in sync with
 * public/css/theme.css :root.
 */
class Palette
{
    public const MEDICAL_BLUE = '#0B78C8';
    public const EMERALD_GREEN = '#18A56B';
    public const DARK_NAVY = '#0B3558';
    public const SOFT_CYAN = '#4EA4EE';
    public const WHITE = '#FFFFFF';

    public const LIGHT_GRAY = '#F4F8FC';
    public const MEDIUM_GRAY = '#D8E6F1';
    public const DARK_GRAY = '#60758A';
    public const NAVY_BLACK = '#12375A';

    public const ROYAL_BLUE = '#0B78C8';
    public const MINT_GREEN = '#DDF5EA';
    public const PALE_BLUE = '#EAF5FD';

    /** Darker Medical Blue for hover / emphasis. */
    public const MEDICAL_BLUE_HOVER = '#0964A8';

    /** Darker Emerald Green for hover and text on mint surfaces. */
    public const EMERALD_GREEN_DARK = '#148058';

    /** Semantic danger — retained for destructive actions only. */
    public const DANGER = '#D95757';

    /** Semantic warning — retained for pending / caution states. */
    public const WARNING = '#D99A19';

    public const MEDICAL_BLUE_RGB = '11, 120, 200';
    public const EMERALD_GREEN_RGB = '24, 165, 107';
    public const DARK_NAVY_RGB = '11, 53, 88';
}
