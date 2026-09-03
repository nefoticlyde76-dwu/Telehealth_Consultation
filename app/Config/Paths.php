<?php

namespace App\Config;

/**
 * Resolves the project root and the public/web root for both layouts:
 *
 * Local:     {project}/app, {project}/public/index.php
 * Hosting:   {htdocs}/app, {htdocs}/index.php
 *
 * Never hardcodes a document-root folder name. Callers that need the
 * web asset directory should use publicRoot(), not a literal "public".
 */
final class Paths
{
    public static function projectRoot(): string
    {
        $root = dirname(__DIR__, 2);
        $resolved = realpath($root);

        return $resolved !== false ? $resolved : $root;
    }

    public static function publicRoot(): string
    {
        $configured = self::configuredPublicRoot();
        if ($configured !== null) {
            return $configured;
        }

        $projectRoot = self::projectRoot();
        $nestedPublic = $projectRoot . DIRECTORY_SEPARATOR . 'public';
        if (is_file($nestedPublic . DIRECTORY_SEPARATOR . 'index.php')) {
            return $nestedPublic;
        }

        if (is_file($projectRoot . DIRECTORY_SEPARATOR . 'index.php')) {
            return $projectRoot;
        }

        throw new \RuntimeException(
            'The public root could not be determined. Expected either '
            . $nestedPublic . DIRECTORY_SEPARATOR . 'index.php (local layout) or '
            . $projectRoot . DIRECTORY_SEPARATOR . 'index.php (document-root layout).'
        );
    }

    /**
     * Private application storage (outside the web root). Used for
     * patient complaint images and other non-public files.
     */
    public static function storageRoot(): string
    {
        return self::projectRoot() . DIRECTORY_SEPARATOR . 'storage';
    }

    private static function configuredPublicRoot(): ?string
    {
        $raw = $_ENV['PUBLIC_ROOT'] ?? $_SERVER['PUBLIC_ROOT'] ?? getenv('PUBLIC_ROOT');
        if ($raw === false || $raw === null) {
            return null;
        }

        $path = trim((string) $raw);
        if ($path === '' || !self::isAbsolutePath($path)) {
            return null;
        }

        return rtrim($path, "/\\");
    }

    private static function isAbsolutePath(string $path): bool
    {
        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }

        return strlen($path) >= 3
            && ctype_alpha($path[0])
            && $path[1] === ':'
            && ($path[2] === '/' || $path[2] === '\\');
    }
}
