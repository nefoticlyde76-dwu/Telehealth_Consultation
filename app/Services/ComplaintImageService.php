<?php

namespace App\Services;

use App\Config\Paths;
use App\Core\Database;
use App\Helpers\Helper;
use PDO;
use Throwable;

/**
 * Optional patient complaint/symptom image attached at booking time.
 *
 * Images are stored outside the public web root and also as a MEDIUMBLOB
 * (complaint_images.photo_blob), matching profile photos. They are served
 * only through authenticated controller routes after ownership checks.
 */
class ComplaintImageService
{
    public const FIELD_NAME = 'complaint_image';
    public const MAX_BYTES = 5242880;
    public const MAX_DIMENSION = 4096;
    public const MAX_LABEL_BYTES = 5;

    private const RELATIVE_PREFIX = 'complaint_images/';
    private const PATH_PATTERN = '#^complaint_images/[1-9][0-9]*/complaint_[a-f0-9]{32}\.(jpg|png)$#';

    /** @var array<string, string> */
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public static function hasUpload(array $files): bool
    {
        $file = $files[self::FIELD_NAME] ?? null;
        if (!is_array($file)) {
            return false;
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        return $error !== UPLOAD_ERR_NO_FILE;
    }

    public static function existsOnRequest(array $request): bool
    {
        if (trim((string) ($request['complaint_image_path'] ?? '')) !== '') {
            return true;
        }

        $requestId = (int) ($request['id'] ?? 0);

        return $requestId > 0 && self::hasBlob($requestId);
    }

    public static function viewerUrl(string $role, int $requestId): string
    {
        if ($requestId <= 0) {
            return '';
        }

        return match ($role) {
            'doctor' => Helper::url('/doctor/consultations/' . $requestId . '/complaint-image'),
            'admin' => Helper::url('/admin/consultation-requests/' . $requestId . '/complaint-image'),
            default => Helper::url('/patient/consultation-requests/' . $requestId . '/complaint-image'),
        };
    }

    /**
     * Store a validated complaint image for the booking patient.
     *
     * @param array<string, mixed> $files
     * @return array{path:string,mime_type:string,size:int}|null
     */
    public static function storeForPatient(int $patientId, array $files): ?array
    {
        if ($patientId <= 0) {
            throw new \RuntimeException('Unable to attach the complaint image to this booking.');
        }

        if (!self::hasUpload($files)) {
            return null;
        }

        $file = $files[self::FIELD_NAME];
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(self::uploadErrorMessage($error));
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            throw new \RuntimeException('The selected complaint image is empty.');
        }
        if ($size > self::MAX_BYTES) {
            throw new \RuntimeException('Complaint image must be ' . self::MAX_LABEL_BYTES . ' MB or smaller.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_file($tmpName)) {
            throw new \RuntimeException('Unable to read the uploaded complaint image.');
        }
        if (PHP_SAPI !== 'cli' && !is_uploaded_file($tmpName)) {
            throw new \RuntimeException('Unable to verify the uploaded complaint image.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) ($finfo->file($tmpName) ?: '');
        if (!isset(self::ALLOWED_MIME[$mimeType])) {
            throw new \RuntimeException('Only JPG, JPEG, or PNG complaint images are allowed.');
        }

        $imageInfo = @getimagesize($tmpName);
        if (!is_array($imageInfo)) {
            throw new \RuntimeException('The uploaded file could not be validated as an image.');
        }

        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        $imageType = (int) ($imageInfo[2] ?? 0);
        $expectedType = $mimeType === 'image/png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG;

        if ($width <= 0 || $height <= 0 || $imageType !== $expectedType) {
            throw new \RuntimeException('The uploaded file could not be validated as an image.');
        }
        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw new \RuntimeException('Complaint image dimensions must be ' . self::MAX_DIMENSION . ' pixels or fewer.');
        }

        $source = $mimeType === 'image/png'
            ? @imagecreatefrompng($tmpName)
            : @imagecreatefromjpeg($tmpName);

        if ($source === false) {
            throw new \RuntimeException('The uploaded complaint image could not be processed.');
        }

        $extension = self::ALLOWED_MIME[$mimeType];
        $filename = 'complaint_' . bin2hex(random_bytes(16)) . '.' . $extension;
        $relativeDir = self::RELATIVE_PREFIX . $patientId;
        $uploadRoot = Paths::storageRoot()
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

        // TODO(complaint-image-disk): After bin/backfill_complaint_image_blobs.php has been
        // run in production and migrated/skipped counts confirmed, stop writing to
        // storage/complaint_images/ and persist only complaint_images.photo_blob.
        if (!is_dir($uploadRoot) && !mkdir($uploadRoot, 0750, true) && !is_dir($uploadRoot)) {
            imagedestroy($source);
            throw new \RuntimeException('Complaint image storage could not be prepared.');
        }

        $destination = $uploadRoot . DIRECTORY_SEPARATOR . $filename;
        $written = $mimeType === 'image/png'
            ? self::writePng($source, $destination)
            : imagejpeg($source, $destination, 90);

        imagedestroy($source);

        if ($written !== true || !is_file($destination)) {
            throw new \RuntimeException('Unable to store the uploaded complaint image.');
        }

        @chmod($destination, 0640);

        return [
            'path' => $relativeDir . '/' . $filename,
            'mime_type' => $mimeType,
            'size' => (int) filesize($destination),
        ];
    }

    /**
     * Copy a newly stored disk file into complaint_images.photo_blob.
     *
     * @param array{path?:string,mime_type?:string} $stored
     */
    public static function persistStoredUpload(int $requestId, array $stored): void
    {
        $relativePath = (string) ($stored['path'] ?? '');
        $absolute = self::absolutePathIfSafe($relativePath);
        if ($absolute === null) {
            return;
        }

        self::persistFromAbsolutePath(
            $requestId,
            $absolute,
            (string) ($stored['mime_type'] ?? '')
        );
    }

    public static function persistFromAbsolutePath(int $requestId, string $absolutePath, string $mimeType): void
    {
        if ($requestId <= 0 || !is_file($absolutePath) || !is_readable($absolutePath) || !self::ensureStorage()) {
            return;
        }

        $bytes = file_get_contents($absolutePath);
        if (!is_string($bytes) || $bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            return;
        }

        self::storeBlob($requestId, $bytes, $mimeType);
    }

    /**
     * Backfill one request from storage/complaint_images/.
     *
     * @return 'migrated'|'skipped_missing'|'skipped_invalid'|'skipped_existing'|'failed'
     */
    public static function backfillFromStoredPath(int $requestId, string $relativePath): string
    {
        if ($requestId <= 0) {
            return 'skipped_invalid';
        }

        if (self::hasBlob($requestId)) {
            return 'skipped_existing';
        }

        $absolute = self::absolutePathIfSafe($relativePath);
        if ($absolute === null) {
            return 'skipped_missing';
        }

        $mime = function_exists('mime_content_type') ? (string) mime_content_type($absolute) : '';
        self::persistFromAbsolutePath($requestId, $absolute, $mime);

        return self::hasBlob($requestId) ? 'migrated' : 'failed';
    }

    public static function hasBlob(int $requestId): bool
    {
        if ($requestId <= 0 || !self::ensureStorage()) {
            return false;
        }

        try {
            $stmt = Database::getInstance()->prepare(
                'SELECT 1
                   FROM complaint_images
                  WHERE request_id = :request_id
                  LIMIT 1'
            );
            $stmt->bindValue(':request_id', $requestId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchColumn() !== false;
        } catch (Throwable $exception) {
            error_log('Complaint image blob could not be inspected: ' . $exception->getMessage());
            return false;
        }
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
                "SELECT COUNT(*) FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'complaint_images'"
            );
            $exists = (int) $check->fetchColumn() > 0;
            if (!$exists) {
                $db->exec(
                    'CREATE TABLE IF NOT EXISTS complaint_images (
                        request_id BIGINT PRIMARY KEY,
                        mime VARCHAR(32) NOT NULL,
                        photo_blob MEDIUMBLOB NOT NULL,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        CONSTRAINT fk_complaint_images_request
                            FOREIGN KEY (request_id) REFERENCES consultation_requests(id)
                            ON DELETE CASCADE
                    ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
                );
            }
            $ready = true;
        } catch (Throwable $exception) {
            error_log('Complaint image storage is unavailable: ' . $exception->getMessage());
            $ready = false;
        }

        return $ready;
    }

    public static function deleteStoredPath(?string $relativePath): void
    {
        $absolute = self::absolutePathIfSafe($relativePath);
        if ($absolute === null) {
            return;
        }

        @unlink($absolute);
    }

    /**
     * @param array{patient_id?:int|string,doctor_id?:int|string} $request
     */
    public static function userMayAccess(string $role, int $userId, array $request): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $role = strtolower(trim($role));
        $patientId = (int) ($request['patient_id'] ?? 0);
        $doctorId = (int) ($request['doctor_id'] ?? 0);

        return match ($role) {
            'admin' => true,
            'doctor' => $doctorId > 0 && $doctorId === $userId,
            'patient' => $patientId > 0 && $patientId === $userId,
            default => false,
        };
    }

