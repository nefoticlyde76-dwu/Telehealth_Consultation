<?php

namespace App\Services;

use App\Config\Paths;
use App\Core\Database;
use App\Helpers\Helper;
use PDO;
use Throwable;

class ProfilePhotoService
{
    public const MAX_PROFILE_PHOTO_BYTES = 5242880;

    /**
     * @param array<string, mixed> $file
     * @return array<string, int|string>
     */
    public static function storeCroppedProfilePhoto(
        int $userId,
        string $directoryName,
        array $file,
        ?string $existingPath
    ): array {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Profile photo upload failed. Please try again.');
        }

        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0) {
            throw new \RuntimeException('The selected profile photo is empty.');
        }

        if ($size > self::MAX_PROFILE_PHOTO_BYTES) {
            throw new \RuntimeException('Profile photo must be 5 MB or smaller.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new \RuntimeException('Unable to verify the uploaded profile photo.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) ($finfo->file($tmpName) ?: '');
        $allowedMimeMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($allowedMimeMap[$mimeType])) {
            throw new \RuntimeException('Only JPG, JPEG, PNG, or WEBP profile photos are allowed.');
        }

        $imageInfo = @getimagesize($tmpName);

        if (!is_array($imageInfo)) {
            throw new \RuntimeException('The uploaded profile photo could not be validated as an image.');
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);

        $uploadRoot = Paths::publicRoot()
            . DIRECTORY_SEPARATOR
            . 'uploads'
            . DIRECTORY_SEPARATOR
            . $directoryName
            . DIRECTORY_SEPARATOR
            . $userId;

        if (!is_dir($uploadRoot) && !mkdir($uploadRoot, 0775, true) && !is_dir($uploadRoot)) {
            throw new \RuntimeException('Profile photo storage could not be prepared.');
        }

        $extension = $allowedMimeMap[$mimeType];
        $filename = 'profile_photo_' . bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $uploadRoot . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpName, $destination)) {
            throw new \RuntimeException('Unable to store the uploaded profile photo.');
        }

        $relativePath = 'uploads/' . $directoryName . '/' . $userId . '/' . $filename;
        self::deletePreviousUpload($existingPath, 'uploads/' . $directoryName . '/' . $userId . '/');
        self::persistFromAbsolutePath($userId, $destination, $mimeType);

        return [
            'path' => $relativePath,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'size' => $size,
            'width' => $width,
            'height' => $height,
        ];
    }

    public static function deletePreviousUpload(?string $existingPath, string $expectedPrefix): void
    {
        if ($existingPath === null || trim($existingPath) === '') {
            return;
        }

        $existingPath = str_replace(['\\', "\0"], ['/', ''], $existingPath);

        if (strpos($existingPath, $expectedPrefix) !== 0 || !self::isSafePublicUploadPath($existingPath)) {
            return;
        }

        self::unlinkPublicRelativePath($existingPath);
    }

    public static function deleteStoredPath(?string $relativePath): void
    {
        if ($relativePath === null || trim($relativePath) === '') {
            return;
        }

        $safePath = str_replace(['\\', "\0"], ['/', ''], $relativePath);
        if (!self::isSafePublicUploadPath($safePath)) {
            return;
        }

        self::unlinkPublicRelativePath($safePath);
    }

    private static function isSafePublicUploadPath(string $relativePath): bool
    {
        $relativePath = ltrim($relativePath, '/');

        return $relativePath !== ''
            && str_starts_with($relativePath, 'uploads/')
            && !str_contains($relativePath, '..');
    }

    private static function unlinkPublicRelativePath(string $relativePath): void
    {
        $publicRoot = realpath(Paths::publicRoot());
        if ($publicRoot === false) {
            return;
        }

        $absolutePath = $publicRoot
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (!is_file($absolutePath)) {
            return;
        }

        $resolved = realpath($absolutePath);
        $publicPrefix = $publicRoot . DIRECTORY_SEPARATOR;
        if ($resolved === false || strncasecmp($resolved, $publicPrefix, strlen($publicPrefix)) !== 0) {
            return;
        }

        @unlink($resolved);
    }

    public static function ensureStorage(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }

        try {
            $db = Database::getInstance();
            $db->exec(
                'CREATE TABLE IF NOT EXISTS profile_photos (
                    user_id BIGINT PRIMARY KEY,
                    mime VARCHAR(32) NOT NULL,
                    photo_blob MEDIUMBLOB NOT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    CONSTRAINT fk_profile_photos_user
                        FOREIGN KEY (user_id) REFERENCES users(id)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );
            $ready = true;
        } catch (Throwable $exception) {
            error_log('Profile photo storage is unavailable: ' . $exception->getMessage());
            $ready = false;
        }

        return $ready;
    }

    public static function persistFromAbsolutePath(int $userId, string $absolutePath, string $mimeType): void
    {
        if ($userId <= 0 || !is_file($absolutePath) || !is_readable($absolutePath) || !self::ensureStorage()) {
            return;
        }

        $bytes = file_get_contents($absolutePath);
        if (!is_string($bytes) || $bytes === '' || strlen($bytes) > self::MAX_PROFILE_PHOTO_BYTES) {
            return;
        }

        self::storeBlob($userId, $bytes, $mimeType);
    }

    public static function url(?string $storedPath, ?int $userId = null): string
    {
        $path = is_string($storedPath) ? trim($storedPath) : '';
        $resolvedUserId = ($userId !== null && $userId > 0) ? $userId : self::userIdFromPath($path);
        if ($resolvedUserId <= 0) {
            return '';
        }

        if (!self::isAvailable($path, $resolvedUserId)) {
            return '';
        }

        return Helper::url('/profile-photo/' . $resolvedUserId);
    }

    public static function isAvailable(?string $storedPath, int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if (self::resolveAbsolute($userId, (string) $storedPath) !== null) {
            return true;
        }

        return self::loadBlob($userId) !== null;
    }

    public static function stream(int $userId): void
    {
        $photo = self::loadPhoto($userId, self::storedPathForUser($userId));
        if ($photo === null) {
            http_response_code(404);
            exit;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            header('Content-Type: ' . $photo['mime']);
            header('Content-Length: ' . (string) strlen($photo['bytes']));
            header('X-Content-Type-Options: nosniff');
            header('X-Robots-Tag: noindex, nofollow');
            header('Cache-Control: private, no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            http_response_code(200);
        }

        echo $photo['bytes'];
        exit;
    }

    public static function clear(int $userId): void
    {
        if ($userId <= 0 || !self::ensureStorage()) {
            return;
        }

        $stmt = Database::getInstance()->prepare('DELETE FROM profile_photos WHERE user_id = :user_id');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    private static function storedPathForUser(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }

        try {
            $stmt = Database::getInstance()->prepare(
                'SELECT COALESCE(admin.profile_photo_path, doctor.profile_photo_path, patient.profile_photo_path)
                   FROM users
                   LEFT JOIN admin ON admin.user_id = users.id
                   LEFT JOIN doctor ON doctor.user_id = users.id
                   LEFT JOIN patient ON patient.user_id = users.id
                  WHERE users.id = :user_id
                  LIMIT 1'
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $path = $stmt->fetchColumn();
        } catch (Throwable) {
            return '';
        }

        return is_string($path) ? $path : '';
    }

    public static function userIdFromPath(string $storedPath): int
    {
        $path = str_replace('\\', '/', trim($storedPath));
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }
        if ($path === '' || str_contains($path, '..') || str_contains($path, ':')) {
            return 0;
        }

        if (!preg_match('#^uploads/(?:doctors|patients|admins)/(\d+)/#', $path, $matches)) {
            return 0;
        }

        return (int) $matches[1];
    }

    /**
     * @return array{bytes:string,mime:string}|null
     */
    private static function loadPhoto(int $userId, string $storedPath): ?array
    {
        $absolute = self::resolveAbsolute($userId, $storedPath);
        if ($absolute !== null) {
            $bytes = file_get_contents($absolute);
            if (is_string($bytes) && $bytes !== '' && strlen($bytes) <= self::MAX_PROFILE_PHOTO_BYTES) {
                $mime = function_exists('mime_content_type') ? (string) mime_content_type($absolute) : '';
                self::persistFromAbsolutePath($userId, $absolute, $mime);
                return [
                    'bytes' => $bytes,
                    'mime' => self::safeMime($mime, $absolute),
                ];
            }
        }

        $blob = self::loadBlob($userId);
        if ($blob === null) {
            return null;
        }

        return $blob;
    }

    private static function resolveAbsolute(int $userId, string $storedPath): ?string
    {
        $path = str_replace('\\', '/', trim($storedPath));
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        if ($path === '' || str_contains($path, '..') || str_contains($path, ':')) {
            return null;
        }
        if (!preg_match('#^uploads/(doctors|patients|admins)/' . $userId . '/#', $path)) {
            return null;
        }

        $roots = [];
        try {
            $roots[] = Paths::publicRoot();
        } catch (Throwable) {
        }
        $projectRoot = Paths::projectRoot();
        $roots[] = $projectRoot;
        $roots[] = $projectRoot . DIRECTORY_SEPARATOR . 'public';

        foreach (array_unique(array_filter($roots)) as $root) {
            $rootReal = realpath((string) $root);
            if (!is_string($rootReal) || $rootReal === '') {
                continue;
            }
            $absolute = realpath($rootReal . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
            if ($absolute === false || !is_file($absolute) || !is_readable($absolute)) {
                continue;
            }
            if (!str_starts_with($absolute, $rootReal . DIRECTORY_SEPARATOR)) {
                continue;
            }

            return $absolute;
        }

        return null;
    }

    /**
     * @return array{bytes:string,mime:string}|null
     */
    private static function loadBlob(int $userId): ?array
    {
        if ($userId <= 0 || !self::ensureStorage()) {
            return null;
        }

        try {
            $stmt = Database::getInstance()->prepare(
                'SELECT photo_blob, mime
                   FROM profile_photos
                  WHERE user_id = :user_id
                  LIMIT 1'
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            error_log('Profile photo blob could not be read: ' . $exception->getMessage());
            return null;
        }

        if (!is_array($row) || !is_string($row['photo_blob'] ?? null) || $row['photo_blob'] === '') {
            return null;
        }

        return [
            'bytes' => $row['photo_blob'],
            'mime' => self::safeMime((string) ($row['mime'] ?? ''), ''),
        ];
    }

    private static function storeBlob(int $userId, string $bytes, string $mimeType): void
    {
        if ($userId <= 0 || $bytes === '' || !self::ensureStorage()) {
            return;
        }

        try {
            $stmt = Database::getInstance()->prepare(
                'INSERT INTO profile_photos (user_id, mime, photo_blob)
                 VALUES (:user_id, :mime, :photo_blob)
                 ON DUPLICATE KEY UPDATE
                    mime = VALUES(mime),
                    photo_blob = VALUES(photo_blob)'
            );
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':mime', self::safeMime($mimeType, ''));
            $stmt->bindValue(':photo_blob', $bytes);
            $stmt->execute();
        } catch (Throwable $exception) {
            error_log('Profile photo blob could not be stored: ' . $exception->getMessage());
        }
    }

    private static function safeMime(string $mimeType, string $absolutePath): string
    {
        $mime = strtolower(trim($mimeType));
        if (in_array($mime, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'], true)) {
            return $mime === 'image/jpg' ? 'image/jpeg' : $mime;
        }

        $extension = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }
}
