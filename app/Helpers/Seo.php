<?php

namespace App\Helpers;

use App\Config\Environment;

/**
 * Public-site SEO metadata for MBPHA TeleHealth.
 *
 * Indexable pages are the marketing routes only. Authenticated healthcare
 * areas must never receive these tags, appear in the sitemap, or be treated
 * as public.
 */
class Seo
{
    public const PRODUCTION_ORIGIN = 'https://mbphatelehealth.com';
    public const SITE_NAME = 'MBPHA TeleHealth';
    public const DEFAULT_IMAGE_PATH = 'images/LOGOS.png';

    /**
     * Public pages that may be indexed. Keys are application paths.
     *
     * @return array<string, array{title: string, description: string}>
     */
    public static function indexablePages(): array
    {
        return [
            '/' => [
                'title' => 'MBPHA TeleHealth | Online Healthcare Consultations in Papua New Guinea',
                'description' => 'MBPHA TeleHealth provides secure online healthcare consultations, connecting patients with healthcare professionals through a convenient digital platform in Papua New Guinea.',
            ],
            '/about' => [
                'title' => 'About MBPHA TeleHealth | Milne Bay Provincial Health Authority',
                'description' => 'Learn about MBPHA TeleHealth, the Milne Bay Provincial Health Authority platform for requesting, attending, and reviewing online consultations with MBPHA doctors in Papua New Guinea.',
            ],
            '/how-it-works' => [
                'title' => 'How MBPHA TeleHealth Works | Register, Book, and Consult Online',
                'description' => 'See how MBPHA TeleHealth works: register as a patient, request a doctor slot, receive administrator approval, join a video consultation, and access your consultation record.',
            ],
            '/contact' => [
                'title' => 'Contact MBPHA TeleHealth | Platform Inquiries',
                'description' => 'Contact the MBPHA TeleHealth platform team for general questions about the service in Milne Bay Province, Papua New Guinea. Consultation bookings are made after you register or sign in.',
            ],
        ];
    }

    /**
     * Guest authentication pages. Public HTML, but must not be indexed.
     *
     * @return list<string>
     */
    public static function noIndexPaths(): array
    {
        return [
            '/login',
            '/register',
            '/forgot-password',
            '/reset-password',
            '/doctor/setup-password',
        ];
    }

    /**
     * Authenticated path prefixes excluded from crawlers and the sitemap.
     *
     * @return list<string>
     */
    public static function privatePathPrefixes(): array
    {
        return [
            '/admin',
            '/doctor',
            '/patient',
            '/account',
            '/notifications',
            '/auth',
        ];
    }

    public static function canonicalOrigin(): string
    {
        $configured = rtrim((string) Environment::get('SEO_CANONICAL_ORIGIN', ''), '/');
        if ($configured !== '' && self::isSafeOrigin($configured)) {
            return $configured;
        }

        return self::PRODUCTION_ORIGIN;
    }

    public static function canonicalUrl(string $path = '/'): string
    {
        $normalized = self::normalizePath($path);
        $origin = self::canonicalOrigin();

        if ($normalized === '/') {
            return $origin . '/';
        }

        return $origin . $normalized;
    }

    public static function publicAssetUrl(string $path): string
    {
        return rtrim(self::canonicalOrigin(), '/') . '/' . ltrim($path, '/');
    }

    public static function defaultImageUrl(): string
    {
        return self::publicAssetUrl(self::DEFAULT_IMAGE_PATH);
    }

    /**
     * Metadata for a public or guest layout render.
     *
     * @return array{
     *   title: string,
     *   description: ?string,
     *   robots: ?string,
     *   canonical: ?string,
     *   openGraph: bool,
     *   jsonLd: ?array<int, array<string, mixed>>
     * }
     */
    public static function forPath(
        string $path,
        ?string $title = null,
        ?string $description = null,
        ?string $robots = null
    ): array {
        $normalized = self::normalizePath($path);
        $pages = self::indexablePages();
        $isIndexable = isset($pages[$normalized]);
        $page = $pages[$normalized] ?? null;

        $resolvedTitle = self::nonEmptyString($title)
            ?? ($page['title'] ?? 'MBPHA TeleHealth Consultation System');
        $resolvedDescription = self::nonEmptyString($description)
            ?? ($page['description'] ?? null);

        $forceNoIndex = $robots !== null && self::containsNoIndex($robots);
        if ($forceNoIndex || self::shouldNoIndexPath($normalized)) {
            return [
                'title' => $resolvedTitle,
                'description' => null,
                'robots' => 'noindex, nofollow',
                'canonical' => null,
                'openGraph' => false,
                'jsonLd' => null,
            ];
        }

        if (!$isIndexable) {
            return [
                'title' => $resolvedTitle,
                'description' => $resolvedDescription,
                'robots' => self::nonEmptyString($robots),
                'canonical' => null,
                'openGraph' => false,
                'jsonLd' => null,
            ];
        }

        $canonical = self::canonicalUrl($normalized);

        return [
            'title' => $resolvedTitle,
            'description' => $resolvedDescription,
            'robots' => null,
            'canonical' => $canonical,
            'openGraph' => true,
            'jsonLd' => $normalized === '/' ? self::homeJsonLd($resolvedTitle, (string) $resolvedDescription, $canonical) : null,
        ];
    }

