<?php

namespace App\Services;

use App\Config\Paths;
use App\Core\Database;
use App\Helpers\Helper;
use PDO;

/**
 * Optional patient complaint/symptom image attached at booking time.
 *
 * Images are stored outside the public web root and served only through
 * authenticated controller routes after ownership checks.
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
        return trim((string) ($request['complaint_image_path'] ?? '')) !== '';
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
        if ($relativePath === '') {
            self::abort(404);
        }

        self::streamRelativePath($relativePath);
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

    private static function streamRelativePath(string $relativePath): void
    {
        $absolute = self::absolutePathIfSafe($relativePath);
        if ($absolute === null || !is_file($absolute) || !is_readable($absolute)) {
            self::abort(404);
        }

        $extension = strtolower((string) pathinfo($absolute, PATHINFO_EXTENSION));
        $mimeType = $extension === 'png' ? 'image/png' : 'image/jpeg';
        $filesize = filesize($absolute);
        $filename = 'complaint-image.' . ($extension === 'png' ? 'png' : 'jpg');

        if (!headers_sent()) {
            header('Content-Type: ' . $mimeType);
            header('X-Content-Type-Options: nosniff');
            header('X-Robots-Tag: noindex, nofollow');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Cache-Control: private, no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            if (is_int($filesize) && $filesize > 0) {
                header('Content-Length: ' . (string) $filesize);
            }
            http_response_code(200);
        }

        readfile($absolute);
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
