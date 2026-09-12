<?php

namespace App\Helpers;

/**
 * MBPHA TeleHealth color tokens for PHP surfaces that cannot read CSS variables
 * (Chart.js payloads, DomPDF documents, inline SVG). Keep in sync with
 * public/css/theme.css :root.
 */
class Palette
{
    public const MEDICAL_BLUE = '#0B7BC5';
    public const EMERALD_GREEN = '#18B96C';
    public const DARK_NAVY = '#063B66';
    public const SOFT_CYAN = '#19BDF4';
    public const WHITE = '#FFFFFF';

    public const LIGHT_GRAY = '#F3F7FB';
    public const MEDIUM_GRAY = '#D8E6F0';
    public const DARK_GRAY = '#637B91';
    public const NAVY_BLACK = '#082F50';

    public const ROYAL_BLUE = '#0B7BC5';
    public const MINT_GREEN = '#DDF5E9';
    public const PALE_BLUE = '#EAF5FC';

    /** Darker Medical Blue for hover / emphasis. */
    public const MEDICAL_BLUE_HOVER = '#0868A8';

    /** Darker Emerald Green for hover and text on mint surfaces. */
    public const EMERALD_GREEN_DARK = '#127B57';

    /** Semantic danger — retained for destructive actions only. */
    public const DANGER = '#DC5757';

    /** Semantic warning — retained for pending / caution states. */
    public const WARNING = '#D99A16';

    public const MEDICAL_BLUE_RGB = '11, 123, 197';
    public const EMERALD_GREEN_RGB = '24, 185, 108';
    public const DARK_NAVY_RGB = '6, 59, 102';
}