    /**
     * @return array{
     *   title: string,
     *   description: ?string,
     *   robots: ?string,
     *   canonical: ?string,
     *   openGraph: bool,
     *   jsonLd: ?array<int, array<string, mixed>>
     * }
     */
    public static function forRequest(?string $title = null, ?string $description = null, ?string $robots = null): array
    {
        return self::forPath(Helper::currentPath(), $title, $description, $robots);
    }

    /**
     * Controller-friendly defaults for an indexable public page.
     *
     * @return array{title: string, metaDescription: string}
     */
    public static function viewData(string $path): array
    {
        $page = self::indexablePages()[self::normalizePath($path)] ?? null;

        return [
            'title' => $page['title'] ?? 'MBPHA TeleHealth Consultation System',
            'metaDescription' => $page['description'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function applyResponseHeaders(string $layout, array $data): void
    {
        if (in_array($layout, ['layouts/dashboard', 'layouts/print'], true)) {
            self::sendNoIndexHeader();
            return;
        }

        $robots = isset($data['robots']) && is_string($data['robots']) ? $data['robots'] : null;
        if (self::containsNoIndex((string) $robots) || self::shouldNoIndexPath(Helper::currentPath())) {
            self::sendNoIndexHeader();
        }
    }

    public static function sendNoIndexHeader(): void
    {
        if (!headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow', false);
        }
    }

    public static function encodeJsonLd(array $graph): string
    {
        $payload = [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        return is_string($json) ? $json : '{}';
    }

    /**
     * @return list<string>
     */
    public static function sitemapUrls(): array
    {
        $urls = [];
        foreach (array_keys(self::indexablePages()) as $path) {
            $urls[] = self::canonicalUrl((string) $path);
        }

        return $urls;
    }

    public static function normalizePath(string $path): string
    {
        $parsed = parse_url($path, PHP_URL_PATH);
        $path = is_string($parsed) && $parsed !== '' ? $parsed : $path;
        $path = '/' . ltrim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path === '' ? '/' : $path;
    }

    public static function isIndexablePath(string $path): bool
    {
        return isset(self::indexablePages()[self::normalizePath($path)]);
    }

    public static function shouldNoIndexPath(string $path): bool
    {
        $normalized = self::normalizePath($path);
        if (in_array($normalized, self::noIndexPaths(), true)) {
            return true;
        }

        foreach (self::privatePathPrefixes() as $prefix) {
            if ($normalized === $prefix || str_starts_with($normalized, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function homeJsonLd(string $title, string $description, string $canonical): array
    {
        $origin = self::canonicalOrigin() . '/';
        $logo = self::defaultImageUrl();

        return [
            [
                '@type' => 'Organization',
                '@id' => $origin . '#organization',
                'name' => self::SITE_NAME,
                'url' => $origin,
                'description' => $description,
                'logo' => $logo,
                'parentOrganization' => [
                    '@type' => 'GovernmentOrganization',
                    'name' => 'Milne Bay Provincial Health Authority',
                ],
                'areaServed' => [
                    '@type' => 'AdministrativeArea',
                    'name' => 'Milne Bay Province, Papua New Guinea',
                ],
            ],
            [
                '@type' => 'WebSite',
                '@id' => $origin . '#website',
                'name' => self::SITE_NAME,
                'url' => $origin,
                'inLanguage' => 'en',
                'publisher' => [
                    '@id' => $origin . '#organization',
                ],
            ],
            [
                '@type' => 'WebPage',
                '@id' => $canonical . '#webpage',
                'name' => $title,
                'url' => $canonical,
                'description' => $description,
                'isPartOf' => [
                    '@id' => $origin . '#website',
                ],
                'about' => [
                    '@id' => $origin . '#organization',
                ],
            ],
        ];
    }

    private static function isSafeOrigin(string $origin): bool
    {
        if (filter_var($origin, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($origin);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $user = (string) ($parts['user'] ?? '');
        $path = (string) ($parts['path'] ?? '');

        if (!in_array($scheme, ['https', 'http'], true) || $host === '') {
            return false;
        }

        if ($user !== '' || isset($parts['query']) || isset($parts['fragment'])) {
            return false;
        }

        return $path === '' || $path === '/';
    }

    private static function containsNoIndex(string $robots): bool
    {
        return str_contains(strtolower($robots), 'noindex');
    }

    private static function nonEmptyString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