    public static function streamForCurrentUser(int $requestId): void
    {
        $userId = AuthService::getUserId();
        $role = AuthService::getUserRole();

        if ($userId === null || $userId <= 0 || $role === null || $role === '') {
            self::abort(403);
        }

        if ($requestId <= 0) {
            self::abort(404);
        }

        $row = self::loadAccessRow($requestId);
        if ($row === null) {
            self::abort(404);
        }

        if (!self::userMayAccess((string) $role, (int) $userId, $row)) {
            self::abort(403);
        }

        $relativePath = trim((string) ($row['complaint_image_path'] ?? ''));
        $photo = self::loadImage($requestId, $relativePath);
        if ($photo === null) {
            self::abort(404);
        }

        self::streamBytes($photo['bytes'], $photo['mime']);
    }

    /**
     * @return array{patient_id:int,doctor_id:int,complaint_image_path:?string}|null
     */
    private static function loadAccessRow(int $requestId): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT patient_id, doctor_id, complaint_image_path
             FROM consultation_requests
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->bindValue(':id', $requestId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Disk first (legacy), then MEDIUMBLOB. Reading a disk file backfills the blob.
     *
     * @return array{bytes:string,mime:string}|null
     */
    private static function loadImage(int $requestId, string $relativePath): ?array
    {
        // TODO(complaint-image-disk): After backfill confirmation, skip disk and
        // return loadBlob($requestId) only.
        $absolute = self::absolutePathIfSafe($relativePath);
        if ($absolute !== null) {
            $bytes = file_get_contents($absolute);
            if (is_string($bytes) && $bytes !== '' && strlen($bytes) <= self::MAX_BYTES) {
                $mime = function_exists('mime_content_type') ? (string) mime_content_type($absolute) : '';
                self::persistFromAbsolutePath($requestId, $absolute, $mime);

                return [
                    'bytes' => $bytes,
                    'mime' => self::safeMime($mime, $absolute),
                ];
            }
        }

        return self::loadBlob($requestId);
    }

    /**
     * @return array{bytes:string,mime:string}|null
     */
    private static function loadBlob(int $requestId): ?array
    {
        if ($requestId <= 0 || !self::ensureStorage()) {
            return null;
        }

        try {
            $stmt = Database::getInstance()->prepare(
                'SELECT photo_blob, mime
                   FROM complaint_images
                  WHERE request_id = :request_id
                  LIMIT 1'
            );
            $stmt->bindValue(':request_id', $requestId, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            error_log('Complaint image blob could not be read: ' . $exception->getMessage());
            return null;
        }

        if (!is_array($row) || !is_string($row['photo_blob'] ?? null) || $row['photo_blob'] === '') {
            return null;
        }

        $bytes = $row['photo_blob'];
        if (strlen($bytes) > self::MAX_BYTES) {
            return null;
        }

        return [
            'bytes' => $bytes,
            'mime' => self::safeMime((string) ($row['mime'] ?? ''), ''),
        ];
    }

    private static function storeBlob(int $requestId, string $bytes, string $mimeType): void
    {
        if ($requestId <= 0 || $bytes === '' || !self::ensureStorage()) {
            return;
        }

        try {
            $stmt = Database::getInstance()->prepare(
                'INSERT INTO complaint_images (request_id, mime, photo_blob)
                 VALUES (:request_id, :mime, :photo_blob)
                 ON DUPLICATE KEY UPDATE
                    mime = VALUES(mime),
                    photo_blob = VALUES(photo_blob)'
            );
            $stmt->bindValue(':request_id', $requestId, PDO::PARAM_INT);
            $stmt->bindValue(':mime', self::safeMime($mimeType, ''));
            $stmt->bindValue(':photo_blob', $bytes);
            $stmt->execute();
        } catch (Throwable $exception) {
            error_log('Complaint image blob could not be stored: ' . $exception->getMessage());
        }
    }

    private static function streamBytes(string $bytes, string $mime): void
    {
        $mime = self::safeMime($mime, '');
        $extension = $mime === 'image/png' ? 'png' : 'jpg';
        $filename = 'complaint-image.' . $extension;

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . (string) strlen($bytes));
            header('X-Content-Type-Options: nosniff');
            header('X-Robots-Tag: noindex, nofollow');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Cache-Control: private, no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            http_response_code(200);
        }

        echo $bytes;
        exit;
    }

