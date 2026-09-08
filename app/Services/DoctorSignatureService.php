<?php

namespace App\Services;

use App\Config\Paths;
use App\Core\Database;
use PDO;
use Throwable;

/**
 * Resolves the prescribing doctor's signature for screen and PDF.
 * The public uploads folder is ephemeral on App Platform, so the image
 * is also stored on the doctor row and used when the file is missing.
 */
class DoctorSignatureService
{
    private const MAX_BYTES = 2097152;
    private const MAX_EDGE_W = 200;
    private const MAX_EDGE_H = 140;

    /**
     * @return array{src:string,width:int,height:int}
     */
    public static function embed(int $doctorId, string $storedPath): array
    {
        $empty = ['src' => '', 'width' => 0, 'height' => 0];
        if ($doctorId <= 0) {
            $doctorId = self::doctorIdFromPath($storedPath);
        }
        if ($doctorId <= 0) {
            return $empty;
        }

        $bytes = self::loadBytes($doctorId, $storedPath);
        if ($bytes === null || $bytes === '') {
            return $empty;
        }

        return self::flattenBytes($bytes);
    }

    public static function persistFromAbsolutePath(int $doctorId, string $absolutePath, string $mimeType): void
    {
        if ($doctorId <= 0 || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return;
        }

        $bytes = file_get_contents($absolutePath);
        if (!is_string($bytes) || $bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            return;
        }

        self::storeBlob($doctorId, $bytes, $mimeType);
    }

    public static function clear(int $doctorId): void
    {
        if ($doctorId <= 0 || !self::ensureStorage()) {
            return;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            'UPDATE doctor
                SET signature_blob = NULL,
                    signature_mime = NULL
              WHERE user_id = :user_id'
        );
        $stmt->bindValue(':user_id', $doctorId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public static function normalizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        return $path;
    }

    public static function isSafeDoctorPath(int $doctorId, string $relative): bool
    {
        if ($doctorId <= 0 || $relative === '') {
            return false;
        }
        if (str_contains($relative, '..') || str_contains($relative, ':')) {
            return false;
        }

        return str_starts_with($relative, 'uploads/doctors/' . $doctorId . '/');
    }

    public static function resolveAbsolute(int $doctorId, string $storedPath): ?string
    {
        $relative = self::normalizeRelativePath($storedPath);
        if (!self::isSafeDoctorPath($doctorId, $relative)) {
            return null;
        }

        foreach (self::searchRoots() as $root) {
            $candidate = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $absolute = realpath($candidate);
            if ($absolute === false || !is_file($absolute) || !is_readable($absolute)) {
                continue;
            }

            $rootReal = realpath($root);
            if (!is_string($rootReal) || $rootReal === '') {
                continue;
            }
            if (!str_starts_with($absolute, $rootReal . DIRECTORY_SEPARATOR)) {
                continue;
            }

            return $absolute;
        }

        return null;
    }

    public static function doctorIdFromPath(string $storedPath): int
    {
        $relative = self::normalizeRelativePath($storedPath);
        if (!preg_match('#^uploads/doctors/(\d+)/#', $relative, $matches)) {
            return 0;
        }

        return (int) $matches[1];
    }

    public static function ensureStorage(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }

        try {
            $db = Database::getInstance();
            $check = $db->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'doctor'
                    AND COLUMN_NAME = 'signature_blob'"
            );
            $exists = (int) $check->fetchColumn() > 0;
            if (!$exists) {
                $db->exec(
                    'ALTER TABLE doctor
                        ADD COLUMN signature_mime VARCHAR(32) NULL AFTER signature_path,
                        ADD COLUMN signature_blob MEDIUMBLOB NULL AFTER signature_mime'
                );
            }
            $ready = true;
        } catch (Throwable $exception) {
            error_log('Doctor signature storage is unavailable: ' . $exception->getMessage());
            $ready = false;
        }

