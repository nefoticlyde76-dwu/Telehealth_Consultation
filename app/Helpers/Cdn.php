<?php

namespace App\Helpers;

/**
 * Pinned third-party CDN URLs with Subresource Integrity hashes.
 * Hashes are SHA-384 of the exact bytes at these versioned jsDelivr paths.
 * Google Fonts CSS cannot be integrity-checked (response varies by UA), so
 * Inter and Poppins are self-hosted from /fonts instead.
 */
class Cdn
{
    public const BOOTSTRAP_CSS = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css';
    public const BOOTSTRAP_CSS_INTEGRITY = 'sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM';

    public const BOOTSTRAP_JS = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js';
    public const BOOTSTRAP_JS_INTEGRITY = 'sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz';

    public const BOOTSTRAP_ICONS_CSS = 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css';
    public const BOOTSTRAP_ICONS_CSS_INTEGRITY = 'sha384-l4UPAMHGzl7zwogLW4nOwaU2XTk6oiM1jhCRQstZEndoIiA2I5bg6fST3wzBSRBD';

    public const CHART_JS = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js';
    public const CHART_JS_INTEGRITY = 'sha384-JUh163oCRItcbPme8pYnROHQMC6fNKTBWtRG3I3I0erJkzNgL7uxKlNwcrcFKeqF';

    public const DAILY_JS = 'https://cdn.jsdelivr.net/npm/@daily-co/daily-js@0.67.0/dist/daily-iframe.min.js';
    public const DAILY_JS_INTEGRITY = 'sha384-Ar38FC9XHR/v0LWhQWD+GSW3xevmZduaobHCatFFqb/DPwCo5DSSyVhC6RVnrZSi';

    public static function stylesheet(string $url, string $integrity): string
    {
        return '<link rel="stylesheet" href="' . Helper::escape($url) . '" integrity="'
            . Helper::escape($integrity) . '" crossorigin="anonymous">';
    }

    /**
     * @param array<string, string> $attributes Extra HTML attributes (id, onerror, …)
     */
    public static function script(string $url, string $integrity, array $attributes = []): string
    {
        $html = '<script src="' . Helper::escape($url) . '" integrity="'
            . Helper::escape($integrity) . '" crossorigin="anonymous"';

        foreach ($attributes as $name => $value) {
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $name)) {
                continue;
            }
            $html .= ' ' . $name . '="' . Helper::escape((string) $value) . '"';
        }

        return $html . '></script>';
    }
}