    private static function absolutePathIfSafe(?string $relativePath): ?string
    {
        if ($relativePath === null) {
            return null;
        }

        $relativePath = str_replace(['\\', "\0"], ['/', ''], trim($relativePath));
        if ($relativePath === '' || !preg_match(self::PATH_PATTERN, $relativePath)) {
            return null;
        }

        $storageRoot = realpath(Paths::storageRoot());
        if ($storageRoot === false) {
            return null;
        }

        $absolute = $storageRoot
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

        if (!is_file($absolute)) {
            return null;
        }

        $resolved = realpath($absolute);
        $prefix = $storageRoot . DIRECTORY_SEPARATOR;
        if ($resolved === false || strncasecmp($resolved, $prefix, strlen($prefix)) !== 0) {
            return null;
        }

        return $resolved;
    }

    private static function writePng(\GdImage $source, string $destination): bool
    {
        imagealphablending($source, false);
        imagesavealpha($source, true);

        return imagepng($source, $destination, 6);
    }

    private static function safeMime(string $mimeType, string $absolutePath): string
    {
        $mime = strtolower(trim($mimeType));
        if (in_array($mime, ['image/jpeg', 'image/jpg', 'image/png'], true)) {
            return $mime === 'image/jpg' ? 'image/jpeg' : $mime;
        }

        $extension = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));

        return $extension === 'png' ? 'image/png' : 'image/jpeg';
    }

    private static function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Complaint image must be ' . self::MAX_LABEL_BYTES . ' MB or smaller.',
            UPLOAD_ERR_PARTIAL => 'The complaint image upload was interrupted. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The complaint image could not be stored right now. Please try again.',
            UPLOAD_ERR_EXTENSION => 'The complaint image upload was blocked. Please try a JPG or PNG file.',
            default => 'Complaint image upload failed. Please try again.',
        };
    }

    private static function abort(int $status): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-store');
        }

        exit;
    }
}
