<?php

namespace App\Helpers;

/**
 * MBPHA TeleHealth color tokens for PHP surfaces that cannot read CSS variables
 * (Chart.js payloads, DomPDF documents, inline SVG). Keep in sync with
 * public/css/theme.css :root.
 */
class Palette
{
    public const MEDICAL_BLUE = '#0A6FB6';
    public const EMERALD_GREEN = '#18A558';
    public const DARK_NAVY = '#17375E';
    public const SOFT_CYAN = '#40C4FF';
    public const WHITE = '#FFFFFF';

    public const LIGHT_GRAY = '#F5F7FA';
    public const MEDIUM_GRAY = '#D9E2EC';
    public const DARK_GRAY = '#4A5568';
    public const NAVY_BLACK = '#102A43';

    public const ROYAL_BLUE = '#1565C0';
    public const MINT_GREEN = '#D8F3E8';
    public const PALE_BLUE = '#E3F2FD';

    /** Darker Medical Blue for hover / emphasis. */
    public const MEDICAL_BLUE_HOVER = '#085A94';

    /** Darker Emerald Green for hover and text on mint surfaces. */
    public const EMERALD_GREEN_DARK = '#147A45';

    /** Semantic danger — retained for destructive actions only. */
    public const DANGER = '#DC3545';

    /** Semantic warning — retained for pending / caution states. */
    public const WARNING = '#F59E0B';

    public const MEDICAL_BLUE_RGB = '10, 111, 182';
    public const EMERALD_GREEN_RGB = '24, 165, 88';
    public const DARK_NAVY_RGB = '23, 55, 94';
}
