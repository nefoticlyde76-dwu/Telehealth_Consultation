<?php

namespace App\Middleware;

use App\Helpers\Helper;

/**
 * Baseline security headers for every HTTP response.
 *
 * Applied once at the start of Router::dispatch() so public pages, JSON
 * endpoints, file streams, and 404s all receive the same defaults.
 * Later header() calls (PDFs, complaint images, profile photos) may
 * overwrite a value; this middleware does not replace a header that is
 * already queued.
 */
class SecurityHeadersMiddleware implements Middleware
{
    public function handle(): void
    {
        self::apply();
    }

    public static function apply(): void
    {
        if (headers_sent()) {
            return;
        }

        foreach (self::headers() as $name => $value) {
            if (self::isQueued($name)) {
                continue;
            }
            header($name . ': ' . $value);
        }
    }

    /**
     * @return array<string, string>
     */
    public static function headers(): array
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Frame-Options' => 'DENY',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Content-Security-Policy' => self::contentSecurityPolicy(),
        ];

        if (Helper::isHttpsRequest()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        return $headers;
    }

    public static function contentSecurityPolicy(): string
    {
        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
            // Inline scripts: layout enter-animation, GIS onload, Daily onerror, print onclick.
            // GIS: accounts.google.com/gsi/client. Daily + Bootstrap + Chart.js: jsdelivr.
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://accounts.google.com",
            // Bootstrap/Icons CSS, GIS injected button styles. Inter/Poppins are self-hosted.
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://accounts.google.com",
            "font-src 'self' https://cdn.jsdelivr.net data:",
            "img-src 'self' data: blob: https://www.gstatic.com https://*.googleusercontent.com https://cdn.jsdelivr.net",
            // Same-origin fetch (join-token, /auth/google). GIS and Daily signalling.
            "connect-src 'self' https://accounts.google.com https://www.googleapis.com https://*.daily.co wss://*.daily.co",
            // GIS button iframe; Daily Prebuilt iframe (mbphatelehealth.daily.co).
            "frame-src https://accounts.google.com https://*.daily.co",
            "media-src 'self' blob: mediastream:",
            "worker-src 'self' blob:",
        ];

        return implode('; ', $directives);
    }

    private static function isQueued(string $name): bool
    {
        $prefix = strtolower($name) . ':';
        foreach (headers_list() as $header) {
            if (str_starts_with(strtolower($header), $prefix)) {
                return true;
            }
        }

        return false;
    }
}
