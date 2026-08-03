<?php

namespace App\Services;

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

        $uploadRoot = dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'public'
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

        if (strpos($existingPath, $expectedPrefix) !== 0) {
            return;
        }

        $absolutePath = dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'public'
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $existingPath);

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    public static function deleteStoredPath(?string $relativePath): void
    {
        if ($relativePath === null || trim($relativePath) === '') {
            return;
        }

        $safePath = str_replace(['\\', "\0"], ['/', ''], $relativePath);
        $absolutePath = dirname(__DIR__, 2)
            . DIRECTORY_SEPARATOR
            . 'public'
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $safePath);

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}
