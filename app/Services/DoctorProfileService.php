<?php

namespace App\Services;

use App\Config\Paths;
use App\Core\Csrf;
use App\Core\Database;
use App\Helpers\Helper;
use App\Models\Doctor;
use App\Models\User;

class DoctorProfileService
{
    private const MAX_SIGNATURE_BYTES = 2097152;

    public static function getProfileDetail(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        return Doctor::findProfileDetailByUserId($userId);
    }

    public static function getProfileFormData(?array $profile = null): array
    {
        return [
            '_token' => '',
            'phone' => (string) ($profile['phone'] ?? ''),
            'specialization' => (string) ($profile['specialization'] ?? ''),
        ];
    }

    public static function updateProfile(int $userId, array $input, array $files): array
    {
        $profile = self::getProfileDetail($userId);

        if ($profile === null) {
            return [
                'success' => false,
                'errors' => ['The doctor profile could not be found.'],
                'fieldErrors' => [],
                'formData' => self::normalizeProfileFormData($input),
            ];
        }

        $formData = self::normalizeProfileFormData($input);
        [$errors, $fieldErrors] = self::validateProfileForm($formData);

        if ($errors !== []) {
            return [
                'success' => false,
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
                'formData' => $formData,
                'profile' => $profile,
            ];
        }

        $doctor = Doctor::findByUserId($userId);
        $user = User::findById($userId);

        if ($doctor === null || $user === null || $user->getRole() !== 'doctor') {
            return [
                'success' => false,
                'errors' => ['The doctor profile could not be loaded for editing.'],
                'fieldErrors' => [],
                'formData' => $formData,
                'profile' => $profile,
            ];
        }

        $db = Database::getInstance();
        $uploadedPaths = [];
        DoctorSignatureService::ensureStorage();

        try {
            $db->beginTransaction();

            $doctor->phone = $formData['phone'];
            $doctor->specialization = $formData['specialization'];

            $uploads = self::handleProfileUploads($userId, $doctor, $files);
            $uploadedPaths = array_values(array_filter([
                $uploads['profile_photo']['path'] ?? null,
                $uploads['signature']['path'] ?? null,
            ]));

            if (!$doctor->update()) {
                throw new \RuntimeException('Doctor profile updates did not persist.');
            }

            $db->commit();

            $updatedProfile = self::getProfileDetail($userId);

            return [
                'success' => true,
                'message' => 'Doctor profile updated successfully.',
                'profile' => $updatedProfile,
                'uploads' => $uploads,
            ];
        } catch (\RuntimeException $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            foreach ($uploadedPaths as $uploadedPath) {
                ProfilePhotoService::deleteStoredPath((string) $uploadedPath);
            }

            $fieldErrors = [];

            if (
                isset($files['profile_photo'])
                && is_array($files['profile_photo'])
                && ($files['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            ) {
                $fieldErrors['profile_photo'] = $exception->getMessage();
            }

            if (
                isset($files['signature'])
                && is_array($files['signature'])
                && ($files['signature']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            ) {
                $fieldErrors['signature'] = $exception->getMessage();
            }

            return [
                'success' => false,
                'errors' => [$exception->getMessage()],
                'fieldErrors' => $fieldErrors,
                'formData' => $formData,
                'profile' => $profile,
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            foreach ($uploadedPaths as $uploadedPath) {
                ProfilePhotoService::deleteStoredPath((string) $uploadedPath);
            }

            error_log('Doctor profile update failed: ' . $exception->getMessage());

            return [
                'success' => false,
                'errors' => ['Doctor profile updates are temporarily unavailable. Please try again later.'],
                'fieldErrors' => [],
                'formData' => $formData,
                'profile' => $profile,
            ];
        }
    }

    public static function updatePassword(int $userId, array $input): array
    {
        $profile = self::getProfileDetail($userId);

        if ($profile === null) {
            return [
                'success' => false,
                'errors' => ['The doctor profile could not be found.'],
                'fieldErrors' => [],
            ];
        }

        $csrfToken = (string) ($input['_token'] ?? '');
        $currentPassword = (string) ($input['current_password'] ?? '');
        $password = (string) ($input['password'] ?? '');
        $confirmPassword = (string) ($input['confirm_password'] ?? '');
        $errors = [];
        $fieldErrors = [];

        if (!Csrf::verify($csrfToken)) {
            $fieldErrors['_token'] = 'Unable to verify the request. Please refresh the page and try again.';
        }

        $user = User::findById($userId);

        if ($user === null || $user->getRole() !== 'doctor') {
            $errors[] = 'The doctor profile could not be loaded.';

            return [
                'success' => false,
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
            ];
        }

        if ($currentPassword === '') {
            $fieldErrors['current_password'] = 'Current password is required.';
        } elseif (!password_verify($currentPassword, (string) $user->password)) {
            $fieldErrors['current_password'] = 'Current password is incorrect.';
        }

        if ($password === '') {
            $fieldErrors['password'] = 'New password is required.';
        } elseif (!Helper::isStrongPassword($password)) {
            $fieldErrors['password'] = 'Password must be at least 8 characters and include uppercase, lowercase, number, and symbol.';
        } elseif (password_verify($password, (string) $user->password)) {
            $fieldErrors['password'] = 'Please choose a new password that is different from the current password.';
        }

        if ($confirmPassword === '') {
            $fieldErrors['confirm_password'] = 'Please confirm the new password.';
        } elseif ($confirmPassword !== $password) {
            $fieldErrors['confirm_password'] = 'Passwords do not match.';
        }

        if ($fieldErrors !== []) {
            if (isset($fieldErrors['_token'])) {
                $errors[] = $fieldErrors['_token'];
            }

            $errors[] = 'Please correct the highlighted password fields.';

            return [
                'success' => false,
                'errors' => $errors,
                'fieldErrors' => $fieldErrors,
            ];
        }

        try {
            if (!User::updatePasswordHash($userId, password_hash($password, PASSWORD_DEFAULT))) {
                throw new \RuntimeException('Doctor password update did not persist.');
            }

            return [
                'success' => true,
                'message' => 'Doctor password updated successfully.',
            ];
        } catch (\Throwable $exception) {
            error_log('Doctor password update failed: ' . $exception->getMessage());

            return [
                'success' => false,
                'errors' => ['Doctor password updates are temporarily unavailable. Please try again later.'],
                'fieldErrors' => [],
            ];
        }
    }

    private static function normalizeProfileFormData(array $input): array
    {
        return [
            '_token' => (string) ($input['_token'] ?? ''),
            'phone' => trim((string) ($input['phone'] ?? '')),
            'specialization' => trim((string) ($input['specialization'] ?? '')),
        ];
    }

    private static function validateProfileForm(array $formData): array
    {
        $errors = [];
        $fieldErrors = [];

        if (!Csrf::verify((string) ($formData['_token'] ?? ''))) {
            $fieldErrors['_token'] = 'Unable to verify the request. Please refresh the page and try again.';
        }

        if ($formData['phone'] === '') {
            $fieldErrors['phone'] = 'Phone number is required.';
        } elseif (mb_strlen($formData['phone']) > 30) {
            $fieldErrors['phone'] = 'Phone number must be 30 characters or fewer.';
        } elseif (!preg_match('/^[0-9+()\\-\\s]{7,30}$/', $formData['phone'])) {
            $fieldErrors['phone'] = 'Please provide a valid phone number.';
        }

        if ($formData['specialization'] === '') {
            $fieldErrors['specialization'] = 'Specialization is required.';
        } elseif (mb_strlen($formData['specialization']) > 255) {
            $fieldErrors['specialization'] = 'Specialization must be 255 characters or fewer.';
        }

        if ($fieldErrors !== []) {
            if (isset($fieldErrors['_token'])) {
                $errors[] = $fieldErrors['_token'];
            }

            $errors[] = 'Please correct the highlighted profile fields.';
        }

        return [$errors, $fieldErrors];
    }

    private static function handleProfileUploads(int $userId, Doctor $doctor, array $files): array
    {
        $results = [
            'profile_photo' => null,
            'signature' => null,
        ];

        $uploadRoot = Paths::publicRoot() . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'doctors' . DIRECTORY_SEPARATOR . $userId;

        if (!is_dir($uploadRoot) && !mkdir($uploadRoot, 0775, true) && !is_dir($uploadRoot)) {
            throw new \RuntimeException('Upload directory could not be prepared.');
        }

        if (isset($files['profile_photo']) && is_array($files['profile_photo']) && ($files['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $results['profile_photo'] = ProfilePhotoService::storeCroppedProfilePhoto(
                $userId,
                'doctors',
                $files['profile_photo'],
                $doctor->profile_photo_path
            );

            $doctor->profile_photo_path = $results['profile_photo']['path'] ?? $doctor->profile_photo_path;
        }

        if (isset($files['signature']) && is_array($files['signature']) && ($files['signature']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $results['signature'] = self::storeUpload($userId, $files['signature'], $uploadRoot, 'signature', self::MAX_SIGNATURE_BYTES, [
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
            ], $doctor->signature_path);

            $doctor->signature_path = $results['signature']['path'] ?? $doctor->signature_path;
            $storedAbsolute = Paths::publicRoot()
                . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, (string) ($results['signature']['path'] ?? ''));
            DoctorSignatureService::persistFromAbsolutePath(
                $userId,
                $storedAbsolute,
                (string) ($results['signature']['mime_type'] ?? 'image/png')
            );
        }

        return $results;
    }

    private static function storeUpload(
        int $userId,
        array $file,
        string $uploadRoot,
        string $prefix,
        int $maxBytes,
        array $allowedMimeMap,
        ?string $existingPath
    ): array {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Upload failed. Please try again.');
        }

        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0) {
            throw new \RuntimeException('The uploaded file is empty.');
        }

        if ($size > $maxBytes) {
            throw new \RuntimeException('The uploaded file exceeds the allowed size limit.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new \RuntimeException('Upload could not be verified.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($tmpName) ?: '';

        if (!isset($allowedMimeMap[$mimeType])) {
            throw new \RuntimeException('The uploaded file type is not allowed.');
        }

        $extension = $allowedMimeMap[$mimeType];
        $random = bin2hex(random_bytes(16));
        $filename = $prefix . '_' . $random . '.' . $extension;

        $destination = $uploadRoot . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpName, $destination)) {
            throw new \RuntimeException('Unable to store the uploaded file.');
        }

        $relativePath = 'uploads/doctors/' . $userId . '/' . $filename;

        self::deletePreviousUpload($userId, $existingPath);

        return [
            'path' => $relativePath,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'size' => $size,
        ];
    }

    private static function deletePreviousUpload(int $userId, ?string $existingPath): void
    {
        ProfilePhotoService::deletePreviousUpload($existingPath, 'uploads/doctors/' . $userId . '/');
    }
}