        return $ready;
    }

    private static function loadBytes(int $doctorId, string $storedPath): ?string
    {
        $absolute = self::resolveAbsolute($doctorId, $storedPath);
        if ($absolute !== null) {
            $size = filesize($absolute);
            if ($size !== false && $size > 0 && $size <= self::MAX_BYTES) {
                $bytes = file_get_contents($absolute);
                if (is_string($bytes) && $bytes !== '') {
                    $mime = function_exists('mime_content_type') ? (string) mime_content_type($absolute) : '';
                    self::backfillBlob($doctorId, $bytes, $mime);
                    return $bytes;
                }
            }
        }

        return self::loadBlob($doctorId);
    }

    private static function loadBlob(int $doctorId): ?string
    {
        if ($doctorId <= 0 || !self::ensureStorage()) {
            return null;
        }

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare(
                'SELECT signature_blob
                   FROM doctor
                  WHERE user_id = :user_id
                  LIMIT 1'
            );
            $stmt->bindValue(':user_id', $doctorId, PDO::PARAM_INT);
            $stmt->execute();
            $blob = $stmt->fetchColumn();
        } catch (Throwable $exception) {
            error_log('Doctor signature blob could not be read: ' . $exception->getMessage());
            return null;
        }

        if (!is_string($blob) || $blob === '' || strlen($blob) > self::MAX_BYTES) {
            return null;
        }

        return $blob;
    }

    private static function backfillBlob(int $doctorId, string $bytes, string $mimeType): void
    {
        if (!self::ensureStorage()) {
            return;
        }

        try {
            $db = Database::getInstance();
            $check = $db->prepare(
                'SELECT signature_blob
                   FROM doctor
                  WHERE user_id = :user_id
                  LIMIT 1'
            );
            $check->bindValue(':user_id', $doctorId, PDO::PARAM_INT);
            $check->execute();
            $existing = $check->fetchColumn();
            if (is_string($existing) && $existing !== '') {
                return;
            }
        } catch (Throwable) {
            return;
        }

        self::storeBlob($doctorId, $bytes, $mimeType);
    }

    private static function storeBlob(int $doctorId, string $bytes, string $mimeType): void
    {
        if ($doctorId <= 0 || $bytes === '' || !self::ensureStorage()) {
            return;
        }

        $mime = strtolower(trim($mimeType));
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/jpg'], true)) {
            $mime = 'image/png';
        }
        if ($mime === 'image/jpg') {
            $mime = 'image/jpeg';
        }

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare(
                'UPDATE doctor
                    SET signature_blob = :signature_blob,
                        signature_mime = :signature_mime
                  WHERE user_id = :user_id'
            );
            $stmt->bindValue(':signature_blob', $bytes);
            $stmt->bindValue(':signature_mime', $mime);
            $stmt->bindValue(':user_id', $doctorId, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Throwable $exception) {
            error_log('Doctor signature blob could not be stored: ' . $exception->getMessage());
        }
    }

    /**
     * @return array{src:string,width:int,height:int}
     */
    private static function flattenBytes(string $bytes): array
    {
        $empty = ['src' => '', 'width' => 0, 'height' => 0];
        if (!extension_loaded('gd')) {
            return $empty;
        }

        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            return $empty;
        }

        $srcW = imagesx($source);
        $srcH = imagesy($source);
        if ($srcW < 1 || $srcH < 1) {
            imagedestroy($source);
            return $empty;
        }

        $scale = min(self::MAX_EDGE_W / $srcW, self::MAX_EDGE_H / $srcH, 1.0);
        $dstW = max(1, (int) round($srcW * $scale));
        $dstH = max(1, (int) round($srcH * $scale));

        $canvas = imagecreatetruecolor($dstW, $dstH);
        if ($canvas === false) {
            imagedestroy($source);
            return $empty;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $dstW, $dstH, $white);
        imagealphablending($canvas, true);
        imagesavealpha($canvas, false);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        imagedestroy($source);

        ob_start();
        imagejpeg($canvas, null, 90);
        $jpeg = (string) ob_get_clean();
        imagedestroy($canvas);

        if ($jpeg === '') {
            return $empty;
        }

        return [
            'src' => 'data:image/jpeg;base64,' . base64_encode($jpeg),
            'width' => $dstW,
            'height' => $dstH,
        ];
    }

    /**
     * @return list<string>
     */
    private static function searchRoots(): array
    {
        $roots = [];
        $candidates = [];

        try {
            $candidates[] = Paths::publicRoot();
        } catch (Throwable) {
        }

        $projectRoot = Paths::projectRoot();
        $candidates[] = $projectRoot;
        $candidates[] = $projectRoot . DIRECTORY_SEPARATOR . 'public';

        foreach ($candidates as $candidate) {
            $real = realpath((string) $candidate);
            if (is_string($real) && $real !== '') {
                $roots[$real] = $real;
            }
        }

        return array_values($roots);
    }
}
